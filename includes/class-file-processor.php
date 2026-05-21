<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles file uploads (PDF / DOCX / DOC / TXT) and URL scraping,
 * extracting plain text ready for the knowledge base.
 */
class Xen_AI_File_Processor {

	const MAX_FILE_SIZE = 10485760; // 10 MB
	const ALLOWED_EXT   = [ 'pdf', 'docx', 'doc', 'txt' ];

	// ── File Upload ───────────────────────────────────────────────────────────

	/**
	 * Validate, move, and extract text from an uploaded file.
	 *
	 * @param array $file  $_FILES entry.
	 * @return array|WP_Error  Array with keys: title, content, file_type.
	 */
	public function handle_upload( array $file ) {
		if ( empty( $file['tmp_name'] ) ) {
			return new WP_Error( 'no_file', __( 'No file was uploaded.', 'xen-ai' ) );
		}

		if ( $file['size'] > self::MAX_FILE_SIZE ) {
			return new WP_Error( 'too_large', __( 'File exceeds the 10 MB limit.', 'xen-ai' ) );
		}

		$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, self::ALLOWED_EXT, true ) ) {
			return new WP_Error(
				'bad_type',
				/* translators: %s = allowed types */
				sprintf( __( 'Unsupported file type. Allowed: %s', 'xen-ai' ), implode( ', ', array_map( 'strtoupper', self::ALLOWED_EXT ) ) )
			);
		}

		// Move to private upload directory
		$upload_dir  = xen_ai_uploads_dir();
		$safe_name   = sanitize_file_name( $file['name'] );
		$unique_name = wp_unique_filename( $upload_dir, $safe_name );
		$dest        = $upload_dir . $unique_name;

		if ( ! move_uploaded_file( $file['tmp_name'], $dest ) ) {
			return new WP_Error( 'move_failed', __( 'Could not save the uploaded file.', 'xen-ai' ) );
		}

		$text = $this->extract_text( $dest, $ext );

		if ( is_wp_error( $text ) ) {
			@unlink( $dest ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return $text;
		}

		$text = trim( $text );
		if ( empty( $text ) ) {
			@unlink( $dest ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return new WP_Error( 'no_text', __( 'No readable text could be extracted from the file.', 'xen-ai' ) );
		}

		return [
			'title'     => pathinfo( $file['name'], PATHINFO_FILENAME ),
			'content'   => $text,
			'file_type' => $ext,
		];
	}

	// ── URL Fetching ──────────────────────────────────────────────────────────

	/**
	 * Fetch a public URL and extract its text content.
	 *
	 * @param string $url
	 * @return array|WP_Error  Array with keys: title, content, url.
	 */
	public function fetch_url( $url ) {
		$url = esc_url_raw( $url );

		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return new WP_Error( 'bad_url', __( 'Please enter a valid URL.', 'xen-ai' ) );
		}

		// Block SSRF targets
		$host = (string) parse_url( $url, PHP_URL_HOST );
		if ( $this->is_private_host( $host ) ) {
			return new WP_Error( 'ssrf', __( 'Internal/private URLs are not permitted.', 'xen-ai' ) );
		}

		$response = wp_remote_get( $url, [
			'timeout'    => 15,
			'user-agent' => 'XenAI-Bot/1.0 (WordPress plugin)',
			'sslverify'  => true,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error( 'http_error', sprintf( __( 'URL returned HTTP %d.', 'xen-ai' ), $code ) );
		}

		$html = wp_remote_retrieve_body( $response );

		// Extract title
		$title = $url;
		if ( preg_match( '/<title[^>]*>([^<]+)<\/title>/is', $html, $m ) ) {
			$title = trim( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ) );
		}

		// Strip noise and convert block elements to newlines
		$html = preg_replace( '/<script[^>]*>[\s\S]*?<\/script>/i',  '', $html );
		$html = preg_replace( '/<style[^>]*>[\s\S]*?<\/style>/i',    '', $html );
		$html = preg_replace( '/<nav[^>]*>[\s\S]*?<\/nav>/i',        '', $html );
		$html = preg_replace( '/<header[^>]*>[\s\S]*?<\/header>/i',  '', $html );
		$html = preg_replace( '/<footer[^>]*>[\s\S]*?<\/footer>/i',  '', $html );
		$html = str_replace( [ '</p>', '</h1>', '</h2>', '</h3>', '</h4>', '</li>', '</tr>', '</div>' ], "\n", $html );
		$text = wp_strip_all_tags( $html );

		$text = trim( $this->clean_text( $text ) );
		if ( empty( $text ) ) {
			return new WP_Error( 'no_text', __( 'No readable text found at that URL.', 'xen-ai' ) );
		}

		return [
			'title'   => $title,
			'content' => $text,
			'url'     => $url,
		];
	}

	// ── Text Extraction ───────────────────────────────────────────────────────

	private function extract_text( $path, $ext ) {
		switch ( $ext ) {
			case 'pdf':  return $this->extract_pdf( $path );
			case 'docx': return $this->extract_docx( $path );
			case 'doc':  return $this->extract_doc( $path );
			case 'txt':  return $this->extract_txt( $path );
			default:     return new WP_Error( 'unsupported', __( 'Unsupported format.', 'xen-ai' ) );
		}
	}

	/**
	 * Basic PDF text extraction without external dependencies.
	 * Works well for uncompressed / lightly compressed PDFs.
	 */
	private function extract_pdf( $path ) {
		$data = file_get_contents( $path );
		if ( false === $data ) {
			return new WP_Error( 'read_err', __( 'Cannot read PDF.', 'xen-ai' ) );
		}

		$text = '';

		// Strategy 1: BT … ET blocks with Tj / TJ operators
		if ( preg_match_all( '/BT[\s\S]*?ET/m', $data, $blocks ) ) {
			foreach ( $blocks[0] as $block ) {
				// (string) Tj
				if ( preg_match_all( '/\(([^)]*(?:\\\\.)*[^)]*)\)\s*Tj/s', $block, $tj ) ) {
					foreach ( $tj[1] as $s ) {
						$text .= $this->pdf_decode( $s ) . ' ';
					}
				}
				// [(array)] TJ
				if ( preg_match_all( '/\[([^\]]+)\]\s*TJ/s', $block, $tj ) ) {
					foreach ( $tj[1] as $s ) {
						preg_match_all( '/\(([^)]*)\)/', $s, $parts );
						foreach ( $parts[1] as $p ) {
							$text .= $this->pdf_decode( $p ) . ' ';
						}
					}
				}
			}
		}

		// Strategy 2: fallback — printable ASCII runs ≥ 5 chars
		if ( mb_strlen( trim( $text ) ) < 50 ) {
			$text    = '';
			$current = '';
			$len     = strlen( $data );
			for ( $i = 0; $i < $len; $i++ ) {
				$o = ord( $data[ $i ] );
				if ( $o >= 32 && $o <= 126 ) {
					$current .= $data[ $i ];
				} else {
					if ( strlen( $current ) >= 5 ) {
						$text .= $current . "\n";
					}
					$current = '';
				}
			}
			if ( strlen( $current ) >= 5 ) {
				$text .= $current;
			}
		}

		return $this->clean_text( $text );
	}

	private function pdf_decode( $s ) {
		return str_replace(
			[ '\\n', '\\r', '\\t', '\\(', '\\)', '\\\\' ],
			[ "\n",  "\r",  "\t",  '(',   ')',   '\\'   ],
			$s
		);
	}

	private function extract_docx( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'no_zip', __( 'ZipArchive PHP extension is required to process DOCX files.', 'xen-ai' ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return new WP_Error( 'zip_err', __( 'Cannot open DOCX file.', 'xen-ai' ) );
		}

		$xml = $zip->getFromName( 'word/document.xml' );
		$zip->close();

		if ( false === $xml ) {
			return new WP_Error( 'no_xml', __( 'Cannot find document body in DOCX.', 'xen-ai' ) );
		}

		$text = strip_tags(
			str_replace(
				[ '</w:p>', '</w:tr>', '<w:br/>', '<w:br />' ],
				[ "\n",     "\n",      "\n",      "\n"        ],
				$xml
			)
		);

		return $this->clean_text( $text );
	}

	private function extract_doc( $path ) {
		$data = file_get_contents( $path );
		if ( false === $data ) {
			return new WP_Error( 'read_err', __( 'Cannot read DOC file.', 'xen-ai' ) );
		}

		// Extract printable runs from binary
		$text    = '';
		$current = '';
		$len     = strlen( $data );

		for ( $i = 0; $i < $len; $i++ ) {
			$o = ord( $data[ $i ] );
			if ( ( $o >= 32 && $o <= 126 ) || $o === 10 || $o === 13 ) {
				$current .= $data[ $i ];
			} else {
				if ( strlen( $current ) > 5 ) {
					$text .= $current . ' ';
				}
				$current = '';
			}
		}

		return $this->clean_text( $text );
	}

	private function extract_txt( $path ) {
		$data = file_get_contents( $path );
		if ( false === $data ) {
			return new WP_Error( 'read_err', __( 'Cannot read text file.', 'xen-ai' ) );
		}
		return $this->clean_text( $data );
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private function clean_text( $text ) {
		$text = preg_replace( '/[^\x09\x0A\x0D\x20-\x7E]/', ' ', $text );
		$text = preg_replace( '/[ \t]+/',  ' ',    $text );
		$text = preg_replace( '/\n{3,}/',  "\n\n", $text );
		return trim( $text );
	}

	private function is_private_host( $host ) {
		if ( in_array( $host, [ 'localhost', '127.0.0.1', '::1', '' ], true ) ) {
			return true;
		}
		// RFC-1918 ranges
		return (bool) preg_match( '/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)/', $host );
	}
}
