<?php
/**
 * Plugin Name:       Church Links
 * Plugin URI:        https://github.com/Comms-Church/comms-church-links
 * Description:       Your church's link page, set up from your own site. Watched links stay current on their own (this week's bulletin, the latest sermon), Church Center is built in, and a QR code is ready to print. Show it as a link list or a phone home screen.
 * Version:           0.2.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            comms.church
 * Author URI:        https://comms.church
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       comms-church-links
 */

defined( 'ABSPATH' ) || exit;

define( 'CHS_VERSION', '0.2.0' );
define( 'CHS_FILE', __FILE__ );
define( 'CHS_DIR', plugin_dir_path( __FILE__ ) );
define( 'CHS_URL', plugin_dir_url( __FILE__ ) );

require_once CHS_DIR . 'includes/icons.php';
require_once CHS_DIR . 'includes/class-chs-settings.php';
require_once CHS_DIR . 'includes/class-chs-events.php';
require_once CHS_DIR . 'includes/class-chs-frontend.php';
require_once CHS_DIR . 'includes/class-chs-scanner.php';
require_once CHS_DIR . 'includes/class-chs-watch.php';

CHS_Settings::init();
CHS_Frontend::init();
CHS_Scanner::init();
CHS_Watch::init();

// Updates come from GitHub Releases and show up on the normal Plugins and
// Dashboard → Updates screens.
if ( is_admin() ) {
	require_once CHS_DIR . 'includes/class-chs-updater.php';
	new CHS_Updater( CHS_FILE, CHS_VERSION );
}

register_activation_hook( __FILE__, array( 'CHS_Frontend', 'activate' ) );
register_deactivation_hook(
	__FILE__,
	function () {
		wp_clear_scheduled_hook( CHS_Watch::CRON );
		flush_rewrite_rules();
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'options-general.php?page=church-links' ) ) . '">' . esc_html__( 'Settings', 'comms-church-links' ) . '</a>'
		);
		return $links;
	}
);
