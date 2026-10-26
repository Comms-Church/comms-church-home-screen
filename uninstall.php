<?php
/**
 * Remove plugin data on uninstall.
 *
 * @package ChurchLinks
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'chs_settings' );
delete_option( 'chs_flush_rewrite' );
delete_option( 'chs_autoscanned' );
delete_option( 'chs_welcome' );
delete_option( 'chs_events_status' );
delete_transient( 'chs_events_cache' );
delete_transient( 'chsupd_release' );
delete_option( 'chs_watch' );
wp_clear_scheduled_hook( 'chs_watch_daily' );
