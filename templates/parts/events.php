<?php
/**
 * Upcoming events list. Shared by both layouts. Variables: $s (settings), $d (built data).
 *
 * @package ChurchLinks
 */

defined( 'ABSPATH' ) || exit;

$chs_time_fmt = get_option( 'time_format', 'g:i a' );
?>
	<?php if ( $d['events'] ) : ?>
	<ul class="rows">
		<?php
		foreach ( $d['events'] as $chs_ev ) :
			$chs_meta = $chs_ev['all_day'] ? wp_date( 'l', $chs_ev['start'] ) . ' · ' . __( 'All day', 'comms-church-links' ) : wp_date( 'l, ' . $chs_time_fmt, $chs_ev['start'] );
			if ( $chs_ev['location'] ) {
				$chs_meta .= ' · ' . $chs_ev['location'];
			}
			$chs_tag = $chs_ev['url'] ? 'a' : 'div';
			?>
		<li>
			<<?php echo esc_html( $chs_tag ); ?> class="ev"<?php echo $chs_ev['url'] ? ' href="' . esc_url( $chs_ev['url'] ) . '" target="_blank" rel="noopener"' . ( CHS_Frontend::is_cc( $chs_ev['url'] ) ? ' data-cc' : '' ) : ''; ?>>
				<span class="datebox"><span><?php echo esc_html( wp_date( 'M', $chs_ev['start'] ) ); ?></span><b><?php echo esc_html( wp_date( 'j', $chs_ev['start'] ) ); ?></b></span>
				<span class="ev-text"><span class="ev-title"><?php echo esc_html( $chs_ev['title'] ); ?></span><span class="ev-meta"><?php echo esc_html( $chs_meta ); ?></span></span>
			</<?php echo esc_html( $chs_tag ); ?>>
		</li>
		<?php endforeach; ?>
	</ul>
	<?php else : ?>
	<div class="card empty"><strong><?php esc_html_e( 'Nothing on the calendar right now', 'comms-church-links' ); ?></strong><span class="muted"><?php esc_html_e( 'Check back soon for upcoming events.', 'comms-church-links' ); ?></span></div>
	<?php endif; ?>
	<?php if ( $d['cc'] ) : ?>
		<a class="btn secondary" href="<?php echo esc_url( $d['cc'] . '/calendar' ); ?>" target="_blank" rel="noopener" data-cc><?php esc_html_e( 'See the full calendar', 'comms-church-links' ); ?></a>
	<?php endif; ?>
