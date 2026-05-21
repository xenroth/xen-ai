<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
$settings = get_option( 'xen_ai_settings', [] );

$openai_models = [
	'gpt-4o'        => 'GPT-4o (Recommended)',
	'gpt-4o-mini'   => 'GPT-4o Mini (Fast & cheap)',
	'gpt-4-turbo'   => 'GPT-4 Turbo',
	'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Default)',
];

$github_models = Xen_AI_Handler::GITHUB_MODELS;

function xen_v( $key, $default = '' ) {
	$s = get_option( 'xen_ai_settings', [] );
	return isset( $s[ $key ] ) ? $s[ $key ] : $default;
}

$current_provider = xen_v( 'provider', 'openai' );
?>
<div class="wrap xen-ai-wrap">

	<div class="xen-ai-page-header">
		<div class="xen-ai-page-title">
			<span class="xen-ai-logo-icon">⚙</span>
			<h1>Settings</h1>
		</div>
		<p class="xen-ai-subtitle">Configure the AI engine, chat widget appearance, and lead capture behaviour.</p>
	</div>

	<div id="xen-settings-notice" class="xen-ai-notice" style="display:none;"></div>

	<form id="xen-settings-form">
		<input type="hidden" name="action" value="xen_ai_save_settings">
		<input type="hidden" name="nonce"  id="xen-settings-nonce" value="<?php echo esc_attr( wp_create_nonce( 'xen_ai_admin' ) ); ?>">

		<!-- ── AI Provider ── -->
		<div class="xen-ai-card">
			<h2 class="xen-ai-card-title">🤖 AI Provider</h2>

			<div class="xen-ai-field-row">
				<label class="xen-ai-label">Provider</label>
				<div class="xen-ai-field-body">
					<div class="xen-ai-provider-tabs">
						<label class="xen-provider-tab <?php echo 'openai' === $current_provider ? 'active' : ''; ?>">
							<input type="radio" name="provider" value="openai" <?php checked( $current_provider, 'openai' ); ?>>
							<span>🔑 OpenAI</span>
							<small>Pay-per-use</small>
						</label>
						<label class="xen-provider-tab <?php echo 'github' === $current_provider ? 'active' : ''; ?>">
							<input type="radio" name="provider" value="github" <?php checked( $current_provider, 'github' ); ?>>
							<span>🐙 GitHub Models</span>
							<small>Free via GitHub PAT</small>
						</label>
					</div>
				</div>
			</div>

			<!-- ── OpenAI fields ── -->
			<div id="xen-openai-fields" <?php echo 'github' === $current_provider ? 'style="display:none"' : ''; ?>>

				<div class="xen-ai-field-row">
					<label class="xen-ai-label" for="xen-api-key">API Key <span class="required">*</span></label>
					<div class="xen-ai-field-body">
						<input type="password" id="xen-api-key" name="api_key"
						       class="xen-ai-input-field"
						       value="<?php echo esc_attr( ! empty( $settings['api_key'] ) ? '••••••••' : '' ); ?>"
						       placeholder="sk-…"
						       autocomplete="off">
						<p class="xen-ai-help">
							Get your API key at
							<a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer">platform.openai.com/api-keys</a>.
						</p>
					</div>
				</div>

				<div class="xen-ai-field-row">
					<label class="xen-ai-label" for="xen-model">Model</label>
					<div class="xen-ai-field-body">
						<select id="xen-model" name="model" class="xen-ai-select">
							<?php foreach ( $openai_models as $val => $label ) : ?>
								<option value="<?php echo esc_attr( $val ); ?>" <?php selected( xen_v( 'model', 'gpt-3.5-turbo' ), $val ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

			</div>

			<!-- ── GitHub Models fields ── -->
			<div id="xen-github-fields" <?php echo 'openai' === $current_provider ? 'style="display:none"' : ''; ?>>

				<div class="xen-ai-field-row">
					<label class="xen-ai-label" for="xen-github-token">GitHub Token <span class="required">*</span></label>
					<div class="xen-ai-field-body">
						<input type="password" id="xen-github-token" name="github_token"
						       class="xen-ai-input-field"
						       value="<?php echo esc_attr( ! empty( $settings['github_token'] ) ? '••••••••' : '' ); ?>"
						       placeholder="github_pat_…"
						       autocomplete="off">
						<p class="xen-ai-help">
							Generate a <strong>Classic Personal Access Token</strong> (no special scopes needed) at
							<a href="https://github.com/settings/tokens" target="_blank" rel="noopener noreferrer">github.com/settings/tokens</a>.
							GitHub Models is <strong>free</strong> during the current preview.
						</p>
					</div>
				</div>

				<div class="xen-ai-field-row">
					<label class="xen-ai-label" for="xen-github-model">Model</label>
					<div class="xen-ai-field-body">
						<select id="xen-github-model" name="github_model" class="xen-ai-select">
							<?php foreach ( $github_models as $val => $label ) : ?>
								<option value="<?php echo esc_attr( $val ); ?>" <?php selected( xen_v( 'github_model', 'gpt-4o' ), $val ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="xen-ai-help">
							Endpoint: <code>models.inference.ai.azure.com</code>
						</p>
					</div>
				</div>

			</div>

			<!-- ── Shared inference settings ── -->
			<div class="xen-ai-field-row">
				<label class="xen-ai-label" for="xen-max-tokens">Max Tokens</label>
				<div class="xen-ai-field-body">
					<input type="number" id="xen-max-tokens" name="max_tokens"
					       class="xen-ai-input-field xen-ai-input-sm"
					       value="<?php echo esc_attr( xen_v( 'max_tokens', 500 ) ); ?>"
					       min="50" max="4096" step="50">
					<p class="xen-ai-help">Controls the length of each AI reply (50–4096).</p>
				</div>
			</div>

			<div class="xen-ai-field-row">
				<label class="xen-ai-label" for="xen-temperature">Temperature</label>
				<div class="xen-ai-field-body">
					<input type="range" id="xen-temperature" name="temperature"
					       class="xen-ai-range"
					       value="<?php echo esc_attr( xen_v( 'temperature', 0.7 ) ); ?>"
					       min="0" max="2" step="0.1"
					       oninput="document.getElementById('xen-temp-val').textContent = this.value">
					<span id="xen-temp-val"><?php echo esc_html( xen_v( 'temperature', 0.7 ) ); ?></span>
					<p class="xen-ai-help">Higher = more creative; lower = more focused (0–2).</p>
				</div>
			</div>

			<div class="xen-ai-field-row">
				<label class="xen-ai-label" for="xen-system-prompt">Custom System Instructions</label>
				<div class="xen-ai-field-body">
					<textarea id="xen-system-prompt" name="system_prompt"
					          class="xen-ai-textarea" rows="5"
					          placeholder="Optional extra instructions appended to the system prompt…"><?php echo esc_textarea( xen_v( 'system_prompt' ) ); ?></textarea>
					<p class="xen-ai-help">Add any specific instructions for the AI (tone, restrictions, etc.).</p>
				</div>
			</div>
		</div>
		</div>

		<!-- ── Chat Widget ── -->
		<div class="xen-ai-card xen-ai-mt">
			<h2 class="xen-ai-card-title">💬 Chat Widget</h2>

			<!-- Bot logo -->
			<div class="xen-ai-field-row">
				<label class="xen-ai-label">Bot Logo / Avatar</label>
				<div class="xen-ai-field-body">
					<input type="hidden" id="xen-bot-logo-url" name="bot_logo_url"
					       value="<?php echo esc_attr( xen_v( 'bot_logo_url' ) ); ?>">
					<div class="xen-ai-logo-preview-row">
						<div class="xen-ai-logo-preview" id="xen-logo-preview">
							<?php if ( xen_v( 'bot_logo_url' ) ) : ?>
								<img src="<?php echo esc_url( xen_v( 'bot_logo_url' ) ); ?>" alt="Bot logo">
							<?php else : ?>
								<span class="xen-logo-placeholder">&#x26A1;</span>
							<?php endif; ?>
						</div>
						<div style="display:flex;flex-direction:column;gap:6px;">						<input type="file" id="xen-logo-file-input" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">							<button type="button" id="xen-upload-logo-btn" class="xen-ai-btn xen-ai-btn-secondary xen-ai-btn-sm">
								&#x1F4C2; Upload Image
							</button>
							<button type="button" id="xen-remove-logo-btn" class="xen-ai-btn xen-ai-btn-outline xen-ai-btn-sm" <?php echo ! xen_v( 'bot_logo_url' ) ? 'style="display:none"' : ''; ?>>
								&#x2715; Remove
							</button>
						</div>
					</div>
					<p class="xen-ai-help">Replaces the default ⚡ icon in the chat header, message bubbles, and the toggle button. Recommended: square image, at least 80&times;80 px.</p>
				</div>
			</div>

			<div class="xen-ai-field-row">
				<label class="xen-ai-label" for="xen-disable-chat">Disable Widget</label>
				<div class="xen-ai-field-body">
					<label class="xen-toggle">
						<input type="checkbox" id="xen-disable-chat" name="disable_chat" value="1"
						       <?php checked( ! empty( $settings['disable_chat'] ) ); ?>>
						<span class="xen-toggle-slider"></span>
					</label>
					<span class="xen-ai-muted" style="margin-left:8px;">Hide the chat widget on the front end</span>
				</div>
			</div>

			<div class="xen-ai-field-row">
				<label class="xen-ai-label" for="xen-bot-name">Bot Name</label>
				<div class="xen-ai-field-body">
					<input type="text" id="xen-bot-name" name="bot_name"
					       class="xen-ai-input-field"
					       value="<?php echo esc_attr( xen_v( 'bot_name', 'XEN A.I' ) ); ?>">
				</div>
			</div>

			<div class="xen-ai-field-row">
				<label class="xen-ai-label" for="xen-accent-color">Accent Colour</label>
				<div class="xen-ai-field-body">
					<input type="color" id="xen-accent-color" name="accent_color"
					       value="<?php echo esc_attr( xen_v( 'accent_color', '#4f46e5' ) ); ?>"
					       style="height:38px;width:60px;cursor:pointer;">
				</div>
			</div>

			<div class="xen-ai-field-row">
				<label class="xen-ai-label" for="xen-greeting">Opening Greeting</label>
				<div class="xen-ai-field-body">
					<textarea id="xen-greeting" name="greeting_message"
					          class="xen-ai-textarea" rows="3"><?php echo esc_textarea( xen_v( 'greeting_message', "Hi there! 👋 I'm XEN, your AI assistant. How can I help you today?" ) ); ?></textarea>
					<p class="xen-ai-help">First message the bot sends when a visitor opens the chat.</p>
				</div>
			</div>

			<div class="xen-ai-field-row">
				<label class="xen-ai-label" for="xen-notify-msg">Notification Bubble Text</label>
				<div class="xen-ai-field-body">
					<input type="text" id="xen-notify-msg" name="notify_message"
					       class="xen-ai-input-field"
					       value="<?php echo esc_attr( xen_v( 'notify_message', 'Hello! Need any help? 💬' ) ); ?>">
					<p class="xen-ai-help">Small pop-up that appears near the chat button to encourage interaction.</p>
				</div>
			</div>

			<div class="xen-ai-field-row">
				<label class="xen-ai-label" for="xen-notify-delay">Notification Delay (ms)</label>
				<div class="xen-ai-field-body">
					<input type="number" id="xen-notify-delay" name="notify_delay"
					       class="xen-ai-input-field xen-ai-input-sm"
					       value="<?php echo esc_attr( xen_v( 'notify_delay', 4000 ) ); ?>"
					       min="0" max="30000" step="500">
					<p class="xen-ai-help">Milliseconds before the notification bubble appears (0 = immediately).</p>
				</div>
			</div>
		</div>

		<div class="xen-ai-mt">
			<button type="submit" class="xen-ai-btn xen-ai-btn-primary xen-ai-btn-lg">
				💾 Save Settings
			</button>
			<span id="xen-settings-spinner" class="xen-spinner" style="display:none;"></span>
		</div>

	</form>

	<!-- ── Data & Uninstall ── -->
	<div class="xen-ai-card xen-ai-mt xen-ai-danger-zone">
		<h2 class="xen-ai-card-title">🗑️ Data &amp; Uninstall</h2>
		<p style="color:var(--xen-muted);font-size:0.875rem;margin-bottom:20px;">
			These actions are <strong>irreversible</strong>. Use with caution.
		</p>

		<div class="xen-ai-field-row">
			<label class="xen-ai-label">On Plugin Delete</label>
			<div class="xen-ai-field-body">
				<label class="xen-ai-toggle-label">
					<input type="checkbox"
					       id="xen-clean-uninstall"
					       name="clean_uninstall"
					       value="1"
					       <?php checked( xen_v( 'clean_uninstall', false ) ); ?>>
					<span class="xen-ai-toggle-text">
						Delete all XEN A.I data when the plugin is removed
						<small class="xen-ai-help" style="display:block;margin-top:2px;">
							Wipes DB tables, settings, license record, and uploaded files when you delete the plugin via WordPress → Plugins. Leave unchecked to keep data for reinstalls.
						</small>
					</span>
				</label>
			</div>
		</div>

		<hr class="xen-ai-divider" style="margin:24px 0;">

		<div class="xen-ai-field-row">
			<label class="xen-ai-label">Manual Wipe</label>
			<div class="xen-ai-field-body">
				<p class="xen-ai-help" style="margin-bottom:12px;">
					Immediately deletes all conversations, leads, knowledge base entries, license data, and uploaded files.
					Plugin settings are preserved. Tables are recreated empty.
				</p>
				<button type="button" id="xen-wipe-data-btn" class="xen-ai-btn xen-ai-btn-danger">
					⚠️ Wipe All Data Now
				</button>
				<span id="xen-wipe-spinner" class="xen-spinner" style="display:none;margin-left:8px;"></span>
			</div>
		</div>
	</div>

	<div id="xen-wipe-notice" class="xen-ai-notice" style="display:none;margin-top:16px;"></div>

	<script>
	(function($){
		/* ── Save clean_uninstall preference with settings form ── */
		$('#xen-settings-form').on('submit', function(){
			// Ensure checkbox value is included even when unchecked
			if ( ! $('input[name="clean_uninstall"]').is(':checked') ) {
				$(this).append('<input type="hidden" name="clean_uninstall" value="0">');
			}
		});

		/* ── Manual wipe ── */
		$('#xen-wipe-data-btn').on('click', function(){
			if ( ! confirm('⚠️ This will permanently delete ALL conversations, knowledge base entries, leads, license data, and uploaded files.\n\nPlugin settings will be kept. This cannot be undone.\n\nAre you absolutely sure?') ) {
				return;
			}
			var $btn = $(this);
			$btn.prop('disabled', true);
			$('#xen-wipe-spinner').show();

			$.post(
				<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
				{
					action : 'xen_ai_wipe_data',
					nonce  : <?php echo wp_json_encode( wp_create_nonce( 'xen_ai_admin' ) ); ?>
				}
			)
			.done(function(res){
				var type = res.success ? 'ok' : 'error';
				var msg  = res.success ? (res.data.message || 'Data wiped.') : ((res.data && res.data.message) || 'Something went wrong.');
				$('#xen-wipe-notice')
					.removeClass('xen-ai-notice-ok xen-ai-notice-error')
					.addClass('xen-ai-notice-' + type)
					.html(msg).slideDown(200);
			})
			.fail(function(){
				$('#xen-wipe-notice').removeClass('xen-ai-notice-ok').addClass('xen-ai-notice-error').html('Request failed.').slideDown(200);
			})
			.always(function(){
				$btn.prop('disabled', false);
				$('#xen-wipe-spinner').hide();
			});
		});
	}(jQuery));
	</script>

</div>
