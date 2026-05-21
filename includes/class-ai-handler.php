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
	 * @return string|WP_Error          Raw AI response text (may contain [NAME]/[EMAIL] markers).
	 */
	public function get_response( array $messages, $knowledge_context = '' ) {
		if ( ! $this->is_configured() ) {
			if ( 'github' === $this->provider ) {
				return new WP_Error( 'no_token', __( 'GitHub token is not configured. Please add it under XEN A.I → Settings.', 'xen-ai' ) );
			}
			return new WP_Error( 'no_api_key', __( 'OpenAI API key is not configured. Please add it under XEN A.I → Settings.', 'xen-ai' ) );
		}

		$s        = get_option( 'xen_ai_settings', [] );
		$bot_name = ! empty( $s['bot_name'] )      ? $s['bot_name']      : 'XEN A.I';
		$custom   = ! empty( $s['system_prompt'] ) ? $s['system_prompt'] : '';

		$system = $this->build_system_prompt( $bot_name, $knowledge_context, $custom );

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
	 * Strip [NAME: …] / [EMAIL: …] markers so they are never shown to the user.
	 */
	public function clean_response( $text ) {
		$text = preg_replace( '/\s*\[NAME:\s*[^\]]+\]/i',  '', $text );
		$text = preg_replace( '/\s*\[EMAIL:\s*[^\]]+\]/i', '', $text );
		return trim( $text );
	}

	// ── Internals ─────────────────────────────────────────────────────────────

	private function build_system_prompt( $bot_name, $knowledge_context, $custom_instructions ) {
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

		$p .= "## Visitor Greeting & Contact Follow-up\n";
		$p .= "- Early in the conversation, warmly introduce yourself and ask what the visitor's name is so you can address them personally. Do this once.\n";
		$p .= "- If a visitor expresses strong interest in a product, service, or wants more information sent to them, you may politely ask for a contact email. Do this once and only if it feels natural.\n";
		$p .= "- When the visitor shares their name, include this token on its own line at the very end of your message: [NAME: <value>]\n";
		$p .= "- When the visitor shares an email address, include this token on its own line at the very end of your message: [EMAIL: <value>]\n";
		$p .= "- These tokens are used internally by the site system and will not be shown to the visitor.\n\n";

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
