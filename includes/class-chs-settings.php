<?php
/**
 * Settings: defaults, sanitizing and the admin page.
 *
 * @package ChurchLinks
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings handler.
 */
class CHS_Settings {

	const OPTION = 'chs_settings';
	const GROUP  = 'chs_settings_group';
	const PAGE   = 'church-links';

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
			esc_html__( 'Church Links is active.', 'comms-church-links' ),
			esc_html__( 'We can fill in most of the setup from your website.', 'comms-church-links' ),
			esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ),
			esc_html__( 'Set up your links page', 'comms-church-links' )
		);
	}

	/**
	 * App types the church can add. "kind" decides how the app behaves:
	 * sheet = opens a panel on the screen, cc = Church Center link, link = plain link, app = Church Center app download,
	 * watch = opens the newest post in an archive on this site (see CHS_Watch).
	 *
	 * @return array
	 */
	public static function app_types() {
		return array(
			'visit'            => array( 'name' => __( 'Plan a Visit (service times panel)', 'comms-church-links' ), 'label' => __( 'Visit', 'comms-church-links' ), 'icon' => 'pin', 'color' => '#1f8a7d', 'kind' => 'sheet' ),
			'events'           => array( 'name' => __( 'Events (upcoming events panel)', 'comms-church-links' ), 'label' => __( 'Events', 'comms-church-links' ), 'icon' => 'today', 'color' => '#ffffff', 'kind' => 'sheet' ),
			'contact'          => array( 'name' => __( 'Contact (phone & email panel)', 'comms-church-links' ), 'label' => __( 'Contact', 'comms-church-links' ), 'icon' => 'phone', 'color' => '#2e9e52', 'kind' => 'sheet' ),
			'directions'       => array( 'name' => __( 'Directions (opens maps)', 'comms-church-links' ), 'label' => __( 'Directions', 'comms-church-links' ), 'icon' => 'navigate', 'color' => '#3d5fe0', 'kind' => 'link' ),
			'cc_giving'        => array( 'name' => __( 'Church Center: Giving', 'comms-church-links' ), 'label' => __( 'Give', 'comms-church-links' ), 'icon' => 'heart', 'color' => '#e08a2c', 'kind' => 'cc', 'path' => '/giving' ),
			'cc_groups'        => array( 'name' => __( 'Church Center: Groups', 'comms-church-links' ), 'label' => __( 'Groups', 'comms-church-links' ), 'icon' => 'people', 'color' => '#3b82d6', 'kind' => 'cc', 'path' => '/groups' ),
			'cc_registrations' => array( 'name' => __( 'Church Center: Sign-ups', 'comms-church-links' ), 'label' => __( 'Sign Ups', 'comms-church-links' ), 'icon' => 'check', 'color' => '#8a5cd0', 'kind' => 'cc', 'path' => '/registrations' ),
			'cc_calendar'      => array( 'name' => __( 'Church Center: Calendar', 'comms-church-links' ), 'label' => __( 'Calendar', 'comms-church-links' ), 'icon' => 'today', 'color' => '#ffffff', 'kind' => 'cc', 'path' => '/calendar' ),
			'cc_home'          => array( 'name' => __( 'Church Center: Home', 'comms-church-links' ), 'label' => __( 'Church Center', 'comms-church-links' ), 'icon' => 'church', 'color' => '#2c3372', 'kind' => 'cc', 'path' => '' ),
			'cc_app'           => array( 'name' => __( 'Church Center: Get the app', 'comms-church-links' ), 'label' => __( 'Get the App', 'comms-church-links' ), 'icon' => 'download', 'color' => '#1c1e33', 'kind' => 'app' ),
			'link'             => array( 'name' => __( 'Custom link', 'comms-church-links' ), 'label' => __( 'Link', 'comms-church-links' ), 'icon' => 'link', 'color' => '#64748b', 'kind' => 'link' ),
			'watch'            => array( 'name' => __( 'Latest post (newest sermon, news post…)', 'comms-church-links' ), 'label' => __( 'Latest Sermon', 'comms-church-links' ), 'icon' => 'book', 'color' => '#2c3372', 'kind' => 'watch' ),
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
				'match' => '',
				'place' => $r[1],
			);
		}
		$apps[] = array( 'type' => 'link', 'label' => __( 'Watch', 'comms-church-links' ), 'icon' => 'play', 'color' => '#d9483b', 'url' => '', 'match' => '', 'place' => 'dock' );
		$apps[] = array( 'type' => 'link', 'label' => __( 'Sermons', 'comms-church-links' ), 'icon' => 'book', 'color' => '#2c3372', 'url' => '', 'match' => '', 'place' => 'grid' );
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
			'display'         => 'list',
			'church_name'     => get_bloginfo( 'name' ),
			'short_name'      => '',
			'location'        => '',
			'address'         => '',
			'phone'           => '',
			'email'           => '',
			'services'        => array(
				array( 'day' => 0, 'time' => '10:00 AM', 'label' => __( 'Worship', 'comms-church-links' ) ),
			),
			'expect'          => __( "Come as you are.\nWorship runs about an hour, with singing, prayer and teaching from the Bible.\nKids programs run during the service.", 'comms-church-links' ),
			'visit_url'       => '',
			'note_body'       => __( 'Come as you are. We would love to meet you.', 'comms-church-links' ),
			'verse_on'        => 1,
			'verse_text'      => 'Come to me, all you who labor and are heavily burdened, and I will give you rest.',
			'verse_ref'       => 'Matthew 11:28 (WEB)',
			'wallpaper'       => 'dawn',
			'wallpaper_image' => 0,
			'steeple'         => 1,
			'brand'           => '#2c3372',
			'cc_subdomain'    => '',
			'cc_modal'        => 1,
			'featured_on'     => 1,
			'events_source'   => 'none',
			'ics_url'         => '',
			'pco_app_id'      => '',
			'pco_secret'      => '',
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
		foreach ( $s['apps'] as $i => $a ) {
			$s['apps'][ $i ] = wp_parse_args( (array) $a, array( 'type' => 'link', 'label' => '', 'icon' => 'link', 'color' => '#64748b', 'url' => '', 'match' => '', 'place' => 'grid' ) );
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
			__( 'Church Links', 'comms-church-links' ),
			__( 'Church Links', 'comms-church-links' ),
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
		wp_enqueue_script( 'chs-qrcode', CHS_URL . 'assets/vendor/qrcode.js', array(), '1.4.4', true );
		wp_enqueue_script( 'chs-admin', CHS_URL . 'assets/admin.js', array( 'jquery', 'jquery-ui-sortable', 'chs-qrcode' ), CHS_VERSION, true );
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
				'chooseText' => __( 'Choose wallpaper', 'comms-church-links' ),
				'useText'    => __( 'Use this photo', 'comms-church-links' ),
				'qrName'     => sanitize_file_name( get_bloginfo( 'name' ) . '-links-qr' ),
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

		$out['display']  = isset( $in['display'] ) && 'phone' === $in['display'] ? 'phone' : 'list';
		$out['verse_on'] = empty( $in['verse_on'] ) ? 0 : 1;
		$out['steeple']  = empty( $in['steeple'] ) ? 0 : 1;
		$out['cc_modal'] = empty( $in['cc_modal'] ) ? 0 : 1;
		$out['featured_on'] = empty( $in['featured_on'] ) ? 0 : 1;

		$out['wallpaper']       = isset( $in['wallpaper'] ) && in_array( $in['wallpaper'], array( 'dawn', 'brand', 'image' ), true ) ? $in['wallpaper'] : 'dawn';
		$out['wallpaper_image'] = isset( $in['wallpaper_image'] ) ? absint( $in['wallpaper_image'] ) : 0;
		if ( 'image' === $out['wallpaper'] && ! $out['wallpaper_image'] ) {
			$out['wallpaper'] = 'dawn';
		}
		$brand        = isset( $in['brand'] ) ? sanitize_hex_color( $in['brand'] ) : '';
		$out['brand'] = $brand ? $brand : $d['brand'];

		$out['cc_subdomain'] = self::clean_subdomain( isset( $in['cc_subdomain'] ) ? $in['cc_subdomain'] : '' );

		$source               = isset( $in['events_source'] ) ? $in['events_source'] : 'none';
		$out['events_source'] = in_array( $source, array( 'none', 'pco', 'ics', 'tec' ), true ) ? $source : 'none';
		$ics                  = isset( $in['ics_url'] ) ? trim( $in['ics_url'] ) : '';
		$ics                  = preg_replace( '#^webcals?://#i', 'https://', $ics );
		$out['ics_url']       = esc_url_raw( $ics, array( 'http', 'https' ) );
		// Planning Center key. The secret is never printed back into the form, so a blank
		// field keeps the saved one; "clear" removes both.
		$old                  = get_option( self::OPTION, array() );
		$out['pco_app_id']    = isset( $in['pco_app_id'] ) ? sanitize_text_field( $in['pco_app_id'] ) : '';
		$secret               = isset( $in['pco_secret'] ) ? trim( sanitize_text_field( $in['pco_secret'] ) ) : '';
		$out['pco_secret']    = '' !== $secret ? $secret : ( isset( $old['pco_secret'] ) ? $old['pco_secret'] : '' );
		if ( ! empty( $in['pco_clear'] ) || '' === $out['pco_app_id'] ) {
			$out['pco_app_id'] = '';
			$out['pco_secret'] = '';
		}
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
					'match' => isset( $row['match'] ) ? mb_substr( sanitize_text_field( $row['match'] ), 0, 40 ) : '',
					'place' => $place,
				);
			}
		}

		delete_transient( CHS_Events::CACHE );
		delete_transient( CHS_Events::FEATURED );
		// Fetch now so the settings page can say right away whether the key works.
		$full = wp_parse_args( $out, self::defaults() );
		if ( 'pco' === $out['events_source'] ) {
			CHS_Events::get( $full );
		}
		if ( $out['featured_on'] ) {
			CHS_Events::featured( $full );
		}
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
			<h1><?php esc_html_e( 'Church Links', 'comms-church-links' ); ?></h1>

			<div class="chs-hero">
				<div>
					<p class="chs-eyebrow"><?php esc_html_e( 'Your links page is live at', 'comms-church-links' ); ?></p>
					<p class="chs-url"><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $url ); ?></a></p>
					<p class="description"><?php esc_html_e( 'Put this link in your Instagram and Facebook bios, and the QR code on slides, in the bulletin and on signs.', 'comms-church-links' ); ?></p>
					<p class="chs-hero-actions">
						<a class="button button-primary" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open links page', 'comms-church-links' ); ?></a>
						<button type="button" class="button" data-qr-download="svg"><?php esc_html_e( 'Download QR (SVG)', 'comms-church-links' ); ?></button>
						<button type="button" class="button" data-qr-download="png"><?php esc_html_e( 'Download QR (PNG)', 'comms-church-links' ); ?></button>
					</p>
				</div>
				<div class="chs-qr" id="chs-qr" data-url="<?php echo esc_url( $url ); ?>" role="img" aria-label="<?php esc_attr_e( 'QR code for your links page', 'comms-church-links' ); ?>"></div>
			</div>

			<?php if ( null !== $applied ) : ?>
				<div class="notice notice-success is-dismissible"><p>
					<?php
					echo esc_html(
						$applied
							/* translators: %d: number of settings */
							? sprintf( _n( 'Filled in %d setting from your site. Look it over below.', 'Filled in %d settings from your site. Look them over below.', $applied, 'comms-church-links' ), $applied )
							: __( 'Nothing was selected, so nothing changed.', 'comms-church-links' )
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
					<h2><?php esc_html_e( 'Auto-fill from your site', 'comms-church-links' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Reads your homepage, menus and pages for service times, contact details, your Church Center address, social links and pages worth adding as apps. You choose what to keep.', 'comms-church-links' ); ?></p>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="chs_scan">
					<?php wp_nonce_field( 'chs_scan' ); ?>
					<button type="submit" class="button button-secondary"><?php esc_html_e( 'Scan my site', 'comms-church-links' ); ?></button>
				</form>
			</section>

			<form method="post" action="options.php" id="chs-form">
				<?php settings_fields( self::GROUP ); ?>

				<section class="chs-card">
					<h2><?php esc_html_e( 'Church details', 'comms-church-links' ); ?></h2>
					<div class="chs-grid">
						<?php
						self::text( 'church_name', __( 'Church name', 'comms-church-links' ), $s );
						self::text( 'short_name', __( 'Short name', 'comms-church-links' ), $s, __( 'Shown on lock-screen notifications. Leave blank to use the church name.', 'comms-church-links' ) );
						self::text( 'location', __( 'Town and state', 'comms-church-links' ), $s, __( 'For example: Willow Street, PA', 'comms-church-links' ) );
						self::text( 'address', __( 'Street address', 'comms-church-links' ), $s, __( 'Used for the Directions app.', 'comms-church-links' ) );
						self::text( 'phone', __( 'Phone', 'comms-church-links' ), $s );
						self::text( 'email', __( 'Email', 'comms-church-links' ), $s, '', 'email' );
						?>
					</div>
				</section>

				<section class="chs-card">
					<h2><?php esc_html_e( 'Services', 'comms-church-links' ); ?></h2>
					<p class="description"><?php esc_html_e( 'The lock screen and the service widget show whichever service comes next.', 'comms-church-links' ); ?></p>
					<table class="chs-services">
						<thead><tr><th><?php esc_html_e( 'Day', 'comms-church-links' ); ?></th><th><?php esc_html_e( 'Time', 'comms-church-links' ); ?></th><th><?php esc_html_e( 'Name', 'comms-church-links' ); ?></th></tr></thead>
						<tbody>
						<?php
						for ( $i = 0; $i < 4; $i++ ) :
							$row = isset( $s['services'][ $i ] ) ? $s['services'][ $i ] : array( 'day' => 0, 'time' => '', 'label' => '' );
							?>
							<tr>
								<td><select name="<?php echo esc_attr( "{$o}[services][$i][day]" ); ?>" aria-label="<?php esc_attr_e( 'Day', 'comms-church-links' ); ?>">
									<?php foreach ( $days as $n => $day ) : ?>
										<option value="<?php echo esc_attr( $n ); ?>" <?php selected( (int) $row['day'], $n ); ?>><?php echo esc_html( $day ); ?></option>
									<?php endforeach; ?>
								</select></td>
								<td><input type="text" name="<?php echo esc_attr( "{$o}[services][$i][time]" ); ?>" value="<?php echo esc_attr( $row['time'] ); ?>" placeholder="10:30 AM" aria-label="<?php esc_attr_e( 'Time', 'comms-church-links' ); ?>"></td>
								<td><input type="text" name="<?php echo esc_attr( "{$o}[services][$i][label]" ); ?>" value="<?php echo esc_attr( $row['label'] ); ?>" placeholder="<?php esc_attr_e( 'Worship', 'comms-church-links' ); ?>" aria-label="<?php esc_attr_e( 'Name', 'comms-church-links' ); ?>"></td>
							</tr>
						<?php endfor; ?>
						</tbody>
					</table>
					<div class="chs-grid">
						<?php
						self::textarea( 'expect', __( 'What to expect', 'comms-church-links' ), $s, __( 'One point per line. Shown in the Visit app.', 'comms-church-links' ) );
						self::textarea( 'note_body', __( 'Lock-screen message', 'comms-church-links' ), $s, __( 'Shown under the next service on the lock screen.', 'comms-church-links' ) );
						self::text( 'visit_url', __( 'Plan-a-visit form link (optional)', 'comms-church-links' ), $s, __( 'A Church Center form works well here.', 'comms-church-links' ), 'url' );
						?>
					</div>
				</section>

				<section class="chs-card">
					<h2><?php esc_html_e( 'Featured', 'comms-church-links' ); ?></h2>
					<label class="chs-check">
						<input type="checkbox" name="<?php echo esc_attr( $o ); ?>[featured_on]" value="1" <?php checked( $s['featured_on'] ); ?>>
						<?php esc_html_e( 'Show what is featured in Planning Center at the top of the page', 'comms-church-links' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Whatever you announce from the stage, in the bulletin or on social, mark it Featured in Planning Center Calendar (or feature the sign-up in Registrations) and it appears here within 15 minutes. Unfeature it, or let the date pass, and it goes away. Nothing to update here.', 'comms-church-links' ); ?></p>
					<?php self::pco_fields( $s ); ?>
				</section>

				<section class="chs-card">
					<h2><?php esc_html_e( 'Church Center', 'comms-church-links' ); ?></h2>
					<label class="chs-field">
						<span><?php esc_html_e( 'Church Center address', 'comms-church-links' ); ?></span>
						<span class="chs-suffix"><span>https://</span><input type="text" name="<?php echo esc_attr( $o ); ?>[cc_subdomain]" value="<?php echo esc_attr( $s['cc_subdomain'] ); ?>" placeholder="yourchurch"><span>.churchcenter.com</span></span>
						<small><?php esc_html_e( 'Turns on the Church Center apps below: Giving, Groups, Sign-ups, Calendar, Home and Get the App.', 'comms-church-links' ); ?></small>
					</label>
					<label class="chs-check">
						<input type="checkbox" name="<?php echo esc_attr( $o ); ?>[cc_modal]" value="1" <?php checked( $s['cc_modal'] ); ?>>
						<?php esc_html_e( 'On computers, open Church Center in a pop-up instead of a new tab', 'comms-church-links' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'On phones, Church Center links open in the Church Center app when it is installed, and in the browser when it is not.', 'comms-church-links' ); ?></p>
				</section>

				<section class="chs-card">
					<h2><?php esc_html_e( 'Events', 'comms-church-links' ); ?></h2>
					<fieldset class="chs-radios">
						<label><input type="radio" name="<?php echo esc_attr( $o ); ?>[events_source]" value="none" <?php checked( $s['events_source'], 'none' ); ?>> <?php esc_html_e( 'No event list (the Events app links to your Church Center calendar)', 'comms-church-links' ); ?></label>
						<label><input type="radio" name="<?php echo esc_attr( $o ); ?>[events_source]" value="pco" <?php checked( $s['events_source'], 'pco' ); ?>> <?php esc_html_e( 'Planning Center: the events you show in Church Center', 'comms-church-links' ); ?></label>
						<label><input type="radio" name="<?php echo esc_attr( $o ); ?>[events_source]" value="ics" <?php checked( $s['events_source'], 'ics' ); ?>> <?php esc_html_e( 'Calendar feed (a public iCal link, such as Google Calendar\'s)', 'comms-church-links' ); ?></label>
						<label><input type="radio" name="<?php echo esc_attr( $o ); ?>[events_source]" value="tec" <?php checked( $s['events_source'], 'tec' ); ?> <?php disabled( ! $tec ); ?>> <?php esc_html_e( 'The Events Calendar plugin on this site', 'comms-church-links' ); ?><?php echo $tec ? '' : ' <em>' . esc_html__( '(not installed)', 'comms-church-links' ) . '</em>'; ?></label>
					</fieldset>
					<div class="chs-grid">
						<?php
						self::text( 'ics_url', __( 'Calendar feed link', 'comms-church-links' ), $s, __( 'Starts with webcal:// or https:// and usually ends in .ics. Refreshed every hour.', 'comms-church-links' ), 'url' );
						?>
						<label class="chs-field">
							<span><?php esc_html_e( 'How many events to show', 'comms-church-links' ); ?></span>
							<input type="number" min="1" max="10" name="<?php echo esc_attr( $o ); ?>[events_count]" value="<?php echo esc_attr( $s['events_count'] ); ?>">
						</label>
					</div>
				</section>

				<section class="chs-card">
					<h2><?php esc_html_e( 'Links', 'comms-church-links' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Drag to reorder. A latest-post link always opens the newest post in a sermon or news archive. Links with no address yet, or Church Center links without a Church Center address, stay hidden. In the phone layout, up to four sit in the dock.', 'comms-church-links' ); ?></p>
					<div id="chs-apps" class="chs-apps">
						<?php
						foreach ( $s['apps'] as $i => $app ) {
							self::app_row( $i, $app );
						}
						?>
					</div>
					<script type="text/html" id="tmpl-chs-app-row"><?php self::app_row( '__i__', array( 'type' => 'link', 'label' => '', 'icon' => 'link', 'color' => '#64748b', 'url' => '', 'match' => '', 'place' => 'grid' ) ); ?></script>
					<p><button type="button" class="button" id="chs-add-app"><?php esc_html_e( 'Add link', 'comms-church-links' ); ?></button></p>
				</section>

				<section class="chs-card">
					<h2><?php esc_html_e( 'Look', 'comms-church-links' ); ?></h2>
					<fieldset class="chs-radios">
						<legend><?php esc_html_e( 'Layout', 'comms-church-links' ); ?></legend>
						<label><input type="radio" name="<?php echo esc_attr( $o ); ?>[display]" value="list" <?php checked( $s['display'], 'list' ); ?>> <?php esc_html_e( 'Link list: your next service, then a button for each link', 'comms-church-links' ); ?></label>
						<label><input type="radio" name="<?php echo esc_attr( $o ); ?>[display]" value="phone" <?php checked( $s['display'], 'phone' ); ?>> <?php esc_html_e( 'Phone home screen: a lock screen to swipe up, then app icons', 'comms-church-links' ); ?></label>
					</fieldset>
					<div class="chs-grid">
						<label class="chs-field">
							<span><?php esc_html_e( 'Brand color', 'comms-church-links' ); ?></span>
							<input type="color" name="<?php echo esc_attr( $o ); ?>[brand]" value="<?php echo esc_attr( $s['brand'] ); ?>">
							<small><?php esc_html_e( 'Used for the header and buttons.', 'comms-church-links' ); ?></small>
						</label>
					</div>
					<h3 class="chs-sub"><?php esc_html_e( 'Phone home screen only', 'comms-church-links' ); ?></h3>
					<fieldset class="chs-radios">
						<legend><?php esc_html_e( 'Wallpaper', 'comms-church-links' ); ?></legend>
						<label><input type="radio" name="<?php echo esc_attr( $o ); ?>[wallpaper]" value="dawn" <?php checked( $s['wallpaper'], 'dawn' ); ?>> <?php esc_html_e( 'Sunrise over hills', 'comms-church-links' ); ?></label>
						<label><input type="radio" name="<?php echo esc_attr( $o ); ?>[wallpaper]" value="brand" <?php checked( $s['wallpaper'], 'brand' ); ?>> <?php esc_html_e( 'Brand color gradient', 'comms-church-links' ); ?></label>
						<label><input type="radio" name="<?php echo esc_attr( $o ); ?>[wallpaper]" value="image" <?php checked( $s['wallpaper'], 'image' ); ?>> <?php esc_html_e( 'Your photo', 'comms-church-links' ); ?></label>
					</fieldset>
					<div class="chs-wall-pick">
						<input type="hidden" id="chs-wall-id" name="<?php echo esc_attr( $o ); ?>[wallpaper_image]" value="<?php echo esc_attr( $s['wallpaper_image'] ); ?>">
						<img id="chs-wall-preview" src="<?php echo esc_url( $wall_src ); ?>" alt="" <?php echo $wall_src ? '' : 'hidden'; ?>>
						<button type="button" class="button" id="chs-wall-choose"><?php esc_html_e( 'Choose photo', 'comms-church-links' ); ?></button>
						<small><?php esc_html_e( 'A tall photo works best, at least 1200px high.', 'comms-church-links' ); ?></small>
					</div>
					<label class="chs-check"><input type="checkbox" name="<?php echo esc_attr( $o ); ?>[steeple]" value="1" <?php checked( $s['steeple'] ); ?>> <?php esc_html_e( 'Show a small church on the hill (sunrise wallpaper)', 'comms-church-links' ); ?></label>
					<label class="chs-check"><input type="checkbox" name="<?php echo esc_attr( $o ); ?>[verse_on]" value="1" <?php checked( $s['verse_on'] ); ?>> <?php esc_html_e( 'Show a Bible verse widget', 'comms-church-links' ); ?></label>
					<div class="chs-grid">
						<?php
						self::textarea( 'verse_text', __( 'Verse', 'comms-church-links' ), $s );
						self::text( 'verse_ref', __( 'Reference', 'comms-church-links' ), $s, __( 'Check the translation\'s copyright terms. The World English Bible is public domain.', 'comms-church-links' ) );
						?>
					</div>
				</section>

				<section class="chs-card">
					<h2><?php esc_html_e( 'Web address', 'comms-church-links' ); ?></h2>
					<label class="chs-field">
						<span><?php esc_html_e( 'Path', 'comms-church-links' ); ?></span>
						<span class="chs-suffix"><span><?php echo esc_html( trailingslashit( home_url() ) ); ?></span><input type="text" name="<?php echo esc_attr( $o ); ?>[path]" value="<?php echo esc_attr( $s['path'] ); ?>"></span>
						<small><?php esc_html_e( 'Pick something that is not already a page on your site.', 'comms-church-links' ); ?></small>
					</label>
					<p class="description">
						<?php
						printf(
							/* translators: %s: shortcode */
							esc_html__( 'To show your links inside a page, add the %s shortcode.', 'comms-church-links' ),
							'<code>[church_links]</code>'
						);
						?>
					</p>
				</section>

				<?php submit_button( __( 'Save changes', 'comms-church-links' ) ); ?>
			</form>
			<p style="margin-top:32px;padding-top:16px;border-top:1px solid #dcdcde;color:#646970;max-width:820px;">
				<?php
				printf(
					/* translators: %s: "pay what's fair" link */
					esc_html__( 'This plugin is free so churches can spend less on their websites. Using it in paid client work? Please %s. It\'s the honor system: no license keys, no checks.', 'comms-church-links' ),
					'<a href="https://buy.stripe.com/00w5kD8BF21R6SigP62B201" target="_blank" rel="noopener">' . esc_html__( 'pay what\'s fair', 'comms-church-links' ) . '</a>'
				);
				?>
			</p>
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
			'details'  => __( 'Church details', 'comms-church-links' ),
			'services' => __( 'Service times', 'comms-church-links' ),
			'cc'       => __( 'Church Center', 'comms-church-links' ),
			'events'   => __( 'Events', 'comms-church-links' ),
			'apps'     => __( 'Links', 'comms-church-links' ),
			'look'     => __( 'Look', 'comms-church-links' ),
		);
		$back   = admin_url( 'options-general.php?page=' . self::PAGE );
		?>
		<section class="chs-card chs-review">
			<h2><?php echo esc_html( $first ? __( 'We looked through your site to get you started', 'comms-church-links' ) : __( 'Here is what we found on your site', 'comms-church-links' ) ); ?></h2>
			<?php if ( ! $findings ) : ?>
				<p><?php esc_html_e( 'Nothing new turned up. Your settings already match what is on your site, or the site keeps that information somewhere we can\'t read.', 'comms-church-links' ); ?></p>
				<p><a class="button" href="<?php echo esc_url( $back ); ?>"><?php esc_html_e( 'Back to settings', 'comms-church-links' ); ?></a></p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'Check what you want to use. Nothing changes until you choose "Use selected", and you can edit everything afterwards.', 'comms-church-links' ); ?></p>
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
							<p class="description"><?php esc_html_e( 'Checked times replace your current service times. Times are read from your pages, so please make sure they are right.', 'comms-church-links' ); ?></p>
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
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Use selected', 'comms-church-links' ); ?></button>
						<a class="button" href="<?php echo esc_url( $back ); ?>"><?php esc_html_e( 'Skip for now', 'comms-church-links' ); ?></a>
					</p>
				</form>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Planning Center key fields and how the last fetch went.
	 *
	 * @param array $s Settings.
	 */
	private static function pco_fields( $s ) {
		$o      = self::OPTION;
		$own    = ! empty( $s['pco_app_id'] ) && ! empty( $s['pco_secret'] );
		$cred   = CHS_Events::pco_credentials( $s );
		$status = get_option( CHS_Events::STATUS );
		?>
		<div class="chs-pco">
			<?php if ( $cred && $cred['from'] ) : ?>
				<p class="description">
					<?php
					/* translators: %s: plugin name */
					echo esc_html( sprintf( __( 'Uses the Planning Center connection from %s. Enter a key below only to use a different one.', 'comms-church-links' ), $cred['from'] ) );
					?>
				</p>
			<?php else : ?>
				<p class="description">
					<?php esc_html_e( 'Create a Personal Access Token at api.planningcenteronline.com (Personal Access Tokens → New) and paste its Application ID and Secret here. The same key feeds Featured and, if you pick it under Events, the event list. Only events shown in Church Center appear, and only the place name, never the street address.', 'comms-church-links' ); ?>
				</p>
			<?php endif; ?>
			<div class="chs-grid">
				<?php self::text( 'pco_app_id', __( 'Planning Center Application ID', 'comms-church-links' ), $s ); ?>
				<label class="chs-field">
					<span><?php esc_html_e( 'Planning Center Secret', 'comms-church-links' ); ?></span>
					<input type="password" name="<?php echo esc_attr( $o . '[pco_secret]' ); ?>" value="" autocomplete="new-password" placeholder="<?php echo $own ? esc_attr__( 'Saved. Leave blank to keep it.', 'comms-church-links' ) : ''; ?>">
				</label>
			</div>
			<?php if ( $own ) : ?>
				<label><input type="checkbox" name="<?php echo esc_attr( $o ); ?>[pco_clear]" value="1"> <?php esc_html_e( 'Remove this key', 'comms-church-links' ); ?></label>
			<?php endif; ?>
			<?php
			$featured = get_option( CHS_Events::FEATURED_STATUS );
			$events   = 'pco' === $s['events_source'] && is_array( $status );
			// A bad key fails both the same way; say it once.
			if ( $s['featured_on'] && is_array( $featured ) && ( $featured['ok'] || ! $events || $status['ok'] ) ) :
				?>
				<p class="chs-pco-status chs-featured-status <?php echo $featured['ok'] ? 'is-ok' : 'is-error'; ?>">
					<?php
					/* translators: 1: status message, 2: time ago */
					echo esc_html( sprintf( __( 'Featured: %1$s Checked %2$s ago.', 'comms-church-links' ), $featured['msg'], human_time_diff( $featured['time'] ) ) );
					?>
				</p>
			<?php endif; ?>
			<?php if ( $events ) : ?>
				<p class="chs-pco-status chs-events-status <?php echo $status['ok'] ? 'is-ok' : 'is-error'; ?>">
					<?php
					/* translators: 1: status message, 2: time ago */
					echo esc_html( sprintf( __( '%1$s Checked %2$s ago.', 'comms-church-links' ), $status['msg'], human_time_diff( $status['time'] ) ) );
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Under a watched-page app: what it links to right now.
	 *
	 * @param array $app App row.
	 */
	private static function watch_status( $app ) {
		if ( 'watch' !== $app['type'] || ! $app['url'] ) {
			echo '<small class="chs-watch-help">' . esc_html__( 'Point it at your sermon archive or news page. It opens the newest post, and shows "New" for a week after one is published.', 'comms-church-links' ) . '</small>';
			return;
		}
		$type = CHS_Watch::post_type_for( $app['url'] );
		if ( ! $type ) {
			echo '<small class="chs-watch-now is-empty">' . esc_html__( 'That isn\'t a post archive on this site, so this just links to the page.', 'comms-church-links' ) . '</small>';
			return;
		}
		$w = CHS_Watch::resolve( $app );
		if ( ! $w ) {
			echo '<small class="chs-watch-now is-empty">' . esc_html__( 'No matching post yet, so this link stays hidden.', 'comms-church-links' ) . '</small>';
			return;
		}
		printf(
			'<small class="chs-watch-now">%1$s <a href="%2$s" target="_blank" rel="noopener">%3$s</a></small>',
			esc_html__( 'Now opens', 'comms-church-links' ),
			esc_url( $w['href'] ),
			esc_html( $w['text'] )
		);
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
			<label><span><?php esc_html_e( 'Type', 'comms-church-links' ); ?></span>
				<select class="chs-type" name="<?php echo esc_attr( $n . '[type]' ); ?>">
					<?php foreach ( $types as $key => $t ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $app['type'], $key ); ?>><?php echo esc_html( $t['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label><span><?php esc_html_e( 'Label', 'comms-church-links' ); ?></span>
				<input class="chs-label" type="text" maxlength="24" name="<?php echo esc_attr( $n . '[label]' ); ?>" value="<?php echo esc_attr( $app['label'] ); ?>">
			</label>
			<label><span><?php esc_html_e( 'Icon', 'comms-church-links' ); ?></span>
				<select class="chs-icon" name="<?php echo esc_attr( $n . '[icon]' ); ?>">
					<?php foreach ( chs_icons() as $key => $icon ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $app['icon'], $key ); ?>><?php echo esc_html( $icon['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label><span><?php esc_html_e( 'Color', 'comms-church-links' ); ?></span>
				<input class="chs-color" type="color" name="<?php echo esc_attr( $n . '[color]' ); ?>" value="<?php echo esc_attr( $app['color'] ); ?>">
			</label>
			<label class="chs-url-field"><span class="chs-url-link"><?php esc_html_e( 'Link', 'comms-church-links' ); ?></span><span class="chs-url-watch"><?php esc_html_e( 'Archive page', 'comms-church-links' ); ?></span>
				<input type="url" name="<?php echo esc_attr( $n . '[url]' ); ?>" value="<?php echo esc_attr( $app['url'] ); ?>" placeholder="https://">
			</label>
			<label><span><?php esc_html_e( 'Where', 'comms-church-links' ); ?></span>
				<select class="chs-place" name="<?php echo esc_attr( $n . '[place]' ); ?>">
					<option value="grid" <?php selected( $app['place'], 'grid' ); ?>><?php esc_html_e( 'Home screen', 'comms-church-links' ); ?></option>
					<option value="dock" <?php selected( $app['place'], 'dock' ); ?>><?php esc_html_e( 'Dock', 'comms-church-links' ); ?></option>
				</select>
			</label>
			<button type="button" class="button-link chs-remove"><?php esc_html_e( 'Remove', 'comms-church-links' ); ?></button>
			<div class="chs-watch-field">
				<label><span><?php esc_html_e( 'Only posts containing (optional)', 'comms-church-links' ); ?></span>
					<input type="text" maxlength="40" name="<?php echo esc_attr( $n . '[match]' ); ?>" value="<?php echo esc_attr( $app['match'] ); ?>" placeholder="<?php esc_attr_e( 'bulletin', 'comms-church-links' ); ?>">
				</label>
				<?php self::watch_status( $app ); ?>
			</div>
		</div>
		<?php
	}
}
