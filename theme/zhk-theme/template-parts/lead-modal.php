<?php
/**
 * Окно заявки. Открывается любой кнопкой с атрибутом data-lead;
 * data-lead-apartment="ID" привязывает заявку к квартире.
 */
defined( 'ABSPATH' ) || exit;
?>
<dialog class="modal" id="lead-modal" aria-labelledby="lead-title">
	<button class="modal__close" type="button" data-modal-close aria-label="Закрыть">×</button>
	<form class="lead-form" data-lead-form novalidate>
		<h2 class="modal__title" id="lead-title" data-lead-title>Оставьте заявку</h2>
		<p class="modal__text" data-lead-subtitle>Менеджер перезвонит в течение 15 минут в рабочее время.</p>

		<input type="hidden" name="apartment_id" value="0">
		<div class="hp" aria-hidden="true">
			<label>Сайт <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
		</div>

		<label class="field">
			<span class="field__label">Имя</span>
			<input class="field__input" type="text" name="name" autocomplete="name" required maxlength="100">
		</label>
		<label class="field">
			<span class="field__label">Телефон</span>
			<input class="field__input" type="tel" name="phone" autocomplete="tel" required placeholder="+7 (___) ___-__-__" data-phone>
		</label>
		<label class="field">
			<span class="field__label">Комментарий <span class="muted">— необязательно</span></span>
			<textarea class="field__input" name="comment" rows="2" maxlength="1000"></textarea>
		</label>
		<label class="checkbox">
			<input type="checkbox" name="consent" required>
			<span>Согласен на <a href="<?php echo esc_url( get_privacy_policy_url() ); ?>" target="_blank" rel="noopener">обработку персональных данных</a></span>
		</label>

		<p class="form-error" data-form-error role="alert" hidden></p>
		<button class="btn btn--full" type="submit">Отправить заявку</button>
	</form>
	<div class="lead-success" data-lead-success hidden>
		<div class="lead-success__icon" aria-hidden="true">✓</div>
		<h2 class="modal__title">Заявка отправлена</h2>
		<p class="modal__text">Спасибо! Мы скоро свяжемся с вами.</p>
		<button class="btn btn--ghost" type="button" data-modal-close>Хорошо</button>
	</div>
</dialog>
