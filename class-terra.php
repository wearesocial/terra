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
	}

	/**
	 * Register and localize the script.
	 */
	public function register_script() {
		$url = trailingslashit( str_replace( ABSPATH, home_url( '/' ), __DIR__ ) );
		// If we're debugging use /src - if production use /dist.
		$dist = ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || $this->develop ) ? 'src/' : 'dist/';
		wp_register_script( 'stella-terra', $url . $dist . 'terra.js', [ 'jquery' ], TERRA_VERSION, true );

		/**
		 * To remove the https protocol replace ajaxurl with the following:
		 * preg_replace( '/https?:\/\//', '//', admin_url( 'admin-ajax.php' ) )
		 */
		$data = [
			'ajaxurl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'terra' ),
			'archiveurl' => get_post_type() == 'post' ? get_permalink( get_option( 'page_for_posts' ) ) : get_post_type_archive_link( get_post_type() ),
		];

		wp_localize_script( 'stella-terra', 'terra', $data );
	}

	/**
	 * Initialise feed object
	 *
	 * @param bool  $start set to true to run start from inside the fee construct().
	 * @param array $options for start().
	 */
	public function create_feed( $start = false, $options = null ) {
		return new \Nine3\Terra_Feed( $start, $options );
	}
}
