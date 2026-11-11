<?php
/**
 * Каталог квартир. Фильтры — обычная форма; catalog.js отправляет её в REST API
 * без перезагрузки и хранит выбранные параметры в адресе страницы.
 */
defined( 'ABSPATH' ) || exit;
get_header();

$facets = ZHK_Apartment::facets();
?>

<section class="page-head">
	<div class="container">
		<h1 class="page-head__title">Квартиры</h1>
		<p class="page-head__lead">Актуальные цены и наличие. Добавляйте квартиры в избранное, чтобы сравнить.</p>
	</div>
</section>

<section class="container catalog" data-catalog="list">
	<form class="filters" data-filters aria-label="Фильтр квартир">
		<fieldset class="filters__group">
			<legend class="filters__label">Комнаты</legend>
			<div class="chips">
				<?php foreach ( array( 0 => 'Ст', 1 => '1', 2 => '2', 3 => '3', 4 => '4+' ) as $value => $label ) : ?>
					<label class="chip">
						<input type="checkbox" name="rooms" value="<?php echo esc_attr( $value ); ?>">
						<span><?php echo esc_html( $label ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</fieldset>

		<?php
		$ranges = array(
			'price' => array( 'Цена, млн ₽', 1000000, 0.1 ),
			'area'  => array( 'Площадь, м²', 1, 1 ),
			'floor' => array( 'Этаж', 1, 1 ),
		);
		foreach ( $ranges as $key => $range ) :
			list( $label, $divider, $step ) = $range;
			$lo = $facets[ $key ][0] / $divider;
			$hi = $facets[ $key ][1] / $divider;
			?>
			<fieldset class="filters__group">
				<legend class="filters__label"><?php echo esc_html( $label ); ?></legend>
				<div class="range" data-divider="<?php echo esc_attr( $divider ); ?>">
					<input type="number" name="<?php echo esc_attr( $key ); ?>_min" step="<?php echo esc_attr( $step ); ?>" min="0" placeholder="от <?php echo esc_attr( floor( $lo * 10 ) / 10 ); ?>" aria-label="<?php echo esc_attr( $label ); ?> от">
					<input type="number" name="<?php echo esc_attr( $key ); ?>_max" step="<?php echo esc_attr( $step ); ?>" min="0" placeholder="до <?php echo esc_attr( ceil( $hi * 10 ) / 10 ); ?>" aria-label="<?php echo esc_attr( $label ); ?> до">
				</div>
			</fieldset>
		<?php endforeach; ?>

		<fieldset class="filters__group">
			<legend class="filters__label">Корпус и отделка</legend>
			<div class="range">
				<select name="building" aria-label="Корпус">
					<option value="">Все корпуса</option>
					<?php foreach ( $facets['buildings'] as $b ) : ?>
						<option value="<?php echo esc_attr( $b ); ?>">Корпус <?php echo esc_html( $b ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="finish" aria-label="Отделка">
					<option value="">Любая отделка</option>
					<?php foreach ( ZHK_Apartment::FINISHES as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</fieldset>

		<div class="filters__footer">
			<label class="checkbox"><input type="checkbox" name="only_free" value="1"> <span>Только свободные</span></label>
			<button class="link-btn" type="reset">Сбросить</button>
		</div>
	</form>

	<div class="catalog__bar">
		<p class="catalog__count" data-count aria-live="polite">Загружаем квартиры…</p>
		<label class="catalog__sort">
			<span class="screen-reader-text">Сортировка</span>
			<select name="sort" data-sort>
				<option value="price_asc">Сначала дешевле</option>
				<option value="price_desc">Сначала дороже</option>
				<option value="area_asc">По площади ↑</option>
				<option value="area_desc">По площади ↓</option>
				<option value="floor_asc">По этажу</option>
			</select>
		</label>
	</div>

	<div class="cards" data-results></div>
	<div class="catalog__more">
		<button class="btn btn--ghost" type="button" data-more hidden>Показать ещё</button>
	</div>
</section>

<?php
get_footer();
