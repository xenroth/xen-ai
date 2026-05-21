<?php
/**
 * XEN A.I — Uninstall handler
 *
 * WordPress runs this file automatically when the plugin is deleted
 * via Plugins → Delete (not just deactivated).
 *
 * Behaviour is controlled by the "clean_uninstall" setting:
 *   - If enabled (checkbox ticked in Settings → Data & Uninstall):
 *     ALL plugin data is permanently wiped (DB tables, options, transients, uploads).
 *   - If disabled (default): nothing is deleted — data is preserved so a
 *     reinstall picks up exactly where it left off.
 */

// WordPress safety guard — must be present in every uninstall.php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'xen_ai_settings', [] );

// Only wipe data when the user explicitly opted in
if ( empty( $settings['clean_uninstall'] ) ) {
	return;
}

global $wpdb;

// ── Drop custom tables ────────────────────────────────────────────────────────
$tables = [
	$wpdb->prefix . 'xen_ai_messages',
	$wpdb->prefix . 'xen_ai_conversations',
	$wpdb->prefix . 'xen_ai_knowledge',
];

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

// ── Delete all wp_options entries ─────────────────────────────────────────────
$options = [
	'xen_ai_settings',
	'xen_ai_license',
	'xen_ai_db_version',
];

foreach ( $options as $opt ) {
	delete_option( $opt );
}

// ── Delete transients ─────────────────────────────────────────────────────────
$transients = [
	'xen_ai_kb_all',
	'xen_ai_license_valid',
	'xen_ai_gh_release',
];

foreach ( $transients as $t ) {
	delete_transient( $t );
}

// Also wipe any site_content cache transients (prefixed with xen_ai_site_)
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_xen_ai_site_%' OR option_name LIKE '_transient_timeout_xen_ai_site_%'"
); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

// ── Remove private uploads folder ────────────────────────────────────────────
$upload_dir = wp_upload_dir();
$xen_dir    = trailingslashit( $upload_dir['basedir'] ) . 'xen-ai';

if ( is_dir( $xen_dir ) ) {
	xen_ai_rmdir( $xen_dir );
}

/**
 * Recursively delete a directory and all its contents.
 *
 * @param string $dir
 */
function xen_ai_rmdir( $dir ) {
	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $items as $item ) {
		if ( $item->isDir() ) {
			rmdir( $item->getRealPath() );
		} else {
			unlink( $item->getRealPath() );
		}
	}
	rmdir( $dir );
}
