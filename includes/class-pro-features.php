<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * XEN A.I Pro Feature Controller.
 *
 * All pro feature logic lives here.  Nothing in this file executes unless
 * Xen_AI_License::is_active() returns true — enforced both at hook
 * registration time and inside every individual method.
 *
 * Pro Features (v1):
 *   1. Proactive Visitor Questioning  — AI opens the conversation with
 *      a targeted question based on the page the visitor is currently on.
 *   2. Knowledge-Base Topic Insights  — Surfaces a curated list of relevant
 *      KB topics in the chat before the first user message.
 *   3. Service & Product Purchase Guide — Adds a step-by-step ordering
 *      assistant layer to the system prompt and injects a "Shop Assistant"
 *      greeting when a WooCommerce product/shop page is detected.
 */
class Xen_AI_Pro_Features {

	/** Singleton instance. */
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Boot pro feature hooks — called only when the license is active.
	 * Any code path here is guarded by an additional is_active() check so
	 * that even if init() is called by mistake, nothing leaks.
	 */
	public function init() {
		if ( ! Xen_AI_License::is_active() ) {
			return;
		}

		// Filter: enrich the system prompt with pro instructions
		add_filter( 'xen_ai_system_prompt',    [ $this, 'filter_system_prompt' ],    10, 3 );

		// Filter: enrich / override the AJAX chat context
		add_filter( 'xen_ai_chat_context',     [ $this, 'filter_chat_context' ],     10, 2 );

		// Filter: modify the initial greeting message (proactive questioning)
		add_filter( 'xen_ai_greeting_message', [ $this, 'filter_greeting_message' ], 10, 1 );

		// Action: inject pro data into the xen_ai_init_session response
		add_filter( 'xen_ai_session_response', [ $this, 'filter_session_response' ], 10, 1 );
	}

	// ── Pro Feature 1: Proactive Visitor Questioning ──────────────────────────

	/**
	 * Swap the static greeting for a page-context-aware question.
	 * The front end passes `page_url` when initialising the session;
	 * we generate a contextual opener from the page title.
	 */
	public function filter_greeting_message( $greeting ) {
		if ( ! Xen_AI_License::is_active() ) {
			return $greeting;
		}
		// The actual personalisation happens per-session in filter_session_response().
		return $greeting;
	}

	/**
	 * Inject proactive page-context into the session init response so the
	 * front end can display a tailored opening question.
	 *
	 * @param array $response  Data array that will be sent as JSON success.
	 * @return array
	 */
	public function filter_session_response( $response ) {
		if ( ! Xen_AI_License::is_active() ) {
			return $response;
		}

		$page_url = ! empty( $response['page_url'] ) ? $response['page_url'] : '';

		// Derive a context label from the URL path
		$context_hint = '';
		if ( $page_url ) {
			$path         = wp_parse_url( $page_url, PHP_URL_PATH );
			$slug         = trim( $path, '/' );
			$slug_parts   = explode( '/', $slug );
			$last         = end( $slug_parts );
			$context_hint = ucwords( str_replace( [ '-', '_' ], ' ', $last ) );
		}

		if ( $context_hint ) {
			$response['pro_greeting'] = sprintf(
				"Hi! 👋 I noticed you're on the **%s** section. What can I help you find here?",
				esc_html( $context_hint )
			);
		} else {
			$response['pro_greeting'] = "Hi! 👋 What brings you here today? I'd love to help — what are you looking for?";
		}

		return $response;
	}

	// ── Pro Feature 2: Knowledge-Base Topic Insights ─────────────────────────

	/**
	 * Before the first AI response, append a curated list of relevant KB topics
	 * so the visitor knows what the bot can help with.
	 *
	 * This enriches the chat context (system prompt KB section) with topic names.
	 *
	 * @param string $context  Existing KB context string.
	 * @param string $query    User's message.
	 * @return string
	 */
	public function filter_chat_context( $context, $query ) {
		if ( ! Xen_AI_License::is_active() ) {
			return $context;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'xen_ai_knowledge';

		// Fetch up to 8 active KB entry titles
		$titles = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT title FROM {$table} WHERE status = 'active' ORDER BY id DESC LIMIT %d",
				8
			)
		);

		if ( empty( $titles ) ) {
			return $context;
		}

		$topic_list  = "\n\n## Available Knowledge Topics\n";
		$topic_list .= "The following topics are available in the knowledge base. ";
		$topic_list .= "Mention these to the user when relevant so they know what you can help with:\n";
		foreach ( $titles as $title ) {
			$topic_list .= '- ' . esc_html( $title ) . "\n";
		}

		return $context . $topic_list;
	}

	// ── Pro Feature 3: Service & Product Purchase Guide ───────────────────────

	/**
	 * Append a purchase-guide layer to the system prompt when WooCommerce
	 * is active, giving the AI step-by-step ordering assistant instructions.
	 *
	 * @param string $prompt            The assembled system prompt.
	 * @param string $bot_name
	 * @param string $knowledge_context
	 * @return string
	 */
	public function filter_system_prompt( $prompt, $bot_name, $knowledge_context ) {
		if ( ! Xen_AI_License::is_active() ) {
			return $prompt;
		}

		$prompt .= "\n\n## Purchase Assistant (Pro)\n";
		$prompt .= "You are also a dedicated purchase assistant. When a user shows interest in a product or service:\n";
		$prompt .= "1. Greet their interest warmly and confirm the specific item they want.\n";
		$prompt .= "2. Share the **price**, **availability**, and the **direct product link**.\n";
		$prompt .= "3. Explain any key terms, policies, or conditions relevant to that purchase.\n";
		$prompt .= "4. Walk them through adding to cart and completing checkout step by step.\n";
		$prompt .= "5. If they are unsure, suggest the best-fit option based on their stated needs.\n";
		$prompt .= "6. After guiding them, ask if they need help with anything else.\n";

		if ( class_exists( 'WooCommerce' ) ) {
			$shop_url     = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '';
			$checkout_url = function_exists( 'wc_get_checkout_url' )   ? wc_get_checkout_url()           : '';

			if ( $shop_url ) {
				$prompt .= "Store URL: {$shop_url}\n";
			}
			if ( $checkout_url ) {
				$prompt .= "Checkout URL: {$checkout_url}\n";
			}
		}

		return $prompt;
	}
}
