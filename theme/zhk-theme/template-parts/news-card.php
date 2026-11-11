<?php
/**
 * Карточка новости — главная и список новостей.
 */
defined( 'ABSPATH' ) || exit;
?>
<article class="news-card">
	<time class="news-card__date" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date( 'j F Y' ) ); ?></time>
	<h3 class="news-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
	<p class="news-card__text"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></p>
</article>
