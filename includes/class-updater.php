<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GitHub-based auto-updater for XEN A.I.
 *
 * Hooks into WordPress's native update mechanism so the plugin appears in
 * Dashboard → Updates and supports one-click updates — just like any
 * WordPress.org plugin, but pulling releases from GitHub instead.
 *
 * How releases work:
 *   1. On GitHub, create a new Release with a tag like  v1.0.1
 *   2. Attach a zip named  ai_assistance.zip  (containing the ai_assistance/ folder)
 *      as a release asset — OR leave it blank and the updater falls back to
 *      GitHub's auto-generated source zip.
 *   3. The plugin checks every 12 hours. If the tag version > installed version,
 *      WordPress shows the "Update available" notice automatically.
 */
class Xen_AI_Updater {

	/** GitHub repository owner. */
	const GH_USER = 'xenroth';

	/** GitHub repository name. */
	const GH_REPO = 'xen-ai';

	/** Transient key for caching the remote release data. */
	const TRANSIENT = 'xen_ai_gh_release';

	/** How long to cache the release check (12 hours). */
	const CACHE_TTL = 12 * HOUR_IN_SECONDS;

	/** Absolute path to the main plugin file. */
	private $plugin_file;

	/** plugin_basename( $plugin_file ) */
	private $plugin_basename;

	/** Slug used by WordPress (folder name). */
	private $plugin_slug;

	public function __construct( $plugin_file ) {
		$this->plugin_file     = $plugin_file;
		$this->plugin_basename = plugin_basename( $plugin_file );
		$this->plugin_slug     = dirname( $this->plugin_basename );
	}

	/** Register all hooks. Call once from the main plugin file. */
	public function init() {
		// Inject update info into WordPress's update transient
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'inject_update' ] );

		// Supply plugin info for the "View version x.x details" modal
		add_filter( 'plugins_api', [ $this, 'plugin_info' ], 10, 3 );

		// Rename the extracted GitHub source folder to match the plugin slug
		add_filter( 'upgrader_source_selection', [ $this, 'fix_source_dir' ], 10, 4 );

		// Force-check action (admin only)
		add_action( 'wp_ajax_xen_ai_force_update_check', [ $this, 'ajax_force_check' ] );
	}

	// ── WordPress update hooks ────────────────────────────────────────────────

	/**
	 * Called when WordPress refreshes its plugin update transient.
	 * If a newer release exists on GitHub, we add our plugin to the list.
	 *
	 * @param  object $transient
	 * @return object
	 */
	public function inject_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $transient;
		}

		$remote_version = ltrim( $release['tag_name'], 'vV. ' );

		if ( version_compare( $remote_version, XEN_AI_VERSION, '>' ) ) {
			$transient->response[ $this->plugin_basename ] = (object) [
				'slug'        => $this->plugin_slug,
				'plugin'      => $this->plugin_basename,
				'new_version' => $remote_version,
				'url'         => $release['html_url'],
				'package'     => $this->get_download_url( $release ),
				'icons'       => [],
				'banners'     => [],
				'tested'      => '',
				'requires_php'=> '7.4',
			];
		} else {
			// Tell WP the plugin is up-to-date
			$transient->no_update[ $this->plugin_basename ] = (object) [
				'slug'        => $this->plugin_slug,
				'plugin'      => $this->plugin_basename,
				'new_version' => XEN_AI_VERSION,
				'url'         => $release['html_url'],
				'package'     => '',
			];
		}

		return $transient;
	}

	/**
	 * Populate the "View version details" modal in the WordPress updates screen.
	 *
	 * @param  false|object|array $result
	 * @param  string             $action
	 * @param  object             $args
	 * @return false|object
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( $action !== 'plugin_information' ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || $args->slug !== $this->plugin_slug ) {
			return $result;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $result;
		}

		$remote_version = ltrim( $release['tag_name'], 'vV. ' );

		return (object) [
			'name'          => 'XEN A.I',
			'slug'          => $this->plugin_slug,
			'version'       => $remote_version,
			'author'        => '<a href="mailto:me@xenroth.com">Xenroth (Richard C. Cupal, LPT)</a>',
			'homepage'      => 'https://github.com/' . self::GH_USER . '/' . self::GH_REPO,
			'requires'      => '5.8',
			'requires_php'  => '7.4',
			'last_updated'  => $release['published_at'],
			'download_link' => $this->get_download_url( $release ),
			'sections'      => [
				'description' => 'AI-powered chat assistant with knowledge base, lead capture, and full admin management.',
				'changelog'   => nl2br( esc_html( $release['body'] ?? '' ) ),
			],
			'banners'       => [],
		];
	}

	/**
	 * GitHub's source zip extracts to a folder named  xen-ai-1.0.1/  (or similar).
	 * WordPress expects  ai_assistance/  — this filter renames it on the fly.
	 *
	 * @param  string      $source        Path to the extracted folder.
	 * @param  string      $remote_source Temp dir.
	 * @param  WP_Upgrader $upgrader
	 * @param  array       $hook_extra
	 * @return string      Corrected path.
	 */
	public function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra = [] ) {
		// Only act on our own plugin
		if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_basename ) {
			return $source;
		}

		$expected = trailingslashit( $remote_source ) . $this->plugin_slug;

		// Already correct (e.g. user uploaded a properly named zip)
		if ( trailingslashit( $source ) === trailingslashit( $expected ) ) {
			return $source;
		}

		global $wp_filesystem;

		if ( $wp_filesystem->exists( $expected ) ) {
			$wp_filesystem->delete( $expected, true );
		}

		if ( ! $wp_filesystem->move( $source, $expected ) ) {
			return new WP_Error(
				'xen_ai_rename_failed',
				__( 'Could not rename the update folder. Please update manually.', 'xen-ai' )
			);
		}

		return trailingslashit( $expected );
	}

	// ── GitHub API ────────────────────────────────────────────────────────────

	/**
	 * Fetch the latest release from GitHub, with 12-hour transient caching.
	 *
	 * @return array|null  Decoded release array or null on failure.
	 */
	private function get_latest_release() {
		$cached = get_transient( self::TRANSIENT );
		if ( false !== $cached ) {
			return $cached ?: null; // '0' stored on failure to avoid hammering the API
		}

		$headers = [
			'timeout'    => 10,
			'user-agent' => 'XEN-AI-Updater/' . XEN_AI_VERSION . '; ' . get_site_url(),
			'headers'    => [ 'Accept' => 'application/vnd.github+json' ],
		];

		// ── Try /releases/latest first (published releases only) ──────────────
		$url      = 'https://api.github.com/repos/' . self::GH_USER . '/' . self::GH_REPO . '/releases/latest';
		$response = wp_remote_get( $url, $headers );

		if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
			$release = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! empty( $release['tag_name'] ) ) {
				set_transient( self::TRANSIENT, $release, self::CACHE_TTL );
				return $release;
			}
		}

		// ── Fallback: fetch the releases list (catches draft/pre-releases too) ──
		$url      = 'https://api.github.com/repos/' . self::GH_USER . '/' . self::GH_REPO . '/releases?per_page=5';
		$response = wp_remote_get( $url, $headers );

		if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
			$list = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( is_array( $list ) && ! empty( $list[0]['tag_name'] ) ) {
				$release = $list[0];
				set_transient( self::TRANSIENT, $release, self::CACHE_TTL );
				return $release;
			}
		}

		// ── Fallback: try the tags API so un-released tags are still detected ──
		$url      = 'https://api.github.com/repos/' . self::GH_USER . '/' . self::GH_REPO . '/tags?per_page=5';
		$response = wp_remote_get( $url, $headers );

		if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
			$tags = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( is_array( $tags ) && ! empty( $tags[0]['name'] ) ) {
				// Synthesise a minimal release-like array from the tag.
				$release = [
					'tag_name'     => $tags[0]['name'],
					'html_url'     => 'https://github.com/' . self::GH_USER . '/' . self::GH_REPO . '/releases/tag/' . rawurlencode( $tags[0]['name'] ),
					'zipball_url'  => $tags[0]['zipball_url'] ?? '',
					'published_at' => '',
					'body'         => '',
					'assets'       => [],
				];
				set_transient( self::TRANSIENT, $release, self::CACHE_TTL );
				return $release;
			}
		}

		// Cache a failure briefly so we don't hammer GitHub on every page load.
		set_transient( self::TRANSIENT, '0', HOUR_IN_SECONDS );
		return null;
	}

	/**
	 * Returns the best available download URL for a release.
	 *
	 * Priority:
	 *  1. A release asset named  ai_assistance.zip  (properly structured plugin zip)
	 *  2. GitHub's auto-generated zipball (folder gets renamed by fix_source_dir())
	 *
	 * @param  array  $release
	 * @return string
	 */
	private function get_download_url( array $release ) {
		// Look for a specifically named release asset first
		if ( ! empty( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				if ( $asset['name'] === 'ai_assistance.zip' && ! empty( $asset['browser_download_url'] ) ) {
					return $asset['browser_download_url'];
				}
			}
		}

		// Fall back to GitHub's source zipball
		return $release['zipball_url'] ?? '';
	}

	// ── Manual cache clear ────────────────────────────────────────────────────

	/**
	 * Force the next update check to hit GitHub instead of cache.
	 * Call this after manually installing a new version or for testing.
	 */
	public static function clear_cache() {
		delete_transient( self::TRANSIENT );
		delete_site_transient( 'update_plugins' );
	}

	/** AJAX handler — admin only force-check. */
	public function ajax_force_check() {
		check_ajax_referer( 'xen_ai_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}

		self::clear_cache();

		// Force WordPress to re-run its plugin update check immediately.
		if ( ! function_exists( 'wp_update_plugins' ) ) {
			require_once ABSPATH . 'wp-includes/update.php';
		}
		wp_update_plugins();

		$release = $this->get_latest_release();
		if ( ! $release ) {
			wp_send_json_error( [ 'message' => 'Could not reach GitHub. Check your server\'s outbound connections.' ] );
		}
		$version = ltrim( $release['tag_name'], 'vV. ' );
		wp_send_json_success( [
			'message'          => 'Latest release on GitHub: v' . $version . ' (installed: ' . XEN_AI_VERSION . ')',
			'remote_version'   => $version,
			'installed'        => XEN_AI_VERSION,
			'update_available' => version_compare( $version, XEN_AI_VERSION, '>' ),
		] );
	}
}
