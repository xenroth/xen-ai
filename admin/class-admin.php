<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers admin menus, enqueues admin assets, and handles all admin AJAX actions.
 */
class Xen_AI_Admin {

	public function __construct() {
		add_action( 'admin_menu',                         [ $this, 'register_menus' ] );
		add_action( 'admin_enqueue_scripts',              [ $this, 'enqueue_assets' ] );

		// AJAX
		add_action( 'wp_ajax_xen_ai_save_settings',  [ $this, 'ajax_save_settings' ] );
		add_action( 'wp_ajax_xen_ai_upload_kb',      [ $this, 'ajax_upload_kb' ] );
		add_action( 'wp_ajax_xen_ai_add_url_kb',     [ $this, 'ajax_add_url_kb' ] );
		add_action( 'wp_ajax_xen_ai_delete_kb',      [ $this, 'ajax_delete_kb' ] );
		add_action( 'wp_ajax_xen_ai_export_leads',   [ $this, 'ajax_export_leads' ] );
		add_action( 'wp_ajax_xen_ai_delete_convo',    [ $this, 'ajax_delete_convo' ] );
		add_action( 'wp_ajax_xen_ai_get_messages',    [ $this, 'ajax_get_messages' ] );
		add_action( 'wp_ajax_xen_ai_upload_logo',     [ $this, 'ajax_upload_logo' ] );
		add_action( 'wp_ajax_xen_ai_activate_license',   [ $this, 'ajax_activate_license' ] );
		add_action( 'wp_ajax_xen_ai_deactivate_license', [ $this, 'ajax_deactivate_license' ] );
	}

	// ── Menus ─────────────────────────────────────────────────────────────────

	public function register_menus() {
		add_menu_page(
			__( 'XEN A.I', 'xen-ai' ),
			__( 'XEN A.I', 'xen-ai' ),
			'manage_options',
			'xen-ai',
			[ $this, 'page_dashboard' ],
			'dashicons-format-chat',
			30
		);

		add_submenu_page( 'xen-ai', __( 'Dashboard', 'xen-ai' ),           __( 'Dashboard', 'xen-ai' ),           'manage_options', 'xen-ai',          [ $this, 'page_dashboard' ] );
		add_submenu_page( 'xen-ai', __( 'Knowledge Base', 'xen-ai' ),      __( 'Knowledge Base', 'xen-ai' ),      'manage_options', 'xen-ai-kb',       [ $this, 'page_kb' ] );
		add_submenu_page( 'xen-ai', __( 'Leads & Conversations', 'xen-ai' ), __( 'Leads & Conversations', 'xen-ai' ), 'manage_options', 'xen-ai-leads', [ $this, 'page_leads' ] );
		add_submenu_page( 'xen-ai', __( 'Settings', 'xen-ai' ),            __( 'Settings', 'xen-ai' ),            'manage_options', 'xen-ai-settings', [ $this, 'page_settings' ] );
		add_submenu_page( 'xen-ai', __( 'Pro License', 'xen-ai' ),         __( '🔑 Pro License', 'xen-ai' ),      'manage_options', 'xen-ai-license',   [ $this, 'page_license' ] );
	}

	// ── Assets ────────────────────────────────────────────────────────────────

	public function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'xen-ai' ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style(
			'xen-ai-admin',
			XEN_AI_PLUGIN_URL . 'assets/css/admin.css',
			[],
			XEN_AI_VERSION
		);
		wp_enqueue_script(
			'xen-ai-admin',
			XEN_AI_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			XEN_AI_VERSION,
			true
		);
		wp_localize_script( 'xen-ai-admin', 'xenAIAdmin', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'xen_ai_admin' ),
			'i18n'    => [
				'confirmDelete' => __( 'Delete this entry? This cannot be undone.', 'xen-ai' ),
				'confirmConvo'  => __( 'Delete this conversation? This cannot be undone.', 'xen-ai' ),
				'processing'    => __( 'Processing…', 'xen-ai' ),
				'saved'         => __( 'Settings saved!', 'xen-ai' ),
				'error'         => __( 'An error occurred. Please try again.', 'xen-ai' ),
			],
		] );
	}

	// ── Page renderers ────────────────────────────────────────────────────────

	public function page_dashboard() {
		$this->check_cap();
		include XEN_AI_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	public function page_kb() {
		$this->check_cap();
		include XEN_AI_PLUGIN_DIR . 'admin/views/knowledge-base.php';
	}

	public function page_leads() {
		$this->check_cap();
		include XEN_AI_PLUGIN_DIR . 'admin/views/leads.php';
	}

	public function page_settings() {
		$this->check_cap();
		include XEN_AI_PLUGIN_DIR . 'admin/views/settings.php';
	}

	public function page_license() {
		$this->check_cap();
		include XEN_AI_PLUGIN_DIR . 'admin/views/license.php';
	}

	// ── AJAX handlers ─────────────────────────────────────────────────────────

	public function ajax_save_settings() {
		$this->verify_admin_nonce();

		$raw = get_option( 'xen_ai_settings', [] );

		$settings = [
			'provider'         => isset( $_POST['provider'] )        ? sanitize_text_field( wp_unslash( $_POST['provider'] ) )              : 'openai',
			'api_key'          => isset( $_POST['api_key'] )          ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) )              : '',
			'github_token'     => isset( $_POST['github_token'] )     ? sanitize_text_field( wp_unslash( $_POST['github_token'] ) )         : '',
			'github_model'     => isset( $_POST['github_model'] )     ? sanitize_text_field( wp_unslash( $_POST['github_model'] ) )         : 'gpt-4o',
			'model'            => isset( $_POST['model'] )            ? sanitize_text_field( wp_unslash( $_POST['model'] ) )                : 'gpt-3.5-turbo',
			'bot_name'         => isset( $_POST['bot_name'] )         ? sanitize_text_field( wp_unslash( $_POST['bot_name'] ) )             : 'XEN A.I',
			'accent_color'     => isset( $_POST['accent_color'] )     ? sanitize_hex_color( wp_unslash( $_POST['accent_color'] ) )         : '#4f46e5',
			'greeting_message' => isset( $_POST['greeting_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['greeting_message'] ) ) : '',
			'notify_message'   => isset( $_POST['notify_message'] )   ? sanitize_text_field( wp_unslash( $_POST['notify_message'] ) )       : '',
			'notify_delay'     => isset( $_POST['notify_delay'] )     ? absint( $_POST['notify_delay'] )                                   : 4000,
			'system_prompt'    => isset( $_POST['system_prompt'] )    ? sanitize_textarea_field( wp_unslash( $_POST['system_prompt'] ) )    : '',
			'max_tokens'       => isset( $_POST['max_tokens'] )       ? min( 4096, max( 50, absint( $_POST['max_tokens'] ) ) )             : 500,
			'temperature'      => isset( $_POST['temperature'] )      ? min( 2.0, max( 0.0, (float) $_POST['temperature'] ) )              : 0.7,
			'bot_logo_url'     => isset( $_POST['bot_logo_url'] ) ? esc_url_raw( wp_unslash( $_POST['bot_logo_url'] ) ) : '',
			'disable_chat'     => ! empty( $_POST['disable_chat'] ),
		];

		// Validate provider value
		if ( ! in_array( $settings['provider'], [ 'openai', 'github' ], true ) ) {
			$settings['provider'] = 'openai';
		}

		// Preserve masked placeholders — don't overwrite saved secrets with the display mask
		if ( '••••••••' === $settings['api_key'] && ! empty( $raw['api_key'] ) ) {
			$settings['api_key'] = $raw['api_key'];
		}
		if ( '••••••••' === $settings['github_token'] && ! empty( $raw['github_token'] ) ) {
			$settings['github_token'] = $raw['github_token'];
		}

		update_option( 'xen_ai_settings', $settings );

		wp_send_json_success( [ 'message' => __( 'Settings saved successfully!', 'xen-ai' ) ] );
	}

	public function ajax_upload_kb() {
		$this->verify_admin_nonce();

		if ( ! isset( $_FILES['kb_file'] ) ) {
			wp_send_json_error( [ 'message' => __( 'No file received.', 'xen-ai' ) ] );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$processor = new Xen_AI_File_Processor();
		$result    = $processor->handle_upload( $_FILES['kb_file'] );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		$kb = new Xen_AI_Knowledge_Base();
		$id = $kb->add_entry(
			$result['title'],
			$result['content'],
			'file',
			null,
			$result['file_type']
		);

		if ( false === $id ) {
			wp_send_json_error( [ 'message' => __( 'File processed but could not be saved to the database.', 'xen-ai' ) ] );
		}

		wp_send_json_success( [
			'id'      => $id,
			'title'   => $result['title'],
			'message' => __( 'File added to knowledge base!', 'xen-ai' ),
		] );
	}

	public function ajax_add_url_kb() {
		$this->verify_admin_nonce();

		$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';

		if ( empty( $url ) ) {
			wp_send_json_error( [ 'message' => __( 'Please enter a URL.', 'xen-ai' ) ] );
		}

		$processor = new Xen_AI_File_Processor();
		$result    = $processor->fetch_url( $url );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		$kb = new Xen_AI_Knowledge_Base();
		$id = $kb->add_entry(
			$result['title'],
			$result['content'],
			'url',
			$result['url'],
			'url'
		);

		if ( false === $id ) {
			wp_send_json_error( [ 'message' => __( 'URL fetched but could not be saved to the database.', 'xen-ai' ) ] );
		}

		wp_send_json_success( [
			'id'      => $id,
			'title'   => $result['title'],
			'message' => __( 'URL content added to knowledge base!', 'xen-ai' ),
		] );
	}

	public function ajax_delete_kb() {
		$this->verify_admin_nonce();

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( ! $id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid entry ID.', 'xen-ai' ) ] );
		}

		$kb = new Xen_AI_Knowledge_Base();
		if ( false === $kb->delete_entry( $id ) ) {
			wp_send_json_error( [ 'message' => __( 'Could not delete the entry.', 'xen-ai' ) ] );
		}

		wp_send_json_success( [ 'message' => __( 'Entry deleted.', 'xen-ai' ) ] );
	}

	public function ajax_delete_convo() {
		$this->verify_admin_nonce();

		global $wpdb;
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( ! $id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid conversation ID.', 'xen-ai' ) ] );
		}

		$wpdb->delete( $wpdb->prefix . 'xen_ai_messages',      [ 'conversation_id' => $id ], [ '%d' ] );
		$wpdb->delete( $wpdb->prefix . 'xen_ai_conversations', [ 'id'              => $id ], [ '%d' ] );

		wp_send_json_success( [ 'message' => __( 'Conversation deleted.', 'xen-ai' ) ] );
	}

	public function ajax_get_messages() {
		$this->verify_admin_nonce();

		global $wpdb;
		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$msgs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT role, content, created_at
				 FROM {$wpdb->prefix}xen_ai_messages
				 WHERE conversation_id = %d
				 ORDER BY created_at ASC",
				$id
			)
		);

		if ( empty( $msgs ) ) {
			wp_send_json_success( [ 'html' => '<p class="xen-ai-muted">No messages in this conversation.</p>' ] );
		}

		$html = '';
		foreach ( $msgs as $m ) {
			$role  = esc_html( $m->role );
			$label = 'user' === $m->role ? 'Visitor' : 'XEN A.I';
			$time  = wp_date( 'g:i A', strtotime( $m->created_at ) );
			$html .= '<div class="xen-modal-msg ' . $role . '">'
			       . '<div class="xen-modal-msg-role">' . esc_html( $label ) . ' <small style="opacity:.6">' . esc_html( $time ) . '</small></div>'
			       . '<div class="xen-modal-msg-bubble">' . nl2br( esc_html( $m->content ) ) . '</div>'
			       . '</div>';
		}

		wp_send_json_success( [ 'html' => $html ] );
	}

	public function ajax_export_leads() {
		$this->verify_admin_nonce();

		global $wpdb;
		$table = $wpdb->prefix . 'xen_ai_conversations';
		$rows  = $wpdb->get_results(
			"SELECT user_name, user_email, page_url, created_at
			 FROM {$table}
			 WHERE user_email IS NOT NULL AND user_email != ''
			 ORDER BY created_at DESC"
		);

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="xen-ai-leads-' . gmdate( 'Y-m-d' ) . '.csv"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, [ 'Name', 'Email', 'Page URL', 'Date' ] );

		foreach ( $rows as $r ) {
			fputcsv( $out, [
				$r->user_name  ?? '',
				$r->user_email ?? '',
				$r->page_url   ?? '',
				$r->created_at,
			] );
		}

		fclose( $out );
		exit;
	}

	// ── Utility ───────────────────────────────────────────────────────────────

	// ── Utility ─────────────────────────────────────────────────────────────────────────

	public function ajax_upload_logo() {
		$this->verify_admin_nonce();

		if ( empty( $_FILES['logo_file'] ) || UPLOAD_ERR_OK !== (int) $_FILES['logo_file']['error'] ) {
			wp_send_json_error( [ 'message' => __( 'No file received or upload error.', 'xen-ai' ) ] );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		// Validate MIME type before processing
		$allowed = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ];
		$checked = wp_check_filetype_and_ext(
			$_FILES['logo_file']['tmp_name'],
			$_FILES['logo_file']['name']
		);
		if ( ! $checked['type'] || ! in_array( $checked['type'], $allowed, true ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid file type. Allowed: JPEG, PNG, GIF, WebP.', 'xen-ai' ) ] );
		}

		// Add to WordPress Media Library (post_parent = 0 = unattached)
		$attachment_id = media_handle_upload( 'logo_file', 0 );

		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( [ 'message' => $attachment_id->get_error_message() ] );
		}

		$url = wp_get_attachment_url( $attachment_id );
		wp_send_json_success( [ 'url' => $url ] );
	}

	private function check_cap() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'xen-ai' ) );
		}
	}

	private function verify_admin_nonce() {
		check_ajax_referer( 'xen_ai_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'xen-ai' ) ] );
		}
	}
}
