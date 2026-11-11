/**
 * Общий JS сайта: избранное, окно заявки, меню, галерея.
 * Настройки приходят из PHP в window.ZHK (api, favoritesUrl).
 */
( function () {
	'use strict';

	/* ---------- Избранное (localStorage) ---------- */

	const FAV_KEY = 'zhk_favorites';

	const Fav = {
		ids() {
			try {
				const ids = JSON.parse( localStorage.getItem( FAV_KEY ) || '[]' );
				return Array.isArray( ids ) ? ids.filter( Number.isInteger ) : [];
			} catch ( e ) {
				return [];
			}
		},
		toggle( id ) {
			const ids = Fav.ids();
			const next = ids.includes( id ) ? ids.filter( ( x ) => x !== id ) : [ ...ids, id ];
			try {
				localStorage.setItem( FAV_KEY, JSON.stringify( next ) );
			} catch ( e ) {
				// Приватный режим или переполненное хранилище: избранное не сохранится.
			}
			Fav.sync();
			document.dispatchEvent( new CustomEvent( 'zhk:favorites', { detail: next } ) );
			return next.includes( id );
		},
		/** Обновляет счётчик в шапке и все кнопки-сердечки на странице. */
		sync() {
			const ids = Fav.ids();
			document.querySelectorAll( '[data-fav-count]' ).forEach( ( el ) => {
				el.textContent = ids.length;
				el.hidden = ids.length === 0;
			} );
			document.querySelectorAll( '[data-fav]' ).forEach( ( btn ) => {
				const on = ids.includes( Number( btn.dataset.fav ) );
				btn.classList.toggle( 'is-active', on );
				btn.setAttribute( 'aria-pressed', String( on ) );
				const label = btn.querySelector( '[data-fav-label]' );
				if ( label ) label.textContent = on ? 'В избранном' : 'В избранное';
			} );
		},
	};
	window.ZHKFav = Fav;

	/* ---------- Аналитика: цели Метрики и события GTM ---------- */

	function track( goal ) {
		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push( { event: goal } );
		if ( window.ym && window.ZHK_METRIKA ) window.ym( window.ZHK_METRIKA, 'reachGoal', goal );
	}

	/* ---------- Окно заявки ---------- */

	const modal = document.getElementById( 'lead-modal' );
	const form = modal && modal.querySelector( '[data-lead-form]' );

	function openLead( trigger ) {
		if ( ! modal ) return;
		form.hidden = false;
		modal.querySelector( '[data-lead-success]' ).hidden = true;
		form.querySelector( '[data-form-error]' ).hidden = true;
		form.elements.apartment_id.value = trigger.dataset.leadApartment || '0';
		modal.querySelector( '[data-lead-title]' ).textContent = trigger.dataset.leadTitle || 'Оставьте заявку';
		modal.showModal();
		form.elements.name.focus();
	}

	document.addEventListener( 'click', ( e ) => {
		const fav = e.target.closest( '[data-fav]' );
		if ( fav ) {
			e.preventDefault();
			track( Fav.toggle( Number( fav.dataset.fav ) ) ? 'favorite_add' : 'favorite_remove' );
			return;
		}
		const lead = e.target.closest( '[data-lead]' );
		if ( lead ) {
			e.preventDefault();
			openLead( lead );
			return;
		}
		if ( e.target.closest( '[data-modal-close]' ) ) {
			e.target.closest( 'dialog' ).close();
			return;
		}
		// Клик по подложке закрывает окно.
		if ( e.target.tagName === 'DIALOG' ) e.target.close();
	} );

	// Маска телефона: +7 (999) 123-45-67.
	document.querySelectorAll( '[data-phone]' ).forEach( ( input ) => {
		input.addEventListener( 'input', () => {
			let d = input.value.replace( /\D/g, '' );
			if ( d.startsWith( '8' ) ) d = '7' + d.slice( 1 );
			if ( d && ! d.startsWith( '7' ) ) d = '7' + d;
			d = d.slice( 0, 11 );
			const p = [ d.slice( 1, 4 ), d.slice( 4, 7 ), d.slice( 7, 9 ), d.slice( 9, 11 ) ];
			let out = d ? '+7' : '';
			if ( p[ 0 ] ) out += ' (' + p[ 0 ];
			if ( p[ 1 ] ) out += ') ' + p[ 1 ];
			if ( p[ 2 ] ) out += '-' + p[ 2 ];
			if ( p[ 3 ] ) out += '-' + p[ 3 ];
			input.value = out;
		} );
	} );

	if ( form ) {
		form.addEventListener( 'submit', async ( e ) => {
			e.preventDefault();
			const error = form.querySelector( '[data-form-error]' );
			const button = form.querySelector( '[type="submit"]' );
			const f = form.elements;

			const problem =
				( ! f.name.value.trim() && 'Укажите имя.' ) ||
				( f.phone.value.replace( /\D/g, '' ).length !== 11 && 'Укажите телефон полностью.' ) ||
				( ! f.consent.checked && 'Нужно согласие на обработку данных.' );
			if ( problem ) {
				error.textContent = problem;
				error.hidden = false;
				return;
			}

			error.hidden = true;
			button.disabled = true;
			try {
				const res = await fetch( window.ZHK.api + 'leads', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify( {
						name: f.name.value.trim(),
						phone: f.phone.value,
						comment: f.comment.value.trim(),
						apartment_id: Number( f.apartment_id.value ),
						consent: f.consent.checked,
						website: f.website.value,
						source: location.href,
					} ),
				} );
				if ( ! res.ok ) {
					const body = await res.json().catch( () => ( {} ) );
					throw new Error( body.message || 'Не удалось отправить заявку.' );
				}
				track( 'lead_submit' );
				form.reset();
				form.hidden = true;
				modal.querySelector( '[data-lead-success]' ).hidden = false;
			} catch ( err ) {
				error.textContent = err.message + ' Попробуйте ещё раз или позвоните нам.';
				error.hidden = false;
			} finally {
				button.disabled = false;
			}
		} );
	}

	/* ---------- Мобильное меню ---------- */

	const burger = document.querySelector( '[data-burger]' );
	if ( burger ) {
		const setOpen = ( open ) => {
			burger.setAttribute( 'aria-expanded', String( open ) );
			document.body.classList.toggle( 'menu-open', open );
		};
		burger.addEventListener( 'click', () => setOpen( burger.getAttribute( 'aria-expanded' ) !== 'true' ) );
		document.getElementById( 'main-nav' ).addEventListener( 'click', ( e ) => {
			if ( e.target.closest( 'a' ) ) setOpen( false );
		} );
	}

	/* ---------- Галерея: просмотр в полный размер ---------- */

	const gallery = document.querySelector( '[data-gallery]' );
	if ( gallery ) {
		const items = [ ...gallery.querySelectorAll( 'a' ) ];
		const box = document.createElement( 'dialog' );
		box.className = 'lightbox';
		box.setAttribute( 'aria-label', 'Просмотр фото' );
		box.innerHTML =
			'<button class="modal__close" type="button" data-modal-close aria-label="Закрыть">×</button>' +
			'<button class="lightbox__nav lightbox__nav--prev" type="button" aria-label="Предыдущее фото">‹</button>' +
			'<figure><img alt=""><figcaption class="lightbox__caption"></figcaption></figure>' +
			'<button class="lightbox__nav lightbox__nav--next" type="button" aria-label="Следующее фото">›</button>';
		document.body.appendChild( box );

		let current = 0;
		const show = ( i ) => {
			current = ( i + items.length ) % items.length;
			const a = items[ current ];
			box.querySelector( 'img' ).src = a.href;
			box.querySelector( 'img' ).alt = a.dataset.caption;
			box.querySelector( '.lightbox__caption' ).textContent = a.dataset.caption;
		};
		items.forEach( ( a, i ) =>
			a.addEventListener( 'click', ( e ) => {
				e.preventDefault();
				show( i );
				box.showModal();
			} )
		);
		box.querySelector( '.lightbox__nav--prev' ).addEventListener( 'click', () => show( current - 1 ) );
		box.querySelector( '.lightbox__nav--next' ).addEventListener( 'click', () => show( current + 1 ) );
		box.addEventListener( 'keydown', ( e ) => {
			if ( e.key === 'ArrowLeft' ) show( current - 1 );
			if ( e.key === 'ArrowRight' ) show( current + 1 );
		} );
	}

	/* ---------- Тень у шапки при прокрутке ---------- */

	const header = document.querySelector( '[data-header]' );
	const onScroll = () => header && header.classList.toggle( 'is-scrolled', window.scrollY > 10 );
	window.addEventListener( 'scroll', onScroll, { passive: true } );
	onScroll();

	Fav.sync();
} )();
