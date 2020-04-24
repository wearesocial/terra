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
	 * $query var used in start()
	 *
	 * @var WP_Query
	 */
	protected $current_query;

	/**
	 * The unique id to identify the current query.
	 *
	 * @var string
	 */
	protected $unique_id = null;

	/**
	 * Current query offset stored as need to be added after the closing tag.
	 *
	 * @var int
	 */
	protected $offset = 0;

	/**
	 * The current form name.
	 *
	 * @var string
	 */
	protected $current_name = null;

	/**
	 * Array to be saved in the temp file.
	 *
	 * This array stores the query data needed by Terra to work properly
	 *
	 * @var array
	 */
	protected $temp_args = [];

	/**
	 * Array to be saved in the temp file.
	 *
	 * This array stores the internal Terra parameters.
	 *
	 * @var array
	 */
	protected $temp_terra = [];

	/**
	 * Initialise.
	 *
	 * @param bool $develop true if debug.
	 */
	public function __construct( $develop = false ) {
		$this->develop = $develop;

		// Require subclasses.
		require_once 'class-terra-feed.php';
		require_once 'class-terra-utils.php';

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
	 * Allows read-only access to private properties.
	 * Cheers Matt.
	 *
	 * @param mixed $property The property to return, regardless of its visibility.
	 */
	public function __get( $property ) {
		return $property;
	}
}
