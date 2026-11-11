<?php
/**
 * Список новостей и запасной шаблон.
 */
defined( 'ABSPATH' ) || exit;
get_header();
?>

<section class="page-head">
	<div class="container">
		<h1 class="page-head__title"><?php echo esc_html( is_home() ? get_the_title( get_option( 'page_for_posts' ) ) : wp_strip_all_tags( get_the_archive_title() ) ); ?></h1>
	</div>
</section>

<section class="container section section--tight">
	<?php if ( have_posts() ) : ?>
		<div class="news-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/news-card' );
			endwhile;
			?>
		</div>
		<?php the_posts_pagination( array( 'mid_size' => 1 ) ); ?>
	<?php else : ?>
		<p>Пока ничего нет.</p>
	<?php endif; ?>
</section>

<?php
get_footer();
