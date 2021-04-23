<?php
/**
 * This class is a rewrite of Lama: https://93digital.gitlab.io/lama/
 * This class helps to filter taxonomies via ajax.
 * 
 * Install: composer require 93devs/terra:dev-master
 *
 * The class needs to be instantiated and globalised in functions.php
 * eg: GLOBALS['terra'] = new \Nine3\Terra( true );
 *
 * @package stella
 */

namespace Nine3;

define( 'TERRA_VERSION', '1.0.0' );

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
	 * The main query
	 *
	 * @var WP_Query
	 */
	private $current_query;

	/**
	 * The current form name.
	 *
	 * @var string
	 */
	private $current_name = null;

	/**
	 * Initialise.
	 *
	 * @param bool $develop true if debug.
	 */
	public function __construct( $develop = false ) {
		$this->develop = $develop;

		// Enqueue the JS script.
		add_action( 'wp_enqueue_scripts', [ $this, 'register_script' ] );

		// Adding main ajax actions.
		add_action( 'wp_ajax_nine3_terra', [ $this, 'load_more' ] );
		add_action( 'wp_ajax_nopriv_nine3_terra', [ $this, 'load_more' ] );

		// Terra will take care of applying the filters present in the url.
		add_action( 'pre_get_posts', [ $this, 'pre_get_posts' ], 99, 1 );

		// Se up ACF and Register block type.
		require_once __DIR__ . '/acf/custom-fields.php';
		add_action( 'acf/init', [ $this, 'terra_block_init' ] );
		// add_filter( 'allowed_block_types', [ $this, 'terra_add_allowed_block_type' ], 100, 2 );
		add_filter( 'acf/load_field/name=terra_post_type', [ $this, 'terra_populate_post_types' ] );
		add_filter( 'acf/load_field/name=terra_taxonomies', [ $this, 'terra_populate_taxonomies' ] );
		add_filter( 'acf/load_field/name=terra_term_select', [ $this, 'terra_populate_terms' ] );
		add_filter( 'acf/load_field/name=terra_template_select', [ $this, 'terra_populate_templates' ] );
		add_filter( 'acf/load_field/name=terra_template_none_select', [ $this, 'terra_populate_templates' ] );
	}

	/**
	 * Register and localize the script.
	 */
	public function register_script() {
		$url = trailingslashit( str_replace( ABSPATH, site_url( '/' ), __DIR__ ) );
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
	 * Parse the data sent via $_POST and so loads the new posts.
	 */
	public function load_more() {
		check_ajax_referer( 'terra', 'nonce' );

		if ( ! isset( $_POST['terraFilter'] ) ) {
			die( -1 );
		}

		// Terra has been initialised.
		do_action( 'terra_init' );

		// The form name (used for the filters).
		$name = sanitize_title( wp_unslash( $_POST['terraName'] ) ); // phpcs:ignore
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

		self::debug( 'FORM NAME: ' . $name );
		self::debug( 'Params received:' );
		self::debug( $params );

		$args = $this->filter_wp_query( $args, $params );

		if ( empty( $params['filter-search'] ) ) {
			self::debug( 'SEARCH EMPTY' );
			unset( $args['s'] );
		}

		// Lets us modify the args for each form.
		$args = apply_filters( 'terra_args__' . $name, $args, $params, $terra );

		// Force posts_per_page.
		if ( isset( $params['posts_per_page'] ) ) {
			$args['posts_per_page'] = $params['posts_per_page'];
		}

		self::debug( 'WP_Query arguments applied:' );
		self::debug( $args );

		// WP_Query run.
		$posts     = [];
		$post_type = $args['post_type'] ?? 'post';

		if ( ! empty( $args ) ) {
			$posts = new \WP_Query( $args );

			self::debug( $posts->request, '(SQL) ' );

			// Set $query for posts_found.
			$this->current_query = $posts;

			// This is custom functionality to compile a list of all taxonomy terms for each post.
			// This is later used in terra.js to disable dropdown options.
			if ( isset( $terra['filter_tax'] ) ) {
				$tax_terms              = [];
				$args['posts_per_page'] = -1;
				$filter_posts           = new \WP_Query( $args );

				$post_ids = wp_list_pluck( $filter_posts->posts, 'ID' );

				foreach ( $post_ids as $post_id ) {
					$filter_tax = $terra['filter_tax'];

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
		if ( isset( $terra['template'] ) ) {
			if ( is_array( $terra['template'] ) ) {
				$template_single = $terra['template']['single'];
				$template_none   = $terra['template']['none'];
			} elseif ( is_string( $terra['template'] ) ) {
				$template_single = $terra['template'];
			}
		}

		if ( ( method_exists( $posts, 'have_posts' ) ) ) {
			if ( $posts->have_posts() ) {
				$count = 0;
				while ( $posts->have_posts() ) {
					$posts->the_post();

					// Allow 3rd party to inject HTML inside terra's loop.
					do_action( 'terra_inside_loop_before__' . $name, $count, get_the_ID(), $params, $posts->post_count );

					$post_type = get_post_type( get_the_ID() );

					if ( $template_single ) {
						$template = apply_filters( 'terra_template__' . $name, $template_single, $post_type, $args );
					} else {
						$template = apply_filters( 'terra_template__' . $name, 'template-parts/' . $post_type . '-single-item', $post_type, $args );
					}

					self::debug( sprintf( 'Using single template: "%s" for "%s (%d)"', $template, get_the_title(), get_the_ID() ) );

					if ( $post_type !== null ) {
						get_template_part( $template );
					}

					// Allow 3rd party to inject HTML inside terra's loop.
					do_action( 'terra_inside_loop_after__' . $name, $count, get_the_ID(), $params, $posts->post_count );

					$count++;
				}
			} else {
				if ( $template_none ) {
					$template = apply_filters( 'terra_template__' . $name . '_none', $template_none, $post_type, $args );
				} else {
					$template = apply_filters( 'terra_template__' . $name . '_none', 'template-parts/' . $post_type . '-single-item-none', $post_type, $args );
				}

				self::debug( sprintf( 'Using single template: "%s" for "%s (%d)"', $template, get_the_title(), get_the_ID() ) );

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
				$this->pagination( $name, $posts, true, $terra );
			}

			// Need to show the # of posts found?
			if ( isset( $terra['posts-found'] ) || isset( $params['posts-found'] ) ) {
				$this->posts_found( $posts, __( 'result', 'stella' ), __( 'results', 'stella' ) );
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
			$this->current_query = $query;

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

				// This is needed if we have 2 archives on the same page.
				if ( isset( $_GET['terra'] ) && $query->query_vars['terra'] !== $_GET['terra'] ) {
					$current_page = 1;
				}

				if ( $current_page > 1 ) {
					$args['paged'] = $current_page;
				}
			}

			self::debug( 'FORM NAME: ' . $this->current_name );
			self::debug( 'Params received:' );
			self::debug( $_GET );

			// Prevent the parameter "posts-offset" from being passed in the URL.
			if ( isset( $_GET['posts-offset'] ) ) {
				unset( $_GET['posts-offset'] );
			}
			if ( isset( $_GET['posts_per_page'] ) ) {
				unset( $_GET['posts_per_page'] );
			}

			$args = $this->filter_wp_query( $args, $_GET );
			$args = apply_filters( 'terra_args__' . $this->current_name, $args, $_GET, [] );

			self::debug( 'Custom $args values:' );
			self::debug( $args );

			if ( is_array( $args ) ) {
				foreach ( $args as $key => $value ) {
					$query->set( $key, $value );
				}

				self::debug( 'WP_Query query_vars:' );
				self::debug( array_filter( $query->query_vars ) );
			}
		}
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
		self::debug( $others, 'others ' );

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
		 * TODO
		 */
		$append = $_POST['terraAppend'] ?? false; // phpcs:ignore
		self::debug( $append, 'Append: ' );
		if ( $append === 'true' && isset( $params['posts-offset'] ) ) {
			$args['offset'] = intval( $params['posts-offset'] );
		}

		// Reset tax query if it's a pre-filtered feed.
		if ( isset( $args['terra-feed'] ) ) {
			$args['tax_query'] = [];
		}

		$args = $this->modify_wp_query_args( $args, $taxonomies, $meta );

		// Allow 3rd part to modify the $args array.
		return apply_filters( 'terra_args', $args, $params );
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
			$args['tax_query'][] = [
				'taxonomy' => $args['taxonomy'],
				'field'    => 'term_id',
				'terms'    => array( (int) $args['term_taxonomy_id'] ),
			];

			unset( $args['taxonomy'] );
			unset( $args['term_taxonomy_id'] );
		}

		self::debug( $taxonomies, '$taxonomies ' );

		if ( ! empty( $taxonomies ) ) {
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
	 * Get the query_args and terra_args data stored in the temp file
	 *
	 * @return array
	 */
	private function get_temp_data() {
		$uid    = sanitize_title( $_POST['uid'] ); // phpcs:ignore
		$return = [ [], [] ];

		self::debug( 'UID: ' . $uid );
		self::debug( $_POST, '$_POST: ' ); // phpcs:ignore

		// Nothing to do here.
		if ( empty( $uid ) ) {
			return $return;
		}

		// Save the "temp" data.
		$temp_file = trailingslashit( sys_get_temp_dir() ) . 'terra-' . $uid;
		if ( ! file_exists( $temp_file ) ) {
			self::debug( 'TERRA: cannot load temp file!' );

			do_action( 'terra_temp_failed' );

			return $return;
		}

		include $temp_file;

		self::debug( 'Parameters loaded' );
		self::debug( $query_args, '($query_args) ' );
		self::debug( $terra_args, '($terra_args) ' );

		return [ $query_args, $terra_args ];
	}

	/**
	 * The Custom pagination
	 *
	 * @param string   $current_name the name of the current feed.
	 * @param WP_Query $query the main or custom query.
	 * @param bool     $show_ends whether to show links to the first and last page (where applicable).
	 * @param array    $params the pagination options.
	 * @return void
	 */
	public function pagination( $current_name, $query, $show_ends = true, $params = [], $multiple = false ) {
		global $wp_query, $wp;

		// Needed by the pagination template.
		global $current_page, $show_ends, $first_page, $args, $total_pages, $last_page;

		$total_pages = $query->max_num_pages;
		if ( $total_pages < 2 ) {
			return;
		}

		$current_page = max( 1, get_query_var( 'paged' ) );

		// This is needed if we have 2 archives on the same page.
		if ( $multiple && isset( $_GET['terra'] ) && $query->query_vars['terra'] !== $_GET['terra'] ) {
			$current_page = 1;
		}

		// Get any custom $_GET params from the url, these will be appended to page links further down.
		$custom_params = count( $_GET ) > 0 ? '?' . http_build_query( $_GET ) : '';

		// Get the base url of the current archive/taxonomy/whatever page without any pagination queries.
		if ( isset( $params['base_url'] ) ) {
			$base_url = $params['base_url'];
		} else {
			$base_url = explode( '?', get_pagenum_link( 1 ) )[0];
		}

		// Get the current filter args from $params, needs to be appended to the base_url.
		if ( isset( $_POST['params'] ) ) { // phpcs:ignore
			parse_str( $_POST['params'], $params_array ); // phpcs:ignore
			foreach ( $params_array as $key => $value ) {
				if (
					strpos( $key, 'terra-' ) !== false ||
					empty( $value ) ||
					stripos( $key, 'query-' ) !== false ||
					stripos( $key, 'posts-' ) !== false
				) {
					unset( $params_array[ $key ] );
				}
			}
			$params_string = http_build_query( $params_array );
			if ( strpos( $custom_params, '?' ) === 0 ) {
				$custom_params .= $params_string;
			} else {
				$custom_params = '?' . $params_string;
			}
		}

		// Current category / taxonomy / archive url for first link.
		$page_slug = 'page';
		if ( function_exists( 'pll__' ) ) {
			$page_slug = pll__( 'page' );
		}

		$first_page = $base_url . $custom_params;
		$last_page  = $base_url . $page_slug . '/' . $total_pages . $custom_params;

		$args = [
			'base'      => $base_url . '%_%' . $custom_params,
			'format'    => $page_slug . '/%#%',
			'current'   => $current_page,
			'total'     => $total_pages,
			'type'      => 'list',
			'prev_text' => esc_html( 'Prev', 'stella' ),
			'next_text' => esc_html( 'Next', 'stella' ),
		];

		if ( $multiple ) {
			$args['add_args'] = [ 'terra' => $current_name ];
		}

		$args = apply_filters( 'terra_pagination_args', $args, $show_ends, $params );
		$args = apply_filters( 'terra_pagination_args__' . $current_name, $args, $show_ends, $params );

		$terra_template = dirname( __FILE__ ) . '/templates/pagination.php';

		$template = apply_filters( 'terra_pagination_template', $terra_template, $query, $args, $params );
		$template = apply_filters( 'terra_pagination_template__' . $current_name, $template, $query, $args, $params );

		self::debug( $args, '(pagination) ' );
		self::debug( $template, 'Pagination Template: ' );

		if ( $template ) {
			include $template;
		}
	}

	/**
	 * Show how many posts have been found using the single and plural form
	 *
	 * $this->current_query is used to get the # of posts found.
	 *
	 * @param WP_Query $query the curent query object.
	 * @param string   $single The text that will be used if $number is 1.
	 * @param string   $plural The text that will be used if $number is plural.
	 */
	public function posts_found( $query = null, $single = '', $plural = '' ) {
		if ( $query === null ) {
			if ( $this->current_query ) {
				$query = $this->current_query;
			} else {
				global $wp_query;
				$query = $wp_query;
			}
		}
		$query_vars = $query->query_vars;

		$page_total = (
			$query_vars['posts_per_page'] < $query->found_posts
			? $query_vars['posts_per_page']
			: $query->found_posts
		);

		$page = array_key_exists( 'paged', $query_vars ) && $query_vars['paged'] ? $query_vars['paged'] : 1;

		// Calculate paginated posts.
		$current = ( $page - 1 ) * $page_total + 1;
		$total   = $query->found_posts;
		if ( ( $page_total * $page ) < $query->found_posts ) {
			$total = ( $page_total * $page );
		}

		// Calculate 'load more' loaded posts.
		if ( $page === 1 && isset( $query_vars['offset'] ) ) {
			$total += $query_vars['offset'];

			if ( $total >= $query->found_posts ) {
				$total = $query->found_posts;
			}
		}

		$name_string = '';
		if ( ! empty( $single ) || ! empty( $plural ) ) {
			$name_string = ( $query->found_posts === 1 ) ? $single : $plural;
		}
		$found_string = __( 'Showing', 'stella' ) . " $current-$total " . __( 'of', 'stella' ) . " <strong>$query->found_posts</strong> $name_string";

		if ( wp_doing_ajax() ) {
			echo '<terra-posts-found-label>' . $found_string . '</terra-posts-found-label>';
		} else {
			echo '<input type="hidden" name="posts-found" value="1" />';
			echo '<span class="terra-posts-found__label">' . $found_string . '</span>';
		}
	}

	/**
	 * Initialise feed object
	 *
	 * Usage:
	 * First get the globalised $terra class:
	 * global $terra;
	 *
	 * A new feed instance can be created by running the method with no params:
	 * $feed = $terra->create_feed();
	 * We can then access Terra_Feed methods, eg: $feed->start( $params );
	 *
	 * Or we can instantiate the feed and run the start() method at the same time:
	 * $feed = $terra->create_feed(
	 *   true,
	 *   $options_array
	 * );
	 *
	 * @param bool  $start set to true to run start from inside the fee construct().
	 * @param array $options for start().
	 */
	public function create_feed( $start = false, $options = null ) {
		return new \Nine3\Terra_Feed( $start, $options );
	}

	/**
	 * Define new custom block for the Terra Feed.
	 */
	public function terra_block_init() {
		// Check function exists.
    if ( function_exists( 'acf_register_block_type' ) ) {
			$feed_template = apply_filters( 'terra_feed_block_template', __DIR__ . '/templates/feed-block.php' );

			// Register a terra block.
			acf_register_block_type(
				[
					'name'            => 'terra-feed',
					'title'           => __( 'Terra Feed', 'stella' ),
					'description'     => __( 'Creates a feed for display of filtered posts.', 'stella' ),
					'render_template' => $feed_template,
					'category'        => 'widgets',
					'enqueue_assets'  => function() {
						if ( is_admin() ) {
							$url = trailingslashit( str_replace( ABSPATH, site_url( '/' ), __DIR__ ) );
							// If we're debugging use /src - if production use /dist.
							$dist = ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || $this->develop ) ? 'src/' : 'dist/';
							wp_enqueue_script( 'stella-terra-feed', $url . $dist . 'feed-block.js', [ 'jquery' ], TERRA_VERSION, true );
						}
					},
				]
			);
		}
	}

	/**
	 * Add block to list of allowed.
	 *
	 * @param array   $allowed_block_types the array list of block types.
	 * @param WP_Post $post the post object.
	 */
	public function terra_add_allowed_block_type( $allowed_block_types, $post ) {
		if ( ! $allowed_block_types || ! is_array( $allowed_block_types ) ) {
			return;
		}

		array_push( $allowed_block_types, 'acf/terra-feed' );
    return $allowed_block_types;
	}

	/**
	 * Populate custom select field with post types from theme.
	 *
	 * @param array $field the array of field values.
	 */
	public function terra_populate_post_types( $field ) {
		// Reset choices.
    $field['choices'] = [];
    
    // Get CPTs.
		$post_types = get_post_types( [ 'public' => true ] );

    // Loop through array and add to field 'choices'.
    if ( is_array( $post_types ) ) { 
			foreach ( $post_types as $type ) {
				$field['choices'][ $type ] = $type;
			} 
    }

    return $field;
	}

	/**
	 * Populate custom select field with taxonomies from theme.
	 *
	 * @param array $field the array of field values.
	 */
	public function terra_populate_taxonomies( $field ) {
		// Reset choices.
    $field['choices'] = [];
    
    // Get CPTs.
		$taxonomies = get_taxonomies( [ 'public' => true ] );

    // Loop through array and add to field 'choices'.
    if ( is_array( $taxonomies ) ) { 
			foreach ( $taxonomies as $tax ) {
				$field['choices'][ $tax ] = $tax;
			} 
    }

    return $field;
	}

	/**
	 * Populate custom select field with terms.
	 *
	 * @param array $field the array of field values.
	 */
	public function terra_populate_terms( $field ) {
		// Reset choices.
    $field['choices'] = [];
    
    // Get CPTs.
		$taxonomies = get_taxonomies( [ 'public' => true ] );
		$tax_terms  = [];

    // Loop through array and add to field 'choices'.
    if ( is_array( $taxonomies ) ) { 
			foreach ( $taxonomies as $tax ) {
				$terms = get_terms(
					[
						'taxonomy'   => $tax,
						'hide_empty' => false,
					]
				);

				foreach ( $terms as $term ) {
					$tax_terms[ $tax ][ $term->term_id ] = $term->name;
				}
			} 
    }

		$field['choices'] = $tax_terms;

    return $field;
	}

	/**
	 * Populate custom select field with templates from theme.
	 *
	 * @param array $field the array of field values.
	 */
	public function terra_populate_templates( $field ) {
		$theme_path = get_template_directory();

		// Reset choices.
    $field['choices'] = [
			0        => __( 'Default Template', 'stella' ),
			'custom' => __( 'Custom Template', 'stella' ),
		];
    
    // Get template-parts files.
		$template_files = glob( $theme_path . '/template-parts/*' );

		//Loop through the array that glob returned.
		foreach ( $template_files as $filename ) {
			$filename = str_replace( $theme_path . '/', '', $filename );
			$filename = str_replace( '.php', '', $filename );
			$field['choices'][ $filename ] = $filename;
		}

    return $field;
	}

	/**
	 * Show debug message, if WP_DEBUG is defined and enabled.
	 *
	 * @param mixed  $message the log message. It will be converted in string using the print_r function.
	 * @param string $prefix string to prefix the log with.
	 * @return void
	 */
	public static function debug( $message, $prefix = '' ) {
		$log = 'TERRA ALERT: ' . $prefix . print_r( $message, true );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( $log );
		} elseif ( defined( 'TERRA_DEBUG' ) && TERRA_DEBUG ) {
			if ( is_string( $log ) ) {
				$log = date( 'Y-m-d H:i:s: ' ) . $log; // phpcs:ignore
			}

			file_put_contents( ABSPATH . '/wp-content/terra.log', $log . PHP_EOL, FILE_APPEND );
		}
	}
}
