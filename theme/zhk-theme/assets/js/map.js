/**
 * Карта инфраструктуры (Leaflet + OpenStreetMap). Данные — ZHK_MAP из functions.php.
 * На боевом сайте можно заменить подложку на Яндекс Карты (нужен API-ключ).
 */
( function () {
	'use strict';

	const el = document.getElementById( 'map' );
	if ( ! el || ! window.L || ! window.ZHK_MAP ) return;

	const data = window.ZHK_MAP;
	const map = L.map( el, { scrollWheelZoom: false } ).setView( data.center, 15 );
	L.tileLayer( 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
		maxZoom: 19,
		attribution: '&copy; участники OpenStreetMap',
	} ).addTo( map );

	const icon = ( type ) =>
		L.divIcon( { className: 'map-pin map-pin--' + type, iconSize: [ 28, 28 ], iconAnchor: [ 14, 14 ] } );

	L.marker( data.home.coords, {
		icon: L.divIcon( { className: 'map-pin map-pin--home', iconSize: [ 44, 44 ], iconAnchor: [ 22, 22 ] } ),
		zIndexOffset: 1000,
	} )
		.addTo( map )
		.bindPopup( '<strong>' + data.home.title + '</strong>' );

	const layers = {};
	data.places.forEach( ( [ type, title, lat, lng ] ) => {
		layers[ type ] = layers[ type ] || L.layerGroup().addTo( map );
		L.marker( [ lat, lng ], { icon: icon( type ), title } ).bindPopup( title ).addTo( layers[ type ] );
	} );

	// Кнопки категорий: показать/скрыть слой.
	const filters = document.querySelector( '[data-map-filters]' );
	Object.entries( data.types ).forEach( ( [ type, label ] ) => {
		if ( ! layers[ type ] ) return;
		const btn = document.createElement( 'button' );
		btn.type = 'button';
		btn.className = 'map-chip map-chip--' + type + ' is-active';
		btn.textContent = label;
		btn.setAttribute( 'aria-pressed', 'true' );
		btn.addEventListener( 'click', () => {
			const on = ! map.hasLayer( layers[ type ] );
			on ? layers[ type ].addTo( map ) : map.removeLayer( layers[ type ] );
			btn.classList.toggle( 'is-active', on );
			btn.setAttribute( 'aria-pressed', String( on ) );
		} );
		filters.appendChild( btn );
	} );
} )();
