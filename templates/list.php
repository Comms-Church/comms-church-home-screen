<?php
/**
 * The link-list layout (the default). Variables: $s (settings), $d (built data), $embed (bool).
 *
 * @package ChurchLinks
 */

defined( 'ABSPATH' ) || exit;

$chs_desc     = $s['location'] ? sprintf( '%s · %s', $s['church_name'], $s['location'] ) : $s['church_name'];
$chs_next_ev  = $d['events'] ? $d['events'][0] : null;
$chs_time_fmt = get_option( 'time_format', 'g:i a' );
$chs_fonts    = apply_filters( 'chs_use_google_fonts', true );
$chs_panels   = array(
	'visit'   => __( "We'd love to meet you", 'comms-church-links' ),
	'events'  => __( 'Coming up', 'comms-church-links' ),
	'contact' => __( 'Get in touch', 'comms-church-links' ),
);
$chs_config   = array(
	'services' => array_values( $s['services'] ),
	'weekdays' => array_values( $d['weekdays'] ),
	'ccModal'  => (bool) $s['cc_modal'] && $d['cc'],
	'url'      => CHS_Frontend::url(),
	'qrLib'    => CHS_URL . 'assets/vendor/qrcode.js?ver=1.4.4',
	'i18n'     => array(
		'today'    => __( 'Today', 'comms-church-links' ),
		'tomorrow' => __( 'Tomorrow', 'comms-church-links' ),
		'copied'   => __( 'Copied', 'comms-church-links' ),
		'selected' => __( 'Selected. Copy it from here.', 'comms-church-links' ),
	),
);
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?php echo esc_html( $s['church_name'] ); ?></title>
<meta name="description" content="<?php echo esc_attr( $chs_desc ); ?>">
<link rel="canonical" href="<?php echo esc_url( CHS_Frontend::url() ); ?>">
<meta property="og:title" content="<?php echo esc_attr( $s['church_name'] ); ?>">
<meta property="og:description" content="<?php echo esc_attr( $chs_desc ); ?>">
<meta property="og:url" content="<?php echo esc_url( CHS_Frontend::url() ); ?>">
<meta property="og:type" content="website">
<meta name="theme-color" content="<?php echo esc_attr( $s['brand'] ); ?>">
<meta name="apple-mobile-web-app-title" content="<?php echo esc_attr( $d['short'] ); ?>">
<?php if ( $d['icon_url'] ) : ?>
<link rel="icon" href="<?php echo esc_url( $d['icon_url'] ); ?>">
<link rel="apple-touch-icon" href="<?php echo esc_url( $d['icon_url'] ); ?>">
<?php endif; ?>
<?php if ( $chs_fonts ) : ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:wght@400;700&family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,600&display=swap">
<?php endif; ?>
<link rel="stylesheet" href="<?php echo esc_url( CHS_URL . 'assets/list.css?ver=' . CHS_VERSION ); ?>">
<style>:root{--brand:<?php echo esc_html( $s['brand'] ); ?>;--brand-ink:<?php echo esc_html( chs_glyph_color( $s['brand'] ) ); ?>}</style>
</head>
<body class="cl<?php echo $embed ? ' is-embed' : ''; ?>">
<main class="cl-page">
	<header class="cl-head">
		<span class="cl-mark" aria-hidden="true">
			<?php if ( $d['icon_url'] ) : ?>
				<img src="<?php echo esc_url( $d['icon_url'] ); ?>" alt="">
			<?php else : ?>
				<?php echo chs_icon_svg( 'church' ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed icon library. ?>
			<?php endif; ?>
		</span>
		<h1 class="cl-name"><?php echo esc_html( $s['church_name'] ); ?></h1>
		<?php if ( $s['location'] ) : ?>
			<p class="cl-where"><?php echo esc_html( $s['location'] ); ?></p>
		<?php endif; ?>
	</header>

	<?php if ( $s['services'] || $chs_next_ev ) : ?>
	<div class="cl-now">
		<?php if ( $s['services'] ) : ?>
		<a class="cl-next" href="#cl-visit" data-panel="visit">
			<span class="cl-eyebrow"><?php esc_html_e( 'Next service', 'comms-church-links' ); ?> · <span data-svc="when"></span></span>
			<span class="cl-big" data-svc="time"></span>
			<span class="cl-sub" data-svc="sub"></span>
		</a>
		<?php endif; ?>
		<?php if ( $chs_next_ev ) : ?>
		<<?php echo $chs_next_ev['url'] ? 'a href="' . esc_url( $chs_next_ev['url'] ) . '" target="_blank" rel="noopener"' . ( CHS_Frontend::is_cc( $chs_next_ev['url'] ) ? ' data-cc' : '' ) : 'div'; ?> class="cl-next is-event">
			<span class="cl-eyebrow"><?php esc_html_e( 'Coming up', 'comms-church-links' ); ?> · <?php echo esc_html( wp_date( 'M j', $chs_next_ev['start'] ) ); ?></span>
			<span class="cl-title"><?php echo esc_html( $chs_next_ev['title'] ); ?></span>
			<span class="cl-sub"><?php echo esc_html( $chs_next_ev['all_day'] ? wp_date( 'l', $chs_next_ev['start'] ) : wp_date( 'l, ' . $chs_time_fmt, $chs_next_ev['start'] ) ); ?></span>
		</<?php echo $chs_next_ev['url'] ? 'a' : 'div'; ?>>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php if ( $s['note_body'] ) : ?>
		<p class="cl-note"><?php echo esc_html( $s['note_body'] ); ?></p>
	<?php endif; ?>

	<nav class="cl-links" aria-label="<?php esc_attr_e( 'Church links', 'comms-church-links' ); ?>">
		<?php
		foreach ( $d['all'] as $chs_app ) :
			$chs_style = sprintf( '--c:%s;--g:%s', $chs_app['color'], $chs_app['glyph'] );
			ob_start();
			?>
			<span class="tile<?php echo 'today' === $chs_app['icon'] ? ' cal' : ''; ?>" style="<?php echo esc_attr( $chs_style ); ?>" aria-hidden="true">
				<?php if ( 'today' === $chs_app['icon'] ) : ?>
					<span class="cal-dow" data-dow></span><span class="cal-day" data-day></span>
				<?php else : ?>
					<?php echo chs_icon_svg( $chs_app['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed icon library. ?>
				<?php endif; ?>
			</span>
			<span class="cl-text">
				<span class="cl-label"><?php echo esc_html( $chs_app['label'] ); ?></span>
				<?php if ( $chs_app['sub'] ) : ?>
					<span class="cl-detail"><?php echo esc_html( $chs_app['sub'] ); ?></span>
				<?php endif; ?>
			</span>
			<?php if ( $chs_app['new'] ) : ?>
				<span class="cl-new"><?php esc_html_e( 'New', 'comms-church-links' ); ?></span>
			<?php endif; ?>
			<?php
			$chs_inner = ob_get_clean();
			if ( $chs_app['sheet'] && isset( $chs_panels[ $chs_app['sheet'] ] ) ) :
				?>
				<details class="cl-panel" id="cl-<?php echo esc_attr( $chs_app['sheet'] ); ?>">
					<summary class="cl-link"><?php echo $chs_inner; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?><span class="cl-arrow" aria-hidden="true"></span></summary>
					<div class="cl-panel-body">
						<h2 class="cl-panel-title"><?php echo esc_html( $chs_panels[ $chs_app['sheet'] ] ); ?></h2>
						<?php include CHS_DIR . 'templates/parts/' . $chs_app['sheet'] . '.php'; ?>
					</div>
				</details>
			<?php else : ?>
				<a class="cl-link" href="<?php echo esc_url( $chs_app['href'] ); ?>" target="_blank" rel="noopener"<?php echo $chs_app['cc'] ? ' data-cc' : ''; ?><?php echo $chs_app['ios'] ? ' data-ios="' . esc_url( $chs_app['ios'] ) . '" data-android="' . esc_url( $chs_app['android'] ) . '"' : ''; ?>><?php echo $chs_inner; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?><span class="cl-arrow" aria-hidden="true"></span></a>
			<?php endif; ?>
		<?php endforeach; ?>
	</nav>

	<?php if ( $s['verse_on'] && $s['verse_text'] ) : ?>
	<figure class="cl-verse">
		<blockquote><?php echo esc_html( $s['verse_text'] ); ?></blockquote>
		<figcaption><?php echo esc_html( $s['verse_ref'] ); ?></figcaption>
	</figure>
	<?php endif; ?>

	<?php if ( ! $embed ) : ?>
	<aside class="cl-qr" hidden>
		<div class="cl-qr-code" id="clQr" role="img" aria-label="<?php esc_attr_e( 'QR code for this page', 'comms-church-links' ); ?>"></div>
		<p><?php esc_html_e( 'Scan to open this on your phone', 'comms-church-links' ); ?></p>
	</aside>
	<?php endif; ?>

	<footer class="cl-foot"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></a></footer>
</main>
<div class="toast" id="toast" role="status" aria-live="polite"></div>

<script>window.CHS = <?php echo wp_json_encode( $chs_config ); ?>;</script>
<script src="<?php echo esc_url( CHS_URL . 'assets/list.js?ver=' . CHS_VERSION ); ?>"></script>
</body>
</html>
