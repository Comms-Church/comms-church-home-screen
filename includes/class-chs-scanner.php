<?php
/**
 * Site scanner: reads the church's own site and suggests settings.
 *
 * Nothing here saves on its own. scan() returns findings; the church reviews
 * them and apply() saves only the ones they check.
 *
 * @package ChurchHomeScreen
 */

defined( 'ABSPATH' ) || exit;

/**
 * Scanner.
 */
class CHS_Scanner {

	const MAX_PAGES = 6;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_post_chs_scan', array( __CLASS__, 'handle_scan' ) );
		add_action( 'admin_post_chs_apply_scan', array( __CLASS__, 'handle_apply' ) );
	}

	/**
	 * Per-user storage for the last scan.
	 *
	 * @return string
	 */
	public static function key() {
		return 'chs_scan_' . get_current_user_id();
	}

	/**
	 * "Scan my site" button.
	 */
	public static function handle_scan() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'comms-church-home-screen' ) );
		}
		check_admin_referer( 'chs_scan' );
		set_transient( self::key(), self::scan(), HOUR_IN_SECONDS );
		wp_safe_redirect( add_query_arg( array( 'page' => CHS_Settings::PAGE, 'chs_review' => 1 ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	/**
	 * "Apply selected" button.
	 */
	public static function handle_apply() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'comms-church-home-screen' ) );
		}
		check_admin_referer( 'chs_apply_scan' );
		$findings = get_transient( self::key() );
		$picked   = isset( $_POST['chs_pick'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['chs_pick'] ) ) : array();
		$count    = is_array( $findings ) ? self::apply( $findings, $picked ) : 0;
		delete_transient( self::key() );
		wp_safe_redirect( add_query_arg( array( 'page' => CHS_Settings::PAGE, 'chs_applied' => $count ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	/**
	 * Save the checked findings.
	 *
	 * @param array    $findings Findings from scan().
	 * @param string[] $picked   Finding ids the church checked.
	 * @return int How many were applied.
	 */
	public static function apply( $findings, $picked ) {
		$s        = CHS_Settings::get();
		$services = array();
		$count    = 0;
		foreach ( $findings as $f ) {
			if ( empty( $f['id'] ) || ! in_array( $f['id'], $picked, true ) ) {
				continue;
			}
			if ( ! empty( $f['set'] ) ) {
				foreach ( $f['set'] as $k => $v ) {
					$s[ $k ] = $v;
				}
			}
			if ( ! empty( $f['service'] ) ) {
				$services[] = $f['service'];
			}
			if ( ! empty( $f['app'] ) && ! self::has_app( $s['apps'], $f['app'] ) ) {
				$s['apps'][] = $f['app'];
			}
			if ( ! empty( $f['fill'] ) ) {
				foreach ( $s['apps'] as $i => $a ) {
					if ( 'link' === $a['type'] && '' === $a['url'] && strtolower( $a['label'] ) === $f['fill'] ) {
						$s['apps'][ $i ]['url'] = esc_url_raw( $f['show'] );
						break;
					}
				}
			}
			if ( ! empty( $f['tip'] ) ) {
				continue;
			}
			++$count;
		}
		if ( $services ) {
			$s['services'] = array_slice( $services, 0, 4 );
		}
		if ( $count ) {
			update_option( CHS_Settings::OPTION, $s ); // Runs through CHS_Settings::sanitize().
		}
		return $count;
	}

	/**
	 * Does the app list already have this app?
	 *
	 * @param array $apps Current apps.
	 * @param array $app  Candidate.
	 * @return bool
	 */
	private static function has_app( $apps, $app ) {
		foreach ( $apps as $a ) {
			if ( 'link' === $app['type'] ) {
				if ( 'link' === $a['type'] && ( untrailingslashit( $a['url'] ) === untrailingslashit( $app['url'] ) || ( '' === $a['url'] && strtolower( $a['label'] ) === strtolower( $app['label'] ) ) ) ) {
					return true;
				}
			} elseif ( $a['type'] === $app['type'] ) {
				return true;
			}
		}
		return false;
	}

	/* ------------------------------------------------------------------ */
	/* Scanning                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Scan the site.
	 *
	 * @return array[] Findings.
	 */
	public static function scan() {
		$s     = CHS_Settings::get();
		$pages = self::collect_pages();
		$links = array();
		$nodes = array();
		$texts = array();
		$home  = trailingslashit( home_url() );

		foreach ( $pages as $url => $html ) {
			$weight = ( trailingslashit( $url ) === $home ) ? 2 : 1;
			self::parse_html( $html, $links, $nodes );
			$texts[] = array( 'text' => self::text( $html ), 'weight' => $weight );
		}

		$org = self::org_from_jsonld( $nodes );
		$f   = array();

		self::find_details( $f, $s, $org, $links, $texts, self::site_name( (string) reset( $pages ) ) );
		self::find_services( $f, $texts );
		$cc = self::find_church_center( $f, $s, $links );
		self::find_events( $f, $s, $links, $cc );
		self::find_apps( $f, $s, $links, $org, $cc );
		self::find_look( $f, $s, $pages );

		return $f;
	}

	/**
	 * Fetch the homepage and likely info pages as a logged-out visitor sees them.
	 * Falls back to stored content when the site can't fetch itself (staging lock, maintenance mode).
	 *
	 * @return array url => html
	 */
	private static function collect_pages() {
		$urls  = array( home_url( '/' ) );
		$match = '/contact|visit|plan|new|about|location|direction|time|worship|sunday|service|welcome|connect/i';
		$ids   = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			)
		);
		foreach ( $ids as $id ) {
			if ( count( $urls ) >= self::MAX_PAGES ) {
				break;
			}
			$p = get_post( $id );
			if ( (int) get_option( 'page_on_front' ) === $id ) {
				continue;
			}
			if ( preg_match( $match, $p->post_name . ' ' . $p->post_title ) ) {
				$urls[] = get_permalink( $id );
			}
		}

		$out = array();
		foreach ( array_unique( $urls ) as $url ) {
			$res = wp_remote_get(
				$url,
				array(
					'timeout'     => 12,
					'redirection' => 3,
					'sslverify'   => apply_filters( 'https_local_ssl_verify', false ),
					'headers'     => array( 'Cache-Control' => 'no-cache' ),
				)
			);
			if ( ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) ) {
				$out[ $url ] = wp_remote_retrieve_body( $res );
				continue;
			}
			$id = url_to_postid( $url );
			if ( ! $id && trailingslashit( $url ) === trailingslashit( home_url() ) ) {
				$id = (int) get_option( 'page_on_front' );
			}
			if ( $id ) {
				$out[ $url ] = self::stored_content( $id );
			}
		}
		return $out;
	}

	/**
	 * Post content plus any text stored in post meta (page builders keep content there).
	 *
	 * @param int $id Post id.
	 * @return string
	 */
	private static function stored_content( $id ) {
		$html = (string) get_post_field( 'post_content', $id );
		foreach ( (array) get_post_meta( $id ) as $key => $values ) {
			if ( '_edit_lock' === $key || '_edit_last' === $key ) {
				continue;
			}
			foreach ( $values as $v ) {
				$v = maybe_unserialize( $v );
				$html .= ' ' . ( is_string( $v ) ? $v : wp_json_encode( $v ) );
			}
		}
		return str_replace( array( '\\/', '\\n' ), array( '/', ' ' ), $html );
	}

	/**
	 * Pull links and JSON-LD nodes out of a page.
	 *
	 * @param string $html  Page HTML.
	 * @param array  $links Collected links (by reference).
	 * @param array  $nodes Collected JSON-LD nodes (by reference).
	 */
	private static function parse_html( $html, &$links, &$nodes ) {
		if ( preg_match_all( '#<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $m ) ) {
			foreach ( $m[1] as $json ) {
				$data = json_decode( trim( $json ), true );
				if ( is_array( $data ) ) {
					self::flatten_jsonld( $data, $nodes );
				}
			}
		}
		if ( preg_match_all( '#<a\b([^>]*)>(.*?)</a>#is', $html, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $a ) {
				if ( ! preg_match( '#href\s*=\s*(["\'])(.*?)\1#is', $a[1], $h ) ) {
					continue;
				}
				$href = trim( html_entity_decode( $h[2], ENT_QUOTES ) );
				if ( '' === $href || '#' === $href[0] ) {
					continue;
				}
				$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( html_entity_decode( $a[2], ENT_QUOTES ) ) ) );
				if ( '' === $text && preg_match( '#aria-label\s*=\s*(["\'])(.*?)\1#is', $a[1], $l ) ) {
					$text = $l[2];
				}
				$links[] = array( 'href' => $href, 'text' => $text );
			}
		}
	}

	/**
	 * The church name the homepage shows visitors: the logo or site-title link
	 * back to the homepage (its text, or its image's alt text), then og:site_name.
	 *
	 * @param string $html Homepage HTML.
	 * @return string
	 */
	private static function site_name( $html ) {
		$cands = array();
		if ( preg_match_all( '#<a\b([^>]*)>(.*?)</a>#is', $html, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $a ) {
				if ( ! preg_match( '#href\s*=\s*(["\'])(.*?)\1#is', $a[1], $h ) ) {
					continue;
				}
				$href = untrailingslashit( trim( html_entity_decode( $h[2], ENT_QUOTES ) ) );
				if ( ! in_array( $href, array( '', untrailingslashit( home_url() ) ), true ) ) {
					continue;
				}
				$logo = preg_match( '#class\s*=\s*(["\'])[^"\']*(logo|site-title|brand)[^"\']*\1#i', $a[1] ) || preg_match( '#rel\s*=\s*(["\'])home\1#i', $a[1] );
				if ( ! $logo ) {
					continue;
				}
				$cands[] = wp_strip_all_tags( html_entity_decode( $a[2], ENT_QUOTES ) );
				if ( preg_match( '#<img[^>]+alt\s*=\s*(["\'])(.*?)\1#is', $a[2], $alt ) ) {
					$cands[] = preg_replace( '/\s+logo$/i', '', html_entity_decode( $alt[2], ENT_QUOTES ) );
				}
			}
		}
		if ( preg_match( '#<meta[^>]+property=["\']og:site_name["\'][^>]+content=["\']([^"\']+)#i', $html, $og ) ) {
			$cands[] = html_entity_decode( $og[1], ENT_QUOTES );
		}
		foreach ( $cands as $c ) {
			$c = trim( preg_replace( '/\s+/', ' ', $c ) );
			if ( strlen( $c ) >= 3 && strlen( $c ) <= 60 && ! preg_match( '/^(home|logo|menu)$/i', $c ) ) {
				return $c;
			}
		}
		return '';
	}

	/**
	 * Walk JSON-LD, including @graph, into a flat list of typed nodes.
	 *
	 * @param array $data  Decoded JSON.
	 * @param array $nodes Collected nodes (by reference).
	 */
	private static function flatten_jsonld( $data, &$nodes ) {
		if ( isset( $data['@graph'] ) && is_array( $data['@graph'] ) ) {
			foreach ( $data['@graph'] as $n ) {
				if ( is_array( $n ) ) {
					self::flatten_jsonld( $n, $nodes );
				}
			}
			return;
		}
		if ( isset( $data[0] ) ) {
			foreach ( $data as $n ) {
				if ( is_array( $n ) ) {
					self::flatten_jsonld( $n, $nodes );
				}
			}
			return;
		}
		if ( isset( $data['@type'] ) ) {
			$nodes[] = $data;
		}
	}

	/**
	 * Best organization-like node: Church, then PlaceOfWorship, then Organization/LocalBusiness.
	 *
	 * @param array $nodes JSON-LD nodes.
	 * @return array
	 */
	private static function org_from_jsonld( $nodes ) {
		$rank = array( 'church' => 4, 'placeofworship' => 3, 'religiousorganization' => 3, 'localbusiness' => 2, 'organization' => 1 );
		$best = array();
		$top  = 0;
		foreach ( $nodes as $n ) {
			foreach ( (array) $n['@type'] as $t ) {
				$r = isset( $rank[ strtolower( (string) $t ) ] ) ? $rank[ strtolower( (string) $t ) ] : 0;
				if ( $r > $top ) {
					$top  = $r;
					$best = $n;
				}
			}
		}
		return $best;
	}

	/**
	 * Visible text of a page.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	private static function text( $html ) {
		$t = preg_replace( '#<(script|style|noscript|svg|template)\b.*?</\1>#is', ' ', $html );
		$t = preg_replace( '#<[^>]+>#', ' ', $t );
		$t = html_entity_decode( $t, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$t = str_replace( array( "\xC2\xA0", "\xE2\x80\x93", "\xE2\x80\x94" ), array( ' ', '-', '-' ), $t );
		return trim( preg_replace( '/\s+/u', ' ', $t ) );
	}

	/**
	 * Add a finding.
	 *
	 * @param array  $f       Findings (by reference).
	 * @param string $group   Group key.
	 * @param string $label   What it is.
	 * @param string $show    The value, for display.
	 * @param array  $payload set / app / service.
	 * @param string $note    Where it came from or what it does.
	 * @param bool   $checked Checked by default.
	 */
	private static function add( &$f, $group, $label, $show, $payload, $note = '', $checked = true ) {
		$f[] = array_merge(
			array(
				'id'      => 'f' . substr( md5( $group . $label . $show ), 0, 10 ),
				'group'   => $group,
				'label'   => $label,
				'show'    => $show,
				'note'    => $note,
				'checked' => $checked,
			),
			$payload
		);
	}

	/**
	 * Most common value in a list.
	 *
	 * @param array $values Values.
	 * @return string
	 */
	private static function most_common( $values ) {
		$values = array_filter( $values );
		if ( ! $values ) {
			return '';
		}
		$counts = array_count_values( $values );
		arsort( $counts );
		return (string) key( $counts );
	}

	/* ---------- Church details ---------- */

	/**
	 * Name, phone, email, address.
	 */
	private static function find_details( &$f, $s, $org, $links, $texts, $site_name = '' ) {
		$g = 'details';

		// Name: structured data, then the name the site header shows visitors
		// (page builders like Bricks often show a different name than the WordPress site title).
		$name = ! empty( $org['name'] ) && is_string( $org['name'] ) ? $org['name'] : $site_name;
		if ( $name && 0 !== strcasecmp( $name, $s['church_name'] ) ) {
			$note = trim( ( $name === $site_name ? __( 'From the header of your homepage.', 'comms-church-home-screen' ) . ' ' : '' ) . self::was( $s['church_name'] ) );
			self::add( $f, $g, __( 'Church name', 'comms-church-home-screen' ), $name, array( 'set' => array( 'church_name' => $name ) ), $note );
		}

		// Phone: structured data, then tel: links, then text.
		$phone = ! empty( $org['telephone'] ) && is_string( $org['telephone'] ) ? self::format_phone( $org['telephone'] ) : '';
		if ( ! $phone ) {
			$tels = array();
			foreach ( $links as $l ) {
				if ( 0 === stripos( $l['href'], 'tel:' ) ) {
					$tels[] = self::format_phone( substr( $l['href'], 4 ) );
				}
			}
			$phone = self::most_common( $tels );
		}
		if ( ! $phone ) {
			foreach ( $texts as $t ) {
				if ( preg_match( '/(?<![\d-])\(?\b(\d{3})\)?[\s.-]?(\d{3})[\s.-](\d{4})\b/', $t['text'], $m ) ) {
					$phone = self::format_phone( $m[1] . $m[2] . $m[3] );
					break;
				}
			}
		}
		if ( $phone && $phone !== $s['phone'] ) {
			self::add( $f, $g, __( 'Phone', 'comms-church-home-screen' ), $phone, array( 'set' => array( 'phone' => $phone ) ), self::was( $s['phone'] ) );
		}

		// Email: structured data, then mailto: links.
		$email = ! empty( $org['email'] ) && is_string( $org['email'] ) ? str_ireplace( 'mailto:', '', $org['email'] ) : '';
		if ( ! $email ) {
			$mails = array();
			foreach ( $links as $l ) {
				if ( 0 === stripos( $l['href'], 'mailto:' ) ) {
					$addr = strtok( substr( $l['href'], 7 ), '?' );
					if ( is_email( $addr ) ) {
						$mails[] = strtolower( $addr );
					}
				}
			}
			$email = self::most_common( $mails );
		}
		if ( $email && is_email( $email ) && $email !== $s['email'] ) {
			self::add( $f, $g, __( 'Email', 'comms-church-home-screen' ), $email, array( 'set' => array( 'email' => $email ) ), self::was( $s['email'] ) );
		}

		// Address.
		$street = '';
		$town   = '';
		if ( ! empty( $org['address'] ) ) {
			$a = $org['address'];
			if ( is_array( $a ) && isset( $a[0] ) ) {
				$a = $a[0];
			}
			if ( is_array( $a ) ) {
				$town   = trim( ( isset( $a['addressLocality'] ) ? $a['addressLocality'] : '' ) . ( ! empty( $a['addressRegion'] ) ? ', ' . $a['addressRegion'] : '' ), ', ' );
				$street = trim( implode( ', ', array_filter( array( isset( $a['streetAddress'] ) ? $a['streetAddress'] : '', $town ) ) ) . ( ! empty( $a['postalCode'] ) ? ' ' . $a['postalCode'] : '' ) );
			} elseif ( is_string( $a ) ) {
				$street = $a;
			}
		}
		if ( ! $street ) {
			$suffix = 'Road|Rd|Street|St|Avenue|Ave|Pike|Lane|Ln|Drive|Dr|Boulevard|Blvd|Highway|Hwy|Way|Court|Ct|Place|Pl|Parkway|Pkwy|Circle|Cir|Trail|Trl|Terrace|Route|Rte';
			foreach ( $texts as $t ) {
				if ( preg_match( '/\b(\d{1,6}\s+(?:[NSEW]\.?\s+)?[A-Z0-9][A-Za-z0-9.\' -]{1,40}?\s(?:' . $suffix . ')\.?)(?:,?\s+(?:Suite|Ste\.?|Unit)\s*\w+)?,?\s+([A-Z][A-Za-z .\'-]{1,30}?),\s*([A-Z]{2})\s+(\d{5})(?:-\d{4})?\b/', $t['text'], $m ) ) {
					$line = trim( $m[1] );
					// Page text runs blocks together, so a footer reading "Sun 11 AM" then
					// "ADDRESS 212 Peach Bottom Road" matches from the 11. Start at the last house
					// number when what comes before it is a time or an all-caps heading
					// (not when it is part of the street, as in "500 7 Mile Road").
					if ( preg_match( '/^(.*)\b(\d{1,6}\s+(?:[NSEW]\.?\s+)?[A-Z].*)$/', $line, $n ) && preg_match( '/\b[AaPp][Mm]\b|\b[A-Z]{3,}\b/', $n[1] ) ) {
						$line = $n[2];
					}
					$street = $line . ', ' . trim( $m[2] ) . ', ' . $m[3] . ' ' . $m[4];
					$town   = trim( $m[2] ) . ', ' . $m[3];
					break;
				}
			}
		}
		if ( $street && $street !== $s['address'] ) {
			self::add( $f, $g, __( 'Address', 'comms-church-home-screen' ), $street, array( 'set' => array( 'address' => $street ) ), self::was( $s['address'] ) );
		}
		if ( $town && $town !== $s['location'] ) {
			self::add( $f, $g, __( 'Town and state', 'comms-church-home-screen' ), $town, array( 'set' => array( 'location' => $town ) ), self::was( $s['location'] ) );
		}
	}

	/**
	 * "Currently: x" note.
	 *
	 * @param string $current Current value.
	 * @return string
	 */
	private static function was( $current ) {
		/* translators: %s: current value */
		return $current ? sprintf( __( 'Replaces: %s', 'comms-church-home-screen' ), $current ) : '';
	}

	/**
	 * Tidy a US phone number; leave others alone.
	 *
	 * @param string $raw Raw number.
	 * @return string
	 */
	private static function format_phone( $raw ) {
		$raw    = rawurldecode( $raw );
		$digits = preg_replace( '/\D/', '', $raw );
		if ( 11 === strlen( $digits ) && '1' === $digits[0] ) {
			$digits = substr( $digits, 1 );
		}
		if ( 10 === strlen( $digits ) ) {
			return sprintf( '(%s) %s-%s', substr( $digits, 0, 3 ), substr( $digits, 3, 3 ), substr( $digits, 6 ) );
		}
		return trim( $raw );
	}

	/* ---------- Service times ---------- */

	/**
	 * Find phrases like "Sundays at 9:00 & 10:30 AM" or "Worship: Sunday 10:30am".
	 */
	private static function find_services( &$f, $texts ) {
		$days  = array( 'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6 );
		$dayre = '\b(sunday|monday|tuesday|wednesday|thursday|friday|saturday)s?\b';
		$found   = array();
		$worship = __( 'Worship', 'comms-church-home-screen' );

		foreach ( $texts as $t ) {
			$text = preg_replace( '/\b([ap])\.\s?m\.?/i', '$1m', $t['text'] );
			if ( ! preg_match_all( '/' . $dayre . '/i', $text, $dm, PREG_OFFSET_CAPTURE ) ) {
				continue;
			}
			foreach ( $dm[0] as $i => $match ) {
				$start  = $match[1] + strlen( $match[0] );
				$window = substr( $text, $start, 90 );
				// Stop at the next weekday or the end of the sentence.
				if ( preg_match( '/' . $dayre . '|[.!?](?=\s|$)/i', $window, $stop, PREG_OFFSET_CAPTURE ) ) {
					$window = substr( $window, 0, $stop[0][1] );
				}
				$before = substr( $text, max( 0, $match[1] - 40 ), min( 40, $match[1] ) );
				$times  = self::times_in( $window );
				if ( ! $times ) {
					continue;
				}
				$day   = $days[ strtolower( $dm[1][ $i ][0] ) ];
				// Names written after each time. Page text runs lines together, so in
				// "11 AM | Bible study 10 AM" the name could belong to either time: only trust
				// it when the next time follows the same "time · name" pattern.
				$afters = array();
				foreach ( $times as $j => $tm ) {
					$next         = isset( $times[ $j + 1 ] ) ? $times[ $j + 1 ]['pos'] : strlen( $window );
					$afters[ $j ] = self::trailing_label( substr( $window, $tm['end'], $next - $tm['end'] ) );
				}
				foreach ( $afters as $j => $a ) {
					if ( $a && isset( $times[ $j + 1 ] ) && empty( $afters[ $j + 1 ] ) ) {
						$afters[ $j ] = null;
					}
				}
				$label = '';
				$prev  = 0;
				foreach ( $times as $j => $tm ) {
					// Name each time from the words just before it; "8:30, 10 & 11:30" share one name.
					$seg   = ( 0 === $prev ? $before . ' ' . $match[0] . ' ' : '' ) . substr( $window, $prev, $tm['pos'] - $prev );
					$named = self::service_label( $seg, $day, '' );
					$label = $named ? $named : ( $label ? $label : self::service_label( $before . ' ' . $match[0] . ' ' . $window, $day ) );
					$prev  = $tm['end'];
					$kind  = $label;
					$name  = $label;
					// The church's own name for it, written after the time ("9:00 AM · Classic gathering"),
					// beats a generic "Worship" read from a heading like "Sunday Gatherings", but not a
					// specific name written before the time ("Sunday School 9:15").
					$after = $afters[ $j ];
					if ( $after && ( '' === $named || $worship === $named ) ) {
						$name  = $after['name'];
						$kind  = self::service_label( $after['name'], $day );
						$prev  = $tm['end'] + $after['len'];
					}
					$time = $tm['time'];
					$k    = $day . '|' . $time;
					if ( ! isset( $found[ $k ] ) ) {
						$found[ $k ] = array( 'day' => $day, 'time' => $time, 'label' => $name, 'kind' => $kind, 'score' => 0 );
					} elseif ( $found[ $k ]['label'] === $found[ $k ]['kind'] && $name !== $kind ) {
						$found[ $k ]['label'] = $name; // A church's own name beats our generic one.
					}
					$found[ $k ]['score'] += $t['weight'];
				}
			}
		}
		if ( ! $found ) {
			return;
		}

		uasort(
			$found,
			function ( $a, $b ) {
				$da = ( $a['day'] + 7 ) % 7;
				$db = ( $b['day'] + 7 ) % 7;
				return $da === $db ? self::minutes( $a['time'] ) - self::minutes( $b['time'] ) : $da - $db;
			}
		);

		$weekdays = CHS_Settings::weekdays();
		$n        = 0;
		foreach ( $found as $svc ) {
			if ( $n >= 6 ) {
				break;
			}
			$main = in_array( $svc['day'], array( 0, 6 ), true ) && $worship === $svc['kind'];
			self::add(
				$f,
				'services',
				$weekdays[ $svc['day'] ] . ' · ' . $svc['label'],
				$svc['time'],
				array( 'service' => array( 'day' => $svc['day'], 'time' => $svc['time'], 'label' => $svc['label'] ) ),
				$svc['score'] > 1 ? __( 'Found in more than one place', 'comms-church-home-screen' ) : __( 'Found once. Please double-check.', 'comms-church-home-screen' ),
				$main
			);
			++$n;
		}
	}

	/**
	 * Times in a short stretch of text. "9 & 10:30 AM" gives both times.
	 *
	 * @param string $window Text.
	 * @return array[] time (normalized like "10:30 AM"), pos, end.
	 */
	private static function times_in( $window ) {
		if ( ! preg_match_all( '/(?<![\d:\/])(\d{1,2})(?::(\d{2}))?\s*(am|pm)?(?![\d:\/])/i', $window, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			return array();
		}
		$out  = array();
		$next = '';
		for ( $i = count( $m ) - 1; $i >= 0; $i-- ) {
			$h   = (int) $m[ $i ][1][0];
			$mm  = isset( $m[ $i ][2] ) && '' !== $m[ $i ][2][0] && -1 !== $m[ $i ][2][1] ? $m[ $i ][2][0] : '00';
			$ap  = isset( $m[ $i ][3] ) && -1 !== $m[ $i ][3][1] ? strtoupper( $m[ $i ][3][0] ) : '';
			$end = $m[ $i ][0][1] + strlen( $m[ $i ][0][0] );
			if ( $h < 1 || $h > 12 || (int) $mm > 59 ) {
				$next = '';
				continue;
			}
			if ( '' === $ap && '' !== $next && isset( $m[ $i + 1 ] ) ) {
				// "9 & 10:30 AM": borrow the AM, but only across a joiner like "&", "and" or ",".
				$between = substr( $window, $end, $m[ $i + 1 ][0][1] - $end );
				if ( preg_match( '/^\s*(&|and|,|\/|or|\+)\s*$/i', $between ) ) {
					$ap = $next;
				}
			}
			if ( '' === $ap ) {
				$next = '';
				continue;
			}
			$next = $ap;
			array_unshift( $out, array( 'time' => $h . ':' . $mm . ' ' . $ap, 'pos' => $m[ $i ][0][1], 'end' => $end ) );
		}
		return $out;
	}

	/**
	 * A service name written right after a time, set off by a separator:
	 * "· Classic gathering", "- Traditional Service", ": Sunday School".
	 * Page text runs block elements together, so the name must end in a service word
	 * ("gathering", "service"…) to know where it stops.
	 *
	 * @param string $text Text between this time and the next one.
	 * @return array|null name, len (characters consumed).
	 */
	private static function trailing_label( $text ) {
		$kw = '(?:gathering|service|worship|celebration|experience|mass|liturgy|eucharist|school|study|prayer|youth|students?)s?';
		if ( ! preg_match( '/^\s*[\x{00B7}\x{2022}|:-]\s*((?:[A-Za-z][A-Za-z\'\x{2019}&-]*\s+){0,3}?' . $kw . '(?:\s+' . $kw . ')?)\b/iu', $text, $m ) ) {
			return null;
		}
		return array( 'name' => ucfirst( $m[1] ), 'len' => strlen( $m[0] ) );
	}

	/**
	 * Name a service from nearby words.
	 *
	 * @param string $context Text around the match.
	 * @param int    $day     Weekday.
	 * @return string
	 */
	private static function service_label( $context, $day, $fallback = null ) {
		$map = array(
			'/sunday\s+school/i'        => __( 'Sunday School', 'comms-church-home-screen' ),
			'/bible study/i'             => __( 'Bible Study', 'comms-church-home-screen' ),
			'/prayer/i'                  => __( 'Prayer', 'comms-church-home-screen' ),
			'/youth|student/i'           => __( 'Youth', 'comms-church-home-screen' ),
			'/\bmass\b/i'                => __( 'Mass', 'comms-church-home-screen' ),
			'/worship|service|gathering|celebration/i' => __( 'Worship', 'comms-church-home-screen' ),
		);
		foreach ( $map as $re => $label ) {
			if ( preg_match( $re, $context ) ) {
				return $label;
			}
		}
		if ( null !== $fallback ) {
			return $fallback;
		}
		return in_array( $day, array( 0, 6 ), true ) ? __( 'Worship', 'comms-church-home-screen' ) : __( 'Gathering', 'comms-church-home-screen' );
	}

	/**
	 * Minutes after midnight for sorting.
	 *
	 * @param string $t "10:30 AM".
	 * @return int
	 */
	private static function minutes( $t ) {
		if ( ! preg_match( '/(\d+):(\d+)\s*(AM|PM)/', $t, $m ) ) {
			return 0;
		}
		$h = (int) $m[1] % 12 + ( 'PM' === $m[3] ? 12 : 0 );
		return $h * 60 + (int) $m[2];
	}

	/* ---------- Church Center ---------- */

	/**
	 * Find the Church Center subdomain and a plan-a-visit form.
	 *
	 * @return string The Church Center base URL found or already set.
	 */
	private static function find_church_center( &$f, $s, $links ) {
		$subs = array();
		foreach ( $links as $l ) {
			$host = strtolower( (string) wp_parse_url( $l['href'], PHP_URL_HOST ) );
			if ( preg_match( '/^([a-z0-9-]+)\.churchcenter\.com$/', $host, $m ) && ! in_array( $m[1], array( 'www', 'js', 'api', 'help' ), true ) ) {
				$subs[] = $m[1];
			}
		}
		$sub = self::most_common( $subs );
		if ( $sub && $sub !== $s['cc_subdomain'] ) {
			self::add(
				$f,
				'cc',
				__( 'Church Center address', 'comms-church-home-screen' ),
				$sub . '.churchcenter.com',
				array( 'set' => array( 'cc_subdomain' => $sub ) ),
				__( 'Turns on the Give, Groups, Sign Ups and Get the App apps.', 'comms-church-home-screen' )
			);
		}
		$base = $sub ? 'https://' . $sub . '.churchcenter.com' : CHS_Settings::cc_base( $s );

		// A Church Center form or link that sounds like "plan a visit".
		if ( ! $s['visit_url'] ) {
			foreach ( $links as $l ) {
				if ( CHS_Frontend::is_cc( $l['href'] ) && preg_match( '/plan|visit|new here|first time|connect card/i', $l['text'] . ' ' . $l['href'] ) ) {
					self::add( $f, 'cc', __( 'Plan-a-visit form', 'comms-church-home-screen' ), $l['href'], array( 'set' => array( 'visit_url' => esc_url_raw( $l['href'] ) ) ), $l['text'] );
					break;
				}
			}
		}
		return $base;
	}

	/* ---------- Events ---------- */

	/**
	 * The Events Calendar, or a calendar feed link on the site.
	 */
	private static function find_events( &$f, $s, $links, $cc ) {
		if ( 'none' !== $s['events_source'] ) {
			return;
		}
		// A Planning Center key from another comms.church plugin: list exactly what Church Center shows.
		$cred = CHS_Events::pco_credentials( $s );
		if ( $cred ) {
			/* translators: %s: plugin name */
			self::add( $f, 'events', __( 'Event list', 'comms-church-home-screen' ), __( 'Planning Center: the events you show in Church Center', 'comms-church-home-screen' ), array( 'set' => array( 'events_source' => 'pco' ) ), sprintf( __( 'Uses the Planning Center connection from %s.', 'comms-church-home-screen' ), $cred['from'] ? $cred['from'] : __( 'these settings', 'comms-church-home-screen' ) ) );
			return;
		}
		if ( function_exists( 'tribe_get_events' ) ) {
			self::add( $f, 'events', __( 'Event list', 'comms-church-home-screen' ), __( 'The Events Calendar on this site', 'comms-church-home-screen' ), array( 'set' => array( 'events_source' => 'tec' ) ), __( 'Shows upcoming events in the Events app and on the lock screen.', 'comms-church-home-screen' ) );
			return;
		}
		foreach ( $links as $l ) {
			if ( preg_match( '#^webcals?://#i', $l['href'] ) || preg_match( '#\.ics(\?|$)#i', $l['href'] ) || false !== stripos( $l['href'], 'calendar/ical' ) ) {
				$url = preg_replace( '#^webcals?://#i', 'https://', $l['href'] );
				self::add( $f, 'events', __( 'Calendar feed', 'comms-church-home-screen' ), $url, array( 'set' => array( 'events_source' => 'ics', 'ics_url' => esc_url_raw( $url ) ) ), __( 'Shows upcoming events in the Events app and on the lock screen.', 'comms-church-home-screen' ) );
				return;
			}
		}
	}

	/* ---------- Apps ---------- */

	/**
	 * Social profiles, key pages from menus and pages, sermon archives.
	 */
	private static function find_apps( &$f, $s, $links, $org, $cc ) {
		$g     = 'apps';
		$added = array();

		$offer = function ( $label, $icon, $color, $url, $note, $checked ) use ( &$f, &$added, $g, $s ) {
			$key = strtolower( $label );
			if ( isset( $added[ $key ] ) || ! $url ) {
				return;
			}
			$app = array( 'type' => 'link', 'label' => $label, 'icon' => $icon, 'color' => $color, 'url' => esc_url_raw( $url ), 'place' => 'grid' );
			if ( self::has_app( $s['apps'], $app ) ) {
				// Fill in an existing placeholder app (like "Watch" with no link) instead of adding a new one.
				foreach ( $s['apps'] as $a ) {
					if ( 'link' === $a['type'] && '' === $a['url'] && strtolower( $a['label'] ) === $key ) {
						$added[ $key ] = true;
						/* translators: %s: app name */
						self::add( $f, $g, $label, $url, array( 'fill' => $key ), sprintf( __( 'Adds the link to your existing %s app.', 'comms-church-home-screen' ), $label ) . ' ' . $note, $checked );
						return;
					}
				}
				return;
			}
			$added[ $key ] = true;
			self::add( $f, $g, $label, $url, array( 'app' => $app ), $note, $checked );
		};

		// Social profiles.
		$social = array(
			'instagram.com'      => array( 'Instagram', 'camera', '#c13584' ),
			'facebook.com'       => array( 'Facebook', 'chat', '#1877f2' ),
			'youtube.com'        => array( 'YouTube', 'play', '#d9483b' ),
			'youtu.be'           => array( 'YouTube', 'play', '#d9483b' ),
			'vimeo.com'          => array( 'Vimeo', 'play', '#1ab7ea' ),
			'tiktok.com'         => array( 'TikTok', 'music', '#111827' ),
			'podcasts.apple.com' => array( __( 'Podcast', 'comms-church-home-screen' ), 'mic', '#8e4fd6' ),
			'open.spotify.com'   => array( __( 'Podcast', 'comms-church-home-screen' ), 'mic', '#1db954' ),
			'x.com'              => array( 'X', 'chat', '#111827' ),
			'twitter.com'        => array( 'X', 'chat', '#111827' ),
		);
		$hrefs = wp_list_pluck( $links, 'href' );
		if ( ! empty( $org['sameAs'] ) ) {
			$hrefs = array_merge( (array) $org['sameAs'], $hrefs );
		}
		foreach ( $hrefs as $href ) {
			if ( ! is_string( $href ) || preg_match( '#sharer|share\?|/intent/|/share/|dialog/#i', $href ) ) {
				continue;
			}
			$host = preg_replace( '/^(www|m)\./', '', strtolower( (string) wp_parse_url( $href, PHP_URL_HOST ) ) );
			$path = trim( (string) wp_parse_url( $href, PHP_URL_PATH ), '/' );
			if ( isset( $social[ $host ] ) && '' !== $path && ! preg_match( '#^(watch|embed|p|reel|status)/#', $path ) ) {
				list( $label, $icon, $color ) = $social[ $host ];
				$offer( $label, $icon, $color, $href, __( 'Linked on your site', 'comms-church-home-screen' ), in_array( $label, array( 'Instagram', 'Facebook', 'YouTube' ), true ) );
			}
		}

		// Key pages, from menus first (the church curated those), then all pages.
		$rules = array(
			array( '/plan.{0,12}visit|new here|i.?m new|first.time|visit us/i', 'visit', '', '', '' ),
			array( '/\b(watch|live|livestream|live stream|stream|watch live)\b/i', __( 'Watch', 'comms-church-home-screen' ), 'play', '#d9483b', '' ),
			array( '/sermon|message|teaching|podcast/i', __( 'Sermons', 'comms-church-home-screen' ), 'book', '#2c3372', '' ),
			array( '/\bkids?\b|children/i', __( 'Kids', 'comms-church-home-screen' ), 'star', '#7fb23a', '' ),
			array( '/student|youth/i', __( 'Students', 'comms-church-home-screen' ), 'people', '#e0712c', '' ),
			array( '/prayer/i', __( 'Prayer', 'comms-church-home-screen' ), 'flame', '#7d5bc4', '' ),
			array( '/serve|volunteer/i', __( 'Serve', 'comms-church-home-screen' ), 'heart', '#d6546e', '' ),
			array( '/^(give|giving|donate|online giving)$/i', __( 'Give', 'comms-church-home-screen' ), 'heart', '#e08a2c', 'nocc' ),
			array( '/^(groups|small groups|life groups|community groups)$/i', __( 'Groups', 'comms-church-home-screen' ), 'people', '#3b82d6', 'nocc' ),
			array( '/belief|about us|who we are|our story/i', __( 'About', 'comms-church-home-screen' ), 'info', '#64748b', 'off' ),
			array( '/bulletin|newsletter/i', __( 'Bulletin', 'comms-church-home-screen' ), 'document', '#5b6474', 'off' ),
		);

		$cands = array();
		foreach ( wp_get_nav_menus() as $menu ) {
			foreach ( (array) wp_get_nav_menu_items( $menu->term_id ) as $item ) {
				$cands[] = array( 'title' => wp_strip_all_tags( $item->title ), 'url' => $item->url, 'src' => __( 'In your menu', 'comms-church-home-screen' ) );
			}
		}
		foreach ( get_pages( array( 'post_status' => 'publish', 'number' => 200 ) ) as $p ) {
			$cands[] = array( 'title' => $p->post_title, 'url' => get_permalink( $p ), 'src' => __( 'A page on your site', 'comms-church-home-screen' ) );
		}
		foreach ( $links as $l ) {
			if ( $l['text'] && strlen( $l['text'] ) < 30 ) {
				$cands[] = array( 'title' => $l['text'], 'url' => $l['href'], 'src' => __( 'Linked on your site', 'comms-church-home-screen' ) );
			}
		}

		$visit_done = (bool) $s['visit_url'];
		foreach ( $f as $prev ) {
			if ( ! empty( $prev['set']['visit_url'] ) ) {
				$visit_done = true;
			}
		}
		foreach ( $cands as $c ) {
			$url = $c['url'];
			if ( ! $url || '#' === $url[0] || preg_match( '#^(mailto|tel):#i', $url ) ) {
				continue;
			}
			if ( 0 === strpos( $url, '/' ) ) {
				$url = home_url( $url );
			}
			foreach ( $rules as $r ) {
				if ( ! preg_match( $r[0], $c['title'] ) ) {
					continue;
				}
				if ( 'visit' === $r[1] ) {
					if ( ! $visit_done ) {
						$visit_done = true;
						self::add( $f, 'details', __( 'Plan-a-visit link', 'comms-church-home-screen' ), $url, array( 'set' => array( 'visit_url' => esc_url_raw( $url ) ) ), $c['src'] . ': ' . $c['title'] );
					}
					break;
				}
				if ( 'nocc' === $r[4] && $cc ) {
					break; // Church Center already covers this.
				}
				$offer( $r[1], $r[2], $r[3], $url, $c['src'] . ': ' . $c['title'], 'off' !== $r[4] );
				break;
			}
		}

		// Sermon archives from sermon plugins.
		foreach ( get_post_types( array( 'public' => true, 'has_archive' => true ), 'objects' ) as $pt ) {
			if ( preg_match( '/sermon|message/i', $pt->name . ' ' . $pt->label ) ) {
				$offer( __( 'Sermons', 'comms-church-home-screen' ), 'book', '#2c3372', get_post_type_archive_link( $pt->name ), __( 'Sermon archive on your site', 'comms-church-home-screen' ), true );
			}
		}
	}

	/* ---------- Look ---------- */

	/**
	 * Brand color from the theme or page builder, and a site icon tip.
	 */
	private static function find_look( &$f, $s, $pages ) {
		$cands = array();

		if ( function_exists( 'wp_get_global_settings' ) ) {
			$palette = wp_get_global_settings( array( 'color', 'palette' ) );
			foreach ( array( 'custom', 'theme' ) as $origin ) {
				if ( ! empty( $palette[ $origin ] ) ) {
					foreach ( $palette[ $origin ] as $c ) {
						if ( ! empty( $c['color'] ) ) {
							$cands[] = array( 'hex' => $c['color'], 'name' => isset( $c['slug'] ) ? $c['slug'] : '' );
						}
					}
				}
			}
		}
		// Kadence global palette.
		$kad = json_decode( (string) get_option( 'kadence_global_palette' ), true );
		if ( is_array( $kad ) && ! empty( $kad['active'] ) && ! empty( $kad[ $kad['active'] ] ) ) {
			foreach ( $kad[ $kad['active'] ] as $c ) {
				if ( ! empty( $c['color'] ) ) {
					$cands[] = array( 'hex' => $c['color'], 'name' => isset( $c['slug'] ) ? $c['slug'] : '' );
				}
			}
		}
		// Bricks color palettes.
		foreach ( (array) get_option( 'bricks_color_palette', array() ) as $pal ) {
			if ( ! empty( $pal['colors'] ) ) {
				foreach ( (array) $pal['colors'] as $c ) {
					// Older palettes store "hex". Variable-based ones store "raw" (var(--brand-primary)
					// or a hex) plus "light"/"dark" values, and the variable name is the only name.
					$raw = isset( $c['raw'] ) ? (string) $c['raw'] : '';
					$hex = ! empty( $c['hex'] ) ? $c['hex'] : ( ! empty( $c['light'] ) ? $c['light'] : ( sanitize_hex_color( $raw ) ? $raw : '' ) );
					if ( $hex ) {
						$cands[] = array( 'hex' => $hex, 'name' => ! empty( $c['name'] ) ? $c['name'] : $raw );
					}
				}
			}
		}
		// The homepage's own theme-color tag.
		$home = reset( $pages );
		if ( $home && preg_match( '#<meta[^>]+name=["\']theme-color["\'][^>]+content=["\'](\#[0-9a-fA-F]{3,6})["\']#', $home, $m ) ) {
			$cands[] = array( 'hex' => $m[1], 'name' => 'theme-color' );
		}

		$best  = '';
		$score = -1;
		foreach ( $cands as $c ) {
			$hex = sanitize_hex_color( $c['hex'] );
			if ( ! $hex ) {
				continue;
			}
			list( $sat, $lum ) = self::sat_lum( $hex );
			if ( $sat < 0.2 || $lum < 0.015 || $lum > 0.55 ) {
				continue; // Skip whites, blacks, greys and pastels.
			}
			$sc = $sat + ( preg_match( '/primary|brand|accent|main/i', $c['name'] ) ? 1 : 0 );
			if ( $sc > $score ) {
				$score = $sc;
				$best  = $hex;
			}
		}
		if ( $best && strtolower( $best ) !== strtolower( $s['brand'] ) ) {
			self::add( $f, 'look', __( 'Brand color', 'comms-church-home-screen' ), $best, array( 'set' => array( 'brand' => $best ), 'swatch' => $best ), __( "From your theme's colors", 'comms-church-home-screen' ) );
		}

		if ( ! get_site_icon_url() ) {
			self::add( $f, 'look', __( 'Site icon', 'comms-church-home-screen' ), __( 'Not set', 'comms-church-home-screen' ), array( 'tip' => true ), __( 'Set one under Appearance → Customize → Site Identity (or Settings → General) so the home screen gets an app icon.', 'comms-church-home-screen' ), false );
		}
	}

	/**
	 * Saturation and relative luminance of a hex color.
	 *
	 * @param string $hex Color.
	 * @return float[]
	 */
	private static function sat_lum( $hex ) {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$r   = hexdec( substr( $hex, 0, 2 ) ) / 255;
		$g   = hexdec( substr( $hex, 2, 2 ) ) / 255;
		$b   = hexdec( substr( $hex, 4, 2 ) ) / 255;
		$max = max( $r, $g, $b );
		$min = min( $r, $g, $b );
		$l   = ( $max + $min ) / 2;
		$sat = $max === $min ? 0 : ( $l > 0.5 ? ( $max - $min ) / ( 2 - $max - $min ) : ( $max - $min ) / ( $max + $min ) );
		$lin = function ( $c ) {
			return $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
		};
		return array( $sat, 0.2126 * $lin( $r ) + 0.7152 * $lin( $g ) + 0.0722 * $lin( $b ) );
	}
}
