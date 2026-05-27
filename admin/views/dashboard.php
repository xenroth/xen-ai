<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
global $wpdb;
$settings      = get_option( 'xen_ai_settings', [] );
$kb_count      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}xen_ai_knowledge WHERE status = 'active'" );
$total_convos  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}xen_ai_conversations" );
$total_msgs    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}xen_ai_messages WHERE role = 'user'" );
$leads_count   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}xen_ai_conversations WHERE user_email IS NOT NULL AND user_email != ''" );
$ai            = new Xen_AI_Handler();
$configured    = $ai->is_configured();
?>
<div class="wrap xen-ai-wrap">

	<div class="xen-ai-page-header">
		<div class="xen-ai-page-title">
			<span class="xen-ai-logo-icon">⚡</span>
			<h1>XEN A.I — Dashboard</h1>
		</div>
		<p class="xen-ai-subtitle">AI-powered chat assistant &amp; lead capture for your WordPress site.</p>
	</div>

	<?php
	$provider_label = ( 'github' === ( $settings['provider'] ?? 'openai' ) )
		? 'GitHub Personal Access Token'
		: 'OpenAI API key';
	?>
	<?php if ( ! $configured ) : ?>
	<div class="xen-ai-notice xen-ai-notice-warn">
		<strong>⚠ Setup required:</strong>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=xen-ai-settings' ) ); ?>">
			Add your <?php echo esc_html( $provider_label ); ?>
		</a>
		to enable the AI chat assistant.
	</div>
	<?php endif; ?>

	<!-- Free Pro License Promo -->
	<?php if ( ! Xen_AI_License::is_active() ) : ?>
	<div class="xen-ai-promo-banner">
		<div class="xen-ai-promo-content">
			<div class="xen-ai-promo-text">
				<div class="xen-ai-promo-badge-label">🎉 LIMITED OFFER</div>
				<h2 class="xen-ai-promo-title">First 10 Users Get a FREE Pro License!</h2>
				<p class="xen-ai-promo-desc">Join our exclusive LINE community to claim your free XEN A.I Pro license key and get early access to updates, tips, and direct developer support.</p>
				<a href="https://line.me/R/ti/g/DBGUQQdSg2" target="_blank" rel="noopener noreferrer" class="xen-ai-btn xen-ai-btn-line">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="currentColor" style="vertical-align:middle;margin-right:6px;"><path d="M12 .5C5.649.5.5 4.534.5 9.5c0 4.41 3.914 8.112 9.21 8.878.358.077.846.236.97.542.11.278.072.713.035 1.003l-.157.947c-.048.278-.222 1.086.952.592 1.174-.494 6.334-3.729 8.641-6.385C21.604 13.14 23.5 11.437 23.5 9.5 23.5 4.534 18.351.5 12 .5z"/></svg>
					Join LINE Group
				</a>
				<p class="xen-ai-promo-note">Or scan the QR code with your LINE app →</p>
			</div>
			<div class="xen-ai-promo-qr">
				<img
					src="<?php echo esc_url( 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&margin=6&data=' . rawurlencode( 'https://line.me/R/ti/g/DBGUQQdSg2' ) ); ?>"
					alt="Scan to join LINE group"
					width="160"
					height="160"
				/>
				<span class="xen-ai-promo-qr-label">Scan with LINE</span>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<!-- Stats -->
	<div class="xen-ai-stats-grid">

		<div class="xen-ai-stat-card">
			<div class="xen-ai-stat-icon">📚</div>
			<div class="xen-ai-stat-number"><?php echo esc_html( $kb_count ); ?></div>
			<div class="xen-ai-stat-label">Knowledge Base Entries</div>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=xen-ai-kb' ) ); ?>" class="xen-ai-stat-link">Manage →</a>
		</div>

		<div class="xen-ai-stat-card">
			<div class="xen-ai-stat-icon">💬</div>
			<div class="xen-ai-stat-number"><?php echo esc_html( $total_convos ); ?></div>
			<div class="xen-ai-stat-label">Total Conversations</div>
		</div>

		<div class="xen-ai-stat-card">
			<div class="xen-ai-stat-icon">✉️</div>
			<div class="xen-ai-stat-number"><?php echo esc_html( $total_msgs ); ?></div>
			<div class="xen-ai-stat-label">Messages Handled</div>
		</div>

		<div class="xen-ai-stat-card xen-ai-stat-highlight">
			<div class="xen-ai-stat-icon">👤</div>
			<div class="xen-ai-stat-number"><?php echo esc_html( $leads_count ); ?></div>
			<div class="xen-ai-stat-label">Leads Captured</div>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=xen-ai-leads' ) ); ?>" class="xen-ai-stat-link">View Leads →</a>
		</div>

	</div>

	<!-- Quick actions -->
	<div class="xen-ai-card xen-ai-mt">
		<h2 class="xen-ai-card-title">Quick Actions</h2>
		<div class="xen-ai-action-row">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=xen-ai-kb' ) ); ?>"       class="xen-ai-btn xen-ai-btn-primary">📂 Add to Knowledge Base</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=xen-ai-settings' ) ); ?>" class="xen-ai-btn xen-ai-btn-secondary">⚙ Settings</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=xen-ai-leads' ) ); ?>"    class="xen-ai-btn xen-ai-btn-secondary">👥 View All Leads</a>
		</div>
	</div>

	<!-- Free Features -->
	<div class="xen-ai-card xen-ai-mt">
		<h2 class="xen-ai-card-title">✅ Included in Free</h2>
		<div class="xen-ai-free-features-grid">

			<div class="xen-ai-free-feature">
				<span>💬</span>
				<div><strong>AI Chat Widget</strong>
				<p>Floating chat bubble auto-injected on all pages. Animated notification bubble encourages interaction.</p></div>
			</div>

			<div class="xen-ai-free-feature">
				<span>📚</span>
				<div><strong>Knowledge Base</strong>
				<p>Upload PDFs, DOCX, DOC, TXT files or scrape content from any URL. AI answers from your content first.</p></div>
			</div>

			<div class="xen-ai-free-feature">
				<span>🤖</span>
				<div><strong>Dual AI Provider</strong>
				<p>Switch between OpenAI (paid) and GitHub Models (free with a GitHub account). Both use the same settings.</p></div>
			</div>

			<div class="xen-ai-free-feature">
				<span>🛒</span>
				<div><strong>Live Site Content Awareness</strong>
				<p>AI automatically reads your published pages, blog posts, and WooCommerce products — including price, stock, and ordering instructions.</p></div>
			</div>

			<div class="xen-ai-free-feature">
				<span>👤</span>
				<div><strong>Lead Capture</strong>
				<p>AI naturally collects visitor name &amp; email through conversation and saves them to the Leads dashboard.</p></div>
			</div>

			<div class="xen-ai-free-feature">
				<span>🎨</span>
				<div><strong>Custom Branding</strong>
				<p>Set the bot name, accent colour, and upload your own logo/avatar image for the chat widget.</p></div>
			</div>

			<div class="xen-ai-free-feature">
				<span>📊</span>
				<div><strong>Leads &amp; Conversations</strong>
				<p>Full conversation history, lead viewer with modal, CSV export, and per-conversation delete.</p></div>
			</div>

			<div class="xen-ai-free-feature">
				<span>🔒</span>
				<div><strong>Rate Limiting &amp; Security</strong>
				<p>Session-based rate limiting (20 msg/hr), nonce verification on every request, SSRF-safe URL scraper.</p></div>
			</div>

		</div>
	</div>

	<!-- Pro Features upsell -->
	<div class="xen-ai-card xen-ai-mt xen-ai-pro-card">
		<div class="xen-ai-pro-header">
			<div>
				<h2 class="xen-ai-card-title" style="margin:0;">✨ XEN A.I Pro</h2>
				<p style="margin:6px 0 0;color:var(--xen-text-muted);font-size:0.875rem;">Supercharge your chat assistant with advanced engagement tools.</p>
			</div>
			<span class="xen-ai-pro-badge">Coming Soon</span>
		</div>

		<div class="xen-ai-pro-features-grid">

			<div class="xen-ai-pro-feature">
				<span class="xen-ai-pro-feature-icon">🎯</span>
				<div>
					<strong>Proactive Visitor Questioning</strong>
					<p>The AI automatically initiates targeted questions to understand each visitor's needs before they even type — driving deeper engagement from the first second.</p>
				</div>
			</div>

			<div class="xen-ai-pro-feature">
				<span class="xen-ai-pro-feature-icon">📋</span>
				<div>
					<strong>Knowledge-Base Topic Insights</strong>
					<p>Surfaces a real-time list of knowledge-base topics most relevant to what the visitor is browsing, so they always find the answers they need instantly.</p>
				</div>
			</div>

			<div class="xen-ai-pro-feature">
				<span class="xen-ai-pro-feature-icon">🛒</span>
				<div>
					<strong>Service &amp; Product Purchase Guide</strong>
					<p>Step-by-step conversational guidance that walks prospects through your offerings and seamlessly directs them to checkout or a sales contact.</p>
				</div>
			</div>

		</div>

		<div class="xen-ai-pro-cta">
			<span class="xen-ai-pro-price">₱999 <small>one-time</small></span>
			<?php if ( Xen_AI_License::is_active() ) : ?>
				<span class="xen-ai-pro-badge" style="background:linear-gradient(135deg,#22c55e,#16a34a);font-size:0.85rem;padding:6px 14px;">✅ Pro Active</span>
			<?php else : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=xen-ai-license' ) ); ?>" class="xen-ai-btn xen-ai-btn-pro" style="opacity:1;cursor:pointer;">🔑 Activate Pro License</a>
			<?php endif; ?>
		</div>

		<div class="xen-ai-pro-contact">
			<span>📩 Interested? Reach out to the developer:</span>
			<a href="mailto:me@xenroth.com">me@xenroth.com</a>
			<span class="xen-ai-pro-contact-sep">·</span>
			<a href="tel:+639150388448">+63 915 038 8448</a>
		</div>
	</div>

	<!-- Status card -->
	<div class="xen-ai-card xen-ai-mt">
		<h2 class="xen-ai-card-title">System Status</h2>
		<?php
		$provider_name = 'github' === ( $settings['provider'] ?? 'openai' ) ? 'GitHub Models' : 'OpenAI';
		$active_model  = 'github' === ( $settings['provider'] ?? 'openai' )
			? ( $settings['github_model'] ?? 'gpt-4o' )
			: ( $settings['model']        ?? 'gpt-3.5-turbo' );
		?>
		<table class="xen-ai-status-table">
			<tr>
				<td><span class="xen-ai-dot <?php echo $configured ? 'green' : 'red'; ?>"></span> <?php echo esc_html( $provider_name ); ?></td>
				<td><strong><?php echo $configured ? 'Connected' : 'Not configured'; ?></strong></td>
			</tr>
			<tr>
				<td><span class="xen-ai-dot green"></span> AI Model</td>
				<td><strong><?php echo esc_html( $active_model ); ?></strong></td>
			</tr>
			<tr>
				<td><span class="xen-ai-dot <?php echo empty( $settings['disable_chat'] ) ? 'green' : 'red'; ?>"></span> Chat Widget</td>
				<td><strong><?php echo empty( $settings['disable_chat'] ) ? 'Enabled' : 'Disabled'; ?></strong></td>
			</tr>
			<tr>
				<td><span class="xen-ai-dot green"></span> Plugin Version</td>
				<td>
					<strong><?php echo esc_html( XEN_AI_VERSION ); ?></strong>
					&nbsp;
					<button type="button" id="xen-force-update-check" class="button button-small" style="vertical-align:middle;">
						🔄 Check for Update
					</button>
					<span id="xen-update-check-result" style="margin-left:8px;font-size:0.85rem;"></span>
				</td>
			</tr>
		</table>
	</div>

</div>

<script>
(function($){
	$('#xen-force-update-check').on('click', function(){
		var $btn = $(this);
		var $res = $('#xen-update-check-result');
		$btn.prop('disabled', true).text('Checking…');
		$res.text('');
		$.post(
			<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
			{ action: 'xen_ai_force_update_check', nonce: <?php echo wp_json_encode( wp_create_nonce( 'xen_ai_admin' ) ); ?> }
		)
		.done(function(res){
			if (res.success) {
				var d = res.data;
				if (d.update_available) {
					$res.html('<span style="color:#16a34a;font-weight:600;">✅ Update available: v' + d.remote_version + '</span> — <a href="' + <?php echo wp_json_encode( admin_url( 'update-core.php' ) ); ?> + '">Go to Updates</a>');
				} else {
					$res.html('<span style="color:#6b7280;">✔ You are on the latest version (v' + d.remote_version + ')</span>');
				}
			} else {
				$res.html('<span style="color:#dc2626;">⚠ ' + (res.data && res.data.message ? res.data.message : 'Check failed') + '</span>');
			}
		})
		.fail(function(){ $res.html('<span style="color:#dc2626;">⚠ Request failed.</span>'); })
		.always(function(){ $btn.prop('disabled', false).text('🔄 Check for Update'); });
	});
}(jQuery));
</script>
