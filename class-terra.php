<?php
/**
 * This class is a rewrite of Lama: https://93digital.gitlab.io/lama/
 * This class helps to filter taxonomies via ajax (or XMLHttpRequest..?).
 *
 * @package stella
 */

namespace Nine3;

define( 'TERRA_VERSION', '0.1.0' );

/**
 * Filtering and load more class
 */
class Terra {
	/**
	 * Set to true for debug.
	 *
	 * @var bool
	 */
	private $develop;

	/**
	 * Initialise.
	 *
	 * @param bool $develop true if debug.
	 */
	public function __construct( $develop = false ) {
		$this->develop = $develop;

		// Enqueue the JS script.
		add_action( 'wp_enqueue_scripts', [ $this, 'register_script' ] );

		// TODO: wp_ajax actions.
	}

	/**
	 * Register and localize the script.
	 */
	public function register_script() {
		// If we're debugging use /src - if production use /dist.
		$dist = ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || $this->develop ) ? 'src/' : 'dist/';
		wp_register_script( 'stella-terra', $dist . 'terra.js', [ 'jquery' ], TERRA_VERSION, true );

		// TODO: Check https/http.
		$data = array(
			'ajaxurl'    => preg_replace( '/https?:\/\//', '//', admin_url( 'admin-ajax.php' ) ),
			// 'ajaxurl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'terra' ),
			'archiveurl' => get_post_type() == 'post' ? get_permalink( get_option( 'page_for_posts' ) ) : get_post_type_archive_link( get_post_type() ),
		);

		wp_localize_script( 'stella-terra', 'terra', $data );
	}

	/**
	 * Initialise feed object
	 */
	public function create_feed() {
		return new \Nine3\Terra_Feed();
	}

	/**
	 * Allows read-only access to private properties.
	 * Cheers Matt.
	 *
	 * @param mixed $property The property to return, regardless of its visibility.
	 */
	public function __get( $property ) {
		return $this->$property;
	}
}
