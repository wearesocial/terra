<?php
/**
 * This is a utility class containing tools and helper functions for Terra.
 *
 * @package luna
 */

namespace Nine3;

/**
 * Functions include:
 * - __construct()
 * - add_search_filter()
 * - add_taxonomy_filter()
 * - add_dropdown_filter()
 * - add_custom_style_filter()
 * - add_radio_or_checkbox_filter()
 * - pagination()
 */
class Terra_Utils {
	/**
	 * $query the current query passed from terra feed.
	 *
	 * @var WP_Query
	 */
	protected $current_query;

	/**
	 * Sets up the $current_query var for later
	 *
	 * @param WP_Query $query the current query from terra feed.
	 */
	public function __construct( $query = false ) {
		if ( $query ) {
			$this->current_query = $query;
		} else {
			global $wp_query;
			$this->current_query = $wp_query;
		}
	}

	/**
	 * Add a search input field with the data-filter attribute
	 *
	 * @param array $args arguments to customise the search field.
	 * @return void
	 */
	public function add_search_filter( $args ) {
		$class = trim( $args['class'] . ' terra-filter' );

		printf(
			'<div class="%s">',
			esc_attr( $class )
		);

		$search = '';
		if ( ! empty( $_GET['filter-search'] ) ) {
			$search = sanitize_title( wp_unslash( $_GET['filter-search'] ) );
		}
		printf(
			'<input type="search" name="filter-search" value="%s" class="terra-search %s__input" placeholder="%s" data-debouce="%d" />',
			esc_attr( $search ),
			esc_attr( $args['class'] ),
			esc_attr( $args['placeholder'] ),
			isset( $args['debounce'] ) ? esc_attr( $args['debounce'] ) : 200
		);

		if ( isset( $args['icon'] ) ) {
			printf(
				'<button class="%s__icon">%s</button>',
				esc_html( $args['class'] ),
				$args['icon'] // phpcs:ignore
			);
		}

		echo '</div>';
	}

	/**
	 * Helper function to simplify rendering taxonomy filters.
	 *
	 * @param array $tax_array array given of tax and label to loop through.
	 */
	public function loop_taxonomy_filters( array $tax_array ) {
		foreach ( $tax_array as $tax => $args ) :
			ob_start();
			?>
			<div class="archive-container__filter-wrap">
				<?php if ( isset( $args['single'] ) ) : ?>
				<label class="archive-container__label" for="filter-<?php echo esc_html( $tax ); ?>"><?php echo esc_html( $args['single'] ); ?></label>
				<?php endif; ?>

				<?php
				$this->add_taxonomy_filter(
					$tax,
					[
						'class'       => 'filter-select archive-container__filter',
						'placeholder' => $args['plural'],
						'hide_empty'  => true,
					]
				);
				?>
			</div>
			<?php
			ob_get_flush();
		endforeach;
	}

	/**
	 * Generate the Terra filters from specified taxonomy.
	 *
	 * @param string $taxonomy the taxonomy to filter.
	 * @param array  $args the arguments.
	 * @param string $style type of HTML selector to generate.
	 *
	 * @throws \Exception When using an unknown style.
	 */
	public function add_taxonomy_filter( $taxonomy, $args = [], $style = 'select' ) {
		$term_args = $args['term-args'] ?? [];

		// If hide empty set only the terms found in current query posts.
		if ( isset( $args['hide_empty'] ) ) {
			if ( is_object( $args['hide_empty'] ) ) {
				$all_posts = $args['hide_empty'];
			} else {
				$all_posts = get_posts(
					[
						'fields'         => 'ids',
						'post_type'      => $this->current_query->get( 'post_type' ),
						'posts_per_page' => -1,
					]
				);
			}

			$terms = wp_get_object_terms( $all_posts, $taxonomy );
		} else {
			$terms = get_terms(
				array_merge(
					$term_args,
					[ 'taxonomy' => $taxonomy ]
				)
			);
		}

		if ( isset( $args['reverse'] ) && $args['reverse'] ) {
			$terms = array_reverse( $terms );
		}

		$values = [];

		foreach ( $terms as $term ) {
			$values[ $term->slug ] = $term->name;
		}

		$args['name']   = $taxonomy;
		$args['values'] = $values;

		if ( $style === 'select' ) {
			$this->add_dropdown_filter( $args );
		} elseif ( $style === 'checkbox' ) {
			$this->add_radio_or_checkbox_filter( $args, 'checkbox' );
		} elseif ( $style === 'radio' ) {
			$this->add_radio_or_checkbox_filter( $args, 'radio' );
		} else {
			throw new \Exception( 'Terra: Unkown style specified for add_taxonomy_filter: ' . $style );
		}
	}

	/**
	 * Generate a <select> "filter" from the source array.
	 *
	 * @param array $args arguments used to generate the <select> tag.
	 *
	 * @throws \Exception If the name parameter is empty.
	 * @throws \Exception If the values parameter is not an array.
	 */
	public function add_dropdown_filter( $args = [] ) {
		$defaults = [
			'name'            => '', // required!
			'values'          => [], // required!
			'placeholder'     => '',
			'clearable'       => true,
			'class'           => '',
			'custom-style'    => false,
			'multiple'        => false,
			'icon'            => '',
			'button-type'     => 'submit',
			'is-meta-filter'  => false,
			'term-args'       => [],
			'container_class' => '',
			'before'          => '',
			'after'           => '',
			'custom-name'     => '',
			'selected'        => '',
		];

		$args = wp_parse_args( $args, $defaults );

		if ( empty( $args['name'] ) ) {
			echo 'Terra: No name specified for the filter!';
			throw new \Exception( 'Terra: No name specified for the filter!' );
		}

		if ( ! is_array( $args['values'] ) ) {
			echo 'Terra: "Values" argument must be an array!';
			throw new \Exception( 'Terra: "Values" argument must be an array' );
		}

		// Is it a meta filter or custom name?
		$is_meta_filter = $args['is-meta-filter'];
		$custom_filter  = $args['custom-name'];

		// Check if the filter is present in the url.
		$name   = sanitize_title( $args['name'] );
		$class  = trim( $args['class'] . ' terra__select terra-filter' );
		$filter = $is_meta_filter ? 'meta' : 'filter';
		if ( $custom_filter ) {
			$filter = $custom_filter;
		}
		$filter_name = sprintf( '%s-%s', $filter, $name );

		// Add selected parameter.
		// This allows us to load a page with a pre-seleced term.
		$custom_selected = $args['selected'];

		// Sets the selected term on page load.
		if ( ! empty( $custom_selected ) ) {
			$selected = $custom_selected;
		} else {
			if ( isset( $_COOKIE[ $filter_name ] ) && ! isset( $_GET[ $filter_name ] ) ) {
				// If a cookie is set eg: $_COOKIE['filter-category'] it will have priority here.
				$selected = sanitize_text_field( wp_unslash( $_COOKIE[ $filter_name ] ) );
			} else if ( isset( $_GET[ $filter_name ] ) ) {
				// Otherwise the term is set from the $_GET param.
				$selected = sanitize_text_field( wp_unslash( $_GET[ $filter_name ] ) );
			} else {
				$selected = '';
			}
		}

		if ( $args['before'] ) {
			echo $args['before']; // phpcs:ignore
		}

		$select_class = $class . ' ' . $args['container_class'];

		// If not using the custom-style we need to let the JS know about it, as we
		// need to handle the change event for this element.
		if ( ! $args['custom-style'] ) {
			$select_class .= ' default-style';
		}

		printf(
			'<select name="%s-%s" data-filter="filter-%s" class="%s" %s>',
			esc_attr( $filter ),
			esc_attr( $name ),
			esc_attr( $name ),
			esc_attr( $select_class ),
			$args['multiple'] ? 'multiple' : ''
		);

		// The placeholder, if set, is added as disbaled option.
		if ( ! empty( $args['placeholder'] ) ) {
			printf(
				'<option %s disabled="disabled" style="display: none;">%s</option>',
				selected( null, $selected, false ),
				esc_html( $args['placeholder'] )
			);
		}

		/**
		 * 'Clearable' option.
		 * if the the arg is a string then it is used as the option's label.
		 */
		if ( $args['clearable'] ) {
			$label = is_string( $args['clearable'] ) ? $args['clearable'] : __( 'Show all', 'luna' );
			if ( $args['custom-name'] ) {
				$args['values'] = [ 'null' => $label ] + $args['values'];
			} else {
				$args['values'] = [ '' => $label ] + $args['values'];
			}
		}

		foreach ( $args['values'] as $value => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $value ),
				$value !== '' ? selected( $value, $selected, false ) : '',
				esc_html( $label )
			);
		}
		echo '</select>';

		// Creates a custom wrapper and <li> + <button> tags for a custom select menu.
		if ( $args['custom-style'] ) {
			$this->add_custom_style_filter( $name, $args, $selected );
		}

		if ( $args['after'] ) {
			echo $args['after'];  // phpcs:ignore
		}
	}

	/**
	 * Generate the HTML for the custom <select>
	 *
	 * @param string $name the filter name.
	 * @param array  $args the filter arguments.
	 * @param string $selected the selected value.
	 * @return void
	 */
	private function add_custom_style_filter( $name, $args, $selected ) {
		$values = $args['values'];

		// BEM base class.
		$class = isset( $args['class'] ) && ! empty( $args['class'] ) ? esc_attr( $args['class'] ) : esc_attr( $name );
		printf(
			'<div class="terra__dropdown %s">',
			esc_attr( $class . ' ' . $args['container_class'] )
		);

		$selected_label = '';
		if ( $selected !== '' ) {
			foreach ( $values as $value => $label ) {
				if ( $selected == $value ) {
					$selected_label = $label;

					break;
				}
			}
		} elseif ( ! empty( $args['placeholder'] ) ) {
			$selected_label = $args['placeholder'];
		} else {
			$selected_label = current( $values );
		}

		printf(
			'<button type="button" class="terra__dropdown__selected %s__selected">
				<span class="terra__dropdown__selected__label %s__span">%s</span>
				<span class="terra__dropdown__selected__icon %s__icon">%s</span>
			</button>',
			esc_html( $class ),
			esc_html( $class ),
			esc_html( $selected_label ),
			esc_html( $class ),
			$args['icon'] // phpcs:ignore
		);

		printf(
			'<div class="terra__dropdown__list %s__list" style="display: none"><ul class="terra__dropdown__list__items %s__list__items">',
			esc_html( $class ),
			esc_html( $class )
		);

		foreach ( $values as $key => $value ) {
			printf(
				'<li class="terra__dropdown__list__item %s__list__item">
					<button type="%s" data-filter="filter-%s" class="terra__dropdown__list__button %s__list__button" name="filter-%s" value="%s">
					%s
				</li>',
				esc_html( $class ),
				esc_html( $args['button-type'] ),
				esc_attr( $args['name'] ),
				esc_html( $class ),
				esc_attr( $args['name'] ),
				esc_html( $key ),
				esc_html( $value )
			);
		}

		echo '</ul></div></div>';
	}

	/**
	 * Add radio/checkbox buttons for the filter specified
	 *
	 * @param array  $args arguments.
	 * @param string $type the filter type: radio/checkbox.
	 * @throws \Exception If the type parameter is empty.
	 */
	public function add_radio_or_checkbox_filter( $args, $type ) {
		$current_query  = $this->current_query;
		$name           = $args['name'];
		$class          = $args['class'];
		$values         = $args['values'];
		$icon           = $args['icon'] ?? '';
		$placeholder    = $args['placeholder'] ?? false;
		$is_meta_filter = $args['is-meta-filter'] ?? false;
		$filter         = $is_meta_filter ? 'meta-' : 'filter-';
		$filter_name    = sprintf( '%s%s', $filter, $name );

		/**
		 * Check if the field is present in the $current_query as taxonomy or meta field
		 */
		$selected = '';
		$filter   = sanitize_title( $filter );
		if ( isset( $_GET[ $filter_name ] ) ) {
			$selected = sanitize_text_field( wp_unslash( $_GET[ $filter_name ] ) );
		} else {
			$tax_query = $current_query->get( 'tax_query' );

			if ( ! empty( $tax_query ) ) {
				$selected = [];

				foreach ( $tax_query as $tax_value ) {
					$tax_key = $tax_value['taxonomy'];
					$slug    = $tax_value['terms'];

					if ( $tax_key === $name ) {
						if ( ! is_array( $slug ) ) {
							$slug = [ $slug ];
						}

						$selected = array_merge( $selected, $slug );
					}
				}
			}
		}

		if ( empty( $type ) ) {
			throw new \Exception( 'Terra: Please specify the field type' );
		}

		// checkbox value is an array.
		if ( $type === 'checkbox' ) {
			$filter_name = $filter_name . '[]';
		}

		// Add a default option for radio buttons.
		if ( $type === 'radio' && $placeholder ) {
			$checked = $selected === false ? ' checked' : '';

			$values = [ '' => $args['placeholder'] ] + $values;
		}

		if ( isset( $args['container_class'] ) ) {
			echo '<div class="' . esc_attr( $args['container_class'] ) . '">';
		}

		// Add data-luna-toggle div.
		if ( isset( $args['toggle'] ) ) {
			echo '<div class="checkbox-trigger" data-luna-toggle="' . esc_attr( $args['toggle'] ) . '"></div>';
		}

		if ( $type === 'checkbox' && $placeholder ) {
			echo '<span class="placeholder">' . esc_html( $placeholder ) . '</span>';
		}

		if ( isset( $args['before'] ) ) {
			echo $args['before']; // phpcs:ignore
		}

		foreach ( $values as $key => $value ) {
			$checked = '';
			if ( ( is_array( $selected ) && in_array( $key, $selected ) ) || ( is_string( $selected ) && $selected == $key ) ) {
				$checked = ' checked';
			}

			if ( isset( $args['item_before'] ) ) {
				echo $args['item_before']; // PHPCS: XSS Ok.
			}

			printf(
				'<input
					class="%s terra__%s terra-filter"
					type="%s"
					id="filter-%s-%s"
					name="%s"
					value="%s"
					data-filter="filter-%s"
					%s
				/>
				<label class="%s__label terra__%s__label" 
					for="filter-%s-%s">%s%s</label>',
				esc_attr( $class ),
				esc_attr( $type ),
				esc_attr( $type ),
				esc_attr( $name ),
				esc_attr( $key ),
				esc_attr( $filter_name ),
				esc_attr( $key ),
				esc_attr( $name ),
				esc_attr( $checked ),
				esc_attr( $class ),
				esc_attr( $type ),
				esc_attr( $name ),
				esc_attr( $key ),
				esc_html( $value ),
				$icon // phpcs:ignore
			);

			if ( isset( $args['item_after'] ) ) {
				echo $args['item_after']; // PHPCS: XSS Ok.
			}
		}

		if ( isset( $args['after'] ) ) {
			echo $args['after']; // phpcs:ignore
		}

		if ( isset( $args['container_class'] ) ) {
			echo '</div>';
		}
	}

	/**
	 * Silly helper function to keep things a bit tidier in the template.
	 */
	public function posts_found() {
		global $terra;
		$terra->posts_found();
	}
}
