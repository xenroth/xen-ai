<?php
/**
 * Plugin Name: XEN A.I
 * Plugin URI:  https://github.com/sepiroth-x/xen-ai
 * Description: AI-powered chat assistant with website knowledge base, lead capture, and full admin management.
 * Version:     1.0.4
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author:      Xenroth (Richard C. Cupal, LPT)
 * Author URI:  mailto:me@xenroth.com
 * Contact:     +63 915 038 8448 | me@xenroth.com
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: xen-ai
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Constants ──────────────────────────────────────────────────────────────────
define( 'XEN_AI_VERSION',          '1.0.4' );
define( 'XEN_AI_PLUGIN_FILE',      __FILE__ );
define( 'XEN_AI_PLUGIN_DIR',       plugin_dir_path( __FILE__ ) );
define( 'XEN_AI_PLUGIN_URL',       plugin_dir_url( __FILE__ ) );
define( 'XEN_AI_PLUGIN_BASENAME',  plugin_basename( __FILE__ ) );

/**
 * Return the filesystem path to the plugin's private uploads folder.
 * Using a function avoids calling wp_upload_dir() at define() time.
 */
function xen_ai_uploads_dir() {
	return wp_upload_dir()['basedir'] . '/xen-ai/';
}

// ── Load required classes ──────────────────────────────────────────────────────
$xen_ai_files = [
	'includes/class-xen-ai-core.php',
	'includes/class-knowledge-base.php',
	'includes/class-site-content.php',
	'includes/class-license.php',
	'includes/class-pro-features.php',
	'includes/class-updater.php',
	'includes/class-ai-handler.php',
	'includes/class-file-processor.php',
	'includes/class-chat-ajax.php',
];

foreach ( $xen_ai_files as $file ) {
	require_once XEN_AI_PLUGIN_DIR . $file;
}

if ( is_admin() ) {
	require_once XEN_AI_PLUGIN_DIR . 'admin/class-admin.php';
}

// ── Activation / Deactivation ──────────────────────────────────────────────────
register_activation_hook( __FILE__, [ 'Xen_AI_Core', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Xen_AI_Core', 'deactivate' ] );

// ── Boot ───────────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', function () {
	Xen_AI_Core::get_instance()->init();
	// Boot pro features only when license is valid
	Xen_AI_Pro_Features::get_instance()->init();
	// GitHub auto-updater
	( new Xen_AI_Updater( XEN_AI_PLUGIN_FILE ) )->init();
} );
