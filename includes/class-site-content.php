<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pulls live site content into the AI context at query time:
 *   - Published pages & blog posts (keyword-matched)
 *   - WooCommerce products with price, stock, URL, and ordering instructions
 *
 * Results are cached with transients (30 min) to avoid repeated DB hits.
 */
class Xen_AI_Site_Content {

	const CACHE_TTL = 1800; // seconds (30 minutes)

	/**
	 * Build a context string from live site content relevant to the user's query.
	 *
	 * @param  string $query     The user's chat message.
	 * @param  int    $max_chars Approximate character budget.
	 * @return string
	 */
	public function get_context_for_query( $query, $max_chars = 2500 ) {
		$parts = [];

		$pages_ctx = $this->get_pages_and_posts_context( $query );
		if ( $pages_ctx ) {
			$parts[] = $pages_ctx;
		}

		if ( $this->woocommerce_active() ) {
			$products_ctx = $this->get_products_context( $query );
			if ( $products_ctx ) {
				$parts[] = $products_ctx;
			}
		}

		if ( empty( $parts ) ) {
			return '';
		}

		$combined = implode( "\n\n", $parts );
		if ( mb_strlen( $combined ) > $max_chars ) {
			$combined = mb_substr( $combined, 0, $max_chars ) . '…';
		}

		return $combined;
	}

	/** Returns true if WooCommerce is installed and active. */
	public function woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	private function get_pages_and_posts_context( $query ) {
		$cache_key = 'xen_sc_pp_' . md5( $query );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$keywords = $this->extract_keywords( $query );
		$search   = implode( ' ', array_slice( $keywords, 0, 4 ) );

		$args = [
			'post_type'      => [ 'page', 'post' ],
			'post_status'    => 'publish',
			'posts_per_page' => 5,
			'no_found_rows'  => true,
		];

		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		} else {
			$args['orderby']        = 'date';
			$args['order']          = 'DESC';
			$args['posts_per_page'] = 3;
		}

		$q   = new WP_Query( $args );
		$out = '';

		if ( $q->have_posts() ) {
			$out .= "### Site Content (Pages & Blog Posts)\n";
			while ( $q->have_posts() ) {
				$q->the_post();
				$type    = 'page' === get_post_type() ? 'Page' : 'Blog Post';
				$title   = get_the_title();
				$url     = get_permalink();
				$excerpt = has_excerpt()
					? get_the_excerpt()
					: wp_trim_words( strip_tags( get_the_content() ), 45 );

				$out .= "**{$type}: {$title}**\n";
				$out .= "URL: {$url}\n";
				if ( $excerpt ) {
					$out .= "{$excerpt}\n";
				}
				$out .= "\n";
			}
			wp_reset_postdata();
		}

		set_transient( $cache_key, $out, self::CACHE_TTL );
		return $out;
	}

	private function get_products_context( $query ) {
		$cache_key = 'xen_sc_wc_' . md5( $query );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$args = [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 8,
			'no_found_rows'  => true,
		];

		if ( ! empty( trim( $query ) ) ) {
			$args['s'] = $query;
		} else {
			$args['orderby'] = 'date';
			$args['order']   = 'DESC';
		}

		$q   = new WP_Query( $args );
		$out = '';

		if ( $q->have_posts() ) {
			$cart_url     = function_exists( 'wc_get_cart_url' )     ? wc_get_cart_url()     : '';
			$checkout_url = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '';

			$out .= "### Store Products\n";

			while ( $q->have_posts() ) {
				$q->the_post();
				$product = function_exists( 'wc_get_product' ) ? wc_get_product( get_the_ID() ) : null;
				if ( ! $product ) {
					continue;
				}

				$name   = $product->get_name();
				$url    = get_permalink();
				$price  = strip_tags( $product->get_price_html() );
				$stock  = $product->is_in_stock() ? 'In Stock' : 'Out of Stock';
				$short  = wp_trim_words(
					strip_tags( $product->get_short_description() ?: $product->get_description() ),
					40
				);

				$out .= "**Product: {$name}**\n";
				$out .= "Price: {$price} | Availability: {$stock}\n";
				$out .= "Link: {$url}\n";
				if ( $short ) {
					$out .= "Description: {$short}\n";
				}
				$out .= "\n";
			}
			wp_reset_postdata();

			// Append store-wide ordering instructions once
			$out .= "**How to Order:**\n";
			$out .= "1. Open the product link and click 'Add to Cart'.\n";
			if ( $cart_url ) {
				$out .= "2. View your cart: {$cart_url}\n";
			}
			if ( $checkout_url ) {
				$out .= "3. Complete your purchase at checkout: {$checkout_url}\n";
			}
			$out .= "\n";
		}

		set_transient( $cache_key, $out, self::CACHE_TTL );
		return $out;
	}

	private function extract_keywords( $text ) {
		$stop = [
			'the','a','an','and','or','but','in','on','at','to','for','of','with',
			'by','from','is','are','was','were','be','been','have','has','had',
			'do','does','did','will','would','could','should','may','might',
			'what','how','when','where','who','which','that','this','these',
			'those','i','you','we','they','my','your','our','their','me','him',
			'her','us','them','not','no','can','just','about','up','out','if',
		];

		$words    = preg_split( '/\W+/u', mb_strtolower( $text ), -1, PREG_SPLIT_NO_EMPTY );
		$keywords = array_diff( $words, $stop );
		$keywords = array_values( array_filter( $keywords, function ( $w ) {
			return mb_strlen( $w ) > 2;
		} ) );

		return array_unique( $keywords );
	}
}
