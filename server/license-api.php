<?php
/**
 * XEN A.I Pro — License Server API
 *
 * Deploy this file to your web server at:
 *   https://api.xenroth.com/xen-ai/license/
 *
 * URL routing (set up via .htaccess or nginx rewrite):
 *   POST /verify    → action: verify   — activate a license key on a domain
 *   POST /deactivate → action: deactivate — remove a domain activation
 *
 * Dependencies: PHP 7.4+, MySQL (PDO)
 *
 * IMPORTANT: Change HMAC_SECRET to any long random string.
 *            It MUST match the value of Xen_AI_License::HMAC_SECRET in the plugin.
 */

// ── Configuration ─────────────────────────────────────────────────────────────

define( 'HMAC_SECRET', 'XEN_REPLACE_WITH_YOUR_OWN_HMAC_SECRET_32CHARS' ); // ← change this!
define( 'DB_DSN',      'mysql:host=127.0.0.1;dbname=xenroth_licenses;charset=utf8mb4' );
define( 'DB_USER',     'db_user' );   // ← change
define( 'DB_PASS',     'db_pass' );   // ← change

// Max activations a single key is allowed (per purchase type). Overridden per-key in DB.
define( 'DEFAULT_MAX_ACTIVATIONS', 1 );

// ── Bootstrap ─────────────────────────────────────────────────────────────────

header( 'Content-Type: application/json; charset=utf-8' );
header( 'X-Content-Type-Options: nosniff' );
header( 'X-Frame-Options: DENY' );

// Only accept POST
if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code( 405 );
    die( json_encode( [ 'message' => 'Method not allowed.' ] ) );
}

// Decode JSON body or fall back to $_POST
$body   = (array) json_decode( file_get_contents( 'php://input' ), true );
$action = sanitize_str( $body['action']  ?? ( $_POST['action']  ?? '' ) );
$key    = sanitize_str( $body['key']     ?? ( $_POST['key']     ?? '' ) );
$domain = sanitize_domain( $body['domain'] ?? ( $_POST['domain'] ?? '' ) );

if ( ! in_array( $action, [ 'verify', 'deactivate' ], true ) ) {
    respond( 400, [ 'message' => 'Invalid action.' ] );
}

if ( empty( $key ) ) {
    respond( 400, [ 'message' => 'License key is required.' ] );
}

if ( empty( $domain ) ) {
    respond( 400, [ 'message' => 'Domain is required.' ] );
}

$pdo = get_pdo();

// ── Route ─────────────────────────────────────────────────────────────────────

if ( $action === 'verify' ) {
    handle_verify( $pdo, $key, $domain );
} else {
    handle_deactivate( $pdo, $key, $domain );
}

// ── Handlers ─────────────────────────────────────────────────────────────────

function handle_verify( PDO $pdo, string $key, string $domain ): void {
    // 1. Look up the key
    $stmt = $pdo->prepare( 'SELECT * FROM license_keys WHERE `key` = ? AND status = "active" LIMIT 1' );
    $stmt->execute( [ $key ] );
    $row = $stmt->fetch( PDO::FETCH_ASSOC );

    if ( ! $row ) {
        respond( 403, [ 'message' => 'Invalid or inactive license key.' ] );
    }

    // 2. Check if this domain is already registered
    $stmt2 = $pdo->prepare( 'SELECT * FROM license_activations WHERE license_id = ? AND domain = ? LIMIT 1' );
    $stmt2->execute( [ $row['id'], $domain ] );
    $existing = $stmt2->fetch( PDO::FETCH_ASSOC );

    if ( ! $existing ) {
        // 3. Count current activations
        $stmt3 = $pdo->prepare( 'SELECT COUNT(*) FROM license_activations WHERE license_id = ?' );
        $stmt3->execute( [ $row['id'] ] );
        $count = (int) $stmt3->fetchColumn();

        $max = (int) ( $row['max_activations'] ?? DEFAULT_MAX_ACTIVATIONS );

        if ( $count >= $max ) {
            respond( 403, [ 'message' => "This license has reached its activation limit ({$max} site). Deactivate from an existing site first, or purchase an additional license." ] );
        }

        // 4. Register new activation
        $stmt4 = $pdo->prepare( 'INSERT INTO license_activations (license_id, domain, activated_at) VALUES (?, ?, NOW())' );
        $stmt4->execute( [ $row['id'], $domain ] );
    }

    // 5. Build and sign a token
    $payload = base64url_encode( json_encode( [
        'key'     => $key,
        'domain'  => $domain,
        'product' => 'xen-ai-pro',
        'iat'     => time(),
    ] ) );
    $signature = base64url_encode( hash_hmac( 'sha256', $payload, HMAC_SECRET, true ) );
    $token = $payload . '.' . $signature;

    respond( 200, [ 'token' => $token ] );
}

function handle_deactivate( PDO $pdo, string $key, string $domain ): void {
    $stmt = $pdo->prepare( 'SELECT id FROM license_keys WHERE `key` = ? LIMIT 1' );
    $stmt->execute( [ $key ] );
    $row = $stmt->fetch( PDO::FETCH_ASSOC );

    if ( ! $row ) {
        respond( 404, [ 'message' => 'License key not found.' ] );
    }

    $stmt2 = $pdo->prepare( 'DELETE FROM license_activations WHERE license_id = ? AND domain = ?' );
    $stmt2->execute( [ $row['id'], $domain ] );

    respond( 200, [ 'message' => 'License deactivated.' ] );
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function get_pdo(): PDO {
    try {
        $pdo = new PDO( DB_DSN, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ] );
        return $pdo;
    } catch ( PDOException $e ) {
        respond( 500, [ 'message' => 'Database connection failed.' ] );
    }
}

function respond( int $code, array $body ): void {
    http_response_code( $code );
    echo json_encode( $body );
    exit;
}

function sanitize_str( $val ): string {
    return trim( strip_tags( (string) $val ) );
}

function sanitize_domain( $val ): string {
    $domain = sanitize_str( $val );
    // Strip scheme, path, port — keep bare hostname
    $parsed = parse_url( $domain );
    if ( isset( $parsed['host'] ) ) {
        $domain = $parsed['host'];
    } elseif ( isset( $parsed['path'] ) ) {
        $domain = $parsed['path'];
    }
    // Remove www. prefix for consistency
    $domain = preg_replace( '/^www\./i', '', $domain );
    return strtolower( trim( $domain, '/' ) );
}

/** URL-safe base64 encode (no padding). */
function base64url_encode( string $data ): string {
    return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
}
