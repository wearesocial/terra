<?php
/**
 * This class generates and loads the html needed for Terra filtering.
 *
 * @package stella
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
 * - posts_found()
 * - get_temp_data()
 */
class Terra_Feed {
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
	 * The utils object
	 *
	 * @var object
	 */
	public $utils;

	/**
	 * String in case we want to change the template part name for single post
	 *
	 * @var $template
	 */
	protected $template;

	/**
	 * Array of taxonomies to be used in load_more() to cross-reference
	 * and hide empty terms
	 *
	 * @var $filter_tax
	 */
	protected $filter_tax;

	/**
	 * Initialise and enqueue the terra script.
	 *
	 * @param bool  $start set to true to run start().
	 * @param array $options for start().
	 */
	public function __construct( $start = false, $options = null ) {
		// Adding main ajax actions.
		add_action( 'wp_ajax_nine3_terra', [ $this, 'load_more' ] );
		add_action( 'wp_ajax_nopriv_nine3_terra', [ $this, 'load_more' ] );

		// Include the terra.js script.
		wp_enqueue_script( 'stella-terra' );

		// Terra will take care of applying the filters present in the url.
		add_action( 'pre_get_posts', [ $this, 'pre_get_posts' ], 99, 1 );

		// Load utils and inject query.
		$query       = isset( $options['query'] ) ? $options['query'] : false;
		$this->utils = new Terra_Utils( $query );

		// If start is true create start() method.
		if ( $start && isset( $options ) && is_array( $options ) ) {
			// Create new variables for each $options key.
			foreach ( $options as $key => $value ) {
				$$key = $value;
			}
			$this->start( $name, $class, $query, $template, $filter_tax );
		}

		// Set init var so we don't double anything up on start().
		$this->terra_init = $start;
	}

	/**
	 * Parse the data sent via $_POST and so loads the new posts.
	 */
	public static function load_more() {
		check_ajax_referer( 'terra', 'nonce' );

		if ( ! isset( $_POST['terraFilter'] ) ) {
			die( -1 );
		}

		// Terra has been initialised.
		do_action( 'terra_init' );

		// The form name (used for the filters).
		$name               = sanitize_title( wp_unslash( $_POST['terraName'] ) ); // phpcs:ignore
		$this->current_name = $name;

		// Allow to run some action for a specific filter only.
		do_action( 'terra_init__' . $name );

		// The WP_Query arguments.
		$params = [];

		if ( isset( $_POST['params'] ) ) {
			$post_data = wp_unslash( $_POST['params'] ); // phpcs:ignore
			parse_str( $post_data, $params );
			unset( $params['query'] );
		}

		// Build the WP_Query $args parameters.
		$args = [
			'post_status' => 'publish',
		];

		// Load the temp file generated with the "hidden" settings.
		$query_args                 = [];
		$terra                      = []; // Internal parameters.
		list( $query_args, $terra ) = $this->get_temp_data();

		if ( is_array( $query_args ) ) {
			$args = array_merge( $args, $query_args );
		}

		foreach ( $params as $key => $param ) {
			if ( stripos( $key, 'terra-' ) === 0 ) {
				$key = str_replace( 'terra-', '', $key );

				$terra[ $key ] = $param;
			} elseif ( is_array( $param ) ) {
				continue;
			} else {
				$params[ $key ] = maybe_unserialize( $param );
			}
		}

		$this->utils->debug( 'FORM NAME: ' . $this->$current_name );
		$this->utils->debug( 'Params received:' );
		$this->utils->debug( $params );

		$args = $this->filter_wp_query( $args, $params );

		if ( empty( $params['filter-search'] ) ) {
			$this->utils->debug( 'SEARCH EMPTY' );
			unset( $args['s'] );
		}

		// Lets us modify the args for each form.
		$args = apply_filters( 'terra_args__' . $name, $args, $params, $terra );

		$this->utils->debug( 'WP_Query arguments applied:' );
		$this->utils->debug( $args );

		// WP_Query run.
		$posts     = [];
		$post_type = $args['post_type'] ?? 'post';

		if ( ! empty( $args ) ) {
			$posts = new \WP_Query( $args );

			$this->utils->debug( $posts->request, '(SQL) ' );

			$this->current_query = $posts;

			// This is custom functionality to compile a list of all taxonomy terms for each post.
			// This is later used in terra.js to disable dropdown options.
			if ( $this->filter_tax ) {
				$tax_terms              = [];
				$args['posts_per_page'] = -1;
				$filter_posts           = new \WP_Query( $args );

				$post_ids = wp_list_pluck( $filter_posts->posts, 'ID' );

				foreach ( $post_ids as $post_id ) {
					$filter_tax = $this->filter_tax;

					if ( ! empty( $filter_tax ) ) {
						foreach ( $filter_tax as $ftx ) {
							$all_tax_obj[] = get_the_terms( $post_id, $ftx );
						}
					}

					if ( is_array( $all_tax_obj ) || is_object( $all_tax_obj ) ) {
						foreach ( $all_tax_obj as $tax_obj ) {
							foreach ( $tax_obj as $single_tax ) {
								if ( ! in_array( $single_tax->slug, $tax_terms, true ) ) {
									array_push( $tax_terms, $single_tax->slug );
								}
							}
						}
					}
				}
			}
		}

		// Allow 3rd party to inject HTML before the terra's loop.
		do_action( 'terra_before_loop__' . $name, $posts, $args, $params );

		/**
		 * By default we're looking for the following file name:
		 * -> template-parts/[post-type]-single-item.php
		 * or
		 * -> template-parts/[post-type]-single-item-none.php
		 * if there are no results.
		 *
		 * The default file name can be overriden using terra_template__[name] filter.
		 * The start method also allows the injection of custom template names.
		 */

		/**
		 * Check if a template name has been set
		 *
		 * If string then assume it's a name for posts found template
		 * eg: 'template' => 'template-parts/post-single-item',
		 *
		 * If array then set single and none found template
		 * eg:
		 * 'template' => [
		 *   'single' => 'template-parts/post-single-item',
		 *   'none'   => 'template-parts/content-none',
		 * ],
		 */
		if ( $this->template ) {
			if ( is_array( $this->template ) ) {
				$template_single = $this->template['single'];
				$template_none   = $this->template['none'];
			} elseif ( is_string( $this->template ) ) {
				$template_single = $this->template;
			}
		}

		if ( ( method_exists( $posts, 'have_posts' ) ) ) {
			if ( $posts->have_posts() ) {
				$count = 0;
				while ( $posts->have_posts() ) {
					$posts->the_post();

					$post_type = get_post_type( get_the_ID() );

					if ( $template_single ) {
						$template = apply_filters( 'terra_template__' . $name, $template_single, $post_type, $args );
					} else {
						$template = apply_filters( 'terra_template__' . $name, 'template-parts/' . $post_type . '-single-item', $post_type, $args );
					}

					$this->utils->debug( sprintf( 'Using single template: "%s" for "%s (%d)"', $template, get_the_title(), get_the_ID() ) );

					if ( $post_type !== null ) {
						get_template_part( $template );
					}
					$count++;
				}
			} else {
				if ( $template_none ) {
					$template = apply_filters( 'terra_template__' . $name . '_none', $template_none, $post_type, $args );
				} else {
					$template = apply_filters( 'terra_template__' . $name . '_none', 'template-parts/' . $post_type . '-single-item-none', $post_type, $args );
				}

				$this->utils->debug( sprintf( 'Using single template: "%s" for "%s (%d)"', $template, get_the_title(), get_the_ID() ) );

				if ( $post_type !== null ) {
					get_template_part( $template );
				}
			}
		}

		if ( is_array( $args ) ) {
			$found      = intval( $posts->found_posts );
			$post_count = intval( $posts->post_count );

			// Let's put back the custom pagination.
			if ( isset( $terra['pagination'] ) ) {
				$this->utils->pagination( $this->$current_name, $posts, true, $terra );
			}

			// Need to show the # of posts found?
			if ( isset( $terra['posts-found'] ) ) {
				$this->posts_found( $terra['posts-found-single'], $terra['posts-found-plural'] );
			}
		}

		// User can add some extra HTML after the loop, like pagination, etc.
		do_action( 'terra_after_loop__' . $name, $posts, $args, $params );

		// Lets add custom HTML tags for later use with terra.js.
		if ( isset( $found ) ) {
			$offset       = intval( $args['offset'] ?? 0 );
			$terra_offset = $offset + $post_count;

			printf( '<terra-posts-count>%d</terra-posts-count>', (int) $post_count );
			printf( '<terra-offset>%d</terra-offset>', (int) $terra_offset );
			printf( '<terra-found>%d</terra-found>', (int) $found );
		}

		// Print cusom tags for tax filters.
		if ( isset( $tax_terms ) ) {
			$tax_terms = implode( ',', $tax_terms );
			printf( '<terra-tax>%s</terra-tax>', esc_html( $tax_terms ) );
		}

		wp_reset_postdata();

		die();
	}

	/**
	 * Filter the WP_Query arguments by applying the data received from the URL.
	 *
	 * @param array $args array of arguments to pass to WP_Query.
	 * @param array $params the $_POST data.
	 */
	public function filter_wp_query( $args, $params = [] ) {
		$is_search = isset( $params['filter-search'] ) & ! empty( $params['filter-search'] );

		/**
		 * To avoid conflict with WP the taxonomy names are prefixed with "filter-"
		 * While internal parameters are prefixed with 'terra-'
		 */
		$terra  = [];
		$data   = [];
		$meta   = [];
		$others = [];

		foreach ( $params as $key => $value ) {
			$key   = sanitize_text_field( $key );
			$value = is_array( $value ) ? $value : sanitize_text_field( $value );

			if ( strpos( $key, 'terra-' ) === 0 ) {
				$key           = str_replace( 'terra-', '', $key );
				$terra[ $key ] = $value;
			} elseif ( strpos( $key, 'meta-' ) === 0 ) {
				$key = str_replace( 'meta-', '', $key );

				/**
				 * The meta field is always defined when using TERRA's built-in functions.
				 * So, need to check if the filter needs to be applied.
				 */
				if ( empty( $value ) ) {
					/**
					 * If the meta value passed is empty, we need to check if it exists in the original
					 * query and if so we have to remove it!
					 */
					$meta_query = $args['meta_query'] ?? [];

					foreach ( $meta_query as $id => $arg ) {
						if ( $arg['key'] === $key ) {
							unset( $args['meta_query'][ $id ] );
						}
					}

					continue;
				}

				/**
				 * Check if the filter is present as a previous $params, if so we need to:
				 *  - Convert the "value" as array (if is not an array yet)
				 *  - append it to the list of values
				 */
				if ( isset( $meta[ $key ] ) ) {
					if ( ! is_array( $meta[ $key ]['value'] ) ) {
						$meta[ $key ]['compare'] = 'IN';
						$meta[ $key ]['value']   = [ $meta[ $key ]['value'] ];
					}

					if ( is_array( $value ) ) {
						foreach ( $value as $val ) {
							$meta[ $key ]['value'][] = sanitize_title( $val );
						}
					} else {
						$meta[ $key ]['value'][] = sanitize_title( $value );
					}
				} else {
					if ( is_array( $value ) ) {
						$value = array_map(
							function( $val ) {
									return sanitize_title( $val );
							},
							$value
						);
					} else {
						$value = sanitize_title( $value );
					}

					$meta[ $key ] = [
						'key'   => str_replace( 'meta-', '', $key ),
						'value' => $value,
					];
				}
			} elseif ( stripos( $key, 'filter-' ) === 0 ) {
				$key          = str_replace( 'filter-', '', $key );
				$data[ $key ] = $value;
			}
		}

		/**
		 * Sanitize the $_POST data.
		 * Also, any filter that is not a taxonomy will be ignored.
		 */
		$taxonomies = [];
		foreach ( $data as $key => $value ) {

			if ( taxonomy_exists( $key ) ) {
				if ( ! is_array( $value ) ) {
					$value = [ $value ];
				}

				$taxonomies[ $key ] = $value;
			} else {
				$others[ $key ] = $value;
			}
		}

		/**
		 * Only "special" keys are kept, like:
		 * - search
		 * - sort
		 * - sortby
		 * - offset
		 *
		 * This will prevent the user from passing any custom argument to the WP_Query, like:
		 *  https://example.com?posts_per_page=-1&post_type=post
		 */
		$sort   = $others['sort'] ?? '';
		$sortby = $others['sortby'] ?? '';
		$this->utils->debug( $others, 'others ' );

		if ( ! empty( $sort ) ) {
			$args['order'] = $sort;
		}

		if ( ! empty( $sortby ) ) {
			$args['orderby'] = 'post_' . $sortby;
		}

		// Search?
		$search = $others['search'] ?? '';
		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}

		/**
		 * Offset conflicts with 'paged', can't use both.
		 * Also offset have to be used only when clicking the LOAD MORE button.
		 */
		$append = $_POST['terraAppend'] ?? false; // phpcs:ignore
		$this->utils->debug( $append, 'Append: ' );
		if ( $append === 'true' && isset( $params['posts-offset'] ) ) {
			$args['offset'] = intval( $params['posts-offset'] );
		}

		$args = $this->modify_wp_query_args( $args, $taxonomies, $meta );

		// Allow 3rd part to modify the $args array.
		return apply_filters( 'terra_args', $args, $params );
	}

	/**
	 * Apply the pre_get_posts filter
	 *
	 * It is possible to allow TERRA to filter your query by just adding the 'terra' => '1', to the
	 * arguments of your WP_Query.
	 *
	 * @param object $query the WP_Query object.
	 *
	 * @return void
	 */
	public function pre_get_posts( $query ) {
		$filter_main_query = isset( $_GET['query'] ) && ! empty( $_GET['query'] ) && $query->is_main_query();
		$need_filtering    = ! empty( $query->get( 'terra' ) );

		if ( $filter_main_query || $need_filtering ) {
			$args = [];

			// Allow 3rd part to modify the $args array.
			if ( $filter_main_query ) {
				$this->current_name = sanitize_title( wp_unslash( $_GET['query'] ) );
			}

			/**
			 * When passing 'terra' => '...' to the custom query, normal pagination does not get considered.
			 * We have to manually check it.
			 */
			if ( $need_filtering ) {
				$this->current_name = sanitize_title( $query->get( 'terra' ) );

				$current_page = max( 1, get_query_var( 'paged' ) );

				if ( $current_page > 1 ) {
					$args['paged'] = $current_page;
				}
			}

			$this->utils->debug( 'FORM NAME: ' . $this->current_name );
			$this->utils->debug( 'Params received:' );
			$this->utils->debug( $_GET );

			// Prevent the parameter "posts-offset" from being passed in the URL.
			if ( isset( $_GET['posts-offset'] ) ) {
				unset( $_GET['posts-offset'] );
			}

			$args = $this->filter_wp_query( $args, $_GET );
			$args = apply_filters( 'terra_args__' . $this->current_name, $args, $_GET, [] );

			$this->utils->debug( 'Custom $args values:' );
			$this->utils->debug( $args );

			if ( is_array( $args ) ) {
				foreach ( $args as $key => $value ) {
					$query->set( $key, $value );
				}

				$this->utils->debug( 'WP_Query query_vars:' );
				$this->utils->debug( array_filter( $query->query_vars ) );
			}
		}
	}

	/**
	 * Use the $args to properly set up the argument array needed for the WP_Query.
	 *
	 * For example:
	 * Information like 'taxonomy' are passed as simple array/string by the form, so we need
	 * to convert it in a "taxonomy_query" array used by WP_Query.
	 *
	 * @param array $args WP_Query args to modify.
	 * @param array $taxonomies list of taxonomies to filter.
	 * @param array $meta the meta_query data.
	 */
	private function modify_wp_query_args( $args, $taxonomies = [], $meta = [] ) {
		// Append the taxonomy term.
		if ( isset( $args['taxonomy'] ) && isset( $args['term_taxonomy_id'] ) ) {
			$tax_query[] = [
				'taxonomy' => $args['taxonomy'],
				'field'    => 'term_id',
				'terms'    => array( (int) $args['term_taxonomy_id'] ),
			];

			unset( $args['taxonomy'] );
			unset( $args['term_taxonomy_id'] );
		}

		// Check if there is any taxonomy to filter.
		if ( ! isset( $args['tax_query'] ) || ! is_array( $args['tax_query'] ) ) {
			$args['tax_query'] = [];
		}

		$this->utils->debug( $taxonomies, '$taxonomies ' );

		foreach ( $taxonomies as $taxonomy => $values ) {
			if ( is_array( $values ) ) {
				$values = array_filter( $values );
			}

			if ( empty( $values ) ) {
				continue;
			}

			$tq = [
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => $values,
			];

			if ( is_array( $values ) ) {
				$tq['compare'] = 'IN';
			}

			$args['tax_query'][] = $tq;
		}

		/**
		 * Category?
		 */
		// Filter by category id(s).
		if ( isset( $args['category'] ) ) {
			$values = $args['category'];

			if ( ! is_array( $values ) ) {
				$values = array( $values );
			}

			$args['category__in'] = $values;
			unset( $args['category'] );
		}

		// Filter by category name(s).
		if ( isset( $args['category-name'] ) ) {
			$values = $args['category-name'];

			if ( is_array( $values ) ) {
				$values = implode( ',', array( $values ) );
			}

			$args['category_name'] = $values;

			unset( $args['category__in'] );
			unset( $args['category-name'] );
			unset( $args['categoryName'] );
		}

		/**
		 * Tag?
		 */
		if ( isset( $args['tag'] ) ) {
			$values = $args['tag'];

			if ( ! is_array( $values ) ) {
				$values = array( $values );
			}
			$args['tag__in'] = $values;
			unset( $args['tag'] );
		}

		/**
		 * Meta filter?
		 */
		if ( ! empty( $meta ) ) {
			if ( ! isset( $args['meta_key'] ) ) {
				$args['meta_query'] = [];
			}

			$args['meta_query'] += $meta;
		}

		return $args;
	}

	/**
	 * Generate the markup for the opening tag form.
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
		global $wp_query;

		if ( empty( $name ) ) {
			echo 'Terra: No name specified!';
			throw new \Exception( 'Terra: No name specified!' );
		}

		// Name used to trigger actions and filters.
		$name = sanitize_title( $name );

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
		$this->template     = $template;
		$this->filter_tax   = $filter_tax;
		$this->generate_hidden_fields( $query );
	}

	/**
	 * Markup for the container used to inject the new elements loaded via ajax.
	 *
	 * @param string $class the extra class to use for the container.
	 */
	public function container_start( $class = '' ) {
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
	 */
	public function container_end( $show_pagination = false ) {
		// The pagination has to be part of the container, as it has to be deleted for every request.
		if ( $show_pagination ) {
			$this->utils->pagination( $this->current_name, $this->current_query );
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
		$value = apply_filters( 'terra_hidden_field', $value, $name );
		$value = apply_filters( 'terra_hidden_field__' . $this->current_name, $value, $name );

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
				if ( isset( $_GET[ 'meta-' . $meta['key'] ] ) ) {
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
				$button_label = __( 'LOAD MORE', 'stella' );
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
			$this->utils->debug( 'TERRA: cannot generate temp file, TERRA will not work properly!' );

			do_action( 'terra_temp_failed' );
		}

		echo '</form>';
	}

	/**
	 * Show how many posts have been found using the single and plural form
	 *
	 * $this->current_query is used to get the # of posts found.
	 *
	 * @param string $single The text that will be used if $number is 1.
	 * @param string $plural The text that will be used if $number is plural.
	 */
	public function posts_found( $single, $plural ) {
		$label = sprintf( _n( $single, $plural, $this->current_query->found_posts ), $this->current_query->found_posts ); // phpcs:ignore

		if ( wp_doing_ajax() ) {
			echo '<terra-posts-found-label>' . esc_html( $label ) . '</terra-posts-found-label>';
		} else {
			$this->hidden_terra_field( 'posts-found', 1 );
			$this->hidden_terra_field( 'posts-found-single', $single );
			$this->hidden_terra_field( 'posts-found-plural', $plural );

			$hidden = '';

			echo '<span class="terra-posts-found__label">' . esc_html( $label ) . '</span>';
		}
	}

	/**
	 * Get the query_args and terra_args data stored in the temp file
	 *
	 * @return array
	 */
	private function get_temp_data() {
		$uid    = sanitize_title( $_POST['uid'] ); // phpcs:ignore
		$return = [ [], [] ];

		$this->utils->debug( 'UID: ' . $uid );
		$this->utils->debug( $_POST, '$_POST: ' ); // phpcs:ignore

		// Nothing to do here.
		if ( empty( $uid ) ) {
			return $return;
		}

		// Save the "temp" data.
		$temp_file = trailingslashit( sys_get_temp_dir() ) . 'terra-' . $uid;
		if ( ! file_exists( $temp_file ) ) {
			$this->utils->debug( 'TERRA: cannot load temp file!' );

			do_action( 'terra_temp_failed' );

			return $return;
		}

		include $temp_file;

		$this->utils->debug( 'Parameters loaded' );
		$this->utils->debug( $query_args, '($query_args) ' );
		$this->utils->debug( $terra_args, '($terra_args) ' );

		return [ $query_args, $terra_args ];
	}
}
