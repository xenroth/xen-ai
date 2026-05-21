<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the knowledge-base: CRUD + keyword-based context retrieval.
 */
class Xen_AI_Knowledge_Base {

	private $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'xen_ai_knowledge';
	}

	// ── Write ─────────────────────────────────────────────────────────────────

	public function add_entry( $title, $content, $source_type = 'file', $source = null, $file_type = null ) {
		global $wpdb;

		return $wpdb->insert(
			$this->table,
			[
				'title'       => sanitize_text_field( $title ),
				'content'     => $content,
				'source_type' => sanitize_text_field( $source_type ),
				'source'      => $source ? esc_url_raw( $source ) : null,
				'file_type'   => $file_type ? sanitize_text_field( $file_type ) : null,
				'status'      => 'active',
			],
			[ '%s', '%s', '%s', '%s', '%s', '%s' ]
		);
	}

	public function delete_entry( $id ) {
		global $wpdb;
		return $wpdb->delete( $this->table, [ 'id' => absint( $id ) ], [ '%d' ] );
	}

	// ── Read ──────────────────────────────────────────────────────────────────

	public function get_all( $status = 'active' ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, title, source_type, file_type, source, created_at
				 FROM {$this->table}
				 WHERE status = %s
				 ORDER BY created_at DESC",
				$status
			)
		);
	}

	public function get_entry( $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", absint( $id ) )
		);
	}

	// ── Search ────────────────────────────────────────────────────────────────

	/**
	 * Keyword search over title + content.
	 *
	 * @param string $query
	 * @param int    $limit
	 * @return array
	 */
	public function search( $query, $limit = 5 ) {
		global $wpdb;

		if ( empty( trim( $query ) ) ) {
			return $this->get_recent( $limit );
		}

		$keywords = $this->extract_keywords( $query );

		if ( empty( $keywords ) ) {
			return $this->get_recent( $limit );
		}

		$conditions = [];
		$values     = [];

		foreach ( $keywords as $kw ) {
			if ( mb_strlen( $kw ) < 3 ) {
				continue;
			}
			$like           = '%' . $wpdb->esc_like( $kw ) . '%';
			$conditions[]   = '(content LIKE %s OR title LIKE %s)';
			$values[]       = $like;
			$values[]       = $like;
		}

		if ( empty( $conditions ) ) {
			return $this->get_recent( $limit );
		}

		$values[] = $limit;
		$where    = implode( ' OR ', $conditions );
		$sql      = "SELECT id, title, content FROM {$this->table}
		             WHERE status = 'active' AND ({$where}) LIMIT %d";

		return $wpdb->get_results( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Build a string of KB context suitable for injection into a system prompt.
	 *
	 * @param string $query
	 * @param int    $max_chars  Approximate character budget.
	 * @return string
	 */
	public function get_context_for_query( $query, $max_chars = 3000 ) {
		$rows = $this->search( $query );

		if ( empty( $rows ) ) {
			return '';
		}

		$context     = '';
		$total_chars = 0;

		foreach ( $rows as $row ) {
			$chunk     = "--- {$row->title} ---\n{$row->content}\n\n";
			$chunk_len = mb_strlen( $chunk );

			if ( $total_chars + $chunk_len > $max_chars ) {
				$remaining = $max_chars - $total_chars;
				if ( $remaining > 100 ) {
					$context .= mb_substr( $chunk, 0, $remaining ) . "…\n\n";
				}
				break;
			}

			$context     .= $chunk;
			$total_chars += $chunk_len;
		}

		return $context;
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private function get_recent( $limit ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, title, content FROM {$this->table}
				 WHERE status = 'active'
				 ORDER BY id DESC LIMIT %d",
				$limit
			)
		);
	}

	private function extract_keywords( $text ) {
		$stop = [
			'the','a','an','and','or','but','in','on','at','to','for','of','with',
			'by','from','is','are','was','were','be','been','have','has','had',
			'do','does','did','will','would','could','should','may','might',
			'what','how','when','where','who','which','that','this','these',
			'those','i','you','we','they','my','your','our','their','me','him',
			'her','us','them','not','no','can','just','about','up','out','if',
			'then','so','its','it','tell','me','know','need','want','please',
		];

		$text     = strtolower( wp_strip_all_tags( $text ) );
		$words    = preg_split( '/[\s\W]+/', $text, -1, PREG_SPLIT_NO_EMPTY );
		$keywords = [];

		foreach ( $words as $word ) {
			if ( mb_strlen( $word ) > 2 && ! in_array( $word, $stop, true ) ) {
				$keywords[ $word ] = true;
			}
		}

		return array_keys( $keywords );
	}
}
