<?php
/**
 * Checks GitHub Releases for newer versions of this plugin and hooks into
 * WordPress's native update UI (Plugins screen, Dashboard → Updates).
 * No third-party service involved: it calls the public GitHub Releases API
 * directly, so the repo must stay public.
 *
 * @package ChurchLinks
 */

defined( 'ABSPATH' ) || exit;

/**
 * GitHub release updater.
 */
class CHS_Updater {

	const GITHUB_REPO = 'Comms-Church/comms-church-links';
	// Deliberately not prefixed 'chs_' so cache flushes of plugin data never
	// touch the release cache.
	const CACHE_KEY = 'chsupd_release';
	const CACHE_TTL = DAY_IN_SECONDS;

	/** @var string e.g. comms-church-links/comms-church-links.php */
	private $plugin_file;

	/** @var string e.g. comms-church-links */
	private $plugin_slug;

	/** @var string Currently installed version. */
	private $version;

	/**
	 * @param string $plugin_file Main plugin file path.
	 * @param string $version     Installed version.
	 */
	public function __construct( $plugin_file, $version ) {
		$this->plugin_file = plugin_basename( $plugin_file );
		$this->plugin_slug = dirname( $this->plugin_file );
		$this->version     = $version;

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugins_api_handler' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_source_dir' ), 10, 4 );
		add_filter( 'plugin_row_meta', array( $this, 'add_check_now_link' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'maybe_manual_check' ) );
	}

	/**
	 * Add this plugin to the update transient when GitHub has a newer release.
	 *
	 * @param object $transient update_plugins transient.
	 * @return object
	 */
	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();
		if ( ! $release || empty( $release['tag_name'] ) ) {
			return $transient;
		}

		$remote_version = $this->normalize_version( $release['tag_name'] );

		$item               = new stdClass();
		$item->slug         = $this->plugin_slug;
		$item->plugin       = $this->plugin_file;
		$item->new_version  = $remote_version;
		$item->url          = 'https://github.com/' . self::GITHUB_REPO;
		$item->package      = '';
		$item->tested       = get_bloginfo( 'version' );
		$item->requires     = '6.2';
		$item->requires_php = '7.4';
		$item->icons        = array();
		$item->banners      = array();

		if ( version_compare( $remote_version, $this->version, '>' ) ) {
			$zip_url = $this->get_zip_asset_url( $release );
			if ( ! $zip_url ) {
				return $transient;
			}

			$item->package                             = $zip_url;
			$transient->response[ $this->plugin_file ] = $item;
			unset( $transient->no_update[ $this->plugin_file ] );
		} else {
			// Populate no_update so WP's auto-update UI treats the plugin as
			// "checked and current" rather than unknown.
			$transient->no_update[ $this->plugin_file ] = $item;
			unset( $transient->response[ $this->plugin_file ] );
		}

		return $transient;
	}

	/**
	 * "View details" popup on the Plugins screen.
	 *
	 * @param false|object|array $result Default result.
	 * @param string             $action API action.
	 * @param object             $args   Request args.
	 * @return false|object|array
	 */
	public function plugins_api_handler( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}
		if ( empty( $args->slug ) || $args->slug !== $this->plugin_slug ) {
			return $result;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $result;
		}

		$body                = isset( $release['body'] ) ? $release['body'] : '';
		$info                = new stdClass();
		$info->name          = 'Church Links';
		$info->slug          = $this->plugin_slug;
		$info->version       = $this->normalize_version( $release['tag_name'] );
		$info->author        = '<a href="https://comms.church">Comms.Church</a>';
		$info->homepage      = 'https://github.com/' . self::GITHUB_REPO;
		$info->sections      = array(
			'description' => wpautop( $body ? $body : 'See GitHub releases for changelog.' ),
			'changelog'   => wpautop( $body ),
		);
		$info->download_link = $this->get_zip_asset_url( $release );

		return $info;
	}

	/**
	 * GitHub zips can unpack to repo-name-vX.X.X; move them to the plugin slug.
	 *
	 * @param string      $source        Unpacked source dir.
	 * @param string      $remote_source Parent dir.
	 * @param WP_Upgrader $upgrader      Upgrader instance.
	 * @param array       $hook_extra    Extra args.
	 * @return string
	 */
	public function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		global $wp_filesystem;

		if ( ! is_object( $upgrader ) || empty( $hook_extra['plugin'] ) ) {
			return $source;
		}
		if ( $hook_extra['plugin'] !== $this->plugin_file ) {
			return $source;
		}

		$desired = trailingslashit( $remote_source ) . $this->plugin_slug . '/';
		if ( trailingslashit( $source ) === $desired ) {
			return $source;
		}

		if ( $wp_filesystem->move( $source, $desired ) ) {
			return $desired;
		}

		return $source;
	}

	/**
	 * Manual "Check for updates" link in the plugin row.
	 *
	 * @param array  $links       Row meta links.
	 * @param string $plugin_file Plugin basename.
	 * @return array
	 */
	public function add_check_now_link( $links, $plugin_file ) {
		if ( $plugin_file !== $this->plugin_file ) {
			return $links;
		}

		$url     = wp_nonce_url(
			add_query_arg( array( 'chs_check_update' => 1 ), admin_url( 'plugins.php' ) ),
			'chs_check_update'
		);
		$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Check for updates', 'comms-church-links' ) . '</a>';
		return $links;
	}

	/**
	 * Handle the manual check link.
	 */
	public function maybe_manual_check() {
		if ( empty( $_GET['chs_check_update'] ) ) {
			return;
		}
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'chs_check_update' ) ) {
			return;
		}

		delete_transient( self::CACHE_KEY );
		delete_site_transient( 'update_plugins' );
		wp_safe_redirect( admin_url( 'plugins.php?chs_checked=1' ) );
		exit;
	}

	/**
	 * Latest release from the GitHub API, cached for a day.
	 *
	 * @return array|false
	 */
	private function get_latest_release() {
		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			return $cached;
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest',
			array(
				'headers' => array( 'Accept' => 'application/vnd.github+json' ),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['tag_name'] ) ) {
			return false;
		}

		set_transient( self::CACHE_KEY, $data, self::CACHE_TTL );
		return $data;
	}

	/**
	 * First .zip asset on the release.
	 *
	 * @param array $release Release payload.
	 * @return string|false
	 */
	private function get_zip_asset_url( $release ) {
		if ( empty( $release['assets'] ) ) {
			return false;
		}
		foreach ( $release['assets'] as $asset ) {
			$name = isset( $asset['name'] ) ? $asset['name'] : '';
			if ( '.zip' === substr( $name, -4 ) ) {
				return $asset['browser_download_url'];
			}
		}
		return false;
	}

	/**
	 * Strip a leading v from the tag.
	 *
	 * @param string $tag Tag name.
	 * @return string
	 */
	private function normalize_version( $tag ) {
		return ltrim( $tag, 'vV' );
	}
}
