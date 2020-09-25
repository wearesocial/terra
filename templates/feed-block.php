<?php
/**
 * The template for displaying a Terra Feed block.
 *
 * @package stella
 */
global $terra;

$post_type      = get_field( 'terra_post_type' );
$post_type      = ! empty( $post_type ) ? $post_type : 'post';
$posts_per_page = get_field( 'terra_posts_per_page' );
$posts_per_page = ! empty( $posts_per_page ) ? $posts_per_page : get_option( 'posts_per_page' );
$class          = get_field( 'terra_class' );
$template       = get_field( 'terra_template' );
$template       = ! empty( $template ) ? $template : 'template-parts/' . $post_type . '-single-item';
$template_none  = get_field( 'terra_template_none' );
$template_none  = ! empty( $template_none ) ? $template_none : 'template-parts/content-none';
$filters        = get_field( 'terra_filters' );
$search         = get_field( 'terra_search' );
$post_count     = get_field( 'terra_post_count' );
$sort           = get_field( 'terra_sort' );
$end            = get_field( 'terra_end' );
$pagination     = false;
$load_more      = false;

if ( $end === 'pagination' ) {
	$pagination = true;
} elseif ( $end === 'load_morre' ) {
	$load_more = true;
}

$terra_items = new WP_Query(
	[
		'posts_per_page' => $posts_per_page,
		'post_type'      => $post_type,
	]
);

?>

<div class="archive terra-feed <?php echo $class ? esc_html( 'terra-feed__' . $class ) : '' ;?>">

	<?php
	if ( $terra_items->have_posts() ) :

		$feed = $terra->create_feed(
			true,
			[
				'name'     => $post_type . '-feed',
				'class'    => 'archive__wrapper',
				'query'    => $terra_items,
				'template' => [
					'single' => $template,
					'none'   => $template_none,
				],
			]
		);
		?>

		<header class="archive__filters">
			<?php
			if ( $filters ) :
				foreach ( $filters as $filter ) :
					?>
					<div class="archive__filter-wrap">
						<label class="archive__filter-label" for="filter-<?php echo esc_html( $filter['terra_taxonomies'] ); ?>"><?php echo esc_html( $filter['label'] ); ?></label>
						<?php
						$feed->utils->add_taxonomy_filter(
							$filter['terra_taxonomies'],
							[
								'class'       => 'filter-select archive__filter',
								'placeholder' => esc_html( $filter['placeholder'] ),
								'hide_empty'  => true,
							]
						);
						?>
					</div>
					<?php
				endforeach;
			endif;

			if ( $search ) :
				?>
				<div class="archive__filter-wrap archive__filter-wrap--search">
					<label class="archive__label" for="filter-search"><?php esc_html_e( 'Search', 'stella' ); ?></label>
					<?php
					$feed->utils->add_search_filter(
						[
							'placeholder' => __( 'Enter search term', 'stella' ),
							'class'       => 'filter-search archive__search',
						]
					);
					?>
				</div>
				<?php
			endif;
			?>

			<button type="reset" value="" class="archive__reset">
				<?php esc_html_e( 'Reset' ); ?>
			</button>
		</header>

		<div class="archive__sorting">
			<?php
			if ( $post_count ) :
				?>
				<div class="archive__sorting--count">
					<?php
					$terra->posts_found( $terra_items );
					?>
				</div>
				<?php
			endif;
			?>

			<div class="archive__sorting--input">
				<label for="filter-sort"><?php esc_html_e( 'Sort:', 'stella' ); ?></label>
				<?php
				$feed->utils->add_dropdown_filter(
					[
						'name'         => 'sort',
						'class'        => 'filter-sort',
						'placeholder'  => false,
						'clearable'    => false,
						'values'       => [
							'DESC' => __( 'Newest - Oldest', 'stella' ),
							'ASC'  => __( 'Oldest - Newest', 'stella' ),
						],
					]
				);
				?>
			</div>
		</div>

		<?php
		$feed->container_start( 'archive__items archive__items--' . $post_type );

		while ( $terra_items->have_posts() ) :
			$terra_items->the_post();
			get_template_part( $template );
		endwhile;

		$feed->container_end( $pagination );
		?>

		<?php
		$feed->hidden_field( 'posts_per_page', $posts_per_page, '' );
		$feed->end( $load_more );

	else :
		get_template_part( $template_none );
	endif;
	?>

</div>
