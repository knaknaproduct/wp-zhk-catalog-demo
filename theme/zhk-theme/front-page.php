<?php
/**
 * Главная: первый экран, о проекте, подбор по комнатам, галерея, карта, новости.
 */
defined( 'ABSPATH' ) || exit;
get_header();

$catalog_url = home_url( '/kvartiry/' );
$facets      = zhk_catalog_active() ? ZHK_Apartment::facets() : null;
?>

<section class="hero" style="--hero-img:url('<?php echo esc_url( zhk_img( '1624204386084-dd8c05e32226', 2000 ) ); ?>')">
	<div class="container hero__inner">
		<p class="hero__eyebrow">Бизнес-класс · 2 корпуса · сдача IV кв. 2027</p>
		<h1 class="hero__title">Квартиры у парка<br>в 10 минутах от центра</h1>
		<p class="hero__lead">Студии и квартиры от 1 до 4 комнат, с отделкой и без. Закрытый двор без машин, детский сад и школа рядом.</p>
		<div class="hero__actions">
			<a class="btn" href="<?php echo esc_url( $catalog_url ); ?>">Выбрать квартиру</a>
			<button class="btn btn--ghost-light" type="button" data-lead data-lead-title="Записаться на показ">Записаться на показ</button>
		</div>
		<?php if ( $facets && $facets['price'][0] ) : ?>
			<dl class="hero__facts">
				<div><dt>от <?php echo esc_html( zhk_price_short( (int) $facets['price'][0] ) ); ?></dt><dd>стоимость квартиры</dd></div>
				<div><dt><?php echo esc_html( number_format( $facets['area'][0], 0 ) . '–' . number_format( $facets['area'][1], 0 ) ); ?> м²</dt><dd>площади</dd></div>
				<div><dt>от 6%</dt><dd>семейная ипотека</dd></div>
			</dl>
		<?php endif; ?>
	</div>
</section>

<section class="section" id="about">
	<div class="container about">
		<div>
			<h2 class="section__title">О проекте</h2>
			<p class="section__lead">Два 17-этажных корпуса с видом на парк. Первые этажи — кафе и сервисы, во дворе — детские и спортивные площадки, подземный паркинг на 320 мест.</p>
		</div>
		<ul class="features">
			<li><strong>Двор без машин</strong><span>Паркинг под землёй, во дворе только пешеходные зоны.</span></li>
			<li><strong>Потолки 3 м</strong><span>Панорамные окна в гостиных и на кухнях.</span></li>
			<li><strong>Отделка на выбор</strong><span>Без отделки, white box или чистовая — по одной цене за метр.</span></li>
			<li><strong>Колясочные и кладовые</strong><span>На первом этаже каждой секции.</span></li>
		</ul>
	</div>
</section>

<section class="section section--alt">
	<div class="container">
		<div class="section__head">
			<h2 class="section__title">Подберите квартиру</h2>
			<a class="link-arrow" href="<?php echo esc_url( $catalog_url ); ?>">Все квартиры</a>
		</div>
		<div class="rooms-grid">
			<?php
			$room_types = array(
				0 => array( 'Студии', '0.svg' ),
				1 => array( '1-комнатные', '1.svg' ),
				2 => array( '2-комнатные', '2.svg' ),
				3 => array( '3-комнатные', '3.svg' ),
				4 => array( '4-комнатные', '4.svg' ),
			);
			foreach ( $room_types as $rooms => $type ) :
				$url = add_query_arg( 'rooms', $rooms, $catalog_url );
				?>
				<a class="room-card" href="<?php echo esc_url( $url ); ?>">
					<?php if ( zhk_catalog_active() ) : ?>
						<img src="<?php echo esc_url( ZHK_URL . 'assets/plans/' . $type[1] ); ?>" alt="" loading="lazy" width="400" height="300">
					<?php endif; ?>
					<span class="room-card__title"><?php echo esc_html( $type[0] ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="section" id="gallery">
	<div class="container">
		<h2 class="section__title">Галерея</h2>
		<div class="gallery" data-gallery>
			<?php foreach ( zhk_gallery() as $i => $img ) : ?>
				<a class="gallery__item<?php echo 0 === $i ? ' gallery__item--wide' : ''; ?>" href="<?php echo esc_url( zhk_img( $img[0], 1800 ) ); ?>" data-caption="<?php echo esc_attr( $img[1] ); ?>">
					<img src="<?php echo esc_url( zhk_img( $img[0], 800 ) ); ?>" alt="<?php echo esc_attr( $img[1] ); ?>" loading="lazy">
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="section section--alt" id="location">
	<div class="container">
		<h2 class="section__title">Расположение и инфраструктура</h2>
		<p class="section__lead">Всё нужное — в пешей доступности. Нажмите на категорию, чтобы показать её на карте.</p>
		<div class="map-filters" data-map-filters></div>
		<div class="map" id="map" role="region" aria-label="Карта инфраструктуры"></div>
	</div>
</section>

<?php
$news = new WP_Query( array( 'post_type' => 'post', 'posts_per_page' => 3, 'ignore_sticky_posts' => true ) );
if ( $news->have_posts() ) :
	?>
	<section class="section">
		<div class="container">
			<div class="section__head">
				<h2 class="section__title">Новости</h2>
				<a class="link-arrow" href="<?php echo esc_url( get_permalink( get_option( 'page_for_posts' ) ) ); ?>">Все новости</a>
			</div>
			<div class="news-grid">
				<?php
				while ( $news->have_posts() ) :
					$news->the_post();
					get_template_part( 'template-parts/news-card' );
				endwhile;
				wp_reset_postdata();
				?>
			</div>
		</div>
	</section>
<?php endif; ?>

<section class="section cta">
	<div class="container cta__inner">
		<div>
			<h2 class="section__title">Не нашли подходящую квартиру?</h2>
			<p class="section__lead">Расскажите, что ищете, — подберём варианты и рассчитаем ипотеку.</p>
		</div>
		<button class="btn" type="button" data-lead data-lead-title="Подобрать квартиру">Подобрать квартиру</button>
	</div>
</section>

<?php
get_footer();
