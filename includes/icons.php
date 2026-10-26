<?php
/**
 * Icon library. Each icon is the inner markup of a 24x24 stroked SVG.
 *
 * @package ChurchHomeScreen
 */

defined( 'ABSPATH' ) || exit;

/**
 * All available app icons.
 *
 * @return array<string, array{label: string, svg: string}>
 */
function chs_icons() {
	return array(
		'today'     => array( 'label' => __( "Calendar (shows today's date)", 'comms-church-home-screen' ), 'svg' => '' ),
		'heart'     => array( 'label' => __( 'Heart', 'comms-church-home-screen' ), 'svg' => '<path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/>' ),
		'people'    => array( 'label' => __( 'People', 'comms-church-home-screen' ), 'svg' => '<circle cx="9" cy="9" r="3"/><circle cx="17" cy="10" r="2.4"/><path d="M3.5 19c.6-3 2.8-4.6 5.5-4.6s4.9 1.6 5.5 4.6"/><path d="M15.2 15c2.5-.3 4.4 1 5 3.5"/>' ),
		'check'     => array( 'label' => __( 'Checkbox', 'comms-church-home-screen' ), 'svg' => '<rect x="4" y="4" width="16" height="16" rx="3.5"/><path d="M8.5 12.3l2.4 2.4 4.8-5"/>' ),
		'pin'       => array( 'label' => __( 'Map pin', 'comms-church-home-screen' ), 'svg' => '<path d="M12 21s-6-5.6-6-11a6 6 0 0 1 12 0c0 5.4-6 11-6 11z"/><circle cx="12" cy="10" r="2.2"/>' ),
		'navigate'  => array( 'label' => __( 'Directions arrow', 'comms-church-home-screen' ), 'svg' => '<path d="M12 3l8 18-8-4-8 4z"/>' ),
		'calendar'  => array( 'label' => __( 'Calendar', 'comms-church-home-screen' ), 'svg' => '<rect x="4" y="5" width="16" height="15" rx="2.5"/><path d="M4 10h16M9 3v4M15 3v4"/>' ),
		'book'      => array( 'label' => __( 'Open book', 'comms-church-home-screen' ), 'svg' => '<path d="M3 5.5c3-1 6-1 9 1v13c-3-2-6-2-9-1z"/><path d="M21 5.5c-3-1-6-1-9 1v13c3-2 6-2 9-1z"/>' ),
		'play'      => array( 'label' => __( 'Play / video', 'comms-church-home-screen' ), 'svg' => '<rect x="3" y="5" width="18" height="13" rx="2.5"/><path d="M10.5 9v5l4-2.5z" fill="currentColor"/>' ),
		'mic'       => array( 'label' => __( 'Microphone / podcast', 'comms-church-home-screen' ), 'svg' => '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5.5 11a6.5 6.5 0 0 0 13 0M12 17.5V21M8.5 21h7"/>' ),
		'music'     => array( 'label' => __( 'Music', 'comms-church-home-screen' ), 'svg' => '<path d="M9 18V6l11-2v12"/><circle cx="6.5" cy="18" r="2.5"/><circle cx="17.5" cy="16" r="2.5"/>' ),
		'flame'     => array( 'label' => __( 'Flame / prayer', 'comms-church-home-screen' ), 'svg' => '<path d="M12 3c1.8 2.8 4 4.6 4 8a4 4 0 0 1-8 0c0-1.9.9-3.1 1.9-4 .1 1.7.9 2.8 2.1 2.8 0-2.2-1.1-4 0-6.8z"/><path d="M8.5 20.5h7"/>' ),
		'star'      => array( 'label' => __( 'Star / kids', 'comms-church-home-screen' ), 'svg' => '<path d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.9l-5.2 2.7 1-5.8-4.3-4.1 5.9-.9z"/>' ),
		'cross'     => array( 'label' => __( 'Cross', 'comms-church-home-screen' ), 'svg' => '<path d="M12 3v18M7 8.5h10"/>' ),
		'church'    => array( 'label' => __( 'Church building', 'comms-church-home-screen' ), 'svg' => '<path d="M12 2.5v4M10.2 4.3h3.6"/><path d="M7 21v-8.5l5-4.5 5 4.5V21"/><path d="M3.5 21v-5.5L7 13M20.5 21v-5.5L17 13"/><path d="M10.5 21v-3.2a1.5 1.5 0 0 1 3 0V21"/><path d="M2.5 21h19"/>' ),
		'document'  => array( 'label' => __( 'Document / bulletin', 'comms-church-home-screen' ), 'svg' => '<rect x="5" y="3.5" width="14" height="17" rx="2"/><path d="M8.5 8h7M8.5 11.5h7M8.5 15h4"/>' ),
		'phone'     => array( 'label' => __( 'Phone', 'comms-church-home-screen' ), 'svg' => '<path d="M6.5 3.5h3l1.5 4-2 1.3a10 10 0 0 0 6.2 6.2l1.3-2 4 1.5v3a2 2 0 0 1-2.2 2A16.5 16.5 0 0 1 4.5 5.7a2 2 0 0 1 2-2.2z"/>' ),
		'mail'      => array( 'label' => __( 'Mail', 'comms-church-home-screen' ), 'svg' => '<rect x="3.5" y="5.5" width="17" height="13" rx="2.5"/><path d="M4.5 7l7.5 6 7.5-6"/>' ),
		'chat'      => array( 'label' => __( 'Chat bubble', 'comms-church-home-screen' ), 'svg' => '<path d="M4.5 5.5h15v10h-8l-4.5 3.5v-3.5h-2.5z"/>' ),
		'camera'    => array( 'label' => __( 'Camera / photos', 'comms-church-home-screen' ), 'svg' => '<path d="M4 8h3l1.5-2h7L17 8h3v11H4z"/><circle cx="12" cy="13" r="3.5"/>' ),
		'download'  => array( 'label' => __( 'Download', 'comms-church-home-screen' ), 'svg' => '<path d="M12 4v11M7.5 10.5 12 15l4.5-4.5"/><path d="M5 19.5h14"/>' ),
		'gift'      => array( 'label' => __( 'Gift', 'comms-church-home-screen' ), 'svg' => '<rect x="4" y="9" width="16" height="11" rx="1.5"/><path d="M3 9h18M12 9v11M12 9c-2-4-6-4-6-1.5S10 9 12 9zM12 9c2-4 6-4 6-1.5S14 9 12 9z"/>' ),
		'coffee'    => array( 'label' => __( 'Coffee', 'comms-church-home-screen' ), 'svg' => '<path d="M5 9h11v5a5 5 0 0 1-5 5h-1a5 5 0 0 1-5-5z"/><path d="M16 10.5h1.5a2.5 2.5 0 0 1 0 5H16"/><path d="M8.5 3.5c-.6 1 .6 1.8 0 3M12 3.5c-.6 1 .6 1.8 0 3"/>' ),
		'globe'     => array( 'label' => __( 'Globe / website', 'comms-church-home-screen' ), 'svg' => '<circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17M12 3.5c2.4 2.6 3.5 5.4 3.5 8.5s-1.1 5.9-3.5 8.5c-2.4-2.6-3.5-5.4-3.5-8.5s1.1-5.9 3.5-8.5z"/>' ),
		'home'      => array( 'label' => __( 'House', 'comms-church-home-screen' ), 'svg' => '<path d="M4 11l8-6.5 8 6.5V20H4z"/><path d="M10 20v-5h4v5"/>' ),
		'info'      => array( 'label' => __( 'Info', 'comms-church-home-screen' ), 'svg' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 11v5.5M12 7.8v.2"/>' ),
		'link'      => array( 'label' => __( 'Link', 'comms-church-home-screen' ), 'svg' => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>' ),
	);
}

/**
 * Output an icon's SVG. The markup comes from the fixed library above, never from user input.
 *
 * @param string $key Icon key.
 */
function chs_icon_svg( $key ) {
	$icons = chs_icons();
	$svg   = isset( $icons[ $key ] ) ? $icons[ $key ]['svg'] : $icons['link']['svg'];
	return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $svg . '</svg>';
}

/**
 * Pick a readable glyph color (white or ink) for a tile background.
 *
 * @param string $hex Background color.
 * @return string
 */
function chs_glyph_color( $hex ) {
	$hex = ltrim( (string) $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if ( 6 !== strlen( $hex ) ) {
		return '#ffffff';
	}
	$rgb = array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
	$lin = array_map(
		function ( $c ) {
			$c = $c / 255;
			return $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
		},
		$rgb
	);
	$lum = 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
	return $lum > 0.45 ? '#1c1e33' : '#ffffff';
}
