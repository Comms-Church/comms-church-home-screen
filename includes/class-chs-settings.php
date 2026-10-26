<?php
/**
 * Settings: defaults, sanitizing and the admin page.
 *
 * @package ChurchHomeScreen
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings handler.
 */
class CHS_Settings {

	const OPTION = 'chs_settings';
	const GROUP  = 'chs_settings_group';
	const PAGE   = 'church-home-screen';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'welcome_notice' ) );
	}

	/**
	 * After activation, point to the settings page until it has been opened once.
	 */
	public static function welcome_notice() {
		if ( ! get_option( 'chs_welcome' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen && 'settings_page_' . self::PAGE === $screen->id ) {
			return;
		}
		printf(
			'<div class="notice notice-info"><p><strong>%1$s</strong> %2$s <a href="%3$s">%4$s</a></p></div>',
			esc_html__( 'Church Home Screen is active.', 'comms-church-home-screen' ),
			esc_html__( 'We can fill in most of the setup from your website.', 'comms-church-home-screen' ),
			esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ),
			esc_html__( 'Set up your home screen', 'comms-church-home-screen' )
		);
	}

	/**
	 * App types the church can add. "kind" decides how the app behaves:
	 * sheet = opens a panel on the screen, cc = Church Center link, link = plain link, app = Church Center app download.
	 *
	 * @return array
	 */
	public static function app_types() {
		return array(
			'visit'            => array( 'name' => __( 'Plan a Visit (service times panel)', 'comms-church-home-screen' ), 'label' => __( 'Visit', 'comms-church-home-screen' ), 'icon' => 'pin', 'color' => '#1f8a7d', 'kind' => 'sheet' ),
			'events'           => array( 'name' => __( 'Events (upcoming events panel)', 'comms-church-home-screen' ), 'label' => __( 'Events', 'comms-church-home-screen' ), 'icon' => 'today', 'color' => '#ffffff', 'kind' => 'sheet' ),
			'contact'          => array( 'name' => __( 'Contact (phone & email panel)', 'comms-church-home-screen' ), 'label' => __( 'Contact', 'comms-church-home-screen' ), 'icon' => 'phone', 'color' => '#2e9e52', 'kind' => 'sheet' ),
			'directions'       => array( 'name' => __( 'Directions (opens maps)', 'comms-church-home-screen' ), 'label' => __( 'Directions', 'comms-church-home-screen' ), 'icon' => 'navigate', 'color' => '#3d5fe0', 'kind' => 'link' ),
			'cc_giving'        => array( 'name' => __( 'Church Center: Giving', 'comms-church-home-screen' ), 'label' => __( 'Give', 'comms-church-home-screen' ), 'icon' => 'heart', 'color' => '#e08a2c', 'kind' => 'cc', 'path' => '/giving' ),
			'cc_groups'        => array( 'name' => __( 'Church Center: Groups', 'comms-church-home-screen' ), 'label' => __( 'Groups', 'comms-church-home-screen' ), 'icon' => 'people', 'color' => '#3b82d6', 'kind' => 'cc', 'path' => '/groups' ),
			'cc_registrations' => array( 'name' => __( 'Church Center: Sign-ups', 'comms-church-home-screen' ), 'label' => __( 'Sign Ups', 'comms-church-home-screen' ), 'icon' => 'check', 'color' => '#8a5cd0', 'kind' => 'cc', 'path' => '/registrations' ),
			'cc_calendar'      => array( 'name' => __( 'Church Center: Calendar', 'comms-church-home-screen' ), 'label' => __( 'Calendar', 'comms-church-home-screen' ), 'icon' => 'today', 'color' => '#ffffff', 'kind' => 'cc', 'path' => '/calendar' ),
			'cc_home'          => array( 'name' => __( 'Church Center: Home', 'comms-church-home-screen' ), 'label' => __( 'Church Center', 'comms-church-home-screen' ), 'icon' => 'church', 'color' => '#2c3372', 'kind' => 'cc', 'path' => '' ),
			'cc_app'           => array( 'name' => __( 'Church Center: Get the app', 'comms-church-home-screen' ), 'label' => __( 'Get the App', 'comms-church-home-screen' ), 'icon' => 'download', 'color' => '#1c1e33', 'kind' => 'app' ),
			'link'             => array( 'name' => __( 'Custom link', 'comms-church-home-screen' ), 'label' => __( 'Link', 'comms-church-home-screen' ), 'icon' => 'link', 'color' => '#64748b', 'kind' => 'link' ),
		);
	}

	/**
	 * Default app layout for a fresh install.
	 *
	 * @return array
	 */
	public static function default_apps() {
		$rows  = array(
			array( 'visit', 'grid' ),
			array( 'events', 'grid' ),
			array( 'cc_giving', 'grid' ),
			array( 'cc_groups', 'grid' ),
			array( 'cc_registrations', 'grid' ),
			array( 'cc_app', 'grid' ),
			array( 'contact', 'dock' ),
			array( 'directions', 'dock' ),
		);
		$types = self::app_types();
		$apps  = array();
		foreach ( $rows as $r ) {
			$t      = $types[ $r[0] ];
			$apps[] = array(
				'type'  => $r[0],
				'label' => $t['label'],
				'icon'  => $t['icon'],
				'color' => $t['color'],
				'url'   => '',
				'place' => $r[1],
			);
		}
		$apps[] = array( 'type' => 'link', 'label' => __( 'Watch', 'comms-church-home-screen' ), 'icon' => 'play', 'color' => '#d9483b', 'url' => '', 'place' => 'dock' );
		$apps[] = array( 'type' => 'link', 'label' => __( 'Sermons', 'comms-church-home-screen' ), 'icon' => 'book', 'color' => '#2c3372', 'url' => '', 'place' => 'grid' );
		return $apps;
	}

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'path'            => 'links',
			'church_name'     => get_bloginfo( 'name' ),
			'short_name'      => '',
			'location'        => '',
			'address'         => '',
			'phone'           => '',
			'email'           => '',
			'services'        => array(
				array( 'day' => 0, 'time' => '10:00 AM', 'label' => __( 'Worship', 'comms-church-home-screen' ) ),
			),
			'expect'          => __( "Come as you are.\nWorship runs about an hour, with singing, prayer and teaching from the Bible.\nKids programs run during the service.", 'comms-church-home-screen' ),
			'visit_url'       => '',
			'note_body'       => __( 'Come as you are. We would love to meet you.', 'comms-church-home-screen' ),
			'verse_on'        => 1,
			'verse_text'      => 'Come to me, all you who labor and are heavily burdened, and I will give you rest.',
			'verse_ref'       => 'Matthew 11:28 (WEB)',
			'wallpaper'       => 'dawn',
			'wallpaper_image' => 0,
			'steeple'         => 1,
			'brand'           => '#2c3372',
			'cc_subdomain'    => '',
			'cc_modal'        => 1,
			'events_source'   => 'none',
			'ics_url'         => '',
			'events_count'    => 5,
			'apps'            => self::default_apps(),
		);
	}

	/**
	 * Current settings merged over defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$saved = get_option( self::OPTION, array() );
		$s     = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		if ( ! is_array( $s['services'] ) ) {
			$s['services'] = array();
		}
		if ( ! is_array( $s['apps'] ) ) {
			$s['apps'] = array();
		}
		return $s;
	}

	/**
	 * Church Center base URL, or '' when no subdomain is set.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	public static function cc_base( $s ) {
		return $s['cc_subdomain'] ? 'https://' . $s['cc_subdomain'] . '.churchcenter.com' : '';
	}

	/**
	 * Admin menu.
	 */
	public static function menu() {
		add_options_page(
			__( 'Church Home Screen', 'comms-church-home-screen' ),
			__( 'Home Screen', 'comms-church-home-screen' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Register the option.
	 */
	public static function register() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Admin assets.
	 *
	 * @param string $hook Current admin page.
	 */
	public static function assets( $hook ) {
		if ( 'settings_page_' . self::PAGE !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'chs-admin', CHS_URL . 'assets/admin.css', array(), CHS_VERSION );
		wp_enqueue_script( 'chs-admin', CHS_URL . 'assets/admin.js', array( 'jquery', 'jquery-ui-sortable' ), CHS_VERSION, true );
		$types = array();
		foreach ( self::app_types() as $key => $t ) {
			$types[ $key ] = array(
				'label' => $t['label'],
				'icon'  => $t['icon'],
				'color' => $t['color'],
				'kind'  => $t['kind'],
			);
		}
		wp_localize_script(
			'chs-admin',
			'CHSAdmin',
			array(
				'types'      => $types,
				'chooseText' => __( 'Choose wallpaper', 'comms-church-home-screen' ),
				'useText'    => __( 'Use this photo', 'comms-church-home-screen' ),
			)
		);
	}

	/**
	 * Sanitize everything that comes in from the form.
	 *
	 * @param mixed $in Raw input.
	 * @return array
	 */
	public static function sanitize( $in ) {
		$in  = is_array( $in ) ? wp_unslash( $in ) : array();
		$old = self::get();
		$d   = self::defaults();
		$out = array();

		$path        = isset( $in['path'] ) ? sanitize_title( $in['path'] ) : $d['path'];
		$out['path'] = $path ? $path : $d['path'];
		if ( $out['path'] !== $old['path'] ) {
			update_option( 'chs_flush_rewrite', 1 );
		}

		foreach ( array( 'church_name', 'short_name', 'location', 'address', 'phone', 'verse_ref' ) as $k ) {
			$out[ $k ] = isset( $in[ $k ] ) ? sanitize_text_field( $in[ $k ] ) : '';
		}
		foreach ( array( 'expect', 'verse_text', 'note_body' ) as $k ) {
			$out[ $k ] = isset( $in[ $k ] ) ? sanitize_textarea_field( $in[ $k ] ) : '';
		}
		$out['email']     = isset( $in['email'] ) ? sanitize_email( $in['email'] ) : '';
		$out['visit_url'] = isset( $in['visit_url'] ) ? esc_url_raw( $in['visit_url'] ) : '';

		$out['services'] = array();
		if ( isset( $in['services'] ) && is_array( $in['services'] ) ) {
			foreach ( $in['services'] as $row ) {
				$time = isset( $row['time'] ) ? sanitize_text_field( $row['time'] ) : '';
				if ( '' === $time ) {
					continue;
				}
				$out['services'][] = array(
					'day'   => min( 6, max( 0, isset( $row['day'] ) ? (int) $row['day'] : 0 ) ),
					'time'  => $time,
					'label' => isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '',
				);
			}
		}

		$out['verse_on'] = empty( $in['verse_on'] ) ? 0 : 1;
		$out['steeple']  = empty( $in['steeple'] ) ? 0 : 1;
		$out['cc_modal'] = empty( $in['cc_modal'] ) ? 0 : 1;

		$out['wallpaper']       = isset( $in['wallpaper'] ) && in_array( $in['wallpaper'], array( 'dawn', 'brand', 'image' ), true ) ? $in['wallpaper'] : 'dawn';
		$out['wallpaper_image'] = isset( $in['wallpaper_image'] ) ? absint( $in['wallpaper_image'] ) : 0;
		if ( 'image' === $out['wallpaper'] && ! $out['wallpaper_image'] ) {
			$out['wallpaper'] = 'dawn';
		}
		$brand        = isset( $in['brand'] ) ? sanitize_hex_color( $in['brand'] ) : '';
		$out['brand'] = $brand ? $brand : $d['brand'];

		$out['cc_subdomain'] = self::clean_subdomain( isset( $in['cc_subdomain'] ) ? $in['cc_subdomain'] : '' );

		$source               = isset( $in['events_source'] ) ? $in['events_source'] : 'none';
		$out['events_source'] = in_array( $source, array( 'none', 'ics', 'tec' ), true ) ? $source : 'none';
		$ics                  = isset( $in['ics_url'] ) ? trim( $in['ics_url'] ) : '';
		$ics                  = preg_replace( '#^webcals?://#i', 'https://', $ics );
		$out['ics_url']       = esc_url_raw( $ics, array( 'http', 'https' ) );
		$out['events_count']  = min( 10, max( 1, isset( $in['events_count'] ) ? (int) $in['events_count'] : 5 ) );

		$out['apps'] = array();
		$types       = self::app_types();
		$icons       = chs_icons();
		$dock        = 0;
		if ( isset( $in['apps'] ) && is_array( $in['apps'] ) ) {
			foreach ( $in['apps'] as $row ) {
				$type = isset( $row['type'] ) ? $row['type'] : '';
				if ( ! isset( $types[ $type ] ) ) {
					continue;
				}
				$t     = $types[ $type ];
				$label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
				$icon  = isset( $row['icon'] ) && isset( $icons[ $row['icon'] ] ) ? $row['icon'] : $t['icon'];
				$color = isset( $row['color'] ) ? sanitize_hex_color( $row['color'] ) : '';
				$place = isset( $row['place'] ) && 'dock' === $row['place'] ? 'dock' : 'grid';
				if ( 'dock' === $place ) {
					++$dock;
					if ( $dock > 4 ) {
						$place = 'grid';
					}
				}
				$out['apps'][] = array(
					'type'  => $type,
					'label' => '' !== $label ? mb_substr( $label, 0, 24 ) : $t['label'],
					'icon'  => $icon,
					'color' => $color ? $color : $t['color'],
					'url'   => isset( $row['url'] ) ? esc_url_raw( trim( $row['url'] ) ) : '',
					'place' => $place,
				);
			}
		}

		delete_transient( CHS_Events::CACHE );
		return $out;
	}

	/**
	 * Accept "mychurch", "mychurch.churchcenter.com" or a full Church Center URL.
	 *
	 * @param string $raw Raw input.
	 * @return string
	 */
	public static function clean_subdomain( $raw ) {
		$raw = strtolower( trim( (string) $raw ) );
		$raw = preg_replace( '#^[a-z]+://#', '', $raw );
		$raw = preg_replace( '#[/?\#].*$#', '', $raw );
		$raw = preg_replace( '#\.churchcenter\.com$#', '', $raw );
		return preg_replace( '/[^a-z0-9-]/', '', $raw );
	}

	/**
	 * Settings page.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s        = self::get();
		$o        = self::OPTION;
		$url      = CHS_Frontend::url();
		$days     = self::weekdays();
		$tec      = function_exists( 'tribe_get_events' );
		$wall_src = $s['wallpaper_image'] ? wp_get_attachment_image_url( $s['wallpaper_image'], 'medium' ) : '';

		// Review a scan the church just ran, or scan automatically the first time anyone opens this page.
		$review = null;
		$first  = false;
		if ( isset( $_GET['chs_review'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- display only.
			$review = get_transient( CHS_Scanner::key() );
		} elseif ( null === get_option( self::OPTION, null ) && ! get_option( 'chs_autoscanned' ) ) {
			update_option( 'chs_autoscanned', 1, false );
			$review = CHS_Scanner::scan();
			$first  = true;
			set_transient( CHS_Scanner::key(), $review, HOUR_IN_SECONDS );
		}
		delete_option( 'chs_welcome' );
		$applied = isset( $_GET['chs_applied'] ) ? absint( $_GET['chs_applied'] ) : null; // phpcs:ignore WordPress.Security.NonceVerification -- display only.
		?>
		<div class="wrap chs-admin">
			<h1><?php esc_html_e( 'Church Home Screen', 'comms-church-home-screen' ); ?></h1>

			<div class="chs-hero">
				<div>
					<p class="chs-eyebrow"><?php esc_html_e( 'Your home screen is live at', 'comms-church-home-screen' ); ?></p>
					<p class="chs-url"><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $url ); ?></a></p>
					<p class="description"><?php esc_html_e( 'Put this link in your Instagram and Facebook bios, on slides, and behind a QR code in the bulletin.', 'comms-church-home-screen' ); ?></p>
				</div>
				<a class="button button-primary button-hero" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open home screen', 'comms-church-home-screen' ); ?></a>
			</div>

			<?php if ( null !== $applied ) : ?>
				<div class="notice notice-success is-dismissible"><p>
					<?php
					echo esc_html(
						$applied
							/* translators: %d: number of settings */
							? sprintf( _n( 'Filled in %d setting from your site. Look it over below.', 'Filled in %d settings from your site. Look them over below.', $applied, 'comms-church-home-screen' ), $applied )
							: __( 'Nothing was selected, so nothing changed.', 'comms-church-home-screen' )
					);
					?>
				</p></div>
			<?php endif; ?>

			<?php
			if ( is_array( $review ) ) {
				self::render_review( $review, $first );
				echo '</div>';
				return;
			}
			?>

			<section class="chs-card chs-scan">
				<div>
					<h2><?php esc_html_e( 'Auto-fill from your site', 'comms-church-home-screen' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Reads your homepage, menus and pages for service times, contact details, your Church Center address, social links and pages worth adding as apps. You choose what to keep.', 'comms-church-home-screen' ); ?></p>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="chs_scan">
					<?php wp_nonce_field( 'chs_scan' ); ?>
					<button type="submit" class="button button-secondary"><?php esc_html_e( 'Scan my site', 'comms-church-home-screen' ); ?></button>
				</form>
			</section>

			<form method="post" action="options.php" id="chs-form">
				<?php settings_fields( self::GROUP ); ?>

				<section class="chs-card">
					<h2><?php esc_html_e( 'Church details', 'comms-church-home-screen' ); ?></h2>
					<div class="chs-grid">
						<?php
						self::text( 'church_name', __( 'Church name', 'comms-church-home-screen' ), $s );
						self::text( 'short_name', __( 'Short name', 'comms-church-home-screen' ), $s, __( 'Shown on lock-screen notifications. Leave blank to use the church name.', 'comms-church-home-screen' ) );
						self::text( 'location', __( 'Town and state', 'comms-church-home-screen' ), $s, __( 'For example: Willow Street, PA', 'comms-church-home-screen' ) );
						self::text( 'address', __( 'Street address', 'comms-church-home-screen' ), $s, __( 'Used for the Directions app.', 'comms-church-home-screen' ) );
						self::text( 'phone', __( 'Phone', 'comms-church-home-screen' ), $s );
						self::text( 'email', __( 'Email', 'comms-church-home-screen' ), $s, '', 'email' );
						?>
					</div>
				</section>

				<section class="chs-card">
					<h2><?php esc_html_e( 'Services', 'comms-church-home-screen' ); ?></h2>
					<p class="description"><?php esc_html_e( 'The lock screen and the service widget show whichever service comes next.', 'comms-church-home-screen' ); ?></p>
					<table class="chs-services">
						<thead><tr><th><?php esc_html_e( 'Day', 'comms-church-home-screen' ); ?></th><th><?php esc_html_e( 'Time', 'comms-church-home-screen' ); ?></th><th><?php esc_html_e( 'Name', 'comms-church-home-screen' ); ?></th></tr></thead>
						<tbody>
						<?php
						for ( $i = 0; $i < 4; $i++ ) :
							$row = isset( $s['services'][ $i ] ) ? $s['services'][ $i ] : array( 'day' => 0, 'time' => '', 'label' => '' );
							?>
							<tr>
								<td><select name="<?php echo esc_attr( "{$o}[services][$i][day]" ); ?>" aria-label="<?php esc_attr_e( 'Day', 'comms-church-home-screen' ); ?>">
									<?php foreach ( $days as $n => $day ) : ?>
										<option value="<?php echo esc_attr( $n ); ?>" <?php selected( (int) $row['day'], $n ); ?>><?php echo esc_html( $day ); ?></option>
									<?php endforeach; ?>
								</select></td>
								<td><input type="text" name="<?php echo esc_attr( "{$o}[services][$i][time]" ); ?>" value="<?php echo esc_attr( $row['time'] ); ?>" placeholder="10:30 AM" aria-label="<?php esc_attr_e( 'Time', 'comms-church-home-screen' ); ?>"></td>
								<td><input type="text" name="<?php echo esc_attr( "{$o}[services][$i][label]" ); ?>" value="<?php echo esc_attr( $row['label'] ); ?>" placeholder="<?php esc_attr_e( 'Worship', 'comms-church-home-screen' ); ?>" aria-label="<?php esc_attr_e( 'Name', 'comms-church-home-screen' ); ?>"></td>
							</tr>
						<?php endfor; ?>
						</tbody>
					</table>
					<div class="chs-grid">
						<?php
						self::textarea( 'expect', __( 'What to expect', 'comms-church-home-screen' ), $s, __( 'One point per line. Shown in the Visit app.', 'comms-church-home-screen' ) );
						self::textarea( 'note_body', __( 'Lock-screen message', 'comms-church-home-screen' ), $s, __( 'Shown under the next service on the lock screen.', 'comms-church-home-screen' ) );
						self::text( 'visit_url', __( 'Plan-a-visit form link (optional)', 'comms-church-home-screen' ), $s, __( 'A Church Center form works well here.', 'comms-church-home-screen' ), 'url' );
						?>
					</div>
				</section>

				<section class="chs-card">
					<h2><?php esc_html_e( 'Church Center', 'comms-church-home-screen' ); ?></h2>
					<label class="chs-field">
						<span><?php esc_html_e( 'Church Center address', 'comms-church-home-screen' ); ?></span>
						<span class="chs-suffix"><span>https://</span><input type="text" name="<?php echo esc_attr( $o ); ?>[cc_subdomain]" value="<?php echo esc_attr( $s['cc_subdomain'] ); ?>" placeholder="yourchurch"><span>.churchcenter.com</span></span>
						<small><?php esc_html_e( 'Turns on the Church Center apps below: Giving, Groups, Sign-ups, Calendar, Home and Get the App.', 'comms-church-home-screen' ); ?></small>
					</label>
					<label class="chs-check">
						<input type="checkbox" name="<?php echo esc_attr( $o ); ?>[cc_modal]" value="1" <?php checked( $s['cc_modal'] ); ?>>
						<?php esc_html_e( 'On computers, open Church Center in a pop-up instead of a new tab', 'comms-church-home-screen' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'On phones, Church Center links open in the Church Center app when it is installed, and in the browser when it is not.', 'comms-church-home-screen' ); ?></p>
				</section>

				<section class="chs-card">
					<h2><?php esc_html_e( 'Events', 'comms-church-home-screen' ); ?></h2>
					<fieldset class="chs-radios">
						<label><input type="radio" name="<?php echo esc_attr( $o ); ?>[events_source]" value="none" <?php checked( $s['events_source'], 'none' ); ?>> <?php esc_html_e( 'No event list (the Events app links to your Church Center calendar)', 'comms-church-home-screen' ); ?></label>
						<label><input type="radio" name="<?php echo esc_attr( $o ); ?>[events_source]" value="ics" <?php checked( $s['events_source'], 'ics' ); ?>> <?php esc_html_e( 'Calendar feed (iCal link from Planning Center Calendar, Google Calendar and others)', 'comms-church-home-screen' ); ?></label>
						<label><input type="radio" name="<?php echo esc_attr( $o ); ?>[events_source]" value="tec" <?php checked( $s['events_source'], 'tec' ); ?> <?php disabled( ! $tec ); ?>> <?php esc_html_e( 'The Events Calendar plugin on this site', 'comms-church-home-screen' ); ?><?php echo $tec ? '' : ' <em>' . esc_html__( '(not installed)', 'comms-church-home-screen' ) . '</em>'; ?></label>
					</fieldset>
					<div class="chs-grid">
						<?php
						self::text( 'ics_url', __( 'Calendar feed link', 'comms-church-home-screen' ), $s, __( 'Starts with webcal:// or https:// and usually ends in .ics. Refreshed every hour.', 'comms-church-home-screen' ), 'url' );
						?>
						<label class="chs-field">
							<span><?php esc_html_e( 'How many events to show', 'comms-church-home-screen' ); ?></span>
							<input type="number" min="1" max="10" name="<?php echo esc_attr( $o ); ?>[events_count]" value="<?php echo esc_attr( $s['events_count'] ); ?>">
						</label>
					</div>
				</section>

				<section class="chs-card">
					<h2><?php esc_html_e( 'Apps', 'comms-church-home-screen' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Drag to reorder. Up to four apps fit in the dock. Apps with no link yet, or Church Center apps without a Church Center address, stay hidden.', 'comms-church-home-screen' ); ?></p>
					<div id="chs-apps" class="chs-apps">
						<?php
						foreach ( $s['apps'] as $i => $app ) {
							self::app_row( $i, $app );
						}
						?>
					</div>
					<script type="text/html" id="tmpl-chs-app-row"><?php self::app_row( '__i__', array( 'type' => 'link', 'label' => '', 'icon' => 'link', 'color' => '#64748b', 'url' => '', 'place' => 'grid' ) ); ?></script>
					<p><button type="button" class="button" id="chs-add-app"><?php esc_html_e( 'Add app', 'comms-church-home-screen' ); ?></button></p>
				</section>

				<section class="chs-card">
					<h2><?php esc_html_e( 'Look', 'comms-church-home-screen' ); ?></h2>
					<fieldset class="chs-radios">
						<legend><?php esc_html_e( 'Wallpaper', 'comms-church-home-screen' ); ?></legend>
						<label><input type="radio" name="<?php echo esc_attr( $o ); ?>[wallpaper]" value="dawn" <?php checked( $s['wallpaper'], 'dawn' ); ?>> <?php esc_html_e( 'Sunrise over hills', 'comms-church-home-screen' ); ?></label>
						<label><input type="radio" name="<?php echo esc_attr( $o ); ?>[wallpaper]" value="brand" <?php checked( $s['wallpaper'], 'brand' ); ?>> <?php esc_html_e( 'Brand color gradient', 'comms-church-home-screen' ); ?></label>
						<label><input type="radio" name="<?php echo esc_attr( $o ); ?>[wallpaper]" value="image" <?php checked( $s['wallpaper'], 'image' ); ?>> <?php esc_html_e( 'Your photo', 'comms-church-home-screen' ); ?></label>
					</fieldset>
					<div class="chs-wall-pick">
						<input type="hidden" id="chs-wall-id" name="<?php echo esc_attr( $o ); ?>[wallpaper_image]" value="<?php echo esc_attr( $s['wallpaper_image'] ); ?>">
						<img id="chs-wall-preview" src="<?php echo esc_url( $wall_src ); ?>" alt="" <?php echo $wall_src ? '' : 'hidden'; ?>>
						<button type="button" class="button" id="chs-wall-choose"><?php esc_html_e( 'Choose photo', 'comms-church-home-screen' ); ?></button>
						<small><?php esc_html_e( 'A tall photo works best, at least 1200px high.', 'comms-church-home-screen' ); ?></small>
					</div>
					<label class="chs-check"><input type="checkbox" name="<?php echo esc_attr( $o ); ?>[steeple]" value="1" <?php checked( $s['steeple'] ); ?>> <?php esc_html_e( 'Show a small church on the hill (sunrise wallpaper)', 'comms-church-home-screen' ); ?></label>
					<div class="chs-grid">
						<label class="chs-field">
							<span><?php esc_html_e( 'Brand color', 'comms-church-home-screen' ); ?></span>
							<input type="color" name="<?php echo esc_attr( $o ); ?>[brand]" value="<?php echo esc_attr( $s['brand'] ); ?>">
							<small><?php esc_html_e( 'Used for buttons inside apps and the brand wallpaper.', 'comms-church-home-screen' ); ?></small>
						</label>
					</div>
					<label class="chs-check"><input type="checkbox" name="<?php echo esc_attr( $o ); ?>[verse_on]" value="1" <?php checked( $s['verse_on'] ); ?>> <?php esc_html_e( 'Show a Bible verse widget', 'comms-church-home-screen' ); ?></label>
					<div class="chs-grid">
						<?php
						self::textarea( 'verse_text', __( 'Verse', 'comms-church-home-screen' ), $s );
						self::text( 'verse_ref', __( 'Reference', 'comms-church-home-screen' ), $s, __( 'Check the translation\'s copyright terms. The World English Bible is public domain.', 'comms-church-home-screen' ) );
						?>
					</div>
				</section>

				<section class="chs-card">
					<h2><?php esc_html_e( 'Web address', 'comms-church-home-screen' ); ?></h2>
					<label class="chs-field">
						<span><?php esc_html_e( 'Path', 'comms-church-home-screen' ); ?></span>
						<span class="chs-suffix"><span><?php echo esc_html( trailingslashit( home_url() ) ); ?></span><input type="text" name="<?php echo esc_attr( $o ); ?>[path]" value="<?php echo esc_attr( $s['path'] ); ?>"></span>
						<small><?php esc_html_e( 'Pick something that is not already a page on your site.', 'comms-church-home-screen' ); ?></small>
					</label>
					<p class="description">
						<?php
						printf(
							/* translators: %s: shortcode */
							esc_html__( 'To show the home screen inside a page, add the %s shortcode.', 'comms-church-home-screen' ),
							'<code>[church_home_screen]</code>'
						);
						?>
					</p>
				</section>

				<?php submit_button( __( 'Save changes', 'comms-church-home-screen' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Review screen for scan results.
	 *
	 * @param array $findings Findings.
	 * @param bool  $first    First automatic scan.
	 */
	private static function render_review( $findings, $first ) {
		$groups = array(
			'details'  => __( 'Church details', 'comms-church-home-screen' ),
			'services' => __( 'Service times', 'comms-church-home-screen' ),
			'cc'       => __( 'Church Center', 'comms-church-home-screen' ),
			'events'   => __( 'Events', 'comms-church-home-screen' ),
			'apps'     => __( 'Apps', 'comms-church-home-screen' ),
			'look'     => __( 'Look', 'comms-church-home-screen' ),
		);
		$back   = admin_url( 'options-general.php?page=' . self::PAGE );
		?>
		<section class="chs-card chs-review">
			<h2><?php echo esc_html( $first ? __( 'We looked through your site to get you started', 'comms-church-home-screen' ) : __( 'Here is what we found on your site', 'comms-church-home-screen' ) ); ?></h2>
			<?php if ( ! $findings ) : ?>
				<p><?php esc_html_e( 'Nothing new turned up. Your settings already match what is on your site, or the site keeps that information somewhere we can\'t read.', 'comms-church-home-screen' ); ?></p>
				<p><a class="button" href="<?php echo esc_url( $back ); ?>"><?php esc_html_e( 'Back to settings', 'comms-church-home-screen' ); ?></a></p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'Check what you want to use. Nothing changes until you choose "Use selected", and you can edit everything afterwards.', 'comms-church-home-screen' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="chs_apply_scan">
					<?php wp_nonce_field( 'chs_apply_scan' ); ?>
					<?php
					foreach ( $groups as $gk => $gname ) :
						$items = wp_list_filter( $findings, array( 'group' => $gk ) );
						if ( ! $items ) {
							continue;
						}
						?>
						<h3><?php echo esc_html( $gname ); ?></h3>
						<?php if ( 'services' === $gk ) : ?>
							<p class="description"><?php esc_html_e( 'Checked times replace your current service times. Times are read from your pages, so please make sure they are right.', 'comms-church-home-screen' ); ?></p>
						<?php endif; ?>
						<ul class="chs-findings">
							<?php foreach ( $items as $it ) : ?>
								<li class="<?php echo ! empty( $it['tip'] ) ? 'is-tip' : ''; ?>">
									<label>
										<?php if ( empty( $it['tip'] ) ) : ?>
											<input type="checkbox" name="chs_pick[]" value="<?php echo esc_attr( $it['id'] ); ?>" <?php checked( ! empty( $it['checked'] ) ); ?>>
										<?php else : ?>
											<span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
										<?php endif; ?>
										<span class="f-label"><?php echo esc_html( $it['label'] ); ?></span>
										<span class="f-val">
											<?php if ( ! empty( $it['swatch'] ) ) : ?>
												<span class="f-swatch" style="background:<?php echo esc_attr( $it['swatch'] ); ?>"></span>
											<?php endif; ?>
											<?php echo esc_html( $it['show'] ); ?>
										</span>
										<?php if ( ! empty( $it['note'] ) ) : ?>
											<small><?php echo esc_html( $it['note'] ); ?></small>
										<?php endif; ?>
									</label>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endforeach; ?>
					<p class="chs-review-actions">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Use selected', 'comms-church-home-screen' ); ?></button>
						<a class="button" href="<?php echo esc_url( $back ); ?>"><?php esc_html_e( 'Skip for now', 'comms-church-home-screen' ); ?></a>
					</p>
				</form>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Weekday names in the site language, Sunday first.
	 *
	 * @return string[]
	 */
	public static function weekdays() {
		global $wp_locale;
		$out = array();
		for ( $i = 0; $i < 7; $i++ ) {
			$out[ $i ] = $wp_locale ? $wp_locale->get_weekday( $i ) : gmdate( 'l', strtotime( "Sunday +$i days" ) );
		}
		return $out;
	}

	/**
	 * Text field helper.
	 */
	private static function text( $key, $label, $s, $help = '', $type = 'text' ) {
		?>
		<label class="chs-field">
			<span><?php echo esc_html( $label ); ?></span>
			<input type="<?php echo esc_attr( $type ); ?>" name="<?php echo esc_attr( self::OPTION . "[$key]" ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>">
			<?php if ( $help ) : ?>
				<small><?php echo esc_html( $help ); ?></small>
			<?php endif; ?>
		</label>
		<?php
	}

	/**
	 * Textarea helper.
	 */
	private static function textarea( $key, $label, $s, $help = '' ) {
		?>
		<label class="chs-field">
			<span><?php echo esc_html( $label ); ?></span>
			<textarea rows="3" name="<?php echo esc_attr( self::OPTION . "[$key]" ); ?>"><?php echo esc_textarea( $s[ $key ] ); ?></textarea>
			<?php if ( $help ) : ?>
				<small><?php echo esc_html( $help ); ?></small>
			<?php endif; ?>
		</label>
		<?php
	}

	/**
	 * One row in the apps repeater.
	 *
	 * @param int|string $i   Row index.
	 * @param array      $app App row.
	 */
	private static function app_row( $i, $app ) {
		$n     = self::OPTION . "[apps][$i]";
		$types = self::app_types();
		?>
		<div class="chs-app" data-type="<?php echo esc_attr( $app['type'] ); ?>">
			<span class="chs-handle" aria-hidden="true">⋮⋮</span>
			<span class="chs-swatch" style="--c:<?php echo esc_attr( $app['color'] ); ?>;--g:<?php echo esc_attr( chs_glyph_color( $app['color'] ) ); ?>"><?php echo chs_icon_svg( 'today' === $app['icon'] ? 'calendar' : $app['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed icon library. ?></span>
			<label><span><?php esc_html_e( 'Type', 'comms-church-home-screen' ); ?></span>
				<select class="chs-type" name="<?php echo esc_attr( $n . '[type]' ); ?>">
					<?php foreach ( $types as $key => $t ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $app['type'], $key ); ?>><?php echo esc_html( $t['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label><span><?php esc_html_e( 'Label', 'comms-church-home-screen' ); ?></span>
				<input class="chs-label" type="text" maxlength="24" name="<?php echo esc_attr( $n . '[label]' ); ?>" value="<?php echo esc_attr( $app['label'] ); ?>">
			</label>
			<label><span><?php esc_html_e( 'Icon', 'comms-church-home-screen' ); ?></span>
				<select class="chs-icon" name="<?php echo esc_attr( $n . '[icon]' ); ?>">
					<?php foreach ( chs_icons() as $key => $icon ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $app['icon'], $key ); ?>><?php echo esc_html( $icon['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label><span><?php esc_html_e( 'Color', 'comms-church-home-screen' ); ?></span>
				<input class="chs-color" type="color" name="<?php echo esc_attr( $n . '[color]' ); ?>" value="<?php echo esc_attr( $app['color'] ); ?>">
			</label>
			<label class="chs-url-field"><span><?php esc_html_e( 'Link', 'comms-church-home-screen' ); ?></span>
				<input type="url" name="<?php echo esc_attr( $n . '[url]' ); ?>" value="<?php echo esc_attr( $app['url'] ); ?>" placeholder="https://">
			</label>
			<label><span><?php esc_html_e( 'Where', 'comms-church-home-screen' ); ?></span>
				<select class="chs-place" name="<?php echo esc_attr( $n . '[place]' ); ?>">
					<option value="grid" <?php selected( $app['place'], 'grid' ); ?>><?php esc_html_e( 'Home screen', 'comms-church-home-screen' ); ?></option>
					<option value="dock" <?php selected( $app['place'], 'dock' ); ?>><?php esc_html_e( 'Dock', 'comms-church-home-screen' ); ?></option>
				</select>
			</label>
			<button type="button" class="button-link chs-remove"><?php esc_html_e( 'Remove', 'comms-church-home-screen' ); ?></button>
		</div>
		<?php
	}
}
