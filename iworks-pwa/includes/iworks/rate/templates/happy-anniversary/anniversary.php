<?php
defined( 'ABSPATH' ) || exit; // Exit if accessed directly
/**
 * Notice displayed in admin panel.
 */
?>
<div class="notice notice-success is-dismissible notice-iworks-rate"
	data-action="hide-anniversary"
	data-slug="<?php echo esc_attr( $args['slug'] ); ?>"
	data-id="<?php echo esc_attr( $args['plugin_id'] ); ?>"
	data-ajax-url="<?php echo esc_url( $args['ajax_url'] ); ?>"
	data-nonce="<?php echo esc_attr( wp_create_nonce( 'iworks-rate' ) ); ?>"
>
	<div class="<?php echo esc_attr( implode( ' ', $args['classes'] ) ); ?>">
		<h4>
		<?php
		if ( ! empty( $args['logo'] ) ) {
			printf( '<span class="iworks-rate-logo" style="background-image:url(%s)"></span>', esc_url( $args['logo'] ) ); }
		?>
			<span><?php printf( '<strong>%s</strong>', esc_html( $args['title'] ) ); ?></span></h4>
		<p style="margin-top: 1em; font-size: 1.5em;"><strong><?php esc_html_e( '🎉 Happy Anniversary! 🎉', 'iworks-pwa' ); ?></strong></p>
<?php
echo wpautop( esc_html__( 'Wow, it’s already been a whole year since you’ve been using our plugin—thank you so much for being part of our journey! We truly appreciate your support and hope the plugin has made your work easier and more enjoyable.', 'iworks-pwa' ) );
echo wpautop( esc_html__( 'If you’ve found our plugin helpful and would like to support its continued development, we’d be incredibly grateful if you’d consider buying us a coffee. Every little bit helps us keep improving and adding new features for awesome users like you!', 'iworks-pwa' ) );
echo wpautop( '<a class="button" href="' . esc_url( add_query_arg( 'utm_campaign', 'happy-anniversary', $args['donate_url'] ) ) . '">' . esc_html__( '☕ Buy Me a Coffee', 'iworks-pwa' ) . '</a>' );
echo wpautop( esc_html__( 'Thank you for your support and for making this community amazing!', 'iworks-pwa' ) );
echo wpautop(
	sprintf(
		'%s<br>%s<br>%s',
		esc_html__( 'With gratitude', 'iworks-pwa' ),
		sprintf(
			'<a target="_blank" href="%s">%s</a>',
			esc_url( 'https://profiles.wordpress.org/iworks/' ),
			esc_html__( 'Marcin Pietrzak', 'iworks-pwa' )
		),
		esc_html__( 'Plugin Author', 'iworks-pwa' )
	)
);
?>
</div>
</div>
