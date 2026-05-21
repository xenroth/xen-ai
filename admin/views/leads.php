<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
global $wpdb;
$conv_table  = $wpdb->prefix . 'xen_ai_conversations';
$msg_table   = $wpdb->prefix . 'xen_ai_messages';

$per_page     = 25;
$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$offset       = ( $current_page - 1 ) * $per_page;

// Only rows with a captured name or email are real leads.
$where = "WHERE ( user_name IS NOT NULL AND user_name != '' )
             OR ( user_email IS NOT NULL AND user_email != '' )";

// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$total       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$conv_table} {$where}" );
$total_pages = max( 1, ceil( $total / $per_page ) );

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$leads = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT c.id, c.user_name, c.user_email, c.visitor_ip, c.page_url, c.created_at,
		        (SELECT COUNT(*) FROM {$msg_table} m WHERE m.conversation_id = c.id) AS msg_count
		 FROM {$conv_table} c
		 {$where}
		 ORDER BY c.created_at DESC
		 LIMIT %d OFFSET %d",
		$per_page,
		$offset
	)
);

$export_url = wp_nonce_url(
	admin_url( 'admin-ajax.php?action=xen_ai_export_leads' ),
	'xen_ai_admin',
	'nonce'
);
?>
<div class="wrap xen-ai-wrap">

	<div class="xen-ai-page-header">
		<div class="xen-ai-page-title">
			<span class="xen-ai-logo-icon">👤</span>
			<h1>Captured Leads</h1>
		</div>
		<p class="xen-ai-subtitle">Only sessions where a visitor's name or email was captured are shown here.</p>
	</div>

	<div id="xen-leads-notice" class="xen-ai-notice" style="display:none;"></div>

	<div class="xen-ai-card">
		<div class="xen-ai-card-header-row">
			<span class="xen-ai-muted"><?php echo esc_html( $total ); ?> captured lead(s)</span>
			<?php if ( $total > 0 ) : ?>
			<a href="<?php echo esc_url( $export_url ); ?>" class="xen-ai-btn xen-ai-btn-secondary xen-ai-btn-sm">
				⬇ Export CSV
			</a>
			<?php endif; ?>
		</div>

		<?php if ( empty( $leads ) ) : ?>
			<p class="xen-ai-muted xen-ai-empty-msg">No leads captured yet. The AI will collect visitor names and emails naturally during chat and they will appear here.</p>
		<?php else : ?>

		<table class="xen-ai-table" id="xen-leads-table">
			<thead>
				<tr>
					<th style="width:30px">#</th>
					<th>Name</th>
					<th>Email</th>
					<th>IP Address</th>
					<th>Landing Page</th>
					<th style="width:60px">Msgs</th>
					<th>Date</th>
					<th style="width:110px">Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $leads as $lead ) : ?>
				<tr id="xen-lead-row-<?php echo esc_attr( $lead->id ); ?>" class="xen-ai-lead-row">
					<td class="xen-ai-muted"><?php echo esc_html( $lead->id ); ?></td>
					<td><?php echo ! empty( $lead->user_name ) ? esc_html( $lead->user_name ) : '<em class="xen-ai-muted">—</em>'; ?></td>
					<td>
						<?php if ( ! empty( $lead->user_email ) ) : ?>
							<a href="mailto:<?php echo esc_attr( $lead->user_email ); ?>"><?php echo esc_html( $lead->user_email ); ?></a>
						<?php else : ?>
							<em class="xen-ai-muted">—</em>
						<?php endif; ?>
					</td>
					<td class="xen-ai-muted"><?php echo ! empty( $lead->visitor_ip ) ? esc_html( $lead->visitor_ip ) : '<em>—</em>'; ?></td>
					<td class="xen-ai-muted" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
						<?php if ( ! empty( $lead->page_url ) ) : ?>
							<a href="<?php echo esc_url( $lead->page_url ); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr( $lead->page_url ); ?>">
								<?php echo esc_html( wp_parse_url( $lead->page_url, PHP_URL_PATH ) ?: $lead->page_url ); ?>
							</a>
						<?php else : ?>—<?php endif; ?>
					</td>
					<td class="xen-ai-muted"><?php echo esc_html( $lead->msg_count ); ?></td>
					<td class="xen-ai-muted"><?php echo esc_html( wp_date( 'M j, Y g:i A', strtotime( $lead->created_at ) ) ); ?></td>
					<td>
						<button class="xen-ai-btn xen-ai-btn-outline xen-ai-btn-sm xen-view-convo"
						        data-id="<?php echo esc_attr( $lead->id ); ?>"
						        data-name="<?php echo esc_attr( $lead->user_name ?? 'Visitor' ); ?>">
							💬 Chat
						</button>
						<button class="xen-ai-btn xen-ai-btn-danger xen-ai-btn-sm xen-delete-convo"
						        data-id="<?php echo esc_attr( $lead->id ); ?>">
							🗑
						</button>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<!-- Pagination -->
		<?php if ( $total_pages > 1 ) : ?>
		<div class="xen-ai-pagination">
			<?php
			$base = admin_url( 'admin.php?page=xen-ai-leads&paged=%#%' );
			echo wp_kses_post( paginate_links( [
				'base'      => $base,
				'format'    => '',
				'current'   => $current_page,
				'total'     => $total_pages,
				'prev_text' => '&laquo; Prev',
				'next_text' => 'Next &raquo;',
			] ) );
			?>
		</div>
		<?php endif; ?>

		<?php endif; ?>
	</div>

</div>

<!-- Conversation modal -->
<div id="xen-convo-modal" class="xen-ai-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="xen-modal-title">
	<div class="xen-ai-modal-overlay"></div>
	<div class="xen-ai-modal-box">
		<div class="xen-ai-modal-header">
			<h3 id="xen-modal-title">Conversation</h3>
			<button class="xen-ai-modal-close" aria-label="Close">&#x2715;</button>
		</div>
		<div class="xen-ai-modal-body" id="xen-convo-messages">
			<p class="xen-ai-muted">Loading…</p>
		</div>
	</div>
</div>

<script>
(function($){
	// View conversation
	$(document).on('click', '.xen-view-convo', function(){
		const id   = $(this).data('id');
		const name = $(this).data('name');
		$('#xen-modal-title').text('Conversation with ' + name);
		$('#xen-convo-messages').html('<p class="xen-ai-muted">Loading…</p>');
		$('#xen-convo-modal').fadeIn(200);

		$.post(xenAIAdmin.ajaxUrl, {
			action : 'xen_ai_get_messages',
			nonce  : xenAIAdmin.nonce,
			id     : id,
		}, function(res){
			if(res.success && res.data.html){
				$('#xen-convo-messages').html(res.data.html);
			} else {
				$('#xen-convo-messages').html('<p class="xen-ai-muted">No messages found.</p>');
			}
		});
	});

	$('.xen-ai-modal-close, .xen-ai-modal-overlay').on('click', function(){
		$('#xen-convo-modal').fadeOut(200);
	});

})(jQuery);
</script>
