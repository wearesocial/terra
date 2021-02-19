<?php
/**
 * The template for displaying a Terra Feed block.
 *
 * @package stella
 */
global $terra;

$pre_filtered   = get_field( 'terra_pre_filter' );
$post_type      = get_field( 'terra_post_type' );
$post_type      = ! empty( $post_type ) ? $post_type : 'post';
$name           = get_field( 'terra_name' );
$name           = ! empty( $name ) ? $name : $post_type . '-feed';
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

if ( $sort === 'date' ) {
	$sort_values = [
		'DESC' => __( 'Newest first', 'stella' ),
		'ASC'  => __( 'Oldest first', 'stella' ),
	];
} else {
	$sort_values = [
		'ASC'  => __( 'A - Z', 'stella' ),
		'DESC' => __( 'Z - A', 'stella' ),
	];
}

if ( $sort === 'menu_order' ) {
	array_unshift( $sort_values, __( 'Default', 'stella' ) );
}

if ( $end === 'pagination' ) {
	$pagination = true;
} elseif ( $end === 'load_more' ) {
	$load_more = true;
}

$terra_args = [
	'posts_per_page' => $posts_per_page,
	'post_type'      => $post_type,
	'terra'          => $name,
	'orderby'        => $sort === 'disable' ? 'title' : $sort,
	'order'          => $sort === 'date' ? 'DESC' : 'ASC',
];

if ( $pre_filtered ) {
	$taxonomy = get_field( 'terra_taxonomies' );
	$term     = get_field( 'terra_term' );

	$terra_args['tax_query'] = [
		[
			'taxonomy' => $taxonomy,
			'field'    => 'name',
			'terms'    => $term,
		]
	];
}

if ( function_exists( 'pll_current_language' ) ) {
	$terra_args['lang'] = pll_current_language();
}

// Allow modification of args.
$terra_args = apply_filters( 'terra_block_args__' . $name, $terra_args, $name );

$terra_items = new WP_Query( $terra_args );

?>

<div class="archive terra-feed <?php echo $class ? esc_html( 'terra-feed__' . $class ) : '' ;?>">

	<?php
	if ( $terra_items->have_posts() ) :

		$feed = $terra->create_feed(
			true,
			[
				'name'     => $name,
				'class'    => 'archive-container__wrapper',
				'query'    => $terra_items,
				'template' => [
					'single' => $template,
					'none'   => $template_none,
				],
			]
		);
		?>

		<?php if ( $filters || $search ) : ?>
			<header class="archive-container__filters">
				<h4 class="archive-container__filters--heading"><?php esc_html_e( 'Filter by', 'stella' ); ?></h4>
				<?php
				if ( $filters ) :
					foreach ( $filters as $filter ) :
						?>
						<div class="archive-container__filter-wrap">
							<label class="archive-container__filter-label" for="filter-<?php echo esc_html( $filter['terra_taxonomies'] ); ?>"><?php echo esc_html( $filter['label'] ); ?></label>
							<?php
							$feed->utils->add_taxonomy_filter(
								$filter['terra_taxonomies'],
								[
									'class'       => 'filter-select archive-container__filter',
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
					<div class="archive-container__filter-wrap archive-container__filter-wrap--search">
						<label class="archive-container__label" for="filter-search"><?php esc_html_e( 'Search', 'stella' ); ?></label>
						<?php
						$feed->utils->add_search_filter(
							[
								'placeholder' => __( 'Enter search term', 'stella' ),
								'class'       => 'filter-search archive-container__search',
							]
						);
						?>
					</div>
					<?php
				endif;
				?>

				<button type="reset" value="" class="archive-container__reset">
					<?php esc_html_e( 'Reset', 'stella' ); ?>
				</button>
			</header>
		<?php endif; ?>

		<div class="container">
			<div class="archive-sorting">
				<?php
				if ( $post_count ) :
					?>
					<div class="archive-sorting__count">
						<?php
						$terra->posts_found( $terra_items );
						?>
					</div>
					<?php
				endif;
				?>

				<?php if ( $sort !== 'disable' ) : ?>
					<div class="archive-sorting__input">
						<label for="filter-sort"><?php esc_html_e( 'Sort:', 'stella' ); ?></label>
						<?php
						$feed->utils->add_dropdown_filter(
							[
								'name'        => 'sort',
								'class'       => 'archive-sorting__sort',
								'placeholder' => false,
								'clearable'   => false,
								'values'      => $sort_values,
							]
						);
						?>
					</div>
				<?php endif; ?>
			</div>

			<?php
			$feed->container_start( 'archive-container__items archive-container__items--' . $post_type );

			while ( $terra_items->have_posts() ) :
				$terra_items->the_post();
				get_template_part( $template );
			endwhile;

			$feed->container_end( $pagination );
			?>
		</div>

		<?php
		$feed->hidden_field( 'posts_per_page', $posts_per_page, '' );
		$feed->end( $load_more );

	else :
		get_template_part( $template_none );
	endif;
	?>

</div>
