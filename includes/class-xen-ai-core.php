<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core singleton — boots all subsystems, creates DB tables on activation,
 * and injects the front-end chat widget.
 */
class Xen_AI_Core {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	// ── Boot ──────────────────────────────────────────────────────────────────

	public function init() {
		$this->maybe_upgrade_db();
		new Xen_AI_Chat_Ajax();

		if ( is_admin() ) {
			new Xen_AI_Admin();
		}

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
		add_action( 'wp_footer',          [ $this, 'render_chat_widget' ] );

		// Daily cleanup of expired rate-limit / lock / cache transients.
		add_action( 'xen_ai_cleanup_transients', [ __CLASS__, 'cleanup_transients' ] );
		if ( ! wp_next_scheduled( 'xen_ai_cleanup_transients' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'xen_ai_cleanup_transients' );
		}
	}

	// ── Front-end assets ──────────────────────────────────────────────────────

	public function enqueue_frontend_assets() {
		$settings = get_option( 'xen_ai_settings', [] );

		if ( ! empty( $settings['disable_chat'] ) ) {
			return;
		}

		wp_enqueue_style(
			'xen-ai-chat',
			XEN_AI_PLUGIN_URL . 'assets/css/chat.css',
			[],
			XEN_AI_VERSION
		);

		wp_enqueue_script(
			'xen-ai-chat',
			XEN_AI_PLUGIN_URL . 'assets/js/chat.js',
			[ 'jquery' ],
			XEN_AI_VERSION,
			true
		);

		// Optional Cloudflare Turnstile loader (only when a site key is configured).
		if ( ! empty( $settings['turnstile_site_key'] ) ) {
			wp_enqueue_script(
				'cf-turnstile',
				'https://challenges.cloudflare.com/turnstile/v0/api.js',
				[],
				null,
				true
			);
		}

		$accent       = ! empty( $settings['accent_color'] )     ? sanitize_hex_color( $settings['accent_color'] )       : '#4f46e5';
		$greeting     = ! empty( $settings['greeting_message'] ) ? $settings['greeting_message']                         : "Hi there! \xf0\x9f\x91\x8b I\xe2\x80\x99m XEN, your AI assistant. How can I help you today?";
		$bot_name     = ! empty( $settings['bot_name'] )         ? $settings['bot_name']                                 : 'XEN A.I';
		$notify_msg   = ! empty( $settings['notify_message'] )   ? $settings['notify_message']                           : "Hello! Need any help? \xf0\x9f\x92\xac";
		$notify_delay = ! empty( $settings['notify_delay'] )     ? absint( $settings['notify_delay'] )                   : 4000;

		$bot_logo = ! empty( $settings['bot_logo_url'] ) ? esc_url( $settings['bot_logo_url'] ) : '';

		wp_localize_script( 'xen-ai-chat', 'xenAI', [
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'xen_ai_chat' ),
			'greeting'     => $greeting,
			'botName'      => $bot_name,
			'accentColor'  => $accent,
			'notifyDelay'  => $notify_delay,
			'notifyMsg'    => $notify_msg,
			'botLogoUrl'   => $bot_logo,
			'turnstileKey' => ! empty( $settings['turnstile_site_key'] ) ? $settings['turnstile_site_key'] : '',
			'maxChars'     => 2000,
		] );
	}

	public function render_chat_widget() {
		$settings = get_option( 'xen_ai_settings', [] );
		if ( ! empty( $settings['disable_chat'] ) ) {
			return;
		}
		include XEN_AI_PLUGIN_DIR . 'admin/views/chat-widget.php';
	}

	// ── DB migration ─────────────────────────────────────────────────────────

	private function maybe_upgrade_db() {
		global $wpdb;
		$table = $wpdb->prefix . 'xen_ai_conversations';

		// Run full dbDelta when version is behind.
		if ( get_option( 'xen_ai_db_version' ) !== XEN_AI_VERSION ) {
			self::activate();
		}

		// Safety-net: add visitor_ip if dbDelta skipped it on existing installs.
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 );
		if ( is_array( $columns ) && ! in_array( 'visitor_ip', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN visitor_ip varchar(45) DEFAULT NULL AFTER user_email" );
		}
	}

	// ── Activation ────────────────────────────────────────────────────────────

	public static function activate() {
		global $wpdb;
		$c = $wpdb->get_charset_collate();

		$sqls = [];

		// Knowledge base
		$sqls[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}xen_ai_knowledge (
			id           bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title        varchar(255)        NOT NULL DEFAULT '',
			content      longtext            NOT NULL,
			source_type  varchar(20)         NOT NULL DEFAULT 'file',
			source       varchar(1000)                DEFAULT NULL,
			file_type    varchar(20)                  DEFAULT NULL,
			status       varchar(20)         NOT NULL DEFAULT 'active',
			created_at   datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY status (status)
		) $c;";

		// Conversations
		$sqls[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}xen_ai_conversations (
			id           bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_id   varchar(100)        NOT NULL,
			user_name    varchar(100)                 DEFAULT NULL,
			user_email   varchar(150)                 DEFAULT NULL,
			visitor_ip   varchar(45)                  DEFAULT NULL,
			page_url     varchar(1000)                DEFAULT NULL,
			created_at   datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at   datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY session_id (session_id)
		) $c;";

		// Messages
		$sqls[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}xen_ai_messages (
			id               bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			conversation_id  bigint(20) unsigned NOT NULL,
			role             varchar(20)         NOT NULL,
			content          text                NOT NULL,
			created_at       datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY conversation_id (conversation_id)
		) $c;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( $sqls as $sql ) {
			dbDelta( $sql );
		}

		// Private uploads folder
		$dir = xen_ai_uploads_dir();
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
			// Block direct HTTP access
			file_put_contents( $dir . '.htaccess', 'deny from all' . PHP_EOL );
			file_put_contents( $dir . 'index.php', '<?php // Silence is golden' . PHP_EOL );
		}

		// Default settings
		if ( ! get_option( 'xen_ai_settings' ) ) {
			add_option( 'xen_ai_settings', [
				'provider'         => 'openai',
				'api_key'          => '',
				'github_token'     => '',
				'github_model'     => 'gpt-4o',
				'model'            => 'gpt-3.5-turbo',
				'bot_name'         => 'XEN A.I',
				'accent_color'     => '#4f46e5',
				'greeting_message' => "Hi there! \xf0\x9f\x91\x8b I\xe2\x80\x99m XEN, your AI assistant. How can I help you today?",
				'notify_message'   => "Hello! Need any help? \xf0\x9f\x92\xac",
				'notify_delay'     => 4000,			'bot_logo_url'     => '',				'system_prompt'    => '',
				'max_tokens'       => 500,
				'temperature'      => 0.7,
				'disable_chat'     => false,
				'turnstile_site_key'   => '',
				'turnstile_secret_key' => '',
				'fallback_message'     => "I'm a little busy at the moment! Please leave your name and email and I'll get back to you as soon as I can.",
			] );
		}

		update_option( 'xen_ai_db_version', XEN_AI_VERSION );
	}

	// ── Deactivation ──────────────────────────────────────────────────────────

	public static function deactivate() {
		// Flush any cached KB transients
		delete_transient( 'xen_ai_kb_all' );		wp_clear_scheduled_hook( 'xen_ai_cleanup_transients' );
	}

	/**
	 * Sweep expired rate-limit, lock, and cache transients so the wp_options
	 * table does not bloat on high-traffic sites. Never touches license or
	 * settings rows.
	 */
	public static function cleanup_transients() {
		global $wpdb;

		$wpdb->query(
			"DELETE a, b FROM {$wpdb->options} a
			 LEFT JOIN {$wpdb->options} b
			   ON b.option_name = CONCAT( '_transient_timeout_', SUBSTRING( a.option_name, 12 ) )
			 WHERE ( a.option_name LIKE '_transient_xen_rl_%'
			     OR a.option_name LIKE '_transient_xen_si_%'
			     OR a.option_name LIKE '_transient_xen_lock_%'
			     OR a.option_name LIKE '_transient_xen_kb_ctx_%'
			     OR a.option_name LIKE '_transient_xen_sc_%' )
			   AND b.option_value < UNIX_TIMESTAMP()"
		);	}
}
