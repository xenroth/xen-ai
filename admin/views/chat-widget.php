<?php
/**
 * Front-end chat widget HTML.
 * Injected into wp_footer — JS/CSS are enqueued separately.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$settings    = get_option( 'xen_ai_settings', [] );
$bot_name    = ! empty( $settings['bot_name'] )     ? $settings['bot_name']    : 'XEN A.I';
$bot_logo    = ! empty( $settings['bot_logo_url'] ) ? $settings['bot_logo_url'] : '';

/**
 * Helper: render the avatar element — <img> when a logo is set, emoji span otherwise.
 * $size:  'sm' (30 px message bubbles) | 'md' (38 px header) | 'lg' (42 px toggle button)
 */
$xen_avatar = function( $size, $bot_name, $bot_logo ) {
	$cls = 'xen-ai-win-avatar';
	if ( 'sm' === $size ) $cls = 'xen-ai-msg-avatar';
	if ( 'lg' === $size ) $cls = 'xen-ai-toggle-avatar';

	if ( $bot_logo ) {
		echo '<span class="' . esc_attr( $cls ) . ' xen-avatar-img" aria-hidden="true">';
		echo '<img src="' . esc_url( $bot_logo ) . '" alt="' . esc_attr( $bot_name ) . '" loading="lazy">';
		echo '</span>';
	} else {
		echo '<span class="' . esc_attr( $cls ) . ' xen-avatar-emoji" aria-hidden="true">&#x26A1;</span>';
	}
};
?>
<div id="xen-ai-widget"
     class="xen-ai-widget"
     role="complementary"
     aria-label="<?php echo esc_attr( $bot_name ); ?> chat assistant">

	<!-- Notification bubble -->
	<div id="xen-ai-notification" class="xen-ai-notification" role="status" aria-live="polite">
		<span id="xen-ai-notification-text"></span>
		<button class="xen-ai-notification-close" aria-label="<?php esc_attr_e( 'Dismiss notification', 'xen-ai' ); ?>">
			&#x2715;
		</button>
	</div>

	<!-- Chat window -->
	<div id="xen-ai-window"
	     class="xen-ai-window"
	     role="dialog"
	     aria-modal="true"
	     aria-label="<?php echo esc_attr( $bot_name ); ?>"
	     aria-hidden="true">

		<!-- Header -->
		<div class="xen-ai-win-header">
			<div class="xen-ai-win-identity">
				<span class="xen-ai-win-avatar" aria-hidden="true">⚡</span>
				<div>
					<strong><?php echo esc_html( $bot_name ); ?></strong>
					<span class="xen-ai-win-status">
						<span class="xen-ai-online-dot"></span>
						<?php esc_html_e( 'Online', 'xen-ai' ); ?>
					</span>
				</div>
			</div>
			<button class="xen-ai-win-close" aria-label="<?php esc_attr_e( 'Close chat', 'xen-ai' ); ?>">
				&#x2715;
			</button>
		</div>

		<!-- Messages -->
		<div id="xen-ai-messages"
		     class="xen-ai-messages"
		     role="log"
		     aria-live="polite"
		     aria-label="<?php esc_attr_e( 'Conversation', 'xen-ai' ); ?>">
		</div>

		<!-- Typing indicator -->
		<div id="xen-ai-typing" class="xen-ai-typing" aria-label="<?php esc_attr_e( 'XEN A.I is typing', 'xen-ai' ); ?>" aria-hidden="true">
			<span></span><span></span><span></span>
		</div>

		<!-- Pro: Topic quick-menu chips (populated by JS when Pro license active & KB has topics) -->
		<div id="xen-ai-topics" class="xen-ai-topics" style="display:none;" aria-label="<?php esc_attr_e( 'Quick topic shortcuts', 'xen-ai' ); ?>"></div>

		<!-- Input -->
		<div class="xen-ai-input-row">
			<textarea id="xen-ai-input"
			          class="xen-ai-user-input"
			          rows="1"
			          placeholder="<?php esc_attr_e( 'Type a message…', 'xen-ai' ); ?>"
			          aria-label="<?php esc_attr_e( 'Your message', 'xen-ai' ); ?>"
			          maxlength="2000"></textarea>
			<!-- Honeypot: hidden from humans, bots tend to fill it. -->
			<input type="text" id="xen-ai-hp" name="xen_hp" value=""
			       tabindex="-1" autocomplete="off" aria-hidden="true"
			       style="position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;opacity:0;pointer-events:none;">
			<?php
			$ts_key = ! empty( $settings['turnstile_site_key'] ) ? $settings['turnstile_site_key'] : '';
			if ( $ts_key ) :
			?>
			<div class="cf-turnstile"
			     data-sitekey="<?php echo esc_attr( $ts_key ); ?>"
			     data-size="invisible"
			     data-callback="xenAITurnstileCallback"
			     id="xen-ai-turnstile"></div>
			<?php endif; ?>
			<button id="xen-ai-send"
			        class="xen-ai-send-btn"
			        aria-label="<?php esc_attr_e( 'Send message', 'xen-ai' ); ?>">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18" aria-hidden="true">
					<path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
				</svg>
			</button>
		</div>

		<div class="xen-ai-win-footer">
			<small>Powered by <strong><?php echo esc_html( $bot_name ); ?></strong></small>
		</div>
	</div>

	<!-- Toggle button (always visible) -->
	<button id="xen-ai-toggle"
	        class="xen-ai-toggle"
	        aria-label="<?php esc_attr_e( 'Open chat', 'xen-ai' ); ?>"
	        aria-expanded="false"
	        aria-controls="xen-ai-window">
		<!-- Logo / default chat icon (open state) -->
		<span class="xen-icon-open" aria-hidden="true">
		<?php if ( $bot_logo ) : ?>
			<img class="xen-toggle-logo" src="<?php echo esc_url( $bot_logo ); ?>" alt="" loading="lazy">
		<?php else : ?>
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24">
				<path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
			</svg>
		<?php endif; ?>
		</span>
		<!-- Close icon (close state) -->
		<span class="xen-icon-close" aria-hidden="true" style="display:none;">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24">
				<path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
			</svg>
		</span>
	</button>

</div>
