<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and handles all front-end AJAX endpoints for the chat widget.
 */
class Xen_AI_Chat_Ajax {

	private $conv_table;
	private $msg_table;

	// ── Rate limit configuration ─────────────────────────────────────────────
	const RATE_LIMIT_SESSION      = 20;  // messages per session per hour
	const RATE_LIMIT_IP_HOUR      = 60;  // messages per IP per hour
	const RATE_LIMIT_IP_MIN       = 10;  // burst protection: per IP per minute
	const RATE_LIMIT_SESSION_INIT = 5;   // new sessions per IP per 10 minutes
	const MAX_MESSAGE_CHARS       = 2000;
	const SESSION_LOCK_TTL        = 30;  // max seconds a single chat call may hold the lock

	public function __construct() {
		global $wpdb;
		$this->conv_table = $wpdb->prefix . 'xen_ai_conversations';
		$this->msg_table  = $wpdb->prefix . 'xen_ai_messages';

		add_action( 'wp_ajax_xen_ai_init_session',        [ $this, 'init_session' ] );
		add_action( 'wp_ajax_nopriv_xen_ai_init_session', [ $this, 'init_session' ] );

		add_action( 'wp_ajax_xen_ai_chat',        [ $this, 'handle_chat' ] );
		add_action( 'wp_ajax_nopriv_xen_ai_chat', [ $this, 'handle_chat' ] );
	}

	// ── Session init ──────────────────────────────────────────────────────────

	public function init_session() {
		check_ajax_referer( 'xen_ai_chat', 'nonce' );

		// Block automated session-flooding before any DB work.
		if ( $this->is_session_init_limited() ) {
			wp_send_json_error( [
				'message' => __( 'Too many requests from your location. Please wait a moment and try again.', 'xen-ai' ),
			] );
		}

		// Reject suspicious or empty user agents (most legitimate browsers send one).
		if ( $this->looks_like_bot() ) {
			wp_send_json_error( [
				'message' => __( 'Please try again from a standard web browser.', 'xen-ai' ),
			] );
		}

		global $wpdb;

		$session_id  = wp_generate_uuid4();
		$page_url    = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '';
		$visitor_ip  = $this->get_trusted_ip();

		// Try insert with visitor_ip; fall back without it if column is missing (pre-migration).
		$inserted = $wpdb->insert(
			$this->conv_table,
			[
				'session_id' => $session_id,
				'page_url'   => $page_url,
				'visitor_ip' => $visitor_ip,
			],
			[ '%s', '%s', '%s' ]
		);

		if ( false === $inserted ) {
			$inserted = $wpdb->insert(
				$this->conv_table,
				[
					'session_id' => $session_id,
					'page_url'   => $page_url,
				],
				[ '%s', '%s' ]
			);
		}

		if ( false === $inserted ) {
			wp_send_json_error( [ 'message' => __( 'Could not start session. Please refresh and try again.', 'xen-ai' ) ] );
		}

		$response = apply_filters( 'xen_ai_session_response', [
			'session_id' => $session_id,
			'page_url'   => $page_url,
		] );

		wp_send_json_success( $response );
	}

	// ── Chat message ──────────────────────────────────────────────────────────

	public function handle_chat() {
		check_ajax_referer( 'xen_ai_chat', 'nonce' );

		$message    = isset( $_POST['message'] )    ? sanitize_text_field( wp_unslash( $_POST['message'] ) )    : '';
		$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
		$honeypot   = isset( $_POST['xen_hp'] )     ? (string) wp_unslash( $_POST['xen_hp'] )                   : '';

		// ── Honeypot: bots typically fill all visible-looking fields ──────────
		if ( '' !== $honeypot ) {
			// Silently accept-then-discard to avoid teaching the bot what failed.
			wp_send_json_success( [ 'reply' => __( "Thanks! I'll get back to you shortly.", 'xen-ai' ) ] );
		}

		if ( empty( $message ) ) {
			wp_send_json_error( [ 'message' => __( 'Please type a message.', 'xen-ai' ) ] );
		}

		// Hard cap on input length to prevent token-exhaustion attacks.
		if ( mb_strlen( $message ) > self::MAX_MESSAGE_CHARS ) {
			wp_send_json_error( [
				'message' => sprintf(
					/* translators: %d = character limit */
					__( 'Your message is too long. Please keep it under %d characters.', 'xen-ai' ),
					self::MAX_MESSAGE_CHARS
				),
			] );
		}

		if ( empty( $session_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Session ID missing. Please refresh and try again.', 'xen-ai' ) ] );
		}

		// Session ID must be a valid UUID4 — anything else is forged/abused.
		if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $session_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid session. Please refresh and try again.', 'xen-ai' ) ] );
		}

		// Optional bot challenge (Cloudflare Turnstile).
		$tt_check = $this->verify_turnstile_if_enabled();
		if ( is_wp_error( $tt_check ) ) {
			wp_send_json_error( [ 'message' => $tt_check->get_error_message() ] );
		}

		// ── Fallback mode (API recently quota-exhausted) ──────────────────────
		if ( get_transient( 'xen_ai_api_unavailable' ) ) {
			wp_send_json_success( [
				'reply'    => $this->get_fallback_reply(),
				'fallback' => true,
			] );
		}

		// ── Layered rate limiting ──────────────────────────────────────────────
		$limit_hit = $this->is_rate_limited( $session_id );
		if ( $limit_hit ) {
			$cooldown = 60; // seconds — frontend uses this to show a countdown
			$msg      = ( 'session' === $limit_hit )
				? __( "You've sent a lot of messages in this chat. Please take a short break or refresh the page.", 'xen-ai' )
				: __( "You're sending messages a bit too quickly. Please wait a moment.", 'xen-ai' );

			wp_send_json_error( [
				'message'      => $msg,
				'rate_limited' => true,
				'cooldown'     => $cooldown,
			] );
		}

		// ── Concurrency lock: stop multi-tab / rapid-fire abuse ───────────────
		if ( ! $this->acquire_session_lock( $session_id ) ) {
			wp_send_json_error( [
				'message' => __( 'Please wait for the previous response to finish.', 'xen-ai' ),
			] );
		}

		// ── Validate session ───────────────────────────────────────────────────
		$conv = $this->get_conversation( $session_id );
		if ( ! $conv ) {
			$this->release_session_lock( $session_id );
			wp_send_json_error( [ 'message' => __( 'Session not found. Please refresh the page.', 'xen-ai' ) ] );
		}

		// ── Build message history (last 10 turns) ──────────────────────────────
		$history  = $this->get_history( $conv->id, 10 );
		$messages = [];
		foreach ( $history as $row ) {
			$messages[] = [ 'role' => $row->role, 'content' => $row->content ];
		}
		$messages[] = [ 'role' => 'user', 'content' => $message ];

		// ── Retrieve KB context ────────────────────────────────────────────────
		$kb      = new Xen_AI_Knowledge_Base();
		$kb_ctx  = $kb->get_context_for_query( $message );

		// ── Retrieve live site content (pages, posts, WooCommerce products) ───
		$site     = new Xen_AI_Site_Content();
		$site_ctx = $site->get_context_for_query( $message );
		$context  = trim( $kb_ctx . ( $kb_ctx && $site_ctx ? "\n\n" : '' ) . $site_ctx );

		// Allow Pro features to enrich context (e.g. KB topic insights)
		$context = apply_filters( 'xen_ai_chat_context', $context, $message );

		// ── Call AI ────────────────────────────────────────────────────────────
		$lead_status = [
			'has_name'  => ! empty( $conv->user_name ),
			'has_email' => ! empty( $conv->user_email ),
		];
		$ai       = new Xen_AI_Handler();
		$raw      = $ai->get_response( $messages, $context, $lead_status );

		if ( is_wp_error( $raw ) ) {
			$this->release_session_lock( $session_id );
			$friendly = $this->map_error_to_friendly( $raw );
			wp_send_json_error( [ 'message' => $friendly ] );
		}

		$user_data = $ai->extract_user_data( $raw );
		$reply     = $ai->clean_response( $raw );

		// ── Persist ────────────────────────────────────────────────────────────
		$this->save_message( $conv->id, 'user',      $message );
		$this->save_message( $conv->id, 'assistant', $reply );

		if ( ! empty( $user_data ) ) {
			$this->update_conversation( $conv->id, $user_data );
		}

		$this->release_session_lock( $session_id );

		// Allow Pro features to append extra fields (e.g. related_topics) to the response.
		$response_data = apply_filters( 'xen_ai_chat_reply_data', [ 'reply' => $reply ], $message );
		wp_send_json_success( $response_data );
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private function get_conversation( $session_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->conv_table} WHERE session_id = %s LIMIT 1",
				$session_id
			)
		);
	}

	private function get_history( $conv_id, $limit ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT role, content FROM {$this->msg_table}
				 WHERE conversation_id = %d
				 ORDER BY created_at ASC
				 LIMIT %d",
				absint( $conv_id ),
				$limit
			)
		);
	}

	private function save_message( $conv_id, $role, $content ) {
		global $wpdb;
		$wpdb->insert(
			$this->msg_table,
			[
				'conversation_id' => absint( $conv_id ),
				'role'            => sanitize_text_field( $role ),
				'content'         => $content,
			],
			[ '%d', '%s', '%s' ]
		);
	}

	private function update_conversation( $conv_id, array $data ) {
		global $wpdb;

		$update  = [];
		$formats = [];

		if ( ! empty( $data['name'] ) ) {
			$update['user_name'] = sanitize_text_field( $data['name'] );
			$formats[]           = '%s';
		}

		if ( ! empty( $data['email'] ) && is_email( $data['email'] ) ) {
			$update['user_email'] = sanitize_email( $data['email'] );
			$formats[]            = '%s';
		}

		if ( $update ) {
			$wpdb->update(
				$this->conv_table,
				$update,
				[ 'id' => absint( $conv_id ) ],
				$formats,
				[ '%d' ]
			);
		}
	}

	private function is_rate_limited( $session_id ) {
		$ip = $this->get_trusted_ip();

		// Layer 1a: per-IP burst protection (minute window)
		$ip_min_key = 'xen_rl_ipm_' . md5( $ip );
		$ip_min_cnt = (int) get_transient( $ip_min_key );
		if ( $ip_min_cnt >= self::RATE_LIMIT_IP_MIN ) {
			return 'ip';
		}

		// Layer 1b: per-IP hourly cap
		$ip_hour_key = 'xen_rl_iph_' . md5( $ip );
		$ip_hour_cnt = (int) get_transient( $ip_hour_key );
		if ( $ip_hour_cnt >= self::RATE_LIMIT_IP_HOUR ) {
			return 'ip';
		}

		// Layer 2: per-session hourly cap
		$sess_key = 'xen_rl_s_' . md5( $session_id );
		$sess_cnt = (int) get_transient( $sess_key );
		if ( $sess_cnt >= self::RATE_LIMIT_SESSION ) {
			return 'session';
		}

		// Increment all counters with their TTL windows.
		set_transient( $ip_min_key,  $ip_min_cnt  + 1, MINUTE_IN_SECONDS );
		set_transient( $ip_hour_key, $ip_hour_cnt + 1, HOUR_IN_SECONDS );
		set_transient( $sess_key,    $sess_cnt    + 1, HOUR_IN_SECONDS );

		return false;
	}

	/**
	 * Block session-init flooding from a single IP.
	 */
	private function is_session_init_limited() {
		$ip  = $this->get_trusted_ip();
		$key = 'xen_si_' . md5( $ip );
		$cnt = (int) get_transient( $key );

		if ( $cnt >= self::RATE_LIMIT_SESSION_INIT ) {
			return true;
		}
		set_transient( $key, $cnt + 1, 10 * MINUTE_IN_SECONDS );
		return false;
	}

	/**
	 * Atomic-ish concurrency lock per session. Prevents the same session
	 * from issuing concurrent AI calls (multi-tab abuse, double-submit).
	 */
	private function acquire_session_lock( $session_id ) {
		$key = 'xen_lock_' . md5( $session_id );
		if ( get_transient( $key ) ) {
			return false;
		}
		set_transient( $key, 1, self::SESSION_LOCK_TTL );
		return true;
	}

	private function release_session_lock( $session_id ) {
		delete_transient( 'xen_lock_' . md5( $session_id ) );
	}

	/**
	 * Map a WP_Error from the AI handler to a visitor-safe message.
	 * Raw quota / rate-limit / billing strings are never shown to the public.
	 */
	private function map_error_to_friendly( WP_Error $err ) {
		$code = $err->get_error_code();
		$raw  = $err->get_error_message();

		// Detect quota / billing / rate exhaustion in the upstream response.
		$is_quota = (
			false !== stripos( $raw, 'quota' ) ||
			false !== stripos( $raw, 'rate limit' ) ||
			false !== stripos( $raw, 'rate_limit' ) ||
			false !== stripos( $raw, 'billing' ) ||
			false !== stripos( $raw, 'insufficient' ) ||
			false !== stripos( $raw, '429' )
		);

		if ( $is_quota ) {
			// Trip the fallback flag so subsequent requests skip the AI call entirely.
			set_transient( 'xen_ai_api_unavailable', 1, 5 * MINUTE_IN_SECONDS );
			$this->log_error( 'quota', $raw );
			return $this->get_fallback_reply();
		}

		if ( 'no_api_key' === $code || 'no_token' === $code ) {
			$this->log_error( $code, $raw );
			return __( "The assistant isn't fully set up yet. Please leave a message and the site owner will follow up.", 'xen-ai' );
		}

		$this->log_error( $code, $raw );
		return __( "I'm having trouble responding right now. Please try again in a moment.", 'xen-ai' );
	}

	private function get_fallback_reply() {
		$settings = get_option( 'xen_ai_settings', [] );
		if ( ! empty( $settings['fallback_message'] ) ) {
			return (string) $settings['fallback_message'];
		}
		return __( "I'm a little busy at the moment! Please leave your name and email and I'll get back to you as soon as I can.", 'xen-ai' );
	}

	/**
	 * Verify a Cloudflare Turnstile token when enabled in settings.
	 * Returns true on success, WP_Error on failure, true (skipped) when disabled.
	 */
	private function verify_turnstile_if_enabled() {
		$s = get_option( 'xen_ai_settings', [] );
		if ( empty( $s['turnstile_secret_key'] ) ) {
			return true; // disabled
		}

		$token = isset( $_POST['cf_turnstile_response'] )
			? sanitize_text_field( wp_unslash( $_POST['cf_turnstile_response'] ) )
			: '';

		if ( empty( $token ) ) {
			return new WP_Error( 'turnstile_missing', __( 'Please complete the verification challenge.', 'xen-ai' ) );
		}

		$response = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', [
			'timeout' => 10,
			'body'    => [
				'secret'   => $s['turnstile_secret_key'],
				'response' => $token,
				'remoteip' => $this->get_trusted_ip(),
			],
		] );

		if ( is_wp_error( $response ) ) {
			// Fail-open: don't lock visitors out if Cloudflare is unreachable.
			return true;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['success'] ) ) {
			return new WP_Error( 'turnstile_failed', __( 'Human verification failed. Please refresh and try again.', 'xen-ai' ) );
		}
		return true;
	}

	/**
	 * Lightweight bot heuristic: reject completely empty user agents and
	 * common scraping libraries on the session-init endpoint only.
	 */
	private function looks_like_bot() {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
		if ( '' === trim( $ua ) ) {
			return true;
		}
		$blocked = [ 'curl/', 'wget/', 'python-requests', 'python-urllib', 'go-http-client', 'httpclient', 'libwww-perl', 'scrapy' ];
		$ua_low  = strtolower( $ua );
		foreach ( $blocked as $needle ) {
			if ( false !== strpos( $ua_low, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Log an error to PHP error_log (never to the frontend).
	 */
	private function log_error( $code, $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( '[XEN AI] [%s] %s', $code, $message ) );
		}
	}

	/**
	 * Resolve the visitor's IP from the TCP connection only, by default.
	 * Forwarded headers (X-Forwarded-For, X-Real-IP, CF-Connecting-IP) are
	 * IGNORED unless explicitly opted into via a wp-config constant — these
	 * headers are attacker-controlled on direct-connection hosting.
	 *
	 * To trust Cloudflare in wp-config.php:
	 *   define( 'XEN_AI_TRUST_CF_IP', true );
	 * To trust a reverse-proxy supplying X-Forwarded-For:
	 *   define( 'XEN_AI_TRUST_PROXY_IP', true );
	 */
	private function get_trusted_ip() {
		if ( defined( 'XEN_AI_TRUST_CF_IP' ) && XEN_AI_TRUST_CF_IP && ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$ip = trim( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return sanitize_text_field( $ip );
			}
		}

		if ( defined( 'XEN_AI_TRUST_PROXY_IP' ) && XEN_AI_TRUST_PROXY_IP && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$parts = explode( ',', wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			$ip    = trim( $parts[0] );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return sanitize_text_field( $ip );
			}
		}

		$ip = ! empty( $_SERVER['REMOTE_ADDR'] ) ? trim( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? sanitize_text_field( $ip ) : '0.0.0.0';
	}
}
