# Hooks & Filters

## Terra initialised on ajax request

When Terra is initialised after the ajax request the following actions are triggered:

```php
do_action( 'terra_init' );

// FORM NAME is the name passed to create_feed() or start() methods.
do_action( 'terra__[FORM NAME]' );
```

**NOTE:** `[FORM NAME]` is the form name specified when calling the [create_feed() or start()]() methods.

### Temp file

To avoid exposing the query parameters inside the HTML generated, Terra stores the data needed to properly run the WP_Query when performing ajax calls inside a temporary file.

This solution should work without problem in any environment and hosting, but in case something goes wrong this filter will be executed:

```php
do_action( 'terra_temp_failed' );
```

An error message is also printed in the debug log.

## Using a custom single item template

By default Terra is looking for the template (unless otherwise directed in `create_feed` or `start` methods):

`template-parts/[POST-TYPE]-single-item.php`

and, if no items are found:

`template-parts/[POST-TYPE]-single-item-none.php`

These values can be respectively customised with the following filters:

```php
$template = apply_filters( 'terra_template__[FORM NAME]', 'template-parts/' . $post_type . '-single-item.php', $post_type, $args );
```

and

```php
$template = apply_filters( 'terra_template__[FORM NAME]_none', 'template-parts/' . $post_type . '-single-item-none.php', $post_type, $args );
```

**NOTE:** `[FORM NAME]` is the form name specified when calling the [create_feed() or start()]() methods.

### Example

```php
/**
 * The template to use is "template-parts/new-file-to-use.php"
 */
add_filter( 'terra_template__custom', function( $template_name, $post_type, $args ) {
	return 'template-parts/new-file-to-use';
}, 10, 3 );
```
```php
/**
 * We can also use some logic to not display a post
 */
add_filter( 'terra_template__custom', function( $template_name, $post_type, $args ) {
  if ( get_the_ID() === 1 ) {
    return null;  // We don't want the post with ID 1 to be ever displayed!
  }

  return $template_name;
}, 10, 3 );
```

## WP_Query parameters

