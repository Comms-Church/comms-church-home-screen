<?php
/**
 * Upcoming events from Planning Center (Church Center events), a calendar feed (iCal) or The Events Calendar,
 * and the events the church has marked Featured in Planning Center.
 *
 * @package ChurchLinks
 */

defined( 'ABSPATH' ) || exit;

/**
 * Event sources.
 */
class CHS_Events {

	const CACHE  = 'chs_events_cache';
	const AHEAD  = 180; // Days to look ahead when expanding repeating events.
	const MAXGEN = 400; // Safety cap on generated occurrences per event.
	const STATUS = 'chs_events_status';
	const PCO    = 'https://api.planningcenteronline.com/calendar/v2';

	const FEATURED        = 'chs_featured_cache';
	const FEATURED_STATUS = 'chs_featured_status';
	const FEATURED_TTL    = 15 * MINUTE_IN_SECONDS;
	const FEATURED_MAX    = 6;

	/**
	 * Upcoming events, soonest first.
	 *
	 * @param array $s Settings.
	 * @return array[] Each: title, start (timestamp), all_day, location, url.
	 */
	public static function get( $s ) {
		$count = max( 1, min( 10, (int) $s['events_count'] ) );

		if ( 'tec' === $s['events_source'] ) {
			return self::from_tec( $count );
		}

		$fetch = null;
		if ( 'pco' === $s['events_source'] ) {
			$fetch = function () use ( $s ) {
				return self::from_pco( self::pco_credentials( $s ) );
			};
		} elseif ( 'ics' === $s['events_source'] && $s['ics_url'] ) {
			$fetch = function () use ( $s ) {
				return self::from_ics( $s['ics_url'] );
			};
		}
		if ( $fetch ) {
			$events = get_transient( self::CACHE );
			if ( ! is_array( $events ) ) {
				$events = $fetch();
				if ( null === $events ) {
					// Feed unreachable: try again in 10 minutes instead of hammering it.
					set_transient( self::CACHE, array(), 10 * MINUTE_IN_SECONDS );
					return array();
				}
				set_transient( self::CACHE, $events, HOUR_IN_SECONDS );
			}
			return array_slice( self::upcoming( $events ), 0, $count );
		}

		return array();
	}

	/**
	 * Drop events that are already over and sort the rest.
	 *
	 * @param array $events Events.
	 * @return array
	 */
	public static function upcoming( $events ) {
		$now = time();
		$out = array_filter(
			$events,
			function ( $e ) use ( $now ) {
				// All-day events stay listed through the end of their day; timed events for two hours after they start.
				$grace = $e['all_day'] ? DAY_IN_SECONDS : 2 * HOUR_IN_SECONDS;
				return $e['start'] + $grace > $now;
			}
		);
		usort(
			$out,
			function ( $a, $b ) {
				return $a['start'] - $b['start'];
			}
		);
		return array_values( $out );
	}

	/**
	 * The Events Calendar plugin.
	 *
	 * @param int $count How many.
	 * @return array
	 */
	private static function from_tec( $count ) {
		if ( ! function_exists( 'tribe_get_events' ) ) {
			return array();
		}
		$posts = tribe_get_events(
			array(
				'posts_per_page' => $count,
				'start_date'     => current_time( 'Y-m-d H:i:s' ),
				'eventDisplay'   => 'list',
			)
		);
		$out   = array();
		foreach ( (array) $posts as $p ) {
			$utc = get_post_meta( $p->ID, '_EventStartDateUTC', true );
			if ( $utc ) {
				$ts = strtotime( $utc . ' UTC' );
			} else {
				$local = get_post_meta( $p->ID, '_EventStartDate', true );
				try {
					$ts = ( new DateTimeImmutable( $local, wp_timezone() ) )->getTimestamp();
				} catch ( Exception $e ) {
					continue;
				}
			}
			$out[] = array(
				'title'    => get_the_title( $p ),
				'start'    => (int) $ts,
				'all_day'  => function_exists( 'tribe_event_is_all_day' ) && tribe_event_is_all_day( $p->ID ),
				'location' => function_exists( 'tribe_get_venue' ) ? (string) tribe_get_venue( $p->ID ) : '',
				'url'      => get_permalink( $p ),
			);
		}
		return $out;
	}

	/**
	 * Planning Center API credentials: the ones entered here, else the ones saved by
	 * Comms.Church PCO, else Comms.Church PCO Events Sync (same key pair, all products).
	 *
	 * @param array $s Settings.
	 * @return array|null app_id, secret, from (plugin name or '').
	 */
	public static function pco_credentials( $s ) {
		if ( ! empty( $s['pco_app_id'] ) && ! empty( $s['pco_secret'] ) ) {
			return array( 'app_id' => $s['pco_app_id'], 'secret' => $s['pco_secret'], 'from' => '' );
		}
		$shared = array(
			array( 'CCPCO_API', 'ccpco_app_id', 'ccpco_secret', 'Comms.Church PCO' ),
			array( 'CCTEC_API', 'cctec_app_id', 'cctec_secret', 'Comms.Church PCO Events Sync' ),
		);
		foreach ( $shared as $c ) {
			if ( class_exists( $c[0] ) && get_option( $c[1] ) && get_option( $c[2] ) ) {
				return array( 'app_id' => (string) get_option( $c[1] ), 'secret' => (string) get_option( $c[2] ), 'from' => $c[3] );
			}
		}
		return null;
	}

	/**
	 * Upcoming instances of the events the church shows in Church Center
	 * (visible_in_church_center and approved), so staff meetings and private
	 * calendars never reach the public page.
	 *
	 * @param array|null $cred From pco_credentials().
	 * @return array|null Null when Planning Center can't be reached.
	 */
	private static function from_pco( $cred ) {
		if ( ! $cred ) {
			self::status( false, __( 'No Planning Center API key yet.', 'comms-church-links' ) );
			return null;
		}
		$get = function ( $url ) use ( $cred ) {
			return self::pco_get( $cred, $url );
		};

		// Which events Church Center shows. Most churches publish a handful of the
		// hundreds or thousands on their calendar.
		$events = array();
		$url    = '/events?per_page=100&filter=future&where[visible_in_church_center]=true';
		for ( $page = 0; $url && $page < 5; $page++ ) {
			$body = $get( $url );
			if ( is_wp_error( $body ) ) {
				self::status( false, $body->get_error_message() );
				return null;
			}
			foreach ( $body['data'] as $e ) {
				if ( isset( $e['attributes']['approval_status'] ) && 'A' === $e['attributes']['approval_status'] ) {
					$events[ $e['id'] ] = $e['attributes'];
				}
			}
			$url = isset( $body['links']['next'] ) ? $body['links']['next'] : '';
		}

		// Next few dates of each, one request per event (the instances endpoint can't
		// filter by event, and paging through every instance is slower for most churches).
		$out = array();
		foreach ( array_slice( $events, 0, 40, true ) as $id => $ev ) {
			$body = $get( '/events/' . rawurlencode( $id ) . '/event_instances?filter=future&order=starts_at&per_page=10' );
			if ( is_wp_error( $body ) ) {
				self::status( false, $body->get_error_message() );
				return null;
			}
			foreach ( $body['data'] as $in ) {
				$a  = $in['attributes'];
				$ts = isset( $a['starts_at'] ) ? strtotime( $a['starts_at'] ) : false;
				if ( ! $ts ) {
					continue;
				}
				$out[] = array(
					'title'    => isset( $a['name'] ) && $a['name'] ? $a['name'] : $ev['name'],
					'start'    => $ts,
					'all_day'  => ! empty( $a['all_day_event'] ),
					'location' => self::place_name( isset( $a['location'] ) ? (string) $a['location'] : '' ),
					'url'      => isset( $a['church_center_url'] ) ? esc_url_raw( $a['church_center_url'] ) : '',
				);
			}
		}
		usort(
			$out,
			function ( $a, $b ) {
				return $a['start'] - $b['start'];
			}
		);
		/* translators: %d: number of events */
		self::status( true, sprintf( _n( '%d event is shown in Church Center.', '%d events are shown in Church Center.', count( $events ), 'comms-church-links' ), count( $events ) ), $cred['from'] );
		return array_slice( $out, 0, 200 );
	}

	/**
	 * One Planning Center API request.
	 *
	 * @param array  $cred    From pco_credentials().
	 * @param string $url     Path under /calendar/v2, or a full next-page link.
	 * @param array  $headers Extra headers.
	 * @return array|WP_Error Decoded body.
	 */
	private static function pco_get( $cred, $url, $headers = array() ) {
		$headers['Authorization'] = 'Basic ' . base64_encode( $cred['app_id'] . ':' . $cred['secret'] );
		$res = wp_safe_remote_get( 0 === strpos( $url, 'https://' ) ? $url : self::PCO . $url, array( 'headers' => $headers, 'timeout' => 10 ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( 200 !== $code || ! is_array( $body ) ) {
			$msg = isset( $body['errors'][0]['detail'] ) ? $body['errors'][0]['detail'] : ( isset( $body['errors'][0]['title'] ) ? $body['errors'][0]['title'] : '' );
			/* translators: %d: HTTP status code */
			return new WP_Error( 'chs_pco', $msg ? $msg : sprintf( __( 'Planning Center answered with error %d.', 'comms-church-links' ), $code ) );
		}
		return $body;
	}

	/**
	 * What the church is promoting right now: the events marked Featured in Planning
	 * Center Calendar. Sign-ups inherit Featured from Registrations, so a featured
	 * registration shows up here too. Feature or unfeature it there and /links follows
	 * within FEATURED_TTL; nobody touches this plugin.
	 *
	 * @param array $s Settings.
	 * @return array[] Each: title, summary, image, start, all_day, url, cc. Soonest first.
	 */
	public static function featured( $s ) {
		if ( empty( $s['featured_on'] ) ) {
			return array();
		}
		$cred = self::pco_credentials( $s );
		if ( ! $cred ) {
			return array();
		}
		$items = get_transient( self::FEATURED );
		if ( ! is_array( $items ) ) {
			$items = self::featured_from_pco( $cred );
			if ( null === $items ) {
				set_transient( self::FEATURED, array(), 10 * MINUTE_IN_SECONDS );
				return array();
			}
			set_transient( self::FEATURED, $items, self::FEATURED_TTL );
		}
		// Each item carries its next few dates; show the first one that hasn't passed.
		$out = array();
		foreach ( $items as $it ) {
			foreach ( $it['dates'] as $d ) {
				if ( $d['start'] + ( $d['all_day'] ? DAY_IN_SECONDS : 2 * HOUR_IN_SECONDS ) > time() ) {
					unset( $it['dates'] );
					$out[] = array_merge( $it, $d );
					break;
				}
			}
		}
		usort(
			$out,
			function ( $a, $b ) {
				return $a['start'] - $b['start'];
			}
		);
		return array_slice( $out, 0, self::FEATURED_MAX );
	}

	/**
	 * Fetch featured, approved, Church Center events and their next dates.
	 *
	 * @param array $cred From pco_credentials().
	 * @return array|null Null when Planning Center can't be reached.
	 */
	private static function featured_from_pco( $cred ) {
		// featured is documented from API version 2022-07-07; ask for it by name so an
		// older default version on the key doesn't quietly drop the filter.
		$ver  = array( 'X-PCO-API-Version' => '2022-07-07' );
		$body = self::pco_get( $cred, '/events?per_page=25&filter=future&where[featured]=true&where[visible_in_church_center]=true', $ver );
		if ( is_wp_error( $body ) ) {
			self::featured_status( false, $body->get_error_message(), $cred['from'] );
			return null;
		}
		$out = array();
		foreach ( $body['data'] as $e ) {
			$a = $e['attributes'];
			// Checked again here, in case Planning Center ever ignores a filter.
			if ( empty( $a['featured'] ) || empty( $a['visible_in_church_center'] ) || ! isset( $a['approval_status'] ) || 'A' !== $a['approval_status'] ) {
				continue;
			}
			$inst = self::pco_get( $cred, '/events/' . rawurlencode( $e['id'] ) . '/event_instances?filter=future&order=starts_at&per_page=3', $ver );
			if ( is_wp_error( $inst ) ) {
				self::featured_status( false, $inst->get_error_message(), $cred['from'] );
				return null;
			}
			$dates = array();
			$page  = '';
			foreach ( $inst['data'] as $in ) {
				$ts = isset( $in['attributes']['starts_at'] ) ? strtotime( $in['attributes']['starts_at'] ) : false;
				if ( $ts ) {
					$dates[] = array( 'start' => $ts, 'all_day' => ! empty( $in['attributes']['all_day_event'] ) );
					if ( '' === $page && ! empty( $in['attributes']['church_center_url'] ) ) {
						$page = esc_url_raw( $in['attributes']['church_center_url'] );
					}
				}
			}
			if ( ! $dates ) {
				continue;
			}
			// A sign-up link is the action people were told about; the event page is the fallback.
			$url   = ! empty( $a['registration_url'] ) ? esc_url_raw( $a['registration_url'] ) : $page;
			$out[] = array(
				'title'   => sanitize_text_field( $a['name'] ),
				'summary' => isset( $a['summary'] ) ? wp_trim_words( sanitize_text_field( $a['summary'] ), 18 ) : '',
				'image'   => ! empty( $a['image_url'] ) ? esc_url_raw( $a['image_url'] ) : '',
				'url'     => $url,
				'cc'      => $url && CHS_Frontend::is_cc( $url ),
				'dates'   => $dates,
			);
			if ( count( $out ) >= 12 ) {
				break;
			}
		}
		/* translators: %d: number of featured events */
		self::featured_status( true, sprintf( _n( '%d featured event.', '%d featured events.', count( $out ), 'comms-church-links' ), count( $out ) ), $cred['from'] );
		return $out;
	}

	/**
	 * Remember how the last featured fetch went, for the settings page.
	 *
	 * @param bool   $ok   Worked.
	 * @param string $msg  Message.
	 * @param string $from Where the key came from.
	 */
	private static function featured_status( $ok, $msg, $from ) {
		update_option( self::FEATURED_STATUS, array( 'ok' => $ok, 'msg' => $msg, 'from' => $from, 'time' => time() ), false );
	}

	/**
	 * Planning Center stores locations as "Name - full address". Show the name only,
	 * the way Church Center does, so a small group's home address isn't published.
	 *
	 * @param string $location Location text.
	 * @return string
	 */
	public static function place_name( $location ) {
		$parts = explode( ' - ', $location, 2 );
		$name  = trim( $parts[0] );
		// A bare street address with no name: leave it out.
		if ( 1 === count( $parts ) && preg_match( '/^\d/', $name ) ) {
			return '';
		}
		return $name;
	}

	/**
	 * Remember how the last Planning Center fetch went, for the settings page.
	 *
	 * @param bool   $ok   Worked.
	 * @param string $msg  Message.
	 * @param string $from Where the key came from.
	 */
	private static function status( $ok, $msg, $from = '' ) {
		update_option( self::STATUS, array( 'ok' => $ok, 'msg' => $msg, 'from' => $from, 'time' => time() ), false );
	}

	/**
	 * Fetch and parse an iCal feed.
	 *
	 * @param string $url Feed URL.
	 * @return array|null Null when the feed can't be fetched.
	 */
	private static function from_ics( $url ) {
		$res = wp_safe_remote_get(
			$url,
			array(
				'timeout'    => 8,
				'user-agent' => 'Church Links/' . CHS_VERSION . '; ' . home_url(),
			)
		);
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			return null;
		}
		$body = wp_remote_retrieve_body( $res );
		if ( false === strpos( $body, 'BEGIN:VCALENDAR' ) ) {
			return null;
		}
		return self::parse_ics( $body );
	}

	/**
	 * Parse iCal text into events within the look-ahead window, expanding common repeat rules.
	 *
	 * @param string $body iCal text.
	 * @param int    $now  Reference time (for tests).
	 * @return array
	 */
	public static function parse_ics( $body, $now = 0 ) {
		$now   = $now ? $now : time();
		$until = $now + self::AHEAD * DAY_IN_SECONDS;
		$from  = $now - DAY_IN_SECONDS;

		$body  = str_replace( array( "\r\n", "\r" ), "\n", $body );
		$body  = preg_replace( "/\n[ \t]/", '', $body ); // Unfold continuation lines.
		$lines = explode( "\n", $body );

		$raw = array();
		$cur = null;
		foreach ( $lines as $line ) {
			if ( 'BEGIN:VEVENT' === $line ) {
				$cur = array( 'EXDATE' => array() );
				continue;
			}
			if ( 'END:VEVENT' === $line ) {
				if ( $cur ) {
					$raw[] = $cur;
				}
				$cur = null;
				continue;
			}
			if ( null === $cur || ! preg_match( '/^([A-Za-z0-9-]+)((?:;[^:]*)*):(.*)$/', $line, $m ) ) {
				continue;
			}
			$name   = strtoupper( $m[1] );
			$params = array();
			foreach ( array_filter( explode( ';', $m[2] ) ) as $p ) {
				$kv = explode( '=', $p, 2 );
				if ( 2 === count( $kv ) ) {
					$params[ strtoupper( $kv[0] ) ] = trim( $kv[1], '"' );
				}
			}
			$prop = array( 'value' => $m[3], 'params' => $params );
			if ( 'EXDATE' === $name ) {
				$cur['EXDATE'][] = $prop;
			} elseif ( ! isset( $cur[ $name ] ) ) {
				$cur[ $name ] = $prop;
			}
		}

		// Single edited occurrences of a repeating event (RECURRENCE-ID) replace the generated ones.
		$overrides = array();
		foreach ( $raw as $e ) {
			if ( isset( $e['RECURRENCE-ID'], $e['UID'] ) ) {
				$rid = self::parse_dt( $e['RECURRENCE-ID'] );
				if ( $rid ) {
					$overrides[ $e['UID']['value'] . '|' . $rid['ts'] ] = true;
				}
			}
		}

		$out = array();
		foreach ( $raw as $e ) {
			if ( ! isset( $e['DTSTART'] ) ) {
				continue;
			}
			if ( isset( $e['STATUS'] ) && 'CANCELLED' === strtoupper( $e['STATUS']['value'] ) ) {
				continue;
			}
			$start = self::parse_dt( $e['DTSTART'] );
			if ( ! $start ) {
				continue;
			}
			$base = array(
				'title'    => isset( $e['SUMMARY'] ) ? self::unescape( $e['SUMMARY']['value'] ) : '',
				'all_day'  => $start['all_day'],
				'location' => isset( $e['LOCATION'] ) ? self::unescape( $e['LOCATION']['value'] ) : '',
				'url'      => isset( $e['URL'] ) ? esc_url_raw( $e['URL']['value'] ) : '',
			);
			if ( '' === $base['title'] ) {
				continue;
			}

			$times = array( $start['ts'] );
			if ( isset( $e['RRULE'] ) && ! isset( $e['RECURRENCE-ID'] ) ) {
				$skip = array();
				foreach ( $e['EXDATE'] as $ex ) {
					foreach ( explode( ',', $ex['value'] ) as $v ) {
						$d = self::parse_dt( array( 'value' => $v, 'params' => $ex['params'] ) );
						if ( $d ) {
							$skip[ $d['ts'] ] = true;
						}
					}
				}
				$uid   = isset( $e['UID'] ) ? $e['UID']['value'] : '';
				$times = array();
				foreach ( self::expand( $start['dt'], $e['RRULE']['value'], $until ) as $ts ) {
					if ( isset( $skip[ $ts ] ) || isset( $overrides[ $uid . '|' . $ts ] ) ) {
						continue;
					}
					$times[] = $ts;
				}
			}

			foreach ( $times as $ts ) {
				if ( $ts >= $from && $ts <= $until ) {
					$out[] = array_merge( $base, array( 'start' => $ts ) );
				}
			}
		}

		usort(
			$out,
			function ( $a, $b ) {
				return $a['start'] - $b['start'];
			}
		);
		return array_slice( $out, 0, 200 );
	}

	/**
	 * Parse an iCal date or date-time.
	 *
	 * @param array $prop value + params.
	 * @return array|null ts, all_day, dt (DateTimeImmutable in the event's own time zone).
	 */
	private static function parse_dt( $prop ) {
		$v       = trim( $prop['value'] );
		$all_day = ( isset( $prop['params']['VALUE'] ) && 'DATE' === strtoupper( $prop['params']['VALUE'] ) ) || preg_match( '/^\d{8}$/', $v );
		$tz      = wp_timezone();
		if ( 'Z' === substr( $v, -1 ) ) {
			$tz = new DateTimeZone( 'UTC' );
			$v  = substr( $v, 0, -1 );
		} elseif ( ! empty( $prop['params']['TZID'] ) ) {
			try {
				$tz = new DateTimeZone( $prop['params']['TZID'] );
			} catch ( Exception $e ) {
				$tz = wp_timezone();
			}
		}
		$dt = $all_day
			? DateTimeImmutable::createFromFormat( '!Ymd', substr( $v, 0, 8 ), wp_timezone() )
			: DateTimeImmutable::createFromFormat( 'Ymd\THis', $v, $tz );
		if ( ! $dt ) {
			return null;
		}
		if ( ! $all_day ) {
			// Show times in the church's own time zone.
			$dt = $dt->setTimezone( wp_timezone() );
		}
		return array( 'ts' => $dt->getTimestamp(), 'all_day' => (bool) $all_day, 'dt' => $dt );
	}

	/**
	 * Expand DAILY, WEEKLY (with BYDAY), MONTHLY (by date or by "2nd Sunday") and YEARLY rules.
	 *
	 * @param DateTimeImmutable $start First occurrence.
	 * @param string            $rule  RRULE value.
	 * @param int               $until Stop after this timestamp.
	 * @return int[]
	 */
	private static function expand( DateTimeImmutable $start, $rule, $until ) {
		$r = array();
		foreach ( explode( ';', strtoupper( $rule ) ) as $part ) {
			$kv = explode( '=', $part, 2 );
			if ( 2 === count( $kv ) ) {
				$r[ $kv[0] ] = $kv[1];
			}
		}
		$freq     = isset( $r['FREQ'] ) ? $r['FREQ'] : '';
		$interval = max( 1, isset( $r['INTERVAL'] ) ? (int) $r['INTERVAL'] : 1 );
		$count    = isset( $r['COUNT'] ) ? (int) $r['COUNT'] : 0;
		if ( isset( $r['UNTIL'] ) ) {
			$u = self::parse_dt( array( 'value' => $r['UNTIL'], 'params' => array() ) );
			if ( $u ) {
				$until = min( $until, $u['all_day'] ? $u['ts'] + DAY_IN_SECONDS - 1 : $u['ts'] );
			}
		}
		$days  = array( 'SU' => 0, 'MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6 );
		$names = array( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' );
		$time  = $start->format( 'H:i:s' );
		$out   = array();
		$n     = 0;

		$emit = function ( DateTimeImmutable $d ) use ( &$out, &$n, $count, $until, $start ) {
			if ( $d < $start ) {
				return true;
			}
			if ( $d->getTimestamp() > $until || ( $count && $n >= $count ) ) {
				return false;
			}
			++$n;
			$out[] = $d->getTimestamp();
			return true;
		};

		$byday = array();
		if ( ! empty( $r['BYDAY'] ) ) {
			foreach ( explode( ',', $r['BYDAY'] ) as $b ) {
				if ( preg_match( '/^([+-]?\d{1,2})?(SU|MO|TU|WE|TH|FR|SA)$/', $b, $m ) ) {
					$byday[] = array( 'nth' => isset( $m[1] ) && '' !== $m[1] ? (int) $m[1] : 0, 'day' => $days[ $m[2] ] );
				}
			}
		}

		for ( $step = 0; $step < self::MAXGEN; $step++ ) {
			if ( 'DAILY' === $freq ) {
				$d = $start->modify( '+' . ( $step * $interval ) . ' days' );
				if ( ! $emit( $d ) ) {
					break;
				}
			} elseif ( 'WEEKLY' === $freq ) {
				$week_start = $start->modify( '-' . (int) $start->format( 'w' ) . ' days' )->modify( '+' . ( $step * $interval ) . ' weeks' );
				$list       = $byday ? wp_list_pluck( $byday, 'day' ) : array( (int) $start->format( 'w' ) );
				sort( $list );
				$go = true;
				foreach ( $list as $wd ) {
					if ( ! $emit( $week_start->modify( "+$wd days" ) ) ) {
						$go = false;
						break;
					}
				}
				if ( ! $go || $week_start->getTimestamp() > $until ) {
					break;
				}
			} elseif ( 'MONTHLY' === $freq ) {
				$month = $start->modify( 'first day of this month' )->modify( '+' . ( $step * $interval ) . ' months' );
				if ( $month->getTimestamp() > $until + 31 * DAY_IN_SECONDS ) {
					break;
				}
				$cands = array();
				if ( $byday ) {
					foreach ( $byday as $b ) {
						$ord = array( 1 => 'first', 2 => 'second', 3 => 'third', 4 => 'fourth', 5 => 'fifth', -1 => 'last' );
						if ( ! isset( $ord[ $b['nth'] ] ) ) {
							continue;
						}
						$d = $month->modify( $ord[ $b['nth'] ] . ' ' . $names[ $b['day'] ] . ' of this month' )->modify( $time );
						if ( $d->format( 'Ym' ) === $month->format( 'Ym' ) ) {
							$cands[] = $d;
						}
					}
				} else {
					$day = (int) $start->format( 'j' );
					if ( $day <= (int) $month->format( 't' ) ) {
						$cands[] = $month->setDate( (int) $month->format( 'Y' ), (int) $month->format( 'n' ), $day )->modify( $time );
					}
				}
				usort(
					$cands,
					function ( $a, $b ) {
						return $a <=> $b;
					}
				);
				$go = true;
				foreach ( $cands as $d ) {
					if ( ! $emit( $d ) ) {
						$go = false;
						break;
					}
				}
				if ( ! $go ) {
					break;
				}
			} elseif ( 'YEARLY' === $freq ) {
				$d = $start->modify( '+' . ( $step * $interval ) . ' years' );
				if ( $d->format( 'md' ) === $start->format( 'md' ) && ! $emit( $d ) ) {
					break;
				}
			} else {
				$emit( $start );
				break;
			}
		}
		return $out;
	}

	/**
	 * Undo iCal text escaping.
	 *
	 * @param string $v Value.
	 * @return string
	 */
	private static function unescape( $v ) {
		$v = str_replace( array( '\\n', '\\N', '\\,', '\\;', '\\\\' ), array( ' ', ' ', ',', ';', '\\' ), $v );
		return sanitize_text_field( $v );
	}
}
