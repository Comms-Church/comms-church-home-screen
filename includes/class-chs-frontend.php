<?php
/**
 * Front end: the standalone links page route and the shortcode.
 *
 * @package ChurchLinks
 */

defined( 'ABSPATH' ) || exit;

/**
 * Front-end handler.
 */
class CHS_Frontend {

	const APP_STORE  = 'https://apps.apple.com/app/id1357742931';
	const PLAY_STORE = 'https://play.google.com/store/apps/details?id=com.ministrycentered.churchcenter';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'rewrite' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ), 0 );
		add_shortcode( 'church_links', array( __CLASS__, 'shortcode' ) );
		add_shortcode( 'church_home_screen', array( __CLASS__, 'shortcode' ) ); // Name before the rename.
	}

	/**
	 * Activation: register the route and flush.
	 */
	public static function activate() {
		self::rewrite();
		flush_rewrite_rules();
		if ( null === get_option( CHS_Settings::OPTION, null ) ) {
			update_option( 'chs_welcome', 1, false );
		}
	}

	/**
	 * Rewrite rule for the chosen path.
	 */
	public static function rewrite() {
		$s = CHS_Settings::get();
		add_rewrite_rule( '^' . preg_quote( $s['path'], '#' ) . '/?$', 'index.php?chs_screen=1', 'top' );
		if ( get_option( 'chs_flush_rewrite' ) ) {
			delete_option( 'chs_flush_rewrite' );
			flush_rewrite_rules( false );
		}
	}

	/**
	 * Query vars.
	 *
	 * @param array $vars Vars.
	 * @return array
	 */
	public static function query_vars( $vars ) {
		$vars[] = 'chs_screen';
		$vars[] = 'chs_embed';
		return $vars;
	}

	/**
	 * Public URL of the links page.
	 *
	 * @return string
	 */
	public static function url() {
		$s = CHS_Settings::get();
		if ( get_option( 'permalink_structure' ) ) {
			return home_url( '/' . $s['path'] . '/' );
		}
		return add_query_arg( 'chs_screen', '1', home_url( '/' ) );
	}

	/**
	 * Render the standalone page when our route is hit.
	 */
	public static function maybe_render() {
		if ( ! get_query_var( 'chs_screen' ) ) {
			return;
		}
		$s     = CHS_Settings::get();
		$d     = self::build( $s );
		$embed = (bool) get_query_var( 'chs_embed' );
		status_header( 200 );
		chs_nocache_if_logged_in();
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		include CHS_DIR . ( 'phone' === $s['display'] ? 'templates/screen.php' : 'templates/list.php' );
		exit;
	}

	/**
	 * Shortcode: the links page inside a normal page (the phone layout sits in a phone frame).
	 *
	 * @return string
	 */
	public static function shortcode() {
		$src = add_query_arg( 'chs_embed', '1', self::url() );
		if ( false === strpos( $src, 'chs_screen' ) && ! get_option( 'permalink_structure' ) ) {
			$src = add_query_arg( 'chs_screen', '1', $src );
		}
		$s = CHS_Settings::get();
		if ( 'phone' !== $s['display'] ) {
			return sprintf(
				'<div class="chs-embed" style="max-width:520px;margin:0 auto;"><iframe src="%1$s" title="%2$s" loading="lazy" style="display:block;width:100%%;height:760px;border:0;border-radius:16px;"></iframe></div>',
				esc_url( $src ),
				esc_attr( $s['church_name'] )
			);
		}
		return sprintf(
			'<div class="chs-embed" style="max-width:390px;margin:0 auto;"><iframe src="%1$s" title="%2$s" loading="lazy" style="display:block;width:100%%;aspect-ratio:390/844;height:auto;border:0;border-radius:44px;box-shadow:0 0 0 10px #16171e,0 30px 60px rgba(0,0,0,.25);"></iframe></div>',
			esc_url( $src ),
			esc_attr( $s['church_name'] )
		);
	}

	/**
	 * Resolve settings into what the template needs.
	 *
	 * @param array $s Settings.
	 * @return array
	 */
	public static function build( $s ) {
		$types   = CHS_Settings::app_types();
		$cc      = CHS_Settings::cc_base( $s );
		$events  = CHS_Events::get( $s );
		$has_feed = 'none' !== $s['events_source'];
		$maps_q  = trim( $s['address'] ? $s['church_name'] . ', ' . $s['address'] : $s['church_name'] . ' ' . $s['location'] );
		$maps    = $maps_q ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $maps_q ) : '';

		$grid = array();
		$dock = array();
		$all  = array();
		foreach ( $s['apps'] as $i => $row ) {
			if ( ! isset( $types[ $row['type'] ] ) ) {
				continue;
			}
			$t   = $types[ $row['type'] ];
			$app = array(
				'id'      => 'app-' . $i,
				'label'   => $row['label'],
				'icon'    => $row['icon'],
				'color'   => $row['color'],
				'glyph'   => chs_glyph_color( $row['color'] ),
				'sheet'   => '',
				'href'    => '',
				'cc'      => false,
				'ios'     => '',
				'android' => '',
				'sub'     => '',
				'new'     => false,
			);

			switch ( $row['type'] ) {
				case 'visit':
					$app['sheet'] = 'visit';
					break;
				case 'contact':
					if ( ! $s['phone'] && ! $s['email'] ) {
						continue 2;
					}
					$app['sheet'] = 'contact';
					break;
				case 'events':
					if ( $has_feed ) {
						$app['sheet'] = 'events';
					} elseif ( $cc ) {
						$app['href'] = $cc . '/calendar';
						$app['cc']   = true;
					} else {
						continue 2;
					}
					break;
				case 'directions':
					if ( ! $maps ) {
						continue 2;
					}
					$app['href'] = $maps;
					break;
				case 'cc_app':
					if ( ! $cc ) {
						continue 2;
					}
					$app['href']    = $cc;
					$app['ios']     = self::APP_STORE;
					$app['android'] = self::PLAY_STORE;
					break;
				case 'watch':
					$w = $row['url'] ? CHS_Watch::resolve( $row ) : null;
					if ( ! $w ) {
						continue 2;
					}
					$app['href'] = $w['href'];
					$app['sub']  = $w['text'];
					$app['new']  = $w['new'];
					$app['cc']   = self::is_cc( $w['href'] );
					break;
				case 'link':
					if ( ! $row['url'] ) {
						continue 2;
					}
					$app['href'] = $row['url'];
					$app['cc']   = self::is_cc( $row['url'] );
					break;
				default: // Church Center pages.
					if ( ! $cc ) {
						continue 2;
					}
					$app['href'] = $cc . $t['path'];
					$app['cc']   = true;
			}

			$all[] = $app;
			if ( 'dock' === $row['place'] ) {
				$dock[] = $app;
			} else {
				$grid[] = $app;
			}
		}

		$wall = '';
		if ( 'image' === $s['wallpaper'] && $s['wallpaper_image'] ) {
			$wall = wp_get_attachment_image_url( $s['wallpaper_image'], 'full' );
		}

		return array(
			'all'       => $all,
			'grid'      => $grid,
			'dock'      => $dock,
			'events'    => $events,
			'has_feed'  => $has_feed,
			'cc'        => $cc,
			'maps'      => $maps,
			'wall'      => $wall ? $wall : '',
			'short'     => $s['short_name'] ? $s['short_name'] : $s['church_name'],
			'icon_url'  => get_site_icon_url( 180 ),
			'visit_cc'  => self::is_cc( $s['visit_url'] ),
			'expect'    => array_filter( array_map( 'trim', explode( "\n", (string) $s['expect'] ) ) ),
			'weekdays'  => CHS_Settings::weekdays(),
		);
	}

	/**
	 * Is this a Church Center link?
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function is_cc( $url ) {
		$host = wp_parse_url( (string) $url, PHP_URL_HOST );
		return $host && preg_match( '/(^|\.)churchcenter\.com$/i', $host );
	}

	/**
	 * Render one app (link or button).
	 *
	 * @param array $a App.
	 */
	public static function app( $a ) {
		$style = sprintf( '--c:%s;--g:%s', $a['color'], $a['glyph'] );
		$badge = $a['new'] ? '<span class="badge"><span class="sr-only">' . esc_html__( 'New', 'comms-church-links' ) . '</span></span>' : '';
		$tile  = 'today' === $a['icon']
			? '<span class="tile cal" style="' . esc_attr( $style ) . '" aria-hidden="true"><span class="cal-dow" data-dow></span><span class="cal-day" data-day></span></span>'
			: '<span class="tile" style="' . esc_attr( $style ) . '">' . chs_icon_svg( $a['icon'] ) . $badge . '</span>';
		$label = '<span class="app-label">' . esc_html( $a['label'] ) . '</span>';

		if ( $a['sheet'] ) {
			printf( '<button class="app" type="button" data-open="%s">%s%s</button>', esc_attr( $a['sheet'] ), $tile, $label ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts.
			return;
		}
		$extra = '';
		if ( $a['cc'] ) {
			$extra .= ' data-cc';
		}
		if ( $a['ios'] ) {
			$extra .= ' data-ios="' . esc_url( $a['ios'] ) . '" data-android="' . esc_url( $a['android'] ) . '"';
		}
		printf( '<a class="app" href="%s" target="_blank" rel="noopener"%s>%s%s</a>', esc_url( $a['href'] ), $extra, $tile, $label ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts.
	}
}

/**
 * Logged-in admins previewing changes should never get a cached copy.
 */
function chs_nocache_if_logged_in() {
	if ( is_user_logged_in() ) {
		nocache_headers();
	}
}
