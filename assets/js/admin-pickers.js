/**
 * Sélecteurs enrichis du back-office : liste des codes promo WooCommerce
 * (remise, statut, expiration) et recherche de produit (miniature, référence,
 * prix, stock). Initialisés au chargement puis sur l'événement
 * `wc-enhanced-select-init`, que admin.js déclenche après l'ajout ou
 * l'import d'une case.
 */
( function ( $ ) {
	'use strict';

	var data = window.saitoCeAdmin || {};
	var i18n = data.i18n || {};
	var coupons = data.coupons && ! Array.isArray( data.coupons ) ? data.coupons : null;
	var couponCodes = coupons ? Object.keys( coupons ).sort() : [];
	var pickerCount = 0;
	var MAX_COUPON_ROWS = 50;

	function todayIso() {
		var now = new Date();
		return now.getFullYear() + '-' + String( now.getMonth() + 1 ).padStart( 2, '0' ) + '-' + String( now.getDate() ).padStart( 2, '0' );
	}

	function escapeHtml( str ) {
		var div = document.createElement( 'div' );
		div.textContent = str == null ? '' : String( str );
		return div.innerHTML;
	}

	/* ------------------------------------------------------------------ *
	 * Codes promo
	 * ------------------------------------------------------------------ */

	function couponStatus( coupon ) {
		if ( coupon.usedUp ) {
			return { key: 'used-up', label: i18n.couponStatusUsedUp };
		}
		if ( coupon.expires && coupon.expires < todayIso() ) {
			return { key: 'expired', label: i18n.couponStatusExpired };
		}
		return { key: 'valid', label: i18n.couponValid };
	}

	function renderCouponRows( $picker, query ) {
		var term = String( query || '' ).trim().toUpperCase();
		var matches = couponCodes.filter( function ( code ) {
			return ! term || code.indexOf( term ) !== -1;
		} );
		var listId = $picker.attr( 'id' );
		var html = '';

		matches.slice( 0, MAX_COUPON_ROWS ).forEach( function ( code, index ) {
			var coupon = coupons[ code ];
			var status = couponStatus( coupon );
			var expiry = coupon.expiresLabel ? i18n.couponExpiresOn.replace( '%s', coupon.expiresLabel ) : i18n.couponNoExpiry;
			html += '<li class="saito-ce-coupon-option" role="option" id="' + listId + '-' + index + '" data-code="' + escapeHtml( code ) + '" aria-selected="false">';
			html += '<span class="saito-ce-coupon-option-main">';
			html += '<span class="saito-ce-coupon-option-code">' + escapeHtml( code ) + '</span>';
			if ( coupon.value ) {
				html += '<span class="saito-ce-coupon-option-value">' + escapeHtml( coupon.value ) + '</span>';
			}
			html += '<span class="saito-ce-pill is-' + status.key + '">' + escapeHtml( status.label ) + '</span>';
			html += '</span>';
			html += '<span class="saito-ce-coupon-option-meta">' + escapeHtml( expiry ) + ( coupon.description ? ' · ' + escapeHtml( coupon.description ) : '' ) + '</span>';
			html += '</li>';
		} );

		if ( ! matches.length ) {
			html = '<li class="saito-ce-coupon-empty" role="presentation">' + escapeHtml( i18n.couponNoMatch ) + '</li>';
		}

		$picker.find( '.saito-ce-coupon-list' ).html( html );
		$picker.find( '.saito-ce-coupon-count' ).text( i18n.couponCount.replace( '%d', matches.length ) );
		$picker.data( 'active', -1 );
	}

	function getPicker( $input ) {
		return $input.closest( '.saito-ce-coupon-wrap' ).find( '.saito-ce-coupon-picker' );
	}

	function openCouponPicker( $input ) {
		if ( ! coupons || $input.prop( 'readonly' ) ) {
			return;
		}
		var $picker = getPicker( $input );
		renderCouponRows( $picker, $input.val() );
		$picker.prop( 'hidden', false );
		$input.attr( 'aria-expanded', 'true' );
	}

	function closeCouponPicker( $input ) {
		getPicker( $input ).prop( 'hidden', true );
		$input.attr( 'aria-expanded', 'false' ).removeAttr( 'aria-activedescendant' );
	}

	function setActive( $input, index ) {
		var $picker = getPicker( $input );
		var $options = $picker.find( '.saito-ce-coupon-option' );
		if ( ! $options.length ) {
			return;
		}
		index = ( index + $options.length ) % $options.length;
		$options.removeClass( 'is-active' ).attr( 'aria-selected', 'false' );
		var $active = $options.eq( index ).addClass( 'is-active' ).attr( 'aria-selected', 'true' );
		$picker.data( 'active', index );
		$input.attr( 'aria-activedescendant', $active.attr( 'id' ) );
		$active[ 0 ].scrollIntoView( { block: 'nearest' } );
	}

	function chooseCoupon( $input, code ) {
		$input.val( code ).trigger( 'input' ).trigger( 'change' );
		closeCouponPicker( $input );
	}

	function initCouponPickers() {
		if ( ! coupons ) {
			return;
		}
		$( '.saito-ce-field-coupon' ).each( function () {
			var $input = $( this );
			if ( $input.closest( '.saito-ce-coupon-wrap' ).length ) {
				return;
			}
			pickerCount++;
			var listId = 'saito-ce-coupon-picker-' + pickerCount;
			$input.wrap( '<div class="saito-ce-coupon-wrap"></div>' );
			$input.after(
				'<div class="saito-ce-coupon-picker" id="' + listId + '" hidden>' +
					'<div class="saito-ce-coupon-count"></div>' +
					'<ul class="saito-ce-coupon-list" role="listbox"></ul>' +
				'</div>'
			);
			$input.on( 'keydown', onCouponKeydown );
			$input.attr( {
				role: 'combobox',
				'aria-autocomplete': 'list',
				'aria-expanded': 'false',
				'aria-controls': listId,
			} );
		} );
	}

	$( document ).on( 'focus click', '.saito-ce-coupon-wrap .saito-ce-field-coupon', function () {
		openCouponPicker( $( this ) );
	} );

	$( document ).on( 'input', '.saito-ce-coupon-wrap .saito-ce-field-coupon', function () {
		var $input = $( this );
		if ( getPicker( $input ).prop( 'hidden' ) ) {
			openCouponPicker( $input );
		} else {
			renderCouponRows( getPicker( $input ), $input.val() );
		}
	} );

	// Branché directement sur le champ (cf. initCouponPickers()) et non délégué
	// au document : admin.js ferme la modale sur Échap au niveau du document,
	// il faut donc intercepter l'événement avant qu'il n'y remonte.
	function onCouponKeydown( event ) {
		var $input = $( this );
		var $picker = getPicker( $input );
		var isOpen = ! $picker.prop( 'hidden' );

		if ( 'ArrowDown' === event.key || 'ArrowUp' === event.key ) {
			event.preventDefault();
			if ( ! isOpen ) {
				openCouponPicker( $input );
			}
			setActive( $input, $picker.data( 'active' ) + ( 'ArrowDown' === event.key ? 1 : -1 ) );
		} else if ( 'Enter' === event.key && isOpen ) {
			// Empêche l'envoi du formulaire du calendrier quand on valide un code.
			event.preventDefault();
			var $active = $picker.find( '.saito-ce-coupon-option.is-active' );
			if ( $active.length ) {
				chooseCoupon( $input, $active.data( 'code' ) );
			} else {
				closeCouponPicker( $input );
			}
		} else if ( 'Escape' === event.key && isOpen ) {
			// Ferme la liste sans fermer la modale de la case (cf. admin.js).
			event.preventDefault();
			event.stopPropagation();
			closeCouponPicker( $input );
		} else if ( 'Tab' === event.key ) {
			closeCouponPicker( $input );
		}
	}

	// mousedown plutôt que click : se déclenche avant le blur du champ, qui
	// fermerait la liste avant que le choix ne soit pris en compte.
	$( document ).on( 'mousedown', '.saito-ce-coupon-option', function ( event ) {
		event.preventDefault();
		var $input = $( this ).closest( '.saito-ce-coupon-wrap' ).find( '.saito-ce-field-coupon' );
		chooseCoupon( $input, $( this ).data( 'code' ) );
	} );

	$( document ).on( 'blur', '.saito-ce-coupon-wrap .saito-ce-field-coupon', function () {
		closeCouponPicker( $( this ) );
	} );

	/* ------------------------------------------------------------------ *
	 * Produits
	 * ------------------------------------------------------------------ */

	function productTemplate( item ) {
		if ( ! item.id ) {
			return item.text;
		}
		var image = item.image || $( item.element ).data( 'image' ) || '';
		var $row = $( '<span class="saito-ce-product-option"></span>' );
		if ( image ) {
			$row.append( $( '<img class="saito-ce-product-option-thumb" alt="">' ).attr( 'src', image ) );
		}
		var $text = $( '<span class="saito-ce-product-option-text"></span>' );
		$text.append( $( '<span class="saito-ce-product-option-name"></span>' ).text( item.text ) );

		var meta = [];
		if ( item.sku ) {
			meta.push( item.sku );
		}
		if ( item.price ) {
			meta.push( item.price );
		}
		var $meta = $( '<span class="saito-ce-product-option-meta"></span>' ).text( meta.join( ' · ' ) );
		if ( 'inStock' in item ) {
			$meta.append(
				$( '<span class="saito-ce-pill"></span>' )
					.addClass( item.inStock ? 'is-valid' : 'is-expired' )
					.text( item.inStock ? i18n.productInStock : i18n.productOutOfStock )
			);
		}
		$text.append( $meta );
		$row.append( $text );
		return $row;
	}

	function initProductPickers() {
		if ( ! $.fn.selectWoo ) {
			return;
		}
		$( 'select.saito-ce-product-search' ).not( '.saito-ce-enhanced' ).each( function () {
			var $select = $( this );
			$select.addClass( 'saito-ce-enhanced' ).selectWoo( {
				width: '100%',
				allowClear: true,
				placeholder: $select.data( 'placeholder' ),
				minimumInputLength: 2,
				// Pas de dropdownParent : ouverte dans la modale (conteneur qui
				// défile), select2 calculait mal sa position et la liste
				// s'affichait tout en bas de la modale. Rattachée à <body> (mode
				// standard, repositionnée au défilement), elle passe au-dessus de
				// la modale grâce au z-index défini dans admin.css.
				dropdownCssClass: 'saito-ce-product-dropdown',
				ajax: {
					url: data.ajaxUrl,
					dataType: 'json',
					delay: 250,
					cache: true,
					data: function ( params ) {
						return { action: 'saito_ce_search_products', security: data.searchNonce, term: params.term };
					},
					processResults: function ( response ) {
						return { results: response && response.success ? response.data : [] };
					},
				},
				templateResult: function ( item ) {
					return productTemplate( item );
				},
				// Texte seul : selectWoo (livré avec WooCommerce) affiche la
				// sélection avec .text(), un élément HTML y deviendrait
				// « [object Object] ».
				templateSelection: function ( item ) {
					return $.trim( item.text || '' );
				},
				language: {
					inputTooShort: function () {
						return i18n.productTooShort;
					},
					searching: function () {
						return i18n.productSearching;
					},
					noResults: function () {
						return i18n.productNoResults;
					},
				},
			} );
		} );
	}

	/**
	 * Échap dans la liste ouverte de la recherche produit doit seulement
	 * refermer la liste, pas la modale de la case (fermée par admin.js au
	 * niveau du document). La liste étant rattachée à <body>, la phase de
	 * capture sur le document note si la touche vient d'une liste ouverte
	 * (selectWoo retire la classe en se refermant, avant la remontée), puis
	 * <body> arrête l'événement avant qu'il n'atteigne le document.
	 */
	var escapeGuardReady = false;
	function guardProductEscape() {
		if ( escapeGuardReady || ! document.body ) {
			return;
		}
		escapeGuardReady = true;
		var fromOpenList = false;
		document.addEventListener( 'keydown', function ( event ) {
			fromOpenList = 'Escape' === event.key && !! ( event.target.closest && event.target.closest( '.select2-container--open' ) );
		}, true );
		document.body.addEventListener( 'keydown', function ( event ) {
			if ( fromOpenList ) {
				event.stopPropagation();
				fromOpenList = false;
			}
		} );
	}

	function initPickers() {
		initCouponPickers();
		initProductPickers();
		guardProductEscape();
	}

	$( initPickers );
	$( document.body ).on( 'wc-enhanced-select-init', initPickers );
} )( jQuery );
