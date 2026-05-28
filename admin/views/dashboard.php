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
$is_pro        = Xen_AI_License::is_active();
$license_record = Xen_AI_License::get_record();
?>
<div class="wrap xen-ai-wrap">

	<div class="xen-ai-page-header">
		<div class="xen-ai-page-title">
			<span class="xen-ai-logo-icon">⚡</span>
			<h1>XEN A.I — Dashboard<?php if ( $is_pro ) : ?> <span class="xen-ai-pro-title-badge">✦ PRO</span><?php endif; ?></h1>
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

	<?php if ( $is_pro ) : ?>
	<!-- ── Pro Activated Hero Banner ─────────────────────────── -->
	<div class="xen-ai-pro-hero">
		<div class="xen-ai-pro-hero-glow"></div>
		<div class="xen-ai-pro-hero-inner">
			<div class="xen-ai-pro-hero-left">
				<div class="xen-ai-pro-hero-crown">✦</div>
				<div>
					<div class="xen-ai-pro-hero-sup">XEN A.I</div>
					<h2 class="xen-ai-pro-hero-title">PRO VERSION ACTIVE</h2>
					<p class="xen-ai-pro-hero-sub">You have unlocked all current and future Pro features. Every new capability released under the Pro tier is automatically included with your license — forever.</p>
				</div>
			</div>
			<div class="xen-ai-pro-hero-perks">
				<div class="xen-ai-pro-hero-perk">✅ Proactive Visitor Questioning</div>
				<div class="xen-ai-pro-hero-perk">✅ Knowledge-Base Topic Insights</div>
				<div class="xen-ai-pro-hero-perk">✅ Service &amp; Product Purchase Guide</div>
				<div class="xen-ai-pro-hero-perk">✅ Priority Support</div>
				<div class="xen-ai-pro-hero-perk xen-ai-pro-hero-perk-future">🔮 All Future Pro Features — Included</div>
			</div>
		</div>
		<?php if ( $license_record ) : ?>
		<div class="xen-ai-pro-hero-meta">
			<span>🔑 License: <strong><?php echo esc_html( Xen_AI_License::get_masked_key() ); ?></strong></span>
			<span class="xen-ai-pro-hero-sep">·</span>
			<span>🌐 Domain: <strong><?php echo esc_html( $license_record['domain'] ?? get_site_url() ); ?></strong></span>
			<span class="xen-ai-pro-hero-sep">·</span>
			<span>📅 Activated: <strong><?php echo esc_html( ! empty( $license_record['activated_at'] ) ? date_i18n( get_option( 'date_format' ), $license_record['activated_at'] ) : '—' ); ?></strong></span>
			<?php if ( ! empty( $license_record['email'] ) ) : ?>
			<span class="xen-ai-pro-hero-sep">·</span>
			<span>✉️ Contact: <strong><?php echo esc_html( $license_record['email'] ); ?></strong></span>
			<?php endif; ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=xen-ai-license' ) ); ?>" class="xen-ai-pro-hero-manage">Manage License →</a>
		</div>
		<?php endif; ?>
	</div>

	<?php else : ?>
	<!-- ── Free Pro License Promo ─────────────────────────────── -->
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

	<!-- Status card -->
	<div class="xen-ai-card xen-ai-mt" style="margin-bottom:24px;">
		<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
			<h2 class="xen-ai-card-title" style="margin:0;">System Status</h2>
			<button type="button" id="xen-test-connection-btn" class="button button-small">🔌 Test Connection</button>
		</div>
		<div id="xen-test-connection-result" style="display:none;margin-bottom:14px;"></div>
		<?php if ( get_transient( 'xen_ai_api_unavailable' ) ) : ?>
		<div class="xen-ai-notice xen-ai-notice-warn" style="margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;gap:12px;">
			<span>⚠ <strong>Fallback mode is active.</strong> A recent API error (quota/rate limit) has put the chatbot in offline mode — visitors are seeing the fallback message. This auto-clears in 5 minutes, or click Clear to reset it immediately.</span>
			<button type="button" id="xen-clear-fallback-btn" class="button button-small" style="white-space:nowrap;flex-shrink:0;">Clear Now</button>
		</div>
		<?php endif; ?>
		<?php
		$provider_name = 'github' === ( $settings['provider'] ?? 'openai' ) ? 'GitHub Models' : 'OpenAI';
		$active_model  = 'github' === ( $settings['provider'] ?? 'openai' )
			? ( $settings['github_model'] ?? 'gpt-4o' )
			: ( $settings['model']        ?? 'gpt-3.5-turbo' );
		?>
		<table class="xen-ai-status-table">
			<tr>
				<td><span class="xen-ai-dot <?php echo esc_attr( $configured ? 'green' : 'red' ); ?>"></span> <?php echo esc_html( $provider_name ); ?></td>
				<td><strong><?php echo esc_html( $configured ? 'Connected' : 'Not configured' ); ?></strong></td>
			</tr>
			<tr>
				<td><span class="xen-ai-dot green"></span> AI Model</td>
				<td><strong><?php echo esc_html( $active_model ); ?></strong></td>
			</tr>
			<tr>
				<td><span class="xen-ai-dot <?php echo esc_attr( empty( $settings['disable_chat'] ) ? 'green' : 'red' ); ?>"></span> Chat Widget</td>
				<td><strong><?php echo esc_html( empty( $settings['disable_chat'] ) ? 'Enabled' : 'Disabled' ); ?></strong></td>
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

	<!-- ── Version Announcement & Community (always visible) ────── -->
	<div class="xen-ai-announcement-bar">
		<div class="xen-ai-announcement-inner">
			<div class="xen-ai-announcement-left">
				<span class="xen-ai-announcement-badge">🎉 WHAT'S NEW</span>
				<h3 class="xen-ai-announcement-title">XEN A.I v<?php echo esc_html( XEN_AI_VERSION ); ?> is Here!</h3>
				<ul class="xen-ai-announcement-list">
					<li>✨ Proactive email capture &mdash; witty invite on the 4th reply</li>
					<li>🔌 Test Connection diagnostics + Fallback Mode indicator on dashboard</li>
					<li>🔒 Layered security hardening (burst &amp; session flood protection)</li>
				</ul>
			</div>
			<div class="xen-ai-announcement-right">
				<strong class="xen-ai-announcement-line-title">💬 Join Our LINE Community</strong>
				<p class="xen-ai-announcement-line-desc">Get updates, tips &amp; direct developer support &mdash; free Pro license giveaways for early members!</p>
				<a href="https://line.me/R/ti/g/DBGUQQdSg2" target="_blank" rel="noopener noreferrer" class="xen-ai-btn xen-ai-btn-line xen-ai-btn-line-sm">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="15" height="15" fill="currentColor" style="vertical-align:middle;margin-right:5px;"><path d="M12 .5C5.649.5.5 4.534.5 9.5c0 4.41 3.914 8.112 9.21 8.878.358.077.846.236.97.542.11.278.072.713.035 1.003l-.157.947c-.048.278-.222 1.086.952.592 1.174-.494 6.334-3.729 8.641-6.385C21.604 13.14 23.5 11.437 23.5 9.5 23.5 4.534 18.351.5 12 .5z"/></svg>
					Join LINE Group →
				</a>
			</div>
		</div>
	</div>

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

	<!-- Getting Started Guide -->
	<div class="xen-ai-card xen-ai-mt xen-ai-getting-started">
		<div class="xen-ai-gs-header">
			<h2 class="xen-ai-card-title" style="margin:0;">🚀 Getting Started</h2>
			<button type="button" class="xen-ai-gs-toggle" id="xen-gs-toggle" aria-expanded="true" aria-controls="xen-gs-body">
				<span class="xen-ai-gs-chevron">▾</span>
			</button>
		</div>
		<div id="xen-gs-body" class="xen-ai-gs-body">
			<p class="xen-ai-gs-intro">Follow these steps to get XEN A.I fully set up on your site:</p>
			<ol class="xen-ai-gs-steps">

				<li class="xen-ai-gs-step <?php echo esc_attr( $configured ? 'xen-ai-gs-done' : '' ); ?>">
					<div class="xen-ai-gs-step-num"><?php echo esc_html( $configured ? '✓' : '1' ); ?></div>
					<div class="xen-ai-gs-step-body">
						<strong>Connect your AI provider</strong>
						<p>Go to <a href="<?php echo esc_url( admin_url( 'admin.php?page=xen-ai-settings' ) ); ?>">Settings</a> and add your <strong>OpenAI API key</strong> (<code>sk-…</code>) or a <strong>GitHub Personal Access Token</strong> (free — works with GitHub Models). Choose your preferred model (GPT-4o, etc.).</p>
					</div>
				</li>

				<li class="xen-ai-gs-step">
					<div class="xen-ai-gs-step-num">2</div>
					<div class="xen-ai-gs-step-body">
						<strong>Set your bot's identity</strong>
						<p>In <a href="<?php echo esc_url( admin_url( 'admin.php?page=xen-ai-settings' ) ); ?>">Settings</a>, set the <strong>Bot Name</strong>, upload a <strong>logo/avatar</strong>, choose an <strong>accent colour</strong>, and write a custom <strong>welcome greeting</strong>. This is what visitors will see.</p>
					</div>
				</li>

				<li class="xen-ai-gs-step <?php echo esc_attr( $kb_count > 0 ? 'xen-ai-gs-done' : '' ); ?>">
					<div class="xen-ai-gs-step-num"><?php echo esc_html( $kb_count > 0 ? '✓' : '3' ); ?></div>
					<div class="xen-ai-gs-step-body">
						<strong>Build your Knowledge Base</strong>
						<p>Go to <a href="<?php echo esc_url( admin_url( 'admin.php?page=xen-ai-kb' ) ); ?>">Knowledge Base</a> and upload PDFs, DOCX or TXT files, or paste a URL to scrape. The AI will prioritise this content when answering visitor questions.</p>
					</div>
				</li>

				<li class="xen-ai-gs-step">
					<div class="xen-ai-gs-step-num">4</div>
					<div class="xen-ai-gs-step-body">
						<strong>Customize your system prompt <span class="xen-ai-gs-optional">(optional)</span></strong>
						<p>In <a href="<?php echo esc_url( admin_url( 'admin.php?page=xen-ai-settings' ) ); ?>">Settings</a> under <em>Custom AI Instructions</em>, tell the AI about your business, tone, and anything it should always keep in mind. Example: <em>"Always recommend contacting sales for orders above ₱5,000."</em></p>
					</div>
				</li>

				<li class="xen-ai-gs-step">
					<div class="xen-ai-gs-step-num">5</div>
					<div class="xen-ai-gs-step-body">
						<strong>Test the chat widget on your site</strong>
						<p>Visit any page of your site — the chat bubble will appear in the bottom-right corner. Open it and send a test message to confirm everything is working. Check <a href="<?php echo esc_url( admin_url( 'admin.php?page=xen-ai-leads' ) ); ?>">Leads &amp; Conversations</a> to see the conversation logged.</p>
					</div>
				</li>

				<li class="xen-ai-gs-step <?php echo esc_attr( $is_pro ? 'xen-ai-gs-done' : '' ); ?>">
					<div class="xen-ai-gs-step-num"><?php echo esc_html( $is_pro ? '✓' : '6' ); ?></div>
					<div class="xen-ai-gs-step-body">
						<strong>Activate Pro <span class="xen-ai-gs-optional">(optional)</span></strong>
						<p><?php if ( $is_pro ) : ?>Your Pro license is active — all advanced features are already enabled including proactive questioning, topic chips and the purchase guide. <?php else : ?>Go to <a href="<?php echo esc_url( admin_url( 'admin.php?page=xen-ai-license' ) ); ?>">Pro License</a> and enter your license key to unlock proactive visitor engagement, topic quick-menus, the purchase guide, and all future Pro features. <?php endif; ?></p>
					</div>
				</li>

			</ol>
		</div>
	</div>
	<script>
	(function(){
		var btn  = document.getElementById('xen-gs-toggle');
		var body = document.getElementById('xen-gs-body');
		if (!btn || !body) return;
		btn.addEventListener('click', function(){
			var open = btn.getAttribute('aria-expanded') === 'true';
			btn.setAttribute('aria-expanded', open ? 'false' : 'true');
			btn.querySelector('.xen-ai-gs-chevron').style.transform = open ? 'rotate(-90deg)' : '';
			body.style.display = open ? 'none' : '';
		});
	})();
	</script>

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

	<!-- Pro Features upsell (shown only when Pro is NOT active) -->
	<?php if ( ! $is_pro ) : ?>
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

			<div class="xen-ai-pro-feature">
				<span class="xen-ai-pro-feature-icon">💬</span>
				<div>
					<strong>Topic Quick-Menu in Chat</strong>
					<p>Clickable topic chips appear above the chat input, letting visitors jump straight to any knowledge-base topic with one tap — no typing needed.</p>
				</div>
			</div>

		</div>

		<div class="xen-ai-pro-cta">
			<span class="xen-ai-pro-price">₱999 <small>one-time</small></span>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=xen-ai-license' ) ); ?>" class="xen-ai-btn xen-ai-btn-pro" style="opacity:1;cursor:pointer;">🔑 Activate Pro License</a>
		</div>

		<div class="xen-ai-pro-contact">
			<span>📩 Interested? Reach out to the developer:</span>
			<a href="mailto:me@xenroth.com">me@xenroth.com</a>
			<span class="xen-ai-pro-contact-sep">·</span>
			<a href="tel:+639150388448">+63 915 038 8448</a>
		</div>
	</div>
	<?php endif; ?>


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
