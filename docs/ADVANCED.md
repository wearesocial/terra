# Advanced

## Use Terra with custom WP_Query

You can also use Terra with custom loops by adding it to the array of options in the `create_feed()` method, or by passing it as a parameter to the `start()` method.

```php
// Custom loop.
$args  = [
	'post_type' => 'custom',
	'terra'     => 'custom',
];
$posts = new WP_Query( $args );

// Using create_feed():
$feed = $terra->create_feed(
	true,
	[
		'name'     => 'custom',
		'class'    => 'archive-custom',
		'query'    => $posts,
	]
);

// Or... using start():
$feed = $terra->create_feed();

$feed->start( 'custom', 'archive-custom', $posts );
```

**NOTE**: The `terra` and `name` parameters have to be the same for Terra to know which filters to load/restore.

## Single Item and Content None templates

By default Terra looks for the following file name to render each post item:

```html
template-parts/[post-type]-single-item.php
```

or if there are no results:

```html
template-parts/[post-type]-single-item-none.php
```

Terra allows you to set template files from within `create_feed()` or `start()` methods. Simply add an array of `single` and `none` template strings pointing to the correct file in the theme, then add as an `$option` array with _'template'_ key to `create_feed()` or as a parameter to `start()`:

```php
$template = [
	'single' => 'template-parts/single-post-item',
	'none'   => 'template-parts/content-none',
];

// Using create_feed():
$feed = $terra->create_feed(
	true,
	[
		'name'     => 'custom',
		'class'    => 'archive-custom',
		'query'    => $posts,
		'template' => $template,
	]
);

// Or... using start():
$feed = $terra->create_feed();

$feed->start( 'custom', 'archive-custom', $posts, $template );
```

The default file name can also be overriden using the `terra_template__[name]` filter, more info [here]().

## Filter taxonomy terms and hide empty

Terra has functionality to cross reference and disable taxonomy terms if no posts are found. For example, say you have a feed of posts which can be filtered by Category and by Tag, if you select and filter by Category 'News', Terra will check and disable any Tag terms where those 'News' posts do not appear.

To achieve this simply add an array of taxonomies to cross reference to the `create_feed()` options (named _'filter_tax'_) or to `start()` parameters:

```php
$filter_tax = [
	'post_tag',
	'category',
];

// Using create_feed():
$feed = $terra->create_feed(
	true,
	[
		'name'       => 'custom',
		'class'      => 'archive-custom',
		'query'      => $posts,
		'template'   => $template,
		'filter_tax' => $filter_tax,
	]
);

// Or... using start():
$feed = $terra->create_feed();

$feed->start( 'custom', 'archive-custom', $posts, $template, $filter_tax );
```
## Customise the "Load More" button

If you want/need to use a custom **Load more** button, instead of the built-in provided by the [end]() method, add a simple HTML submit button between the `start` (or `create_feed`) and `end` methods.

```html
<!-- Normal input type submit -->
<input type="submit" value="Custom load more button" />

<!-- Or you can use a <button> -->
<button type="submit">This is my custom load more button</button>
```

**NOTE**: If you're adding your custom button inside the Terra container, you have to also output it for every ajax request, because the container will be automatically emptied. (See note below)

**NOTE**: If you're custom button has to append items to the container, instead of emptying it, your custom element **MUST** use the class `terra-submit`:

```html
<!-- This custom button triggers the `load-more` functionality -->
<input type="submit" value="Load more" class="terra-submit" />
```

## Posts found

```php
$feed->posts_found( $query = null, $single = '', $plural = '' );
```

You can use the built-in `posts_found` function to display the # of posts found, and Terra will take care to
update automatically update it when performing dynamic filtering.

Example:

```php
$feed->posts_found(
	null,
  __( 'post', 'my-theme' ),
  __( 'posts', 'my-theme' )
);

// Or just:
$feed->posts_found();
```
