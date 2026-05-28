<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
// Force re-validation every time this page is viewed — never serve a stale cache here
delete_transient( 'xen_ai_license_valid' );
$record  = Xen_AI_License::get_record();
$active  = Xen_AI_License::is_active();
$masked  = Xen_AI_License::get_masked_key();
?>
<div class="wrap xen-ai-wrap">

	<div class="xen-ai-page-header">
		<div class="xen-ai-page-title">
			<span class="xen-ai-logo-icon">🔑</span>
			<h1>XEN A.I Pro — License</h1>
		</div>
		<p class="xen-ai-subtitle">Activate your Pro license to unlock advanced features.</p>
	</div>

	<div id="xen-license-notice" class="xen-ai-notice" style="display:none;"></div>

	<!-- Status banner -->
	<div class="xen-ai-card xen-ai-mt">
		<div class="xen-ai-license-status-row">
		<div class="xen-ai-license-status-icon <?php echo esc_attr( $active ? 'active' : 'inactive' ); ?>">
			<?php echo esc_html( $active ? '✅' : '🔒' ); ?>
			</div>
			<div>
				<strong class="xen-ai-license-status-label">
					<?php echo $active ? esc_html__( 'Pro License Active', 'xen-ai' ) : esc_html__( 'No Active License', 'xen-ai' ); ?>
				</strong>
				<p class="xen-ai-help" style="margin:4px 0 0;">
					<?php if ( $active ) : ?>
						Licensed to: <strong><?php echo esc_html( $record['domain'] ?? get_site_url() ); ?></strong>
						&nbsp;·&nbsp; Activated: <strong><?php echo esc_html( $record['activated_at'] ? date_i18n( get_option( 'date_format' ), $record['activated_at'] ) : '—' ); ?></strong>
						<?php if ( ! empty( $record['email'] ) ) : ?>
						&nbsp;·&nbsp; Email: <strong><?php echo esc_html( $record['email'] ); ?></strong>
						<?php endif; ?>
					<?php else : ?>
						Enter your license key below to unlock all Pro features.
					<?php endif; ?>
				</p>
			</div>
			<?php if ( $active ) : ?>
				<button type="button" id="xen-deactivate-license" class="xen-ai-btn xen-ai-btn-danger xen-ai-btn-sm" style="margin-left:auto;">
					Deactivate
				</button>
			<?php endif; ?>
		</div>
	</div>

	<!-- Activation form -->
	<?php if ( ! $active ) : ?>
	<div class="xen-ai-card xen-ai-mt">
		<h2 class="xen-ai-card-title">✨ Activate Pro</h2>
		<p style="color:var(--xen-muted);font-size:0.9rem;margin-bottom:20px;">
			Your license key was provided when you purchased XEN A.I Pro.
			One key activates one domain — purchasing supports continued development. ₱999 one-time.
		</p>

		<div class="xen-ai-license-input-row" style="flex-wrap:wrap;gap:10px;">
			<input type="text"
			       id="xen-license-key-input"
			       class="xen-ai-input-field"
			       placeholder="XEN-XXXX-XXXX-XXXX-XXXX"
			       autocomplete="off"
			       spellcheck="false"
			       maxlength="64"
			       style="font-family:monospace;letter-spacing:.05em;max-width:360px;">
			<input type="email"
			       id="xen-license-email-input"
			       class="xen-ai-input-field"
			       placeholder="your@email.com (optional)"
			       autocomplete="email"
			       spellcheck="false"
			       maxlength="150"
			       style="max-width:260px;">
			<button type="button" id="xen-activate-license" class="xen-ai-btn xen-ai-btn-primary">
				🔓 Verify &amp; Activate
			</button>
			<span id="xen-license-spinner" class="xen-spinner" style="display:none;"></span>
		</div>

		<p class="xen-ai-help" style="margin-top:10px;">
			Don't have a license key? Contact
			<a href="mailto:me@xenroth.com">me@xenroth.com</a> or
			<a href="tel:+639150388448">+63 915 038 8448</a>.
		</p>
	</div>
	<?php else : ?>
	<!-- Active license: show masked key -->
	<div class="xen-ai-card xen-ai-mt">
		<h2 class="xen-ai-card-title">🔑 License Key</h2>
		<p style="font-family:monospace;font-size:1rem;letter-spacing:.08em;color:var(--xen-text);">
			<?php echo esc_html( $masked ?: '—' ); ?>
		</p>
		<p class="xen-ai-help">
			To transfer this license to a different domain, deactivate it here first, then activate on the new site.
		</p>
	</div>
	<?php endif; ?>

	<!-- Pro features list -->
	<div class="xen-ai-card xen-ai-mt xen-ai-pro-card">
		<div class="xen-ai-pro-header">
			<div>
				<h2 class="xen-ai-card-title" style="margin:0;">✨ Pro Features</h2>
				<p style="margin:6px 0 0;color:var(--xen-text-muted);font-size:0.875rem;">
					<?php echo esc_html( $active ? 'All features below are active on your site.' : 'Unlock all of the following by activating your license.' ); ?>
				</p>
			</div>
			<?php if ( $active ) : ?>
				<span class="xen-ai-pro-badge" style="background:linear-gradient(135deg,#22c55e,#16a34a);">Active</span>
			<?php else : ?>
				<span class="xen-ai-pro-badge">Locked</span>
			<?php endif; ?>
		</div>

		<div class="xen-ai-pro-features-grid">
			<div class="xen-ai-pro-feature <?php echo esc_attr( $active ? '' : 'xen-pro-locked' ); ?>">
				<span class="xen-ai-pro-feature-icon">🎯</span>
				<div>
					<strong>Proactive Visitor Questioning</strong>
					<p>AI opens with a page-contextual question tailored to where the visitor landed.</p>
				</div>
			</div>
			<div class="xen-ai-pro-feature <?php echo esc_attr( $active ? '' : 'xen-pro-locked' ); ?>">
				<span class="xen-ai-pro-feature-icon">📋</span>
				<div>
					<strong>Knowledge-Base Topic Insights</strong>
					<p>Surfaces relevant KB topics in the chat so visitors instantly know what to ask.</p>
				</div>
			</div>
			<div class="xen-ai-pro-feature <?php echo esc_attr( $active ? '' : 'xen-pro-locked' ); ?>">
				<span class="xen-ai-pro-feature-icon">🛒</span>
				<div>
					<strong>Service &amp; Product Purchase Guide</strong>
					<p>Step-by-step conversational checkout assistant with terms, pricing, and order guidance.</p>
				</div>
			</div>
		</div>
	</div>

</div>

<script>
(function($){
	var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
	var nonce   = <?php echo wp_json_encode( wp_create_nonce( 'xen_ai_admin' ) ); ?>;

	function showNotice(msg, type) {
		$('#xen-license-notice')
			.removeClass('xen-ai-notice-ok xen-ai-notice-warn xen-ai-notice-error')
			.addClass('xen-ai-notice-' + (type || 'ok'))
			.html(msg).slideDown(200);
		setTimeout(function(){ $('#xen-license-notice').slideUp(300); }, 6000);
	}

	$('#xen-activate-license').on('click', function(){
		var key   = $.trim($('#xen-license-key-input').val());
		var email = $.trim($('#xen-license-email-input').val());
		if (!key) { showNotice('Please enter your license key.', 'warn'); return; }

		$(this).prop('disabled', true);
		$('#xen-license-spinner').show();

		$.post(ajaxUrl, { action: 'xen_ai_activate_license', nonce: nonce, key: key, email: email })
			.done(function(res){
				if (res.success) {
					showNotice(res.data.message || 'License activated!', 'ok');
					setTimeout(function(){ location.reload(); }, 1200);
				} else {
					showNotice((res.data && res.data.message) || 'Activation failed.', 'error');
				}
			})
			.fail(function(){ showNotice('Request failed. Check your connection.', 'error'); })
			.always(function(){
				$('#xen-activate-license').prop('disabled', false);
				$('#xen-license-spinner').hide();
			});
	});

	$('#xen-deactivate-license').on('click', function(){
		if (!confirm('Deactivate this license? Pro features will be disabled on this site.')) return;

		$(this).prop('disabled', true);

		$.post(ajaxUrl, { action: 'xen_ai_deactivate_license', nonce: nonce })
			.done(function(res){
				if (res.success) {
					showNotice(res.data.message || 'License deactivated.', 'ok');
					setTimeout(function(){ location.reload(); }, 1200);
				} else {
					showNotice((res.data && res.data.message) || 'Deactivation failed.', 'error');
				}
			})
			.fail(function(){ showNotice('Request failed.', 'error'); })
			.always(function(){ $('#xen-deactivate-license').prop('disabled', false); });
	});
}(jQuery));
</script>
