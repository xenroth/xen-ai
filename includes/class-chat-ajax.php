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

	const RATE_LIMIT = 20; // messages per session per hour

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

		global $wpdb;

		$session_id = wp_generate_uuid4();
		$page_url   = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '';

		$wpdb->insert(
			$this->conv_table,
			[
				'session_id' => $session_id,
				'page_url'   => $page_url,
			],
			[ '%s', '%s' ]
		);

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

		if ( empty( $message ) ) {
			wp_send_json_error( [ 'message' => __( 'Please type a message.', 'xen-ai' ) ] );
		}

		if ( empty( $session_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Session ID missing. Please refresh and try again.', 'xen-ai' ) ] );
		}

		// ── Rate limiting ──────────────────────────────────────────────────────
		if ( $this->is_rate_limited( $session_id ) ) {
			wp_send_json_error( [
				'message' => __( "You've sent too many messages. Please wait a moment before sending more.", 'xen-ai' ),
			] );
		}

		// ── Validate session ───────────────────────────────────────────────────
		$conv = $this->get_conversation( $session_id );
		if ( ! $conv ) {
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
		$ai       = new Xen_AI_Handler();
		$raw      = $ai->get_response( $messages, $context );

		if ( is_wp_error( $raw ) ) {
			wp_send_json_error( [ 'message' => $raw->get_error_message() ] );
		}

		$user_data = $ai->extract_user_data( $raw );
		$reply     = $ai->clean_response( $raw );

		// ── Persist ────────────────────────────────────────────────────────────
		$this->save_message( $conv->id, 'user',      $message );
		$this->save_message( $conv->id, 'assistant', $reply );

		if ( ! empty( $user_data ) ) {
			$this->update_conversation( $conv->id, $user_data );
		}

		wp_send_json_success( [ 'reply' => $reply ] );
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
		$key   = 'xen_ai_rl_' . md5( $session_id );
		$count = get_transient( $key );

		if ( false === $count ) {
			set_transient( $key, 1, HOUR_IN_SECONDS );
			return false;
		}

		if ( $count >= self::RATE_LIMIT ) {
			return true;
		}

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return false;
	}
}
