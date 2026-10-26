<?php
/**
 * Plan-a-visit panel: service times, where, directions, what to expect. Shared by both layouts. Variables: $s (settings), $d (built data).
 *
 * @package ChurchLinks
 */

defined( 'ABSPATH' ) || exit;
?>
	<?php if ( $s['services'] ) : ?>
	<div class="card">
		<span class="label"><?php echo esc_html( _n( 'Service', 'Services', count( $s['services'] ), 'comms-church-links' ) ); ?></span>
		<ul class="services">
			<?php foreach ( $s['services'] as $chs_svc ) : ?>
			<li><span class="svc-day"><?php echo esc_html( $d['weekdays'][ (int) $chs_svc['day'] ] ); ?></span><span class="svc-time"><?php echo esc_html( $chs_svc['time'] ); ?></span><span class="svc-name"><?php echo esc_html( $chs_svc['label'] ); ?></span></li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php endif; ?>
	<?php if ( $s['address'] || $s['location'] ) : ?>
	<div class="card">
		<span class="label"><?php esc_html_e( 'Where', 'comms-church-links' ); ?></span>
		<strong><?php echo esc_html( $s['church_name'] ); ?></strong>
		<span class="muted"><?php echo esc_html( $s['address'] ? $s['address'] : $s['location'] ); ?></span>
	</div>
	<?php endif; ?>
	<?php if ( $d['maps'] ) : ?>
		<a class="btn<?php echo $s['visit_url'] ? ' secondary' : ''; ?>" href="<?php echo esc_url( $d['maps'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Get directions', 'comms-church-links' ); ?></a>
	<?php endif; ?>
	<?php if ( $s['visit_url'] ) : ?>
		<a class="btn" href="<?php echo esc_url( $s['visit_url'] ); ?>" target="_blank" rel="noopener"<?php echo $d['visit_cc'] ? ' data-cc' : ''; ?>><?php esc_html_e( 'Plan your visit', 'comms-church-links' ); ?></a>
	<?php endif; ?>
	<?php if ( $d['expect'] ) : ?>
	<div class="card">
		<span class="label"><?php esc_html_e( 'What to expect', 'comms-church-links' ); ?></span>
		<ul class="expect">
			<?php foreach ( $d['expect'] as $chs_line ) : ?>
				<li><?php echo esc_html( $chs_line ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php endif; ?>
