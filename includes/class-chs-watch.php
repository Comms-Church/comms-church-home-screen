<?php
/**
 * Watched links: an app that always points at the newest item on a page.
 *
 * Two kinds of source:
 * - A post type archive or the blog page on this site (/sermons/, /news/): the
 *   newest published post, looked up on every page view, so it is never stale.
 * - Any other page (a bulletin page, a Church Center page): we read the page and
 *   pick the newest link on it. The result is stored in chs_watch and refreshed
 *   when that page is saved, when settings are saved, and once a day.
 *
 * Changes go live on their own; nobody approves them. A link that changed in
 * the last week is marked "New".
 *
 * @package ChurchLinks
 */

defined( 'ABSPATH' ) || exit;

/**
 * Watched links.
 */
class CHS_Watch {

	const OPTION  = 'chs_watch';
	const CRON    = 'chs_watch_daily';
	const NEW_FOR = 7 * DAY_IN_SECONDS;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( self::CRON, array( __CLASS__, 'check_all' ) );
		add_action( 'save_post', array( __CLASS__, 'on_save' ), 20, 2 );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
	}

	/**
	 * Daily re-check, only while something is being watched.
	 */
	public static function schedule() {
		$next = wp_next_scheduled( self::CRON );
		if ( self::pages() && ! $next ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		} elseif ( ! self::pages() && $next ) {
			wp_clear_scheduled_hook( self::CRON );
		}
	}

	/**
	 * Watched pages (not archives) from the current settings.
	 *
	 * @param array|null $s Settings.
	 * @return array[] Each with url and match.
	 */
	public static function pages( $s = null ) {
		$s   = $s ? $s : CHS_Settings::get();
		$out = array();
		foreach ( $s['apps'] as $a ) {
			if ( 'watch' === $a['type'] && $a['url'] && ! self::post_type_for( $a['url'] ) ) {
				$out[ self::key( $a['url'], $a['match'] ) ] = array( 'url' => $a['url'], 'match' => $a['match'] );
			}
		}
		return $out;
	}

	/**
	 * Re-check a watched page as soon as it is saved, so the link follows within the same edit.
	 *
	 * @param int     $post_id Post id.
	 * @param WP_Post $post    Post.
	 */
	public static function on_save( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || 'publish' !== $post->post_status ) {
			return;
		}
		$here = untrailingslashit( (string) get_permalink( $post_id ) );
		foreach ( self::pages() as $k => $w ) {
			if ( untrailingslashit( $w['url'] ) === $here ) {
				self::check( $w['url'], $w['match'] );
			}
		}
	}

	/**
	 * Re-check every watched page and drop results nothing uses any more.
	 *
	 * @param array|null $s Settings (the settings being saved, when called from sanitize).
	 */
	public static function check_all( $s = null ) {
		$pages = self::pages( $s );
		$store = get_option( self::OPTION, array() );
		$store = array_intersect_key( is_array( $store ) ? $store : array(), $pages );
		update_option( self::OPTION, $store, false );
		foreach ( $pages as $w ) {
			self::check( $w['url'], $w['match'] );
		}
	}

	/**
	 * Storage key for one watched page.
	 *
	 * @param string $url   Watched page.
	 * @param string $match Words the link must contain.
	 * @return string
	 */
	public static function key( $url, $match ) {
		return md5( untrailingslashit( $url ) . '|' . strtolower( trim( (string) $match ) ) );
	}

	/**
	 * What a watch app links to right now.
	 *
	 * @param array $app App row (url, match).
	 * @return array|null href, text, new; null when nothing was found.
	 */
	public static function resolve( $app ) {
		$type = self::post_type_for( $app['url'] );
		if ( $type ) {
			$posts = get_posts(
				array(
					'post_type'      => $type,
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					's'              => $app['match'],
					'orderby'        => 'date',
					'order'          => 'DESC',
				)
			);
			if ( ! $posts ) {
				return null;
			}
			return array(
				'href' => get_permalink( $posts[0] ),
				'text' => wp_strip_all_tags( get_the_title( $posts[0] ) ),
				'new'  => time() - get_post_time( 'U', true, $posts[0] ) < self::NEW_FOR,
			);
		}

		$k     = self::key( $app['url'], $app['match'] );
		$store = get_option( self::OPTION, array() );
		$w     = is_array( $store ) && isset( $store[ $k ] ) ? $store[ $k ] : self::check( $app['url'], $app['match'] );
		if ( ! $w || empty( $w['href'] ) ) {
			return null;
		}
		return array(
			'href' => $w['href'],
			'text' => $w['text'],
			'new'  => $w['changed'] && time() - $w['changed'] < self::NEW_FOR,
		);
	}

	/**
	 * Read a watched page and store its newest link.
	 *
	 * @param string $url   Page.
	 * @param string $match Words the link must contain.
	 * @return array|null The stored result.
	 */
	public static function check( $url, $match ) {
		$k     = self::key( $url, $match );
		$store = get_option( self::OPTION, array() );
		$store = is_array( $store ) ? $store : array();
		$prev  = isset( $store[ $k ] ) ? $store[ $k ] : null;
		$html  = self::fetch( $url );
		$pick  = '' !== $html ? self::pick( $html, $url, $match ) : null;

		if ( ! $pick ) {
			// Keep what we had; a page that failed to load shouldn't blank the link.
			if ( $prev ) {
				$store[ $k ]['checked'] = time();
				update_option( self::OPTION, $store, false );
			}
			return $prev;
		}
		$changed = $prev ? ( $prev['href'] === $pick['href'] ? $prev['changed'] : time() ) : 0;
		$store[ $k ] = array(
			'href'    => $pick['href'],
			'text'    => $pick['text'],
			'checked' => time(),
			'changed' => $changed,
		);
		update_option( self::OPTION, $store, false );
		return $store[ $k ];
	}

	/**
	 * A post type whose archive (or the blog page) is at this URL on this site.
	 *
	 * @param string $url URL.
	 * @return string Post type, or ''.
	 */
	public static function post_type_for( $url ) {
		$url = untrailingslashit( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		$blog = (int) get_option( 'page_for_posts' );
		if ( $blog && untrailingslashit( (string) get_permalink( $blog ) ) === $url ) {
			return 'post';
		}
		foreach ( get_post_types( array( 'public' => true, 'has_archive' => true ) ) as $pt ) {
			$link = get_post_type_archive_link( $pt );
			if ( $link && untrailingslashit( $link ) === $url ) {
				return $pt;
			}
		}
		return '';
	}

	/**
	 * Page HTML, rendered when we can reach it, otherwise as stored (page builders keep content in meta).
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private static function fetch( $url ) {
		$local = 0 === strpos( $url, home_url() );
		$args  = array(
			'timeout'     => 10,
			'redirection' => 3,
			'headers'     => array( 'Cache-Control' => 'no-cache' ),
		);
		if ( $local ) {
			$args['sslverify'] = apply_filters( 'https_local_ssl_verify', false );
			$res               = wp_remote_get( $url, $args );
		} else {
			$res = wp_safe_remote_get( $url, $args );
		}
		if ( ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) ) {
			return (string) wp_remote_retrieve_body( $res );
		}
		$id = $local ? url_to_postid( $url ) : 0;
		return $id ? CHS_Scanner::stored_content( $id ) : '';
	}

	/**
	 * The newest link on a page.
	 *
	 * Order of preference: links containing the match words; documents (PDFs,
	 * Google Docs, Canva and the like) over other links; the latest date in the link
	 * text or address when links carry dates; otherwise the first link, since
	 * archive pages list the newest first.
	 *
	 * @param string $html  Page HTML.
	 * @param string $page  Page URL (to resolve relative links and skip self-links).
	 * @param string $match Words the link must contain.
	 * @return array|null href, text.
	 */
	public static function pick( $html, $page, $match = '' ) {
		// Site chrome repeats on every page and is never the newest item.
		$html = preg_replace( '#<(head|script|style|noscript|template|header|nav|footer)\b.*?</\1>#is', ' ', $html );
		if ( ! preg_match_all( '#<a\s[^>]*href=(["\'])(.*?)\1[^>]*>(.*?)</a>#is', $html, $m, PREG_SET_ORDER ) ) {
			return null;
		}
		$base  = wp_parse_url( $page );
		$self  = untrailingslashit( strtok( $page, '#?' ) );
		$home  = untrailingslashit( home_url() );
		$words = array_filter( preg_split( '/\s+/', strtolower( trim( (string) $match ) ) ) );
		$links = array();
		foreach ( $m as $a ) {
			$href = html_entity_decode( trim( $a[2] ), ENT_QUOTES );
			if ( '' === $href || preg_match( '#^(\#|mailto:|tel:|javascript:|sms:)#i', $href ) ) {
				continue;
			}
			if ( 0 === strpos( $href, '//' ) ) {
				$href = ( isset( $base['scheme'] ) ? $base['scheme'] : 'https' ) . ':' . $href;
			} elseif ( '/' === $href[0] ) {
				$href = $base['scheme'] . '://' . $base['host'] . ( isset( $base['port'] ) ? ':' . $base['port'] : '' ) . $href;
			} elseif ( ! preg_match( '#^https?://#i', $href ) ) {
				$href = trailingslashit( $self ) . $href;
			}
			$bare = untrailingslashit( strtok( $href, '#' ) );
			if ( $bare === $self || $bare === $home || preg_match( '#/(wp-login\.php|wp-admin|feed|tag|category|author)(/|$)|[?&](share|replytocom)=#i', $href ) ) {
				continue;
			}
			$text = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $a[3] ), ENT_QUOTES ) ) );
			if ( '' === $text ) {
				$text = self::name_from_url( $href );
			}
			if ( $words ) {
				$hay = strtolower( $text . ' ' . rawurldecode( $href ) );
				foreach ( $words as $w ) {
					if ( false === strpos( $hay, $w ) ) {
						continue 2;
					}
				}
			}
			$links[] = array( 'href' => $href, 'text' => mb_substr( $text, 0, 80 ) );
		}
		if ( ! $links ) {
			return null;
		}

		$docs = array_values( array_filter( $links, array( __CLASS__, 'is_document' ) ) );
		if ( $docs ) {
			$links = $docs;
		}
		$best = null;
		$when = 0;
		foreach ( $links as $l ) {
			$t = self::date_in( $l['text'] . ' ' . rawurldecode( $l['href'] ) );
			if ( $t > $when ) {
				$when = $t;
				$best = $l;
			}
		}
		return $best ? $best : $links[0];
	}

	/**
	 * Is this a bulletin-style document rather than a page?
	 *
	 * @param array $l Link.
	 * @return bool
	 */
	private static function is_document( $l ) {
		$host = strtolower( (string) wp_parse_url( $l['href'], PHP_URL_HOST ) );
		return (bool) preg_match( '#\.(pdf|docx?|pptx?)([?\#]|$)#i', $l['href'] )
			|| (bool) preg_match( '/(^|\.)(docs\.google\.com|drive\.google\.com|canva\.com|issuu\.com|flipsnack\.com|dropbox\.com|smore\.com|flipbookpdf\.net)$/', $host );
	}

	/**
	 * Latest date written in a string, as a timestamp (0 when none).
	 *
	 * Reads "October 26, 2026", "Oct 26", "26 October 2026", "10/26/2026", "10.26.26",
	 * "2026-10-26" and WordPress upload paths ("/2026/10/", day 1).
	 *
	 * @param string $s Text.
	 * @return int
	 */
	public static function date_in( $s ) {
		$months = 'jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|june?|july?|aug(?:ust)?|sep(?:t(?:ember)?)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?';
		$found  = array();
		if ( preg_match_all( '/\b(\d{4})[-_.](\d{1,2})[-_.](\d{1,2})\b/', $s, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $x ) {
				$found[] = array( (int) $x[1], (int) $x[2], (int) $x[3] );
			}
		}
		if ( preg_match_all( '/\b(\d{1,2})[\/.\-_](\d{1,2})[\/.\-_](\d{4}|\d{2})\b/', $s, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $x ) {
				$found[] = array( (int) ( strlen( $x[3] ) === 2 ? '20' . $x[3] : $x[3] ), (int) $x[1], (int) $x[2] );
			}
		}
		if ( preg_match_all( '/\b(' . $months . ')\.?[\s\-_]+(\d{1,2})(?:st|nd|rd|th)?\b(?:,?[\s\-_]+(\d{4}))?/i', $s, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $x ) {
				$found[] = array( isset( $x[3] ) && '' !== $x[3] ? (int) $x[3] : 0, self::month( $x[1] ), (int) $x[2] );
			}
		}
		if ( preg_match_all( '/\b(\d{1,2})(?:st|nd|rd|th)?[\s\-_]+(' . $months . ')\.?,?[\s\-_]+(\d{4})\b/i', $s, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $x ) {
				$found[] = array( (int) $x[3], self::month( $x[2] ), (int) $x[1] );
			}
		}
		if ( ! $found && preg_match( '#/(20\d\d)/(\d\d)/#', $s, $x ) ) {
			$found[] = array( (int) $x[1], (int) $x[2], 1 );
		}

		$best = 0;
		$now  = time();
		foreach ( $found as $f ) {
			list( $y, $mo, $d ) = $f;
			$year_given = $y > 0;
			if ( ! $year_given ) {
				$y = (int) gmdate( 'Y', $now );
			}
			if ( ! checkdate( $mo, $d, $y ) ) {
				continue;
			}
			$t = gmmktime( 12, 0, 0, $mo, $d, $y );
			// "Dec 28" read in January is last year's.
			if ( ! $year_given && $t > $now + 60 * DAY_IN_SECONDS ) {
				$t = gmmktime( 12, 0, 0, $mo, $d, $y - 1 );
			}
			$best = max( $best, $t );
		}
		return $best;
	}

	/**
	 * Month number from a name or abbreviation.
	 *
	 * @param string $name Month.
	 * @return int
	 */
	private static function month( $name ) {
		$i = array_search( strtolower( substr( $name, 0, 3 ) ), array( 'jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec' ), true );
		return false === $i ? 0 : $i + 1;
	}

	/**
	 * Readable name from a file link: ".../Bulletin-Oct-26.pdf" → "Bulletin Oct 26".
	 *
	 * @param string $href URL.
	 * @return string
	 */
	private static function name_from_url( $href ) {
		$path = (string) wp_parse_url( $href, PHP_URL_PATH );
		$name = preg_replace( '/\.[a-z0-9]{2,4}$/i', '', basename( rawurldecode( $path ) ) );
		return trim( preg_replace( '/[-_\s]+/', ' ', $name ) );
	}
}
