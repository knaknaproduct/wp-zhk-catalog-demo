<?php
/**
 * Избранное: квартиры, которые посетитель отметил сердечком (хранятся в браузере).
 */
defined( 'ABSPATH' ) || exit;
get_header();
?>

<section class="page-head">
	<div class="container">
		<h1 class="page-head__title">Избранное</h1>
		<p class="page-head__lead">Квартиры сохраняются в этом браузере. Регистрация не нужна.</p>
	</div>
</section>

<section class="container catalog" data-catalog="favorites">
	<p class="catalog__count" data-count aria-live="polite">Загружаем…</p>
	<div class="cards" data-results></div>
	<div class="empty" data-empty hidden>
		<p>В избранном пока пусто.</p>
		<a class="btn" href="<?php echo esc_url( home_url( '/kvartiry/' ) ); ?>">Перейти к квартирам</a>
	</div>
</section>

<?php
get_footer();
