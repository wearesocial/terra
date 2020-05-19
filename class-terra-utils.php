<?php
/**
 * This is a utility class containing tools and helper functions for Terra.
 *
 * @package stella
 */

namespace Nine3;

/**
 * Functions include:
 * - add_search_filter()
 * - add_taxonomy_filter()
 * - add_dropdown_filter()
 * - add_custom_style_filter()
 * - add_radio_or_checkbox_filter()
 */
class Terra_Utils extends Terra {
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
		$terms     = get_terms(
			array_merge(
				$term_args,
				[ 'taxonomy' => $taxonomy ]
			)
		);

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
			'custom-style'    => true,
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
		// TODO: undocumented.
		$custom_selected = $args['selected'];

		// TODO: undocumented (cookies and custom).
		if ( ! empty( $custom_selected ) ) {
			$selected = $custom_selected;
		} else {
			if ( isset( $_COOKIE[ $filter_name ] ) && ! isset( $_GET[ $filter_name ] ) ) {
				$selected = sanitize_text_field( wp_unslash( $_COOKIE[ $filter_name ] ) );
			} else if ( isset( $_GET[ $filter_name ] ) ) {
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
		// TODO: test
		if ( ! $args['custom-style'] ) {
			$select_class .= ' default-style';
		}

		printf(
			'<select name="%s-%s" data-filter="filter-%s" class="%s" %s>',
			esc_attr( $filter ),
			esc_attr( $name ),
			esc_attr( $name ),
			esc_attr( $select_class ),
			$args['multiple'] ? 'multiple' : '' // TODO: ??
		);

		/**
		 * The placeholder, if set, is added as disbaled option.
		 */
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
			$label = is_string( $args['clearable'] ) ? $args['clearable'] : 'Show all';
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

		/**
		 * The <li> tag can't be styles
		 * http://msdn.microsoft.com/en-us/library/ms535877(v=vs.85).aspx
		 */
		// TODO: check this.
		if ( $args['custom-style'] ) {
			$this->add_custom_style_filter( $name, $args, $selected );
		}

		if ( $args['after'] ) {
			echo $args['after'];  // phpcs:ignore
		}
	}

	/**
	 * Generate the HTML for the custom <select>
	 * TODO: THIS WHOLE THING. Do we need it??
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
	 */
	public function add_radio_or_checkbox_filter( $args, $type ) {
		// TODO: double check everything.
		// global $wp_query;
		// parent::$current_query = $wp_query;

		var_dump( parent::$unique_id );

		$name           = $args['name'];
		$class          = $args['class'];
		$values         = $args['values'];
		$icon           = $args['icon'] ?? '';
		$placeholder    = $args['placeholder'] ?? false;
		$is_meta_filter = $args['is-meta-filter'] ?? false;

		$filter      = $is_meta_filter ? 'meta-' : 'filter-';
		$filter_name = sprintf( '%s%s', $filter, $name );

		/**
		 * Check if the field is present in the $current_query as taxonomy or meta field
		 */
		$selected = '';
		$filter   = sanitize_title( $filter );
		if ( isset( $_GET[ $filter_name ] ) ) {
			$selected = sanitize_title( wp_unslash( $_GET[ $filter_name ] ) );
		} else {
			$tax_query = parent::$current_query->get( 'tax_query' );

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
			echo '<div class="' . $args['container_class'] . '">';
		}

		if ( isset( $args['toggle'] ) ) {
			echo '<div class="checkbox-trigger" data-luna-toggle="' . $args['toggle'] . '"></div>';
		}

		if ( $type === 'checkbox' && $placeholder ) {
			echo '<span class="placeholder">' . $placeholder . '</span>';
		}

		if ( isset( $args['before'] ) ) {
			echo $args['before'];
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
			echo $args['after'];
		}

		if ( isset( $args['container_class'] ) ) {
			echo '</div>';
		}
	}
}
