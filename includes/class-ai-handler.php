<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wraps the OpenAI-compatible Chat Completions API.
 *
 * Supports two providers:
 *   - openai  : api.openai.com  — requires an OpenAI API key (sk-…)
 *   - github  : models.inference.ai.azure.com — requires a GitHub PAT
 *                 (free access via GitHub Models marketplace)
 *
 * Builds a layered system prompt:
 *   1. Role + website identity
 *   2. Injected knowledge-base context (when available)
 *   3. Lead-capture instructions
 *   4. Optional admin-supplied custom instructions
 *
 * Responses that contain user data are tagged with [NAME: …] / [EMAIL: …]
 * markers so the AJAX handler can extract and persist them.
 */
class Xen_AI_Handler {

	private $provider;
	private $api_key;       // OpenAI key  (provider = openai)
	private $github_token;  // GitHub PAT  (provider = github)
	private $model;
	private $max_tokens;
	private $temperature;

	const OPENAI_API_URL = 'https://api.openai.com/v1/chat/completions';
	const GITHUB_API_URL = 'https://models.inference.ai.azure.com/chat/completions';

	// GitHub Models available as of plugin release
	const GITHUB_MODELS = [
		'gpt-4o'                      => 'GPT-4o',
		'gpt-4o-mini'                 => 'GPT-4o Mini',
		'Llama-3.1-405B-Instruct'     => 'Llama 3.1 405B',
		'Phi-3.5-mini-instruct'       => 'Phi-3.5 Mini',
		'Mistral-large'               => 'Mistral Large',
	];

	public function __construct() {
		$s                   = get_option( 'xen_ai_settings', [] );
		$this->provider      = ! empty( $s['provider'] )      ? $s['provider']                  : 'openai';
		$this->api_key       = isset( $s['api_key'] )         ? $s['api_key']                   : '';
		$this->github_token  = isset( $s['github_token'] )    ? $s['github_token']              : '';
		$this->model         = isset( $s['model'] )           ? $s['model']                     : 'gpt-3.5-turbo';
		$this->max_tokens    = isset( $s['max_tokens'] )      ? absint( $s['max_tokens'] )      : 500;
		$this->temperature   = isset( $s['temperature'] )     ? (float) $s['temperature']       : 0.7;
	}

	// ── Public API ────────────────────────────────────────────────────────────

	public function is_configured() {
		// Auto-correct: GitHub PAT accidentally saved in the OpenAI api_key field.
		if ( 'openai' === $this->provider && $this->looks_like_github_pat( $this->api_key ) ) {
			$this->provider     = 'github';
			$this->github_token = $this->api_key;
			$this->api_key      = '';
		}
		if ( 'github' === $this->provider ) {
			return ! empty( $this->github_token );
		}
		return ! empty( $this->api_key );
	}

	/** Returns true if a string looks like a GitHub PAT (classic or fine-grained). */
	private function looks_like_github_pat( $value ) {
		if ( empty( $value ) ) return false;
		// Fine-grained: github_pat_   Classic: ghp_   OAuth: gho_   Actions: ghs_
		return (bool) preg_match( '/^(github_pat_|ghp_|gho_|ghs_)/i', $value );
	}

	public function get_provider() {
		return $this->provider;
	}

	/**
	 * @param array  $messages          OpenAI-format message history (role/content pairs).
	 * @param string $knowledge_context Pre-retrieved KB context string.
	 * @param array  $conv_state        Optional: keys 'has_name' (bool), 'has_email' (bool).
	 * @return string|WP_Error          Raw AI response text (may contain [NAME]/[EMAIL] markers).
	 */
	public function get_response( array $messages, $knowledge_context = '', $conv_state = [] ) {
		if ( ! $this->is_configured() ) {
			if ( 'github' === $this->provider ) {
				return new WP_Error( 'no_token', __( 'GitHub token is not configured. Please add it under XEN A.I → Settings.', 'xen-ai' ) );
			}
			return new WP_Error( 'no_api_key', __( 'OpenAI API key is not configured. Please add it under XEN A.I → Settings.', 'xen-ai' ) );
		}

		$s        = get_option( 'xen_ai_settings', [] );
		$bot_name = ! empty( $s['bot_name'] )      ? $s['bot_name']      : 'XEN A.I';
		$custom   = ! empty( $s['system_prompt'] ) ? $s['system_prompt'] : '';

		$system = $this->build_system_prompt( $bot_name, $knowledge_context, $custom, $conv_state );

		// Resolve endpoint + auth token
		if ( 'github' === $this->provider ) {
			$endpoint = self::GITHUB_API_URL;
			$token    = $this->github_token;
			$model    = ! empty( $s['github_model'] ) ? $s['github_model'] : 'gpt-4o';
		} else {
			$endpoint = self::OPENAI_API_URL;
			$token    = $this->api_key;
			$model    = $this->model;
		}

		// Enforce a context budget so a single oversized request can never blow
		// past the model's context window or rack up surprise costs.
		$messages = $this->trim_history_to_budget( $messages, $system );

		$payload = [
			'model'       => $model,
			'messages'    => array_merge( [ [ 'role' => 'system', 'content' => $system ] ], $messages ),
			'max_tokens'  => $this->max_tokens,
			'temperature' => $this->temperature,
		];

		$response = wp_remote_post( $endpoint, [
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			],
			'body'    => wp_json_encode( $payload ),
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			$msg = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Unknown API error.', 'xen-ai' );
			return new WP_Error( 'api_error', $msg );
		}

		if ( isset( $body['choices'][0]['message']['content'] ) ) {
			return $body['choices'][0]['message']['content'];
		}

		return new WP_Error( 'empty_response', __( 'The AI returned an empty response.', 'xen-ai' ) );
	}

	/**
	 * Parse [NAME: …] and [EMAIL: …] markers from a raw AI response.
	 *
	 * @param string $text
	 * @return array  Keys: 'name', 'email' (only present when found).
	 */
	public function extract_user_data( $text ) {
		$data = [];

		if ( preg_match( '/\[NAME:\s*([^\]]+)\]/i', $text, $m ) ) {
			$data['name'] = sanitize_text_field( trim( $m[1] ) );
		}

		if ( preg_match( '/\[EMAIL:\s*([^\]]+)\]/i', $text, $m ) ) {
			$email = sanitize_email( trim( $m[1] ) );
			if ( is_email( $email ) ) {
				$data['email'] = $email;
			}
		}

		return $data;
	}

	/**
	 * Drop oldest history turns until system + history fit a conservative
	 * input-token budget (chars/4 ≈ tokens). This protects against context
	 * window overflow AND runaway token costs on long conversations.
	 */
	private function trim_history_to_budget( array $messages, $system ) {
		$model_input_chars = 80000;                   // ~20k input tokens — safe for gpt-4o (128k)
		$output_reserve    = $this->max_tokens * 4;   // chars reserved for the reply
		$budget            = $model_input_chars - mb_strlen( $system ) - $output_reserve;

		if ( $budget < 2000 ) {
			$budget = 2000; // never drop below a usable floor
		}

		$total = 0;
		foreach ( $messages as $m ) {
			$total += mb_strlen( (string) $m['content'] );
		}

		// Always keep the latest message (the visitor's current question).
		while ( $total > $budget && count( $messages ) > 1 ) {
			$dropped = array_shift( $messages );
			$total  -= mb_strlen( (string) $dropped['content'] );
		}

		return $messages;
	}

	/**
	 * Strip [NAME: …] / [EMAIL: …] markers so they are never shown to the user.
	 */
	public function clean_response( $text ) {
		$text = preg_replace( '/\s*\[NAME:\s*[^\]]+\]/i',  '', $text );
		$text = preg_replace( '/\s*\[EMAIL:\s*[^\]]+\]/i', '', $text );
		return trim( $text );
	}

	// ── Internals ─────────────────────────────────────────────────────────────

	private function build_system_prompt( $bot_name, $knowledge_context, $custom_instructions, $conv_state = [] ) {
		$has_name  = ! empty( $conv_state['has_name'] );
		$has_email = ! empty( $conv_state['has_email'] );

		$site_name = get_bloginfo( 'name' );
		$site_desc = get_bloginfo( 'description' );

		$p  = "You are {$bot_name}, a friendly and knowledgeable AI assistant for the website \"{$site_name}\".";
		if ( $site_desc ) {
			$p .= " The site is described as: \"{$site_desc}\".";
		}

		$p .= "\n\n## Behaviour Rules\n";
		$p .= "1. **Knowledge-base first** — When answering questions about this website, its products, services, or team, always draw from the knowledge base provided below before using general knowledge.\n";
		$p .= "2. **General knowledge fallback** — For topics not covered in the knowledge base, answer using your general training data.\n";
		$p .= "3. Be warm, concise, and conversational. Keep replies to 2–4 sentences unless more detail is clearly needed.\n";
		$p .= "4. If you genuinely do not know something, say so honestly.\n\n";

		if ( class_exists( 'WooCommerce' ) ) {
			$p .= "## Products & Ordering\n";
			$p .= "When users ask about products, pricing, availability, or how to place an order:\n";
			$p .= "- Share specific product details (name, price, stock, and direct link) from the site content below.\n";
			$p .= "- Walk them step-by-step through the checkout process.\n";
			$p .= "- Always include the product URL when mentioning a product.\n";
			$p .= "- For out-of-stock items, suggest contacting the store or checking back later.\n\n";
		}

		$p .= "## Visitor Name & Contact\n";

		if ( ! $has_name ) {
			$p .= "- You do NOT yet know the visitor's name. Your FIRST priority in this conversation is to naturally ask for it. ";
			$p .= "On your very first reply, after a brief greeting, ask something like: \"By the way, what's your name so I can address you personally?\" — weave it in naturally, not as a standalone question. Ask only once.\n";
			$p .= "- When the visitor provides their name, immediately acknowledge it warmly and use it going forward.\n";
			$p .= "- When the visitor shares their name, include this token on its own line at the very end of your message: [NAME: <value>]\n";
		} else {
			$p .= "- You already know the visitor's name. Do NOT ask for it again. Address them by name naturally when appropriate.\n";
		}

		$reply_count = isset( $conv_state['reply_count'] ) ? (int) $conv_state['reply_count'] : 0;

		if ( ! $has_email ) {
			if ( $reply_count < 3 ) {
				// Too early — focus on helping; capture only if offered spontaneously.
				$p .= "- Do NOT ask for the visitor's email address yet. Focus entirely on being helpful.\n";
				$p .= "- However, if the visitor volunteers their email spontaneously, capture it with this token on its own line at the end of your reply: [EMAIL: <value>]\n";
			} elseif ( 3 === $reply_count ) {
				// Sweet spot — ask now, wittily, making them feel privileged.
				$p .= "- **This is the moment to ask for the visitor's email.** Weave the ask into your current reply in a warm, witty way that makes them feel genuinely special — like they're being let into an exclusive club, not just being data-harvested. ";
				$p .= "Example tone (adapt freely to the conversation): \"I don't do this for everyone, but you've honestly been such a pleasure to chat with today — would you mind sharing your email? I'll make sure you're the first to know about any exclusive offers or updates. Zero spam, I promise! 😊\" ";
				$p .= "The ask must be present in this reply. Keep it short, charming, and non-pushy.\n";
				$p .= "- When the visitor provides their email address, include this token on its own line at the very end of your reply: [EMAIL: <value>]\n";
			} else {
				// Already asked — don't push; only capture if offered.
				$p .= "- Do not bring up email again. If the visitor offers their email address on their own, include this token on its own line at the end of your reply: [EMAIL: <value>]\n";
			}
		} else {
			$p .= "- You already have the visitor's email address. Do NOT ask for it again.\n";
		}

		$p .= "- These [NAME] and [EMAIL] tokens are used internally and will never be visible to the visitor.\n\n";

		if ( ! empty( $knowledge_context ) ) {
			$p .= "## Website Knowledge Base\n";
			$p .= "Use the information below to answer questions about this website:\n\n";
			$p .= $knowledge_context;
			$p .= "\n## End of Knowledge Base\n\n";
		}

		if ( ! empty( $custom_instructions ) ) {
			$p .= "## Additional Instructions\n" . $custom_instructions . "\n";
		}

		// Allow Pro features to append to the system prompt
		$p = apply_filters( 'xen_ai_system_prompt', $p, $bot_name, $knowledge_context );

		return $p;
	}
}
