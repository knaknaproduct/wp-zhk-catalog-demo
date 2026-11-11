<?php defined( 'ABSPATH' ) || exit; ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main">Перейти к содержимому</a>

<header class="site-header" data-header>
	<div class="container site-header__inner">
		<a class="logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="На главную">
			<span class="logo__mark" aria-hidden="true"></span>
			<span class="logo__text">Демо-квартал</span>
		</a>

		<nav class="main-nav" id="main-nav" aria-label="Главное меню">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'main-nav__list',
					'fallback_cb'    => 'zhk_fallback_menu',
					'depth'          => 1,
				)
			);
			?>
		</nav>

		<div class="site-header__actions">
			<a class="fav-link" href="<?php echo esc_url( home_url( '/izbrannoe/' ) ); ?>" aria-label="Избранное">
				<svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7.5-4.6-9.6-9.2C.9 8.4 3 4.5 6.6 4.5c2 0 3.4 1.1 4.2 2.3h.4c.8-1.2 2.2-2.3 4.2-2.3 3.6 0 5.7 3.9 4.2 7.3C19.5 16.4 12 21 12 21z" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
				<span class="fav-link__count" data-fav-count hidden>0</span>
			</a>
			<a class="site-header__phone" href="tel:<?php echo esc_attr( preg_replace( '/[^\d+]/', '', zhk_option( 'phone' ) ) ); ?>"><?php echo esc_html( zhk_option( 'phone' ) ); ?></a>
			<button class="btn btn--small" type="button" data-lead>Заказать звонок</button>
			<button class="burger" type="button" aria-controls="main-nav" aria-expanded="false" data-burger>
				<span class="screen-reader-text">Меню</span><span></span>
			</button>
		</div>
	</div>
</header>

<main id="main">
