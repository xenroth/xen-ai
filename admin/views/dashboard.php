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

	<?php if ( ! $configured ) : ?>
	<div class="xen-ai-notice xen-ai-notice-warn">
		<strong>⚠ Setup required:</strong>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=xen-ai-settings' ) ); ?>">Add your OpenAI API key</a>
		to enable the AI chat assistant.
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
			<button type="button" class="xen-ai-btn xen-ai-btn-pro" disabled>🔒 Upgrade to Pro — Coming Soon</button>
		</div>
	</div>

	<!-- Status card -->
	<div class="xen-ai-card xen-ai-mt">
		<h2 class="xen-ai-card-title">System Status</h2>
		<table class="xen-ai-status-table">
			<tr>
				<td><span class="xen-ai-dot <?php echo $configured ? 'green' : 'red'; ?>"></span> OpenAI API</td>
				<td><strong><?php echo $configured ? 'Connected' : 'Not configured'; ?></strong></td>
			</tr>
			<tr>
				<td><span class="xen-ai-dot green"></span> AI Model</td>
				<td><strong><?php echo esc_html( $settings['model'] ?? 'gpt-3.5-turbo' ); ?></strong></td>
			</tr>
			<tr>
				<td><span class="xen-ai-dot <?php echo empty( $settings['disable_chat'] ) ? 'green' : 'red'; ?>"></span> Chat Widget</td>
				<td><strong><?php echo empty( $settings['disable_chat'] ) ? 'Enabled' : 'Disabled'; ?></strong></td>
			</tr>
			<tr>
				<td><span class="xen-ai-dot green"></span> Plugin Version</td>
				<td><strong><?php echo esc_html( XEN_AI_VERSION ); ?></strong></td>
			</tr>
		</table>
	</div>

</div>
