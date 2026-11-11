/**
 * Карточки квартир из REST API (/wp-json/zhk/v1/apartments).
 *
 * Режимы контейнера [data-catalog]:
 *   list      — каталог с фильтрами; параметры хранятся в адресе страницы;
 *   favorites — квартиры из избранного;
 *   ids       — заданный список (data-ids), например «Похожие квартиры».
 */
( function () {
	'use strict';

	const root = document.querySelector( '[data-catalog]' );
	if ( ! root ) return;

	const mode = root.dataset.catalog;
	const results = root.querySelector( '[data-results]' );
	const countEl = root.querySelector( '[data-count]' );
	const PER_PAGE = 12;

	const rub = ( n ) => new Intl.NumberFormat( 'ru-RU' ).format( n ) + ' ₽';
	const esc = ( s ) =>
		String( s ).replace( /[&<>"']/g, ( c ) => ( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' } )[ c ] );

	function plural( n, one, few, many ) {
		const m10 = n % 10;
		const m100 = n % 100;
		if ( m10 === 1 && m100 !== 11 ) return one;
		if ( m10 >= 2 && m10 <= 4 && ( m100 < 10 || m100 >= 20 ) ) return few;
		return many;
	}

	function card( a ) {
		const sold = a.status === 'sold';
		return `
			<article class="card${ sold ? ' card--sold' : '' }">
				<a class="card__plan" href="${ esc( a.url ) }" tabindex="-1" aria-hidden="true">
					<img src="${ esc( a.plan ) }" alt="" loading="lazy" width="400" height="300">
				</a>
				<span class="badge badge--${ esc( a.status ) }">${ esc( a.status_label ) }</span>
				<button class="card__fav" type="button" data-fav="${ a.id }" aria-pressed="false" aria-label="Добавить в избранное">
					<svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7.5-4.6-9.6-9.2C.9 8.4 3 4.5 6.6 4.5c2 0 3.4 1.1 4.2 2.3h.4c.8-1.2 2.2-2.3 4.2-2.3 3.6 0 5.7 3.9 4.2 7.3C19.5 16.4 12 21 12 21z"/></svg>
				</button>
				<div class="card__body">
					<h3 class="card__title"><a href="${ esc( a.url ) }">${ esc( a.title ) }</a></h3>
					<p class="card__meta">Корпус ${ esc( a.building ) } · этаж ${ a.floor } · ${ esc( a.finish_label ) }</p>
					${
						sold
							? '<p class="card__price">Продана</p>'
							: `<p class="card__price">${ rub( a.price ) }</p><p class="card__m2">${ rub( a.price_m2 ) } за м²</p>`
					}
				</div>
			</article>`;
	}

	async function fetchApartments( params ) {
		const res = await fetch( window.ZHK.api + 'apartments?' + params.toString() );
		if ( ! res.ok ) throw new Error( 'HTTP ' + res.status );
		return res.json();
	}

	function render( items, append ) {
		const html = items.map( card ).join( '' );
		if ( append ) results.insertAdjacentHTML( 'beforeend', html );
		else results.innerHTML = html;
		window.ZHKFav.sync();
	}

	function showError() {
		if ( countEl ) countEl.textContent = 'Не удалось загрузить квартиры. Обновите страницу.';
	}

	/* ---------- Заданный список и избранное ---------- */

	async function loadIds( ids ) {
		const empty = root.querySelector( '[data-empty]' );
		if ( ! ids.length ) {
			results.innerHTML = '';
			if ( countEl ) countEl.textContent = '';
			if ( empty ) empty.hidden = false;
			return;
		}
		const params = new URLSearchParams();
		ids.forEach( ( id ) => params.append( 'ids[]', id ) );
		try {
			const data = await fetchApartments( params );
			render( data.items );
			if ( countEl ) countEl.textContent = `${ data.items.length } ${ plural( data.items.length, 'квартира', 'квартиры', 'квартир' ) } в избранном`;
			if ( empty ) empty.hidden = data.items.length > 0;
		} catch ( e ) {
			showError();
		}
	}

	if ( mode === 'ids' ) {
		loadIds( root.dataset.ids.split( ',' ).map( Number ).filter( Boolean ) );
		return;
	}

	if ( mode === 'favorites' ) {
		loadIds( window.ZHKFav.ids() );
		// Убрали из избранного — карточка исчезает сразу.
		document.addEventListener( 'zhk:favorites', ( e ) => loadIds( e.detail ) );
		return;
	}

	/* ---------- Каталог с фильтрами ---------- */

	const form = root.querySelector( '[data-filters]' );
	const sort = root.querySelector( '[data-sort]' );
	const more = root.querySelector( '[data-more]' );
	let page = 1;
	let request = 0; // Номер последнего запроса: ответы на устаревшие игнорируются.

	/** Значения формы → параметры адреса страницы (как ввёл пользователь, цена в млн). */
	function formToUrlParams() {
		const params = new URLSearchParams();
		new FormData( form ).forEach( ( value, key ) => {
			if ( value !== '' ) params.append( key, value );
		} );
		if ( sort.value !== 'price_asc' ) params.set( 'sort', sort.value );
		return params;
	}

	/** Параметры адреса → форма (при открытии ссылки с фильтрами). */
	function urlToForm() {
		const params = new URLSearchParams( location.search );
		const rooms = params.getAll( 'rooms' );
		form.querySelectorAll( '[name="rooms"]' ).forEach( ( cb ) => ( cb.checked = rooms.includes( cb.value ) ) );
		[ 'price_min', 'price_max', 'area_min', 'area_max', 'floor_min', 'floor_max', 'building', 'finish' ].forEach( ( name ) => {
			if ( params.has( name ) ) form.elements[ name ].value = params.get( name );
		} );
		form.elements.only_free.checked = params.get( 'only_free' ) === '1';
		if ( params.has( 'sort' ) ) sort.value = params.get( 'sort' );
	}

	/** Параметры адреса → запрос к API: rooms[] и цена в рублях. */
	function apiParams( urlParams ) {
		const api = new URLSearchParams();
		urlParams.forEach( ( value, key ) => {
			if ( key === 'rooms' ) api.append( 'rooms[]', value );
			else if ( key === 'price_min' || key === 'price_max' ) api.set( key, Math.round( parseFloat( value.replace( ',', '.' ) ) * 1e6 ) );
			else api.set( key, value );
		} );
		api.set( 'page', page );
		api.set( 'per_page', PER_PAGE );
		return api;
	}

	async function load( append ) {
		const id = ++request;
		const urlParams = formToUrlParams();
		const query = urlParams.toString();
		history.replaceState( null, '', location.pathname + ( query ? '?' + query : '' ) );

		root.classList.add( 'is-loading' );
		try {
			const data = await fetchApartments( apiParams( urlParams ) );
			if ( id !== request ) return;
			render( data.items, append );
			countEl.textContent = data.total
				? `Найдено ${ data.total } ${ plural( data.total, 'квартира', 'квартиры', 'квартир' ) }`
				: 'По этим параметрам квартир нет. Измените фильтр или оставьте заявку — подберём вручную.';
			more.hidden = page >= data.pages;
		} catch ( e ) {
			if ( id === request ) showError();
		} finally {
			if ( id === request ) root.classList.remove( 'is-loading' );
		}
	}

	let timer;
	const reload = () => {
		clearTimeout( timer );
		timer = setTimeout( () => {
			page = 1;
			load( false );
		}, 300 );
	};

	form.addEventListener( 'input', reload );
	form.addEventListener( 'change', reload );
	form.addEventListener( 'submit', ( e ) => e.preventDefault() );
	form.addEventListener( 'reset', () => setTimeout( reload ) );
	sort.addEventListener( 'change', reload );
	more.addEventListener( 'click', () => {
		page++;
		load( true );
	} );

	urlToForm();
	load( false );
} )();
