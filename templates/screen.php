<?php
/**
 * The home screen page. Variables: $s (settings), $d (built data), $embed (bool).
 *
 * @package ChurchLinks
 */

defined( 'ABSPATH' ) || exit;

$chs_desc     = $s['location'] ? sprintf( '%s · %s', $s['church_name'], $s['location'] ) : $s['church_name'];
$chs_next_ev  = $d['events'] ? $d['events'][0] : null;
$chs_time_fmt = get_option( 'time_format', 'g:i a' );
$chs_fonts    = apply_filters( 'chs_use_google_fonts', true );
$chs_config   = array(
	'services' => array_values( $s['services'] ),
	'weekdays' => array_values( $d['weekdays'] ),
	'ccModal'  => (bool) $s['cc_modal'] && $d['cc'],
	'i18n'     => array(
		'today'    => __( 'Today', 'comms-church-links' ),
		'tomorrow' => __( 'Tomorrow', 'comms-church-links' ),
		/* translators: 1: service name, 2: "today"/"tomorrow"/weekday, 3: time */
		'note'     => __( '%1$s %2$s at %3$s', 'comms-church-links' ),
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
<meta name="theme-color" content="#141a3d">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
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
<link rel="stylesheet" href="<?php echo esc_url( CHS_URL . 'assets/screen.css?ver=' . CHS_VERSION ); ?>">
<style>:root{--brand:<?php echo esc_html( $s['brand'] ); ?>}<?php if ( $d['wall'] ) : ?>.wall.photo{background-image:url("<?php echo esc_url( $d['wall'] ); ?>")}<?php endif; ?></style>
</head>
<body class="<?php echo $embed ? 'is-embed' : ''; ?>">
<div class="device">
<div class="screen" id="screen">

	<?php if ( 'image' === $s['wallpaper'] && $d['wall'] ) : ?>
		<div class="wall photo" aria-hidden="true"></div>
	<?php elseif ( 'brand' === $s['wallpaper'] ) : ?>
		<div class="wall brand" aria-hidden="true"></div>
	<?php else : ?>
		<div class="wall" aria-hidden="true">
			<svg viewBox="0 0 390 844" preserveAspectRatio="xMidYMid slice">
				<defs>
					<linearGradient id="chs-sky" x1="0" y1="0" x2="0" y2="1">
						<stop offset="0" stop-color="#141a3d"/><stop offset=".36" stop-color="#3a3366"/><stop offset=".58" stop-color="#a35e75"/><stop offset=".69" stop-color="#e6996b"/><stop offset=".78" stop-color="#f7c88d"/>
					</linearGradient>
					<radialGradient id="chs-glow" cx=".5" cy=".5" r=".5">
						<stop offset="0" stop-color="#fff1cf"/><stop offset=".3" stop-color="#ffd79a" stop-opacity=".85"/><stop offset="1" stop-color="#f6b077" stop-opacity="0"/>
					</radialGradient>
				</defs>
				<rect width="390" height="844" fill="url(#chs-sky)"/>
				<g fill="#fff">
					<circle cx="40" cy="84" r="1.1" opacity=".8"/><circle cx="92" cy="146" r=".8" opacity=".6"/><circle cx="152" cy="62" r="1.2" opacity=".85"/><circle cx="214" cy="126" r=".7" opacity=".55"/><circle cx="302" cy="72" r="1" opacity=".8"/><circle cx="352" cy="156" r=".8" opacity=".6"/><circle cx="62" cy="228" r=".6" opacity=".4"/><circle cx="332" cy="246" r=".7" opacity=".4"/><circle cx="252" cy="204" r=".6" opacity=".45"/>
				</g>
				<circle cx="268" cy="600" r="130" fill="url(#chs-glow)"/>
				<circle cx="268" cy="604" r="24" fill="#fff3d6"/>
				<path d="M0 640 C70 612 150 628 220 610 S340 596 390 616 V844 H0Z" fill="#6d4f72"/>
				<path d="M0 676 C80 650 160 668 240 646 S350 650 390 642 V844 H0Z" fill="#3a3555"/>
				<?php if ( $s['steeple'] ) : ?>
				<g fill="#2f2b48"><rect x="84" y="648" width="26" height="18"/><path d="M81 650 L97 637 L113 650Z"/><rect x="106" y="628" width="8" height="24"/><path d="M105 629 L110 609 L115 629Z"/><rect x="109.4" y="598" width="1.2" height="11"/><rect x="107" y="601.5" width="6" height="1.2"/></g>
				<rect x="93" y="654" width="3.5" height="6" fill="#f7c88d"/><rect x="100" y="654" width="3.5" height="6" fill="#f7c88d" opacity=".75"/>
				<?php endif; ?>
				<path d="M0 724 C110 690 210 736 310 704 S375 708 390 714 V844 H0Z" fill="#1c2236"/>
				<g fill="none" stroke="#fff" stroke-opacity=".06" stroke-width="1.2"><path d="M0 760 C120 730 220 772 390 742"/><path d="M0 790 C130 760 230 800 390 772"/><path d="M0 822 C140 792 240 830 390 804"/></g>
			</svg>
		</div>
	<?php endif; ?>
	<div class="veil"></div>

	<div class="status">
		<button class="status-time" id="statusTime" type="button" tabindex="-1" aria-label="<?php esc_attr_e( 'Lock screen', 'comms-church-links' ); ?>"></button>
		<div class="status-icons" aria-hidden="true">
			<svg viewBox="0 0 18 12"><rect x="0" y="8" width="3" height="4" rx="1"/><rect x="5" y="5.5" width="3" height="6.5" rx="1"/><rect x="10" y="3" width="3" height="9" rx="1"/><rect x="15" y="0" width="3" height="12" rx="1"/></svg>
			<svg viewBox="0 0 16 12"><path d="M8 11.5 5.6 9a3.4 3.4 0 0 1 4.8 0zM3.4 6.8a6.5 6.5 0 0 1 9.2 0l-1.5 1.5a4.4 4.4 0 0 0-6.2 0zM1 4.4a9.9 9.9 0 0 1 14 0l-1.5 1.5a7.8 7.8 0 0 0-11 0z"/></svg>
			<svg viewBox="0 0 27 12"><rect x=".5" y=".5" width="22" height="11" rx="3" fill="none" stroke="currentColor" stroke-opacity=".5"/><rect x="2" y="2" width="16" height="8" rx="1.8"/><rect x="24" y="4" width="2" height="4" rx="1" opacity=".5"/></svg>
		</div>
	</div>

	<main class="home" id="home" inert>
		<h1 class="sr-only"><?php echo esc_html( $s['church_name'] ); ?></h1>
		<div class="widgets<?php echo $s['verse_on'] ? '' : ' single'; ?>">
			<?php if ( $s['services'] ) : ?>
			<button class="widget" type="button" data-open="visit">
				<span class="w-eyebrow" data-svc="when"></span>
				<span class="w-big" data-svc="time"></span>
				<span class="w-sub" data-svc="sub"></span>
				<span class="w-foot"><?php echo esc_html( $s['location'] ? $s['location'] : $s['church_name'] ); ?></span>
			</button>
			<?php endif; ?>
			<?php if ( $s['verse_on'] && $s['verse_text'] ) : ?>
			<div class="widget verse">
				<span class="w-eyebrow"><?php echo esc_html( $s['verse_ref'] ); ?></span>
				<span class="w-verse"><?php echo esc_html( $s['verse_text'] ); ?></span>
			</div>
			<?php endif; ?>
		</div>

		<nav class="apps" aria-label="<?php esc_attr_e( 'Church links', 'comms-church-links' ); ?>">
			<?php
			foreach ( $d['grid'] as $chs_app ) {
				CHS_Frontend::app( $chs_app );
			}
			?>
		</nav>

		<?php if ( $d['dock'] ) : ?>
		<nav class="dock" aria-label="<?php esc_attr_e( 'Quick links', 'comms-church-links' ); ?>" style="--n:<?php echo (int) count( $d['dock'] ); ?>">
			<?php
			foreach ( $d['dock'] as $chs_app ) {
				CHS_Frontend::app( $chs_app );
			}
			?>
		</nav>
		<?php endif; ?>
	</main>

	<section class="lock" id="lock" aria-label="<?php esc_attr_e( 'Lock screen', 'comms-church-links' ); ?>">
		<div class="lock-inner">
			<div class="lock-date" id="lockDate"></div>
			<div class="lock-time" id="lockTime"></div>
			<div class="lock-church"><?php echo esc_html( $s['church_name'] ); ?></div>
			<div class="notes">
				<?php if ( $s['services'] ) : ?>
				<div class="note">
					<span class="note-icon" aria-hidden="true">
						<?php if ( $d['icon_url'] ) : ?>
							<img src="<?php echo esc_url( $d['icon_url'] ); ?>" alt="">
						<?php else : ?>
							<?php echo chs_icon_svg( 'church' ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed icon library. ?>
						<?php endif; ?>
					</span>
					<div class="note-head"><span><?php echo esc_html( $d['short'] ); ?></span><span><?php esc_html_e( 'now', 'comms-church-links' ); ?></span></div>
					<div class="note-title" data-svc="note"></div>
					<?php if ( $s['note_body'] ) : ?>
						<div class="note-body"><?php echo esc_html( $s['note_body'] ); ?></div>
					<?php endif; ?>
				</div>
				<?php endif; ?>
				<?php if ( $chs_next_ev ) : ?>
				<div class="note">
					<span class="note-icon cal-note" aria-hidden="true"><?php echo chs_icon_svg( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed icon library. ?></span>
					<div class="note-head"><span><?php esc_html_e( 'Events', 'comms-church-links' ); ?></span><span><?php echo esc_html( wp_date( 'M j', $chs_next_ev['start'] ) ); ?></span></div>
					<div class="note-title"><?php echo esc_html( $chs_next_ev['title'] ); ?></div>
					<div class="note-body"><?php echo esc_html( $chs_next_ev['all_day'] ? wp_date( 'l', $chs_next_ev['start'] ) : wp_date( 'l, ' . $chs_time_fmt, $chs_next_ev['start'] ) ); ?></div>
				</div>
				<?php endif; ?>
			</div>
			<button class="unlock-hint" id="unlockBtn" type="button">
				<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 14l6-6 6 6"/></svg>
				<?php esc_html_e( 'Swipe up to open', 'comms-church-links' ); ?>
			</button>
		</div>
	</section>

	<section class="sheet" id="sheet" role="dialog" aria-modal="true" aria-labelledby="sheetTitle" hidden>
		<header class="sheet-head">
			<div>
				<div class="sheet-eyebrow" id="sheetEyebrow"></div>
				<h2 class="sheet-title" id="sheetTitle"></h2>
			</div>
			<button class="done" id="doneBtn" type="button"><?php esc_html_e( 'Done', 'comms-church-links' ); ?></button>
		</header>
		<div class="sheet-body" id="sheetBody"></div>
	</section>

	<div class="toast" id="toast" role="status" aria-live="polite"></div>
	<button class="homebar" id="homebar" type="button" aria-label="<?php esc_attr_e( 'Go to home screen', 'comms-church-links' ); ?>"></button>
</div>
</div>

<template id="sheet-visit" data-eyebrow="<?php esc_attr_e( 'Plan a visit', 'comms-church-links' ); ?>" data-title="<?php esc_attr_e( "We'd love to meet you", 'comms-church-links' ); ?>">
	<?php include CHS_DIR . 'templates/parts/visit.php'; ?>
</template>

<?php if ( $d['has_feed'] ) : ?>
<template id="sheet-events" data-eyebrow="<?php esc_attr_e( 'Events', 'comms-church-links' ); ?>" data-title="<?php esc_attr_e( 'Coming up', 'comms-church-links' ); ?>">
	<?php include CHS_DIR . 'templates/parts/events.php'; ?>
</template>
<?php endif; ?>

<template id="sheet-contact" data-eyebrow="<?php esc_attr_e( 'Contact', 'comms-church-links' ); ?>" data-title="<?php esc_attr_e( 'Get in touch', 'comms-church-links' ); ?>">
	<?php include CHS_DIR . 'templates/parts/contact.php'; ?>
</template>

<script>window.CHS = <?php echo wp_json_encode( $chs_config ); ?>;</script>
<script src="<?php echo esc_url( CHS_URL . 'assets/screen.js?ver=' . CHS_VERSION ); ?>"></script>
</body>
</html>
