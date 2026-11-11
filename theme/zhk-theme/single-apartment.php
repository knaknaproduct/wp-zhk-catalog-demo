<?php
/**
 * Страница квартиры: планировка, параметры, цена, заявка, похожие квартиры.
 */
defined( 'ABSPATH' ) || exit;
get_header();

the_post();
$a       = ZHK_Apartment::to_array( get_post() );
$is_sold = 'sold' === $a['status'];
?>

<section class="container apartment">
	<nav class="breadcrumbs" aria-label="Навигация">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a>
		<span aria-hidden="true">/</span>
		<a href="<?php echo esc_url( home_url( '/kvartiry/' ) ); ?>">Квартиры</a>
		<span aria-hidden="true">/</span>
		<span>№<?php echo esc_html( $a['number'] ); ?></span>
	</nav>

	<div class="apartment__grid">
		<figure class="apartment__plan">
			<img src="<?php echo esc_url( $a['plan'] ); ?>" alt="Планировка: <?php echo esc_attr( $a['title'] ); ?>" width="800" height="600">
		</figure>

		<div class="apartment__info">
			<span class="badge badge--<?php echo esc_attr( $a['status'] ); ?>"><?php echo esc_html( $a['status_label'] ); ?></span>
			<h1 class="apartment__title"><?php echo esc_html( $a['title'] ); ?></h1>

			<dl class="specs">
				<div><dt>Корпус</dt><dd><?php echo esc_html( $a['building'] ); ?></dd></div>
				<div><dt>Секция</dt><dd><?php echo esc_html( $a['section'] ); ?></dd></div>
				<div><dt>Этаж</dt><dd><?php echo esc_html( $a['floor'] ); ?></dd></div>
				<div><dt>Номер</dt><dd><?php echo esc_html( $a['number'] ); ?></dd></div>
				<div><dt>Площадь</dt><dd><?php echo esc_html( number_format( $a['area'], 1, ',', ' ' ) ); ?> м²</dd></div>
				<div><dt>Отделка</dt><dd><?php echo esc_html( $a['finish_label'] ); ?></dd></div>
			</dl>

			<?php if ( ! $is_sold ) : ?>
				<p class="apartment__price"><?php echo esc_html( zhk_price( $a['price'] ) ); ?></p>
				<p class="apartment__m2"><?php echo esc_html( zhk_price( $a['price_m2'] ) ); ?> за м²</p>
			<?php endif; ?>

			<div class="apartment__actions">
				<?php if ( ! $is_sold ) : ?>
					<button class="btn" type="button" data-lead data-lead-apartment="<?php echo esc_attr( $a['id'] ); ?>" data-lead-title="Заявка на квартиру №<?php echo esc_attr( $a['number'] ); ?>">Оставить заявку</button>
				<?php endif; ?>
				<button class="btn btn--ghost fav-btn" type="button" data-fav="<?php echo esc_attr( $a['id'] ); ?>" aria-pressed="false">
					<span data-fav-label>В избранное</span>
				</button>
			</div>

			<?php if ( get_the_content() ) : ?>
				<div class="apartment__text"><?php the_content(); ?></div>
			<?php endif; ?>
		</div>
	</div>
</section>

<?php
$similar = new WP_Query(
	ZHK_Apartment::query_args(
		array(
			'rooms'     => array( $a['rooms'] ),
			'only_free' => true,
			'per_page'  => 3,
			'exclude'   => array( $a['id'] ),
		)
	) + array( 'fields' => 'ids', 'no_found_rows' => true )
);
if ( $similar->posts ) :
	// Карточки рисует catalog.js — та же разметка, что в каталоге и избранном.
	?>
	<section class="section section--alt">
		<div class="container" data-catalog="ids" data-ids="<?php echo esc_attr( implode( ',', $similar->posts ) ); ?>">
			<h2 class="section__title">Похожие квартиры</h2>
			<div class="cards" data-results></div>
		</div>
	</section>
	<?php
endif;

get_footer();
