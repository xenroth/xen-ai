<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * License manager for XEN A.I Pro.
 *
 * Security model:
 *  1. Plugin sends key + site domain to the remote license server.
 *  2. Server returns a signed token: base64( json_payload ) + "." + HMAC-SHA256( payload, server_secret ).
 *  3. Plugin stores the raw token in wp_options (encrypted with a site-unique key).
 *  4. Every is_active() call validates the HMAC signature AND the domain binding —
 *     simply flipping a DB value to "1" does nothing without a valid signature.
 *  5. A cached result (transient) avoids repeated remote calls; full re-verify runs
 *     once every 24 h or on explicit user action.
 *
 * The HMAC verification secret below must match what your license server uses to sign.
 * Keep this value private / obfuscated in production builds.
 */
class Xen_AI_License {

	// ── Configuration ─────────────────────────────────────────────────────────

	/** Your license server endpoint (POST). */
	const API_URL = 'https://api.xenroth.com/xen-ai/license-api.php';

	/**
	 * HMAC secret — must match HMAC_SECRET in license-api.php on your server.
	 * This is what makes tokens tamper-proof. Keep this value private.
	 */
	const HMAC_SECRET = 'x3N!rTh7@qL2mW9#pK5vY8&bZ1cJ4sF6';

	/** wp_options key that stores the encrypted license record. */
	const OPTION_KEY  = 'xen_ai_license';

	/** How long (seconds) a successful activation is cached before re-checking. */
	const CACHE_TTL   = DAY_IN_SECONDS;

	// ── Public API ────────────────────────────────────────────────────────────

	/**
	 * Returns true only when a cryptographically valid, domain-bound license
	 * token is present in the database.
	 *
	 * @return bool
	 */
	public static function is_active() {
		// Fast path: transient cache
		$cached = get_transient( 'xen_ai_license_valid' );
		if ( false !== $cached ) {
			return (bool) $cached;
		}

		$record = self::get_record();
		$result = false;

		if ( $record && self::validate_token( $record['token'], $record['key'] ) ) {
			$result = true;
		}

		set_transient( 'xen_ai_license_valid', $result ? '1' : '0', self::CACHE_TTL );
		return $result;
	}

	/**
	 * Retrieve the stored (decrypted) license record.
	 *
	 * @return array|null  Keys: key, token, domain, activated_at — or null if none.
	 */
	public static function get_record() {
		$raw = get_option( self::OPTION_KEY );
		if ( empty( $raw ) ) {
			return null;
		}
		$json = self::decrypt( $raw );
		if ( ! $json ) {
			return null;
		}
		$record = json_decode( $json, true );
		if ( ! is_array( $record ) || empty( $record['token'] ) ) {
			return null;
		}
		return $record;
	}

	/**
	 * Get the stored license key string (masked for display).
	 *
	 * @return string  e.g. "XEN-XXXX-••••-••••-••••" or '' if none.
	 */
	public static function get_masked_key() {
		$record = self::get_record();
		if ( ! $record || empty( $record['key'] ) ) {
			return '';
		}
		$key   = $record['key'];
		$parts = explode( '-', $key );
		// Show only the first segment; mask the rest
		$masked = array_map( function ( $p, $i ) {
			return $i === 0 ? $p : str_repeat( '•', strlen( $p ) );
		}, $parts, array_keys( $parts ) );
		return implode( '-', $masked );
	}

	/**
	 * Activate a license key.
	 * Contacts the remote server, validates the returned token, and persists it.
	 *
	 * @param  string $key
	 * @return true|WP_Error
	 */
	public static function activate( $key ) {
		$key    = sanitize_text_field( trim( $key ) );
		$domain = self::site_domain();

		if ( empty( $key ) ) {
			return new WP_Error( 'empty_key', __( 'Please enter a license key.', 'xen-ai' ) );
		}

		$response = wp_remote_post( self::API_URL, [
			'timeout' => 15,
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => wp_json_encode( [
				'key'     => $key,
				'domain'  => $domain,
				'product' => 'xen-ai',
				'action'  => 'verify',
			] ),
		] );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'connection_error',
				__( 'Could not reach the license server. Please check your connection.', 'xen-ai' )
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== (int) $code || empty( $body['token'] ) ) {
			$msg = ! empty( $body['message'] ) ? $body['message'] : __( 'License activation failed.', 'xen-ai' );
			return new WP_Error( 'activation_failed', $msg );
		}

		// Validate the token the server returned before trusting it
		if ( ! self::validate_token( $body['token'], $key ) ) {
			return new WP_Error( 'invalid_token', __( 'The server returned an invalid token. Contact support.', 'xen-ai' ) );
		}

		// Persist (encrypted)
		$record = [
			'key'          => $key,
			'token'        => $body['token'],
			'domain'       => $domain,
			'activated_at' => time(),
		];
		update_option( self::OPTION_KEY, self::encrypt( wp_json_encode( $record ) ), false );
		delete_transient( 'xen_ai_license_valid' );

		return true;
	}

	/**
	 * Deactivate the license (contact server + remove local record).
	 *
	 * @return true|WP_Error
	 */
	public static function deactivate() {
		$record = self::get_record();

		if ( $record ) {
			// Best-effort call to free the slot on the server
			wp_remote_post( self::API_URL, [
				'timeout' => 10,
				'headers' => [ 'Content-Type' => 'application/json' ],
				'body'    => wp_json_encode( [
					'key'     => $record['key'],
					'domain'  => self::site_domain(),
					'product' => 'xen-ai',
				] ),
			] );
		}

		delete_option( self::OPTION_KEY );
		delete_transient( 'xen_ai_license_valid' );

		return true;
	}

	// ── Token validation ──────────────────────────────────────────────────────

	/**
	 * Validate a license token issued by the server.
	 *
	 * Expected token format (dot-separated, base64url):
	 *   <base64url(json_payload)>.<base64url(hmac_sha256_signature)>
	 *
	 * Payload JSON must contain: { "key": "...", "domain": "...", "product": "xen-ai" }
	 *
	 * @param  string $token  The token stored/returned by the server.
	 * @param  string $key    The license key to cross-check in the payload.
	 * @return bool
	 */
	private static function validate_token( $token, $key ) {
		$parts = explode( '.', $token );
		if ( count( $parts ) !== 2 ) {
			return false;
		}

		[ $payload_b64, $sig_b64 ] = $parts;

		// Re-compute the expected HMAC
		$expected_sig = self::b64url_encode(
			hash_hmac( 'sha256', $payload_b64, self::HMAC_SECRET, true )
		);

		// Constant-time comparison to prevent timing attacks
		if ( ! hash_equals( $expected_sig, $sig_b64 ) ) {
			return false;
		}

		$payload = json_decode( self::b64url_decode( $payload_b64 ), true );
		if ( ! is_array( $payload ) ) {
			return false;
		}

		// Verify domain binding
		if ( empty( $payload['domain'] ) || $payload['domain'] !== self::site_domain() ) {
			return false;
		}

		// Verify product
		if ( empty( $payload['product'] ) || 'xen-ai' !== $payload['product'] ) {
			return false;
		}

		// Verify key matches
		if ( empty( $payload['key'] ) || ! hash_equals( $payload['key'], $key ) ) {
			return false;
		}

		return true;
	}

	// ── Encryption helpers ────────────────────────────────────────────────────

	/**
	 * Encrypt a string using AES-256-CBC with a site-unique key derived from
	 * AUTH_KEY (WordPress secret). Falls back to base64 if openssl unavailable.
	 */
	private static function encrypt( $plaintext ) {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return base64_encode( $plaintext ); // degraded but better than nothing
		}
		$iv  = openssl_random_pseudo_bytes( 16 );
		$enc = openssl_encrypt( $plaintext, 'AES-256-CBC', self::derive_key(), 0, $iv );
		return self::b64url_encode( $iv . '||' . $enc );
	}

	/**
	 * Decrypt a value previously encrypted with self::encrypt().
	 */
	private static function decrypt( $ciphertext ) {
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return base64_decode( $ciphertext ); // degraded path
		}
		$decoded = self::b64url_decode( $ciphertext );
		if ( false === $decoded ) {
			return false;
		}
		$pos = strpos( $decoded, '||' );
		if ( false === $pos ) {
			return false;
		}
		$iv  = substr( $decoded, 0, $pos );
		$enc = substr( $decoded, $pos + 2 );
		return openssl_decrypt( $enc, 'AES-256-CBC', self::derive_key(), 0, $iv );
	}

	/** Derive a 32-byte encryption key from WordPress AUTH_KEY. */
	private static function derive_key() {
		$salt = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'xen-ai-fallback-salt';
		return hash( 'sha256', $salt . 'xen_ai_license', true );
	}

	// ── Misc helpers ──────────────────────────────────────────────────────────

	/** Normalised site domain (scheme-stripped, lowercased). */
	private static function site_domain() {
		$url  = get_site_url();
		$host = wp_parse_url( $url, PHP_URL_HOST );
		return strtolower( $host ?: $url );
	}

	private static function b64url_encode( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	private static function b64url_decode( $data ) {
		return base64_decode( strtr( $data, '-_', '+/' ) );
	}
}
