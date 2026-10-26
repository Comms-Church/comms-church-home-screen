<?php
/**
 * Plugin Name:       Church Home Screen
 * Plugin URI:        https://github.com/Comms-Church/comms-church-home-screen
 * Description:       A phone-style link page for your church with Church Center built in. Visitors swipe up to unlock, then tap "apps" for giving, groups, sign-ups, events and more. Use it as your link-in-bio.
 * Version:           0.2.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            comms.church
 * Author URI:        https://comms.church
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       comms-church-home-screen
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

CHS_Settings::init();
CHS_Frontend::init();
CHS_Scanner::init();

// Updates come from GitHub Releases and show up on the normal Plugins and
// Dashboard → Updates screens.
if ( is_admin() ) {
	require_once CHS_DIR . 'includes/class-chs-updater.php';
	new CHS_Updater( CHS_FILE, CHS_VERSION );
}

register_activation_hook( __FILE__, array( 'CHS_Frontend', 'activate' ) );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'options-general.php?page=church-home-screen' ) ) . '">' . esc_html__( 'Settings', 'comms-church-home-screen' ) . '</a>'
		);
		return $links;
	}
);
