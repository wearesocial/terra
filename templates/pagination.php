<?php
/**
 * Pagination template
 *
 * This module can be override using the `terra_pagination` filters.
 *
 * @used by:
 *  - public static function pagination
 *
 * @package stella
 */

use function Nine3\Stella\Icons\svg;

global $current_page, $show_ends, $first_page, $args, $total_pages, $last_page, $container_name;

$next_label = '<i class="pagination__icon pagination__icon--next aria-hidden="true">' . svg( 'ico_next', false ) . '</i>';
$prev_label = '<i class="pagination__icon pagination__icon--prev aria-hidden="true">' . svg( 'ico_next', false ) . '</i>';

unset( $args['type'] );
$args = array_merge(
	$args,
	[
		'mid_size'  => 1,
		'next_text' => '<span class="screen-reader-text">Next page</span>' . $next_label,
		'prev_text' => '<span class="screen-reader-text">Previous page</span>' . $prev_label,
	]
);
?>

<nav class="pagination">
	<?php echo paginate_links( $args ); // phpcs:ignore ?>
</nav>
