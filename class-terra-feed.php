<?php
/**
 * This class generates and loads the html needed for Terra filtering.
 * This class is a rewrite of Lama: https://93digital.gitlab.io/lama/
 * This class helps to filter taxonomies via ajax (or XMLHttpRequest..?).
 *
 * @package luna
 */

namespace Nine3;

/**
 * Functions include:
 * - __construct()
 * - load_more()
 * - filter_wp_query()
 * - pre_get_posts()
 * - modify_wp_query_args()
 * - start()
 * - container_start()
 * - container_end()
 * - hidden_field()
 * - hidden_terra_field()
 * - hidden_query_field()
 * - generate_hidden_fields()
 * - end()
 */
class Terra_Feed extends Terra {
	/**
	 * Check to see if start() is called in __construct
	 *
	 * @var $terra_init
	 */
	private $terra_init;

	/**
	 * $query var used in start()
	 *
	 * @var WP_Query
	 */
	private $current_query;

	/**
	 * The unique id to identify the current query.
	 *
	 * @var string
	 */
	private $unique_id = null;

	/**
	 * Current query offset stored as need to be added after the closing tag.
	 *
	 * @var int
	 */
	private $offset = 0;

	/**
	 * The current form name.
	 *
	 * @var string
	 */
	private $current_name = null;

	/**
	 * Array to be saved in the temp file.
	 *
	 * This array stores the query data needed by Terra to work properly
	 *
	 * @var array
	 */
	private $temp_args = [];

	/**
	 * Array to be saved in the temp file.
	 *
	 * This array stores the internal Terra parameters.
	 *
	 * @var array
	 */
	private $temp_terra = [];

	/**
	 * The utils object
	 *
	 * @var object
	 */
	public $utils;

	/**
	 * String or array in case we want to change the template part name for single post
	 *
	 * @var $template
	 */
	private $template;

	/**
	 * Array of taxonomies to be used in load_more() to cross-reference
	 * and hide empty terms
	 *
	 * @var $filter_tax
	 */
	private $filter_tax;

	/**
	 * Create Feed and enqueue the terra script.
	 *
	 * @param bool  $start set to true to run start().
	 * @param array $options for start().
	 */
	public function __construct( $start = false, $options = null ) {
		// Enqueue the terra.js script.
		wp_enqueue_script( 'luna-terra' );

		// Load utils and inject query.
		$query       = isset( $options['query'] ) ? $options['query'] : null;
		$this->utils = new Terra_Utils( $query );

		// If start is true create start() method.
		if ( $start && isset( $options ) && is_array( $options ) ) {
			// Defaults.
			$name       = isset( $options['name'] ) ? $options['name'] : false;
			$class      = isset( $options['class'] ) ? $options['class'] : '';
			$template   = isset( $options['template'] ) ? $options['template'] : false;
			$filter_tax = isset( $options['filter_tax'] ) ? $options['filter_tax'] : false;
			$this->start( $name, $class, $query, $template, $filter_tax );
		}

		// Set init var so we don't double anything up on start().
		$this->terra_init = $start;
	}

	/**
	 * Generate the markup for the opening tag form.
	 *
	 * This methid can be called after the class is instantiated or by passing a true parameter
	 * (and options) into the $terra->create_feed( $start, $options ) method.
	 *
	 * @param string   $name a name used to specify the current form.
	 * @param string   $class a class to add to the form.
	 * @param WP_Query $query the custom query used. This is needed to figure out if there are more posts to load.
	 * @param mixed    $template either string for single post template name or array of single and none.
	 * @param array    $filter_tax the array of taxonomies to later cross-reference dropdowns.
	 *
	 * @throws \Exception Exception if 'name' parameter is empty.
	 */
	public function start( $name, $class = '', $query = null, $template = false, $filter_tax = false ) {
		if ( $this->terra_init ) {
			return;
		}

		global $wp_query;

		if ( empty( $name ) ) {
			echo 'Terra: No name specified!';
			throw new \Exception( 'Terra: No name specified!' );
		}

		// Name used to trigger actions and filters.
		$name = sanitize_title( $name );

		// Add utils if not exists.
		if ( ! isset( $this->utils ) && ! is_null( $query ) ) {
			$this->utils = new Terra_Utils( $query );
		}

		// If $query isn't set use default $wp_query.
		if ( is_null( $query ) ) {
			$query = $wp_query;
		}
		$this->current_query = $query;

		// Create a unique ID for the query.
		// Used later for temp storage.
		$this->unique_id = spl_object_hash( $query );

		// Are there more items to be loaded?
		$count        = isset( $query->posts ) ? count( $query->posts ) : 0;
		$found_posts  = $query->found_posts ?? 0;
		$this->offset = $count;

		// The form tag.
		$classes = [ 'terra' ];

		// Add class if there are no more posts to be loaded.
		if ( $count >= $found_posts ) {
			$classes[] = 'terra-more--none';
		}

		$classes = join( ' ', $classes );
		$class   = trim( $classes . ' ' . $class );
		$action  = '';

		// Build the form element.
		// We need the data attribute for the AJAX request.
		$uid = $this->unique_id;
		printf(
			'<form id="filters" name="%s" action="%s" method="get" class="%s" data-uid="%s">',
			esc_attr( $name ),
			esc_attr( $action ),
			esc_attr( $class ),
			esc_attr( $uid )
		);

		$this->current_name = $name;
		$this->hidden_terra_field( 'template', $template );
		$this->hidden_terra_field( 'filter_tax', $filter_tax );
		$this->generate_hidden_fields( $query );
	}

	/**
	 * Markup for the container used to inject the new elements loaded via ajax.
	 *
	 * @param string $class the extra class to use for the container.
	 */
	public function container_start( $class = '') {
		printf(
			'<div id="%s" class="terra-container %s">',
			esc_attr( $this->current_name ),
			esc_attr( $class )
		);
	}

	/**
	 * The end of the container.
	 *
	 * @param bool $show_pagination if true add the custom pagination.
	 * @param bool   $multiple whether we have several feeds on one page, default = false.
	 */
	public function container_end( $show_pagination = false, $multiple = false  ) {
		// The pagination has to be part of the container, as it has to be deleted for every request.
		if ( $show_pagination ) {
			$this->pagination( $this->current_name, $this->current_query, true, [], $multiple );
		}

		echo '</div>';

		/**
		 * The field doesn't have to be deleted on the ajax request, that's
		 * why is outside the container
		 */
		if ( $show_pagination ) {
			$this->hidden_terra_field( 'pagination', 1 );
		}
	}

	/**
	 * Hidden fields are generated so Terra knows which parameters exist in the query used.
	 *
	 * All the fields prefixed with 'query' are stored in $temp_args and added to the temp file.
	 * Main WP query will contain args such as post_type, eg: $args['post_type'] = 'resources'.
	 *
	 * Any Terra fields prefixed with 'terra-' will be stored in $temp_terra and also added to the temp file.
	 *
	 * @param string $name the field name.
	 * @param string $value the field value.
	 * @param string $prefix the prefix to prepend to the field.
	 */
	public function hidden_field( $name, $value, $prefix = 'field-' ) {
		// Return if no value.
		if ( empty( $value ) || $value === 0 ) {
			return;
		}

		if ( $prefix === 'query-' ) { 
			$this->temp_args[ $name ] = $value;
		} elseif ( $prefix === 'terra-' ) {
			$this->temp_terra[ $name ] = $value;
		} else {
			if ( is_array( $value ) || is_object( $value ) ) {
				$value = maybe_serialize( $value );
			}

			printf(
				'<input type="hidden" name="%s%s" value="%s" />',
				esc_attr( $prefix ),
				esc_attr( sanitize_title( $name ) ),
				esc_attr( $value )
			);
		}
	}

	/**
	 * Utility function used to call hidden_field with the 3rd parameter set to 'terra-'
	 *
	 * @param string $name  the field name.
	 * @param string $value the field value.
	 * @return void
	 */
	private function hidden_terra_field( $name, $value ) {
		$this->hidden_field( $name, $value, 'terra-' );
	}

	/**
	 * Utility function used to call hidden_field with the 3rd parameter set to 'query-'
	 *
	 * @param string $name  the field name.
	 * @param string $value the field value.
	 * @return void
	 */
	private function hidden_query_field( $name, $value ) {
		$this->hidden_field( $name, $value, 'query-' );
	}

	/**
	 * Generate the input hidden fields needed for the class to work properly
	 *
	 * @param WP_Query $query the WP_Query used to retrieve the query_vars information.
	 */
	private function generate_hidden_fields( $query ) {
		$query_vars = $query->query_vars;

		// Keys that do not have to be passed.
		$to_ignore = [
			'update_post_term_cache',
			'no_found_rows',
			'comments_per_page',
			'lazy_load_term_meta',
			'update_post_meta_cache',
			'nopaging',
			'terra',
			'cache-results',
			'posts_per_page',
			'order',
			'orderby',
			'query-cache_results',
			'cache_results',
			'paged',
		];

		/**
		 * Used to know if we need to append new items or replace the content.
		 *
		 * 0 => no load more (so ignore the offset parameter)
		 * 1 => load more
		 */
		// TODO: test this weirdness.
		$this->hidden_terra_field( 'more', 0 );

		// Setup tax query vars.
		$tax_query = $query->get( 'tax_query' );
		$taxonomy  = $query->get( 'taxonomy' );
		$term      = $query->get( 'term' );

		/**
		 * When using tax_query with a single term, WP also sets the properties:
		 *  - taxonomy
		 *  - term
		 *
		 * But we don't need them in the ajax call.
		 */
		if ( ! empty( $tax_query ) && ! empty( $taxonomy ) && ! empty( $term ) ) {
			foreach ( $tax_query as $tax ) {
				if ( isset( $tax['taxonomy'] ) && $tax['taxonomy'] === $taxonomy ) {
					$to_ignore[] = 'taxonomy';
					$to_ignore[] = 'term';
					break;
				}
			}
		}

		/**
		 * Do not need to store the 'tax_query' if the filter is applied via $_GET
		 */
		if ( is_array( $tax_query ) ) {
			$tax_to_apply = [];

			foreach ( $tax_query as $id => $tq ) {
				if ( isset( $_GET[ 'filter-' . $tq['taxonomy'] ] ) ) {
					$to_ignore[] = 'tax_query';
				} else {
					$tax_to_apply[ $id ] = $tq;
				}
			}

			$this->hidden_terra_field( 'tax_query', $tax_to_apply );
		}

		/**
		 * We don't have to store the meta_query if the filter is applied via $_GET,
		 * otherwise it will be applied every time we remove the filter.
		 */
		$meta_query = $query->get( 'meta_query' );
		if ( is_array( $meta_query ) ) {
			$meta_to_apply = [];

			foreach ( $meta_query as $id => $meta ) {
				// If is not in the URL has to be applied.
				$meta_key = isset( $meta['key'] ) ? $meta['key'] : '';
				if ( isset( $_GET[ 'meta-' . $meta_key ] ) ) {
					$to_ignore[] = 'meta_query';
				} else {
					$meta_to_apply[ $id ] = $meta;
				}
			}

			$this->hidden_terra_field( 'meta_query', $meta_to_apply );
		}

		foreach ( $_GET as $filter => $value ) {
			if ( $filter === 'filter-category' ) {
				$to_ignore[] = 'category_name';
				$to_ignore[] = 'cat';
			}
		}

		/**
		 * Sometime the `order` and `orderby` parameters causes problems to the
		 * main query, having WP displaying wrong result for the page.
		 * So, to avoid conflicts we internally use the keyword 'sort'
		 */
		$this->hidden_query_field( 'order', $query->get( 'order' ) );
		$this->hidden_query_field( 'orderby', $query->get( 'orderby' ) );

		// Let's store all the information needed.
		foreach ( $query_vars as $key => $value ) {
			if ( ! empty( $value ) && ! in_array( $key, $to_ignore, true ) ) {
				$this->hidden_query_field( $key, $value );
			}
		}

		// Let's add some custom information needed internally.
		$this->hidden_terra_field( 'base_url', explode( '?', get_pagenum_link( 1 ) )[0] );
		$this->hidden_terra_field( 'page_id', get_queried_object_id() );

		if ( $query->is_main_query() ) {
			$this->hidden_field( 'query', $this->current_name, '' );
		}
	}

	/**
	 * Close the form tag and add pagination if true.
	 *
	 * @param bool   $load_more if true add a submit "load more" button.
	 * @param string $button_label the button label.
	 */
	public function end( $load_more = false, $button_label = 'Load More' ) {
		// The offset.
		$this->hidden_field( 'offset', $this->offset, 'posts-' );

		if ( $load_more ) {
			if ( $button_label === null ) {
				$button_label = __( 'LOAD MORE', 'luna' );
			}

			echo '<button type="submit" class="terra-submit" value="load-more">' . esc_html( $button_label ) . '</button>';
		}

		// Save the temp data.
		$temp_file  = trailingslashit( sys_get_temp_dir() ) . 'terra-' . $this->unique_id;
		$query_data = var_export( $this->temp_args, true );
		$terra_data = var_export( $this->temp_terra, true );
		$temp_data  = '<?php $query_args = ' . $query_data . '; $terra_args = ' . $terra_data . ';';

		$saved = file_put_contents( $temp_file, $temp_data, LOCK_EX );

		if ( ! $saved ) {
			Terra::debug( 'TERRA: cannot generate temp file, TERRA will not work properly!' );

			do_action( 'terra_temp_failed' );
		}

		echo '</form>';
	}
}
