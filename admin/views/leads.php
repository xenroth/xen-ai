<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
global $wpdb;
$conv_table = $wpdb->prefix . 'xen_ai_conversations';
$msg_table  = $wpdb->prefix . 'xen_ai_messages';

$per_page     = 25;
$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$offset       = ( $current_page - 1 ) * $per_page;

// ── Search keyword ────────────────────────────────────────────────────────────
$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

// ── Sort ──────────────────────────────────────────────────────────────────────
$allowed_orderby = [ 'user_name', 'user_email', 'visitor_ip', 'created_at', 'msg_count' ];
$orderby  = isset( $_GET['orderby'] ) && in_array( $_GET['orderby'], $allowed_orderby, true )
            ? $_GET['orderby'] : 'created_at';
$order    = isset( $_GET['order'] ) && 'asc' === strtolower( $_GET['order'] ) ? 'ASC' : 'DESC';

// ── De-duplication logic ──────────────────────────────────────────────────────
// Treat rows with the same LOWER(user_name) AND same visitor_ip as the same lead.
// Keep the most complete row (prefer email, then latest updated_at) by using MIN(id)
// per group — we pick the earliest id that has email if possible, fallback to MIN(id).
// Implementation: sub-select the representative id per (norm_name, ip) group,
// then join back to get the full row.

$base_where = "( user_name IS NOT NULL AND user_name != '' )
            OR ( user_email IS NOT NULL AND user_email != '' )";

$search_where = '';
$search_args  = [];
if ( $search !== '' ) {
	$like = '%' . $wpdb->esc_like( $search ) . '%';
	$search_where = ' AND ( c.user_name LIKE %s OR c.user_email LIKE %s OR c.visitor_ip LIKE %s )';
	$search_args  = [ $like, $like, $like ];
}

// Representative id per duplicate group — pick the row with email if available,
// otherwise the first (MIN id) in the group.
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$dedup_subquery = "
	SELECT
		COALESCE(
			MIN( CASE WHEN user_email IS NOT NULL AND user_email != '' THEN id END ),
			MIN( id )
		) AS rep_id
	FROM {$conv_table}
	WHERE {$base_where}
	GROUP BY
		LOWER( COALESCE( user_name, '' ) ),
		COALESCE( visitor_ip, '' )
";

// Total unique leads (for pagination)
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$total       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ( {$dedup_subquery} ) AS dedup" );
$total_pages = max( 1, ceil( $total / $per_page ) );

// Order clause — msg_count is computed so must reference alias
$order_col = 'msg_count' === $orderby ? 'msg_count' : "c.{$orderby}";

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$query = $wpdb->prepare(
	"SELECT c.id, c.user_name, c.user_email, c.visitor_ip, c.page_url, c.created_at,
	        (SELECT COUNT(*) FROM {$msg_table} m WHERE m.conversation_id = c.id) AS msg_count
	 FROM {$conv_table} c
	 INNER JOIN ( {$dedup_subquery} ) AS dedup ON dedup.rep_id = c.id
	 WHERE 1=1 {$search_where}
	 ORDER BY {$order_col} {$order}
	 LIMIT %d OFFSET %d",
	array_merge( $search_args, [ $per_page, $offset ] )
);
// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$leads = $wpdb->get_results( $query );

// Recalculate total with search applied
if ( $search !== '' ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$total_query = $wpdb->prepare(
		"SELECT COUNT(*) FROM {$conv_table} c
		 INNER JOIN ( {$dedup_subquery} ) AS dedup ON dedup.rep_id = c.id
		 WHERE 1=1 {$search_where}",
		$search_args
	);
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$total       = (int) $wpdb->get_var( $total_query );
	$total_pages = max( 1, ceil( $total / $per_page ) );
}

// ── Export URL ────────────────────────────────────────────────────────────────
$export_url = wp_nonce_url(
	admin_url( 'admin-ajax.php?action=xen_ai_export_leads' ),
	'xen_ai_admin',
	'nonce'
);

// ── Sort link helper ──────────────────────────────────────────────────────────
$sort_link = function( $col, $label ) use ( $orderby, $order, $search ) {
	$next_order = ( $orderby === $col && $order === 'DESC' ) ? 'asc' : 'desc';
	$arrow      = '';
	if ( $orderby === $col ) {
		$arrow = ' ' . ( $order === 'ASC' ? '↑' : '↓' );
	}
	$url = esc_url( admin_url( 'admin.php?' . http_build_query( [
		'page'    => 'xen-ai-leads',
		'orderby' => $col,
		'order'   => $next_order,
		's'       => $search,
	] ) ) );
	return '<a href="' . $url . '" class="xen-ai-sort-link' . ( $orderby === $col ? ' active' : '' ) . '">'
	       . esc_html( $label ) . '<span class="xen-sort-arrow">' . $arrow . '</span></a>';
};
?>
<div class="wrap xen-ai-wrap">

	<div class="xen-ai-page-header">
		<div class="xen-ai-page-title">
			<span class="xen-ai-logo-icon">👤</span>
			<h1>Captured Leads</h1>
		</div>
		<p class="xen-ai-subtitle">Unique leads (same name + IP treated as one). Click a column header to sort.</p>
	</div>

	<div id="xen-leads-notice" class="xen-ai-notice" style="display:none;"></div>

	<div class="xen-ai-card">

		<!-- Toolbar: search + export -->
		<div class="xen-leads-toolbar">
			<form method="get" action="" class="xen-leads-search-form">
				<input type="hidden" name="page" value="xen-ai-leads">
				<?php if ( $orderby ) : ?><input type="hidden" name="orderby" value="<?php echo esc_attr( $orderby ); ?>"><?php endif; ?>
				<?php if ( $order ) : ?><input type="hidden" name="order" value="<?php echo esc_attr( strtolower( $order ) ); ?>"><?php endif; ?>
				<input type="search"
				       name="s"
				       value="<?php echo esc_attr( $search ); ?>"
				       placeholder="Search name, email, or IP…"
				       class="xen-leads-search-input">
				<button type="submit" class="xen-ai-btn xen-ai-btn-secondary xen-ai-btn-sm">🔍 Search</button>
				<?php if ( $search ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=xen-ai-leads' ) ); ?>" class="xen-ai-btn xen-ai-btn-outline xen-ai-btn-sm">✕ Clear</a>
				<?php endif; ?>
			</form>
			<div class="xen-leads-toolbar-right">
				<span class="xen-ai-muted">
					<?php echo esc_html( $total ); ?> unique lead(s)<?php echo $search ? ' matching <strong>' . esc_html( $search ) . '</strong>' : ''; ?>
				</span>
				<?php if ( $total > 0 ) : ?>
				<a href="<?php echo esc_url( $export_url . ( $search ? '&s=' . urlencode( $search ) : '' ) ); ?>"
				   class="xen-ai-btn xen-ai-btn-secondary xen-ai-btn-sm">
					⬇ Export CSV
				</a>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( empty( $leads ) ) : ?>
			<p class="xen-ai-muted xen-ai-empty-msg">
				<?php echo $search ? 'No leads match your search.' : 'No leads captured yet. The AI will collect visitor names and emails naturally during chat and they will appear here.'; ?>
			</p>
		<?php else : ?>

		<div class="xen-leads-table-wrap">
		<table class="xen-ai-table" id="xen-leads-table">
			<thead>
				<tr>
					<th style="width:36px">#</th>
					<th><?php echo wp_kses_post( $sort_link( 'user_name',  'Name' ) ); ?></th>
					<th><?php echo wp_kses_post( $sort_link( 'user_email', 'Email' ) ); ?></th>
					<th><?php echo wp_kses_post( $sort_link( 'visitor_ip', 'IP / Location' ) ); ?></th>
					<th>Landing Page</th>
					<th style="width:55px"><?php echo wp_kses_post( $sort_link( 'msg_count', 'Msgs' ) ); ?></th>
					<th><?php echo wp_kses_post( $sort_link( 'created_at', 'Date' ) ); ?></th>
					<th style="width:110px">Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $leads as $i => $lead ) : ?>
				<tr id="xen-lead-row-<?php echo esc_attr( $lead->id ); ?>" class="xen-ai-lead-row">
					<td class="xen-ai-muted"><?php echo esc_html( ( $current_page - 1 ) * $per_page + $i + 1 ); ?></td>
					<td><?php echo ! empty( $lead->user_name ) ? esc_html( $lead->user_name ) : '<em class="xen-ai-muted">—</em>'; ?></td>
					<td>
						<?php if ( ! empty( $lead->user_email ) ) : ?>
							<a href="mailto:<?php echo esc_attr( $lead->user_email ); ?>"><?php echo esc_html( $lead->user_email ); ?></a>
						<?php else : ?>
							<em class="xen-ai-muted">—</em>
						<?php endif; ?>
					</td>
					<td class="xen-ai-muted xen-ip-cell" data-ip="<?php echo esc_attr( $lead->visitor_ip ?? '' ); ?>">
						<span class="xen-ip-text"><?php echo ! empty( $lead->visitor_ip ) ? esc_html( $lead->visitor_ip ) : '<em>—</em>'; ?></span>
						<?php if ( ! empty( $lead->visitor_ip ) ) : ?>
						<span class="xen-geo-badge" title="Loading location…">🌐</span>
						<?php endif; ?>
					</td>
					<td class="xen-ai-muted" style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
						<?php if ( ! empty( $lead->page_url ) ) : ?>
							<a href="<?php echo esc_url( $lead->page_url ); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr( $lead->page_url ); ?>">
								<?php echo esc_html( wp_parse_url( $lead->page_url, PHP_URL_PATH ) ?: $lead->page_url ); ?>
							</a>
						<?php else : ?>—<?php endif; ?>
					</td>
					<td class="xen-ai-muted"><?php echo esc_html( $lead->msg_count ); ?></td>
					<td class="xen-ai-muted" style="white-space:nowrap;"><?php echo esc_html( wp_date( 'M j, Y g:i A', strtotime( $lead->created_at ) ) ); ?></td>
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
		</div>

		<!-- Pagination -->
		<?php if ( $total_pages > 1 ) : ?>
		<div class="xen-ai-pagination">
			<?php
			echo wp_kses_post( paginate_links( [
				'base'      => esc_url( admin_url( 'admin.php?' . http_build_query( array_filter( [
					'page'    => 'xen-ai-leads',
					'paged'   => '%#%',
					'orderby' => $orderby !== 'created_at' ? $orderby : '',
					'order'   => $order !== 'DESC' ? strtolower( $order ) : '',
					's'       => $search,
				] ) ) ) ),
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
		var id   = $(this).data('id');
		var name = $(this).data('name');
		$('#xen-modal-title').text('Conversation with ' + name);
		$('#xen-convo-messages').html('<p class="xen-ai-muted">Loading\u2026</p>');
		$('#xen-convo-modal').fadeIn(200);

		$.post(xenAIAdmin.ajaxUrl, {
			action : 'xen_ai_get_messages',
			nonce  : xenAIAdmin.nonce,
			id     : id,
		}, function(res){
			if (res.success && res.data.html) {
				$('#xen-convo-messages').html(res.data.html);
			} else {
				$('#xen-convo-messages').html('<p class="xen-ai-muted">No messages found.</p>');
			}
		});
	});

	$('.xen-ai-modal-close, .xen-ai-modal-overlay').on('click', function(){
		$('#xen-convo-modal').fadeOut(200);
	});

	// ── Geo-IP: resolve country for each IP cell ──────────────────────────────
	// Uses ip-api.com (free, no key, 45 req/min).  Batched with short delay to
	// avoid rate-limiting. Results cached per page-load in a JS map.
	var geoCache = {};

	function resolveGeo($cell) {
		var ip = $cell.data('ip');
		if (!ip || ip === '') return;

		var $badge = $cell.find('.xen-geo-badge');

		if (geoCache[ip] !== undefined) {
			$badge.text(geoCache[ip]).attr('title', geoCache[ip]).addClass('loaded');
			return;
		}

		// ip-api.com/json/{ip}?fields=country,countryCode — plain http is fine for admin
		$.getJSON('https://ip-api.com/json/' + encodeURIComponent(ip) + '?fields=country,countryCode,status')
			.done(function(data) {
				var label = '';
				if (data.status === 'success' && data.countryCode) {
					// Flag emoji from country code (two regional indicator letters)
					var flag = data.countryCode.toUpperCase().split('').map(function(c){
						return String.fromCodePoint(c.charCodeAt(0) + 127397);
					}).join('');
					label = flag + ' ' + data.country;
				} else {
					label = '🌐 Unknown';
				}
				geoCache[ip] = label;
				$badge.text(label).attr('title', label).addClass('loaded');
			})
			.fail(function() {
				geoCache[ip] = '—';
				$badge.text('—').addClass('loaded');
			});
	}

	// Stagger requests: 250 ms apart to stay well under 45/min limit
	var $cells = $('.xen-ip-cell[data-ip]');
	$cells.each(function(i) {
		var $cell = $(this);
		setTimeout(function() { resolveGeo($cell); }, i * 260);
	});

})(jQuery);
</script>
