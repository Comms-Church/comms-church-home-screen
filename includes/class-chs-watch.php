<?php
/**
 * Latest-post links: an app that always opens the newest post in an archive on this
 * site (/sermons/, /news/, the blog page). Looked up on every page view, so it is never
 * stale, and marked "New" for a week after a post is published.
 *
 * Anything that isn't an archive here just links to the page itself. (0.3.0 dev builds
 * also read other pages, like a bulletin page, for their newest link; that was dropped in
 * favour of Planning Center's Featured events, see CHS_Events::featured().)
 *
 * @package ChurchLinks
 */

defined( 'ABSPATH' ) || exit;

/**
 * Latest-post links.
 */
class CHS_Watch {

	const NEW_FOR = 7 * DAY_IN_SECONDS;

	/**
	 * Clear what the page-reading dev builds left behind.
	 */
	public static function init() {
		add_action(
			'admin_init',
			function () {
				if ( false !== get_option( 'chs_watch' ) ) {
					delete_option( 'chs_watch' );
					wp_clear_scheduled_hook( 'chs_watch_daily' );
				}
			}
		);
	}

	/**
	 * What a latest-post app links to right now.
	 *
	 * @param array $app App row (url, match).
	 * @return array|null href, text, new; null when the archive has no matching post.
	 */
	public static function resolve( $app ) {
		$type = self::post_type_for( $app['url'] );
		if ( ! $type ) {
			return array( 'href' => $app['url'], 'text' => '', 'new' => false );
		}
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
}
