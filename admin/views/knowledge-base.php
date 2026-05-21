<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
$kb      = new Xen_AI_Knowledge_Base();
$entries = $kb->get_all();
?>
<div class="wrap xen-ai-wrap">

	<div class="xen-ai-page-header">
		<div class="xen-ai-page-title">
			<span class="xen-ai-logo-icon">📚</span>
			<h1>Knowledge Base</h1>
		</div>
		<p class="xen-ai-subtitle">Upload files or add URLs to teach XEN A.I about your website.</p>
	</div>

	<!-- Global notices area -->
	<div id="xen-kb-notice" class="xen-ai-notice" style="display:none;"></div>

	<div class="xen-ai-two-col">

		<!-- ── Upload File ── -->
		<div class="xen-ai-card">
			<h2 class="xen-ai-card-title">📄 Upload Document</h2>
			<p class="xen-ai-muted">Supported formats: <strong>PDF, DOCX, DOC, TXT</strong> — max 10 MB</p>

			<div id="xen-drop-zone" class="xen-drop-zone">
				<div class="xen-drop-icon">📂</div>
				<p>Drag &amp; drop a file here, or click to browse</p>
				<input type="file" id="xen-file-input" accept=".pdf,.doc,.docx,.txt" style="display:none;">
				<button type="button" class="xen-ai-btn xen-ai-btn-secondary" id="xen-browse-btn">Browse Files</button>
			</div>

			<div id="xen-upload-progress" style="display:none;">
				<div class="xen-progress-bar"><div class="xen-progress-fill" id="xen-progress-fill"></div></div>
				<p id="xen-progress-label" class="xen-ai-muted"></p>
			</div>
		</div>

		<!-- ── Add URL ── -->
		<div class="xen-ai-card">
			<h2 class="xen-ai-card-title">🔗 Add a URL</h2>
			<p class="xen-ai-muted">XEN A.I will fetch and index the page content.</p>

			<div class="xen-ai-form-row">
				<input type="url" id="xen-url-input" class="xen-ai-input-field"
				       placeholder="https://example.com/about" style="flex:1;">
				<button type="button" id="xen-add-url-btn" class="xen-ai-btn xen-ai-btn-primary">Fetch &amp; Add</button>
			</div>
			<div id="xen-url-status" class="xen-ai-muted" style="margin-top:8px;"></div>
		</div>

	</div>

	<!-- ── Entries table ── -->
	<div class="xen-ai-card xen-ai-mt">
		<div class="xen-ai-card-header-row">
			<h2 class="xen-ai-card-title">Stored Entries <span class="xen-ai-badge"><?php echo count( $entries ); ?></span></h2>
		</div>

		<?php if ( empty( $entries ) ) : ?>
			<p class="xen-ai-muted xen-ai-empty-msg">No knowledge base entries yet. Upload a file or add a URL above.</p>
		<?php else : ?>
		<table class="xen-ai-table" id="xen-kb-table">
			<thead>
				<tr>
					<th>#</th>
					<th>Title</th>
					<th>Type</th>
					<th>Source</th>
					<th>Added</th>
					<th>Action</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $entries as $i => $entry ) : ?>
				<tr id="xen-kb-row-<?php echo esc_attr( $entry->id ); ?>">
					<td><?php echo esc_html( $i + 1 ); ?></td>
					<td><?php echo esc_html( $entry->title ); ?></td>
					<td>
						<span class="xen-ai-badge xen-ai-badge-<?php echo esc_attr( $entry->file_type ?? 'url' ); ?>">
							<?php echo esc_html( strtoupper( $entry->file_type ?? 'URL' ) ); ?>
						</span>
					</td>
					<td class="xen-ai-muted" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
						<?php if ( $entry->source ) : ?>
							<a href="<?php echo esc_url( $entry->source ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $entry->source ); ?></a>
						<?php else : ?>
							<em>—</em>
						<?php endif; ?>
					</td>
					<td class="xen-ai-muted"><?php echo esc_html( wp_date( 'M j, Y', strtotime( $entry->created_at ) ) ); ?></td>
					<td>
						<button class="xen-ai-btn xen-ai-btn-danger xen-ai-btn-sm xen-kb-delete"
						        data-id="<?php echo esc_attr( $entry->id ); ?>">
							Delete
						</button>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>
	</div>

</div>
