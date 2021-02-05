# Dynamic filtering

The class offers built-in solutions to generate dropdowns, checkbox and radio buttons lists for the specified taxonomy or a custom list.

## Taxonomy

To add filter by taxonomy just use:

```php
$feed->utils->add_taxonomy_filter( $taxonomy, $args, $style );
```

- \$taxonomy (String) the taxonomy slug
- \$args (Array) array of arguments used to style the dropdown:
  | argument | description |
  |---|---|
  |after| The custom HTML to print after the dropdown.|
  |before| The custom HTML to print before the dropdown.|
  |class| The class name prefix to use for the HTML elements to be created. _NOTE_ All the class names for the child elements follow the [BEM Metodology](http://getbem.com/introduction/). |
  | container_class | Class to assign to the &lt;select&gt; and the main container generated when using the custom-style. |
  |placeholder| Default label to be shown. |
  |clearable| If true the placeholder can be selected to clear the filter. _(Default true)_ |
  |custom-style| (For dropdowns only) If set to true, generates a custom dropdown that can be easily styled. _(Default false)_|
  || also a normal &lt;select&gt; element is rendered to be used as native elements for mobile or when JS is not available.
  |icon| - Dropdown icon.|
  || - radio/checkboxes the icon is added on the right of the label.|
  |item_after| (for Checkbox/Radio only) the custom HTML to print after the input & radio one.|
  |item_before| (for Checkbox/Radio only) the custom HTML to print before the input & radio one.|
  |term-args| Array of arguments to be passed as 2nd argument of the [get_terms](https://developer.wordpress.org/reference/functions/get_terms/) function.|
  |button-type| The button type inside the custom select (default is Submit).|
  |is-meta-filter| Need to let Terra know how to treat this filter as meta.|
  |multiple| Add the 'multiple' attribute to the &lt;select&gt; tag. |
	|custom-name| Overwrite Terra generated name for the &lt;select&gt; tag. |
	|selected| Pre-select a specific term on page load. |
	|reverse| Reverses the list of terms. |
	|hide_empty| Hides terms with no posts. _(Default true)_ |
	|toggle| (for Checkbox/Radio only) adds HTML to create a trigger for [luna-toggle](https://www.npmjs.com/package/luna-toggle). |

- \$style (String) which style to use to render the list of taxonomy.
  |value|description|
  |---|---|
  |select| &lt;select&gt; (single option only, unless 'multiple' arg is added ). _Default_|
  |checkbox| Checkboxes (multiple options can be selected) |
  |radio| Radio (single option only) |

### Usage

```php
  $feed->utils->add_taxonomy_filter(
    'industry',
		[
      'class'        => 'm14__filter',
      'placeholder'  => __( 'All industries', 'my-theme' ),
      'icon'         => svg( 'icon-arrow-down' ),
    ]
  )

  // Style parameter using checkbox
  $feed->utils->add_taxonomy_filter(
    'industry',
		[
      'class'        => 'm14__filter',
      'placeholder'  => __( 'All industries', 'my-theme' ),
      'icon'         => svg( 'icon-arrow-down' ),
    ],
    'checkbox'
  );

  // Wrap items in custom HTML elements
  echo '<ul>';
  $feed->utils->add_taxonomy_filter(
    'industry',
		[
      'class'  => 'm14__filter',
      'before' => '<li class="item">',
      'after'  => '</li>',
    ],
    'radio'
  );
  echo '</ul>';
```

### Taxonomies loop helper function

Use this function to loop through taxonomies and generate required HTML.

```php
$tax_filters = [
	'region'  => [
		'single' => __( 'Region', 'my-theme' ),
		'plural' => __( 'Regions', 'my-theme' ),
	],
	'industry' => [
		'single' => __( 'Industry', 'my-theme' ),
		'plural' => __( 'Industries', 'my-theme' ),
	],
];

$feed->utils->loop_taxonomy_filters( $tax_filters );
```

Example of output HTML:

```html
<div class="archive-container__filter-wrap">
	<label class="archive-container__label" for="filter-region">Region</label>
	<select name="filter-region" data-filter="filter-region" class="filter-select archive-container__filter terra__select terra-filter  default-style" tabindex="0">
		<option selected="selected" disabled="disabled" style="display: none;">Regions</option>
		<option value="">Show all</option>
		<option value="americas">Americas</option>
		<option value="asia">Asia</option>
		<option value="australasia">Australasia</option>
		<option value="europe">Europe</option>
		<option value="other">Other</option>
	</select>
</div>
```

## Search field

```php
$feed->utils->add_search_filter( $args );
```

- `$args` (Array) of arguments used to customise the search field

| argument    | description                                                                            |
| ----------- | -------------------------------------------------------------------------------------- |
| class       | The custom class name to use for the HTML elements to be created.                       |
| placeholder | Default label to be shown. |
| debounce    | ms to wait before performing the load more. _(Default 10ms)_                             |
| icon        | iIf set prints the icon inside a &lt;button&gt; element.                                 |

### Usage

```php
$feed->utils->add_search_filter(
	[
		'placeholder' => __( 'Enter your search term...' ),
		'icon'        => svg( 'search-icon-menu' ),
		'class'       => 'm14__search',
	]
);
```

## Custom filter

Is possible to use the built-in filter functions to generate [dropdown](#dropdown-example), [checkbox](#checkbox-example) and [radio](#radio-example) inputs by passing custom values.

- \$args (Array) array of arguments used to style the dropdown - see [add_taxonomy_filter()]() above.

### Dropdown example

The code below shows the Sort filter:

```php
$feed->utils->add_dropdown_filter(
	[
		'name'        => 'sort',
		'class'       => 'm32__sort',
		'placeholder' => 'Sort by',
		'clearable'   => false,
		'values'      => [
			'ASC'  => __( 'Ascending', 'my-theme' ),
			'DESC' => __( 'Descending', 'my-theme' ),
		],
		'icon' => svg( 'icon-arrow-down' ),
	]
);
```

## Filter Meta fields

You just need to pass the property `is-meta-filter = true`:

### Dropdown example

```php
$meta = [
  'value1' => 'Label 1',
  'value2' => 'Label 2',
];

$feed->utils->add_dropdown_filter(
  [
    'name'           => 'my_meta_key',
    'placeholder'    => __( 'Select meta field', 'my-theme' ),
    'values'         => $meta,
    'is-meta-filter' => true,
  ]
);
```

For radio or checkbox inputs, instead of `$feed->utils->add_dropdown_filter();`

Use: `$feed->utils->add_radio_filter()`

Or: `$feed->utils->add_checkbox_filter()`
