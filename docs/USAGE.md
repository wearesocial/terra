# Usage

## Initialise the class

Add the following line to your `functions.php` file, or inside the main script of your plugin:

```php
$GLOBALS['terra'] = new \Nine3\Terra();
```

#### Parameters

| parameter   | Type    | Required | Default |Description |
| ----------- | ------- | -------- | ------- | -------------------------------------------------------------- |
| \$develop | boolean |          | false    | Set to true to use uncompiled JS in the `/src` folder for debugging. |

## Use the class in your template

First, get the global object:

```php
global $terra;
```

### Create feed

A new feed instance can be created by running the `create_feed()` method with no parameters:

```php
$feed = $terra->create_feed();
```

We can then access `Terra_Feed` methods, eg: `$feed->start( $params );`

Or we can instantiate the feed and run the `start()` method at the same time:

```php
$feed = $terra->create_feed(
	true,
	$options
);
```

| parameter   | Type    | Required | Default | Description |
| ----------- | ------- | -------- | ------- | -------------------------------------------------------------- |
| \$start | boolean |          | false    | Set to true to instantiate `Terra_Feed` class and run `start()` method. |
| \$options | array |          |     | Set up array of options to pass to `start()` method. |

The `$options` array keys can only contain the following: name, class, query, template, filter_tax

Example:

```php
$options = [
	'name'      => '[FORM_NAME]',
	'class'     => 'archive-container',
	'template'  => [
		'single' => 'template-parts/single-post-item',
		'none'   => 'template-parts/content-none',
	],
	'filter_tax' => [
		'post_tag',
		'category',
	],
];
```

Example with custom query:

```php
$posts = new WP_Query(
  'post_type'   => 'post',
  'post_status' => 'publish',
  'terra'       => '[FORM_NAME]', // This parameter is needed!
);

$options = [
	'name'     => '[FORM_NAME]',
	'class'    => 'archive-container',
	'query'    => $posts,
	'template' => [
		'single' => 'template-parts/single-post-item',
		'none'   => 'template-parts/content-none',
	],
];
```

NOTE: The parameter 'terra' => '[FORM_NAME]' is needed to restore the filters used on the page load.

NOTE: The parameter [FORM_NAME] is the name of the form passed to the [Start]() method.

### Start

This method is needed by Terra to generate the HTML `<form>` tag. The method does not need to be used if you already used `$feed = $terra->create_feed( true, $options );`

```php
$feed->start( $name, $class = '', $query = null, $template = false, $filter_tax = false );
```
| parameter   | Type    | Required | Default | Description |
| ----------- | ------- | -------- | ------- | -------------------------------------------------------------- |
| \$name | string | Yes | | The "name" to assign to the form. _This parameter is also used to run all the internal filters_. |
| \$class | string | | | Custom class name to assign to the form. |
| \$query | WP_Query | | | Needed when using a custom loop to set up the data needed for Terra to work. |
| \$template | array/string | | | Either string for single post template name or array of single and none. |
| \$filter_tax | array | | | The array of taxonomies to later cross-reference dropdowns. |

Example:

```php
$feed = $terra->create_feed();

$posts = new WP_Query(
  'post_type'   => 'post',
  'post_status' => 'publish',
  'terra'       => 'news',
);

$template = [
	'single' => 'template-parts/single-post-item',
	'none'   => 'template-parts/content-none',
];

$feed->start( 'news', 'archive-news', $posts, $template );
```

### The container

The container is needed and used by the class to append/refresh the content loaded via ajax.

```php
/* start container */
$feed->container_start( $class = '' );

/* close the container */
$feed->container_end( $show_pagination = false );
```

| parameter  | Type    | Required | Default | Description |
| ---------- | ------- | -------- | ------- | ---------------------------------------------------------------------- |
| class | string  | | | The custom class name to assign to the container. |
| pagination | boolean | | false   | If true use a custom version of wp pagination that works with ajax. |

### End

```php
$feed->end( $load_more = false, $button_label = 'Load More' );
```

| parameter    | Type    | Required | Default   | Description |
| ------------ | ------- | -------- | --------- | -------------------------------------------------------------------------------------------- |
| load_more | boolean | | false | If true adds a submit button. |
| button_label| string  | | Load More | Allow customising the text of the &lt;button&gt; element. |

### Basic example of `index.php` template:

```php
global $terra;

get_header();

if ( have_posts() ) :

	$feed = $terra->create_feed(
		true,
		[
			'name'     => 'news',
			'class'    => 'archive-container',
			'template' => [
				'single' => 'template-parts/single-post-item',
				'none'   => 'template-parts/content-none',
			],
		]
	);

	$feed->container_start( 'archive-container__items archive-container__items--news' );

	while ( have_posts() ) :
		the_post();
		get_template_part( 'template-parts/single', 'post-item' );
	endwhile;

	$feed->container_end( true );

	$feed->end();

endif;

get_footer();
```
