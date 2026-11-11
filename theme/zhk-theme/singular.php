<?php
/**
 * Новость или обычная страница.
 */
defined( 'ABSPATH' ) || exit;
get_header();
the_post();
?>

<article class="container prose">
	<?php if ( is_single() ) : ?>
		<a class="link-back" href="<?php echo esc_url( get_permalink( get_option( 'page_for_posts' ) ) ); ?>">← Все новости</a>
		<time class="news-card__date" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date( 'j F Y' ) ); ?></time>
	<?php endif; ?>
	<h1 class="prose__title"><?php the_title(); ?></h1>
	<?php the_content(); ?>
</article>

<?php
get_footer();
