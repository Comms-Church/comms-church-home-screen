<?php
/**
 * Contact panel: phone, email, address. Shared by both layouts. Variables: $s (settings), $d (built data).
 *
 * @package ChurchLinks
 */

defined( 'ABSPATH' ) || exit;
?>
	<?php if ( $s['phone'] ) : ?>
	<div class="card">
		<span class="label"><?php esc_html_e( 'Phone', 'comms-church-links' ); ?></span>
		<div class="copy-row"><span class="val"><?php echo esc_html( $s['phone'] ); ?></span><button class="copy" type="button" data-copy="<?php echo esc_attr( $s['phone'] ); ?>"><?php esc_html_e( 'Copy', 'comms-church-links' ); ?></button></div>
	</div>
	<?php endif; ?>
	<?php if ( $s['email'] ) : ?>
	<div class="card">
		<span class="label"><?php esc_html_e( 'Email', 'comms-church-links' ); ?></span>
		<div class="copy-row"><span class="val"><?php echo esc_html( $s['email'] ); ?></span><button class="copy" type="button" data-copy="<?php echo esc_attr( $s['email'] ); ?>"><?php esc_html_e( 'Copy', 'comms-church-links' ); ?></button></div>
	</div>
	<?php endif; ?>
	<div class="btn-row">
		<?php if ( $s['phone'] ) : ?>
			<a class="btn" href="<?php echo esc_url( 'tel:' . preg_replace( '/[^\d+]/', '', $s['phone'] ), array( 'tel' ) ); ?>"><?php esc_html_e( 'Call', 'comms-church-links' ); ?></a>
		<?php endif; ?>
		<?php if ( $s['email'] ) : ?>
			<a class="btn" href="<?php echo esc_url( 'mailto:' . $s['email'], array( 'mailto' ) ); ?>"><?php esc_html_e( 'Email', 'comms-church-links' ); ?></a>
		<?php endif; ?>
	</div>
	<?php if ( $s['address'] && $d['maps'] ) : ?>
	<div class="card">
		<span class="label"><?php esc_html_e( 'Address', 'comms-church-links' ); ?></span>
		<span><?php echo esc_html( $s['address'] ); ?></span>
		<a class="link" href="<?php echo esc_url( $d['maps'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open in maps', 'comms-church-links' ); ?></a>
	</div>
	<?php endif; ?>
