( function ( $ ) {
	'use strict';

	/**
	 * Reflète en direct, dans le bloc #saito-ce-preview, l'habillage et les
	 * couleurs custom en cours de saisie — sans attendre l'enregistrement
	 * du post, pour pouvoir comparer les réglages avant de publier.
	 */
	function updatePreview() {
		var $preview = $( '#saito-ce-preview' );
		if ( ! $preview.length ) {
			return;
		}

		var style = $( '#saito_ce_style' ).val() || 'noel';
		// Préserve les classes de disposition posées par rebuildPreviewCases().
		var keep = ( $preview.attr( 'class' ) || '' ).split( /\s+/ ).filter( function ( cls ) {
			return /^(saito-ce-mosaic|saito-ce-mosaic-fixed|saito-ce-mosaic-cols-4|has-header)$/.test( cls );
		} );
		$preview.attr( 'class', [ 'saito-ce-calendar', 'saito-ce-style-' + style ].concat( keep ).join( ' ' ) );

		var vars = {
			'--saito-ce-accent': $( '#saito_ce_color_accent' ).val(),
			'--saito-ce-accent-dark': $( '#saito_ce_color_accent_dark' ).val(),
			'--saito-ce-bg': $( '#saito_ce_color_bg' ).val(),
			'--saito-ce-text': $( '#saito_ce_color_text' ).val(),
			'--saito-ce-border': $( '#saito_ce_border' ).val(),
		};

		var radius = parseInt( $( '#saito_ce_radius' ).val(), 10 );
		if ( ! isNaN( radius ) ) {
			vars[ '--saito-ce-radius' ] = radius + 'px';
		}

		var declarations = [];
		Object.keys( vars ).forEach( function ( key ) {
			if ( vars[ key ] ) {
				declarations.push( key + ':' + vars[ key ] );
			}
		} );

		$preview.attr( 'style', declarations.join( ';' ) );
	}

	/**
	 * Reconstruit la grille de l'aperçu (#saito-ce-preview) à partir des
	 * modales actuellement dans la page plutôt que des cases déjà
	 * enregistrées en base : jusqu'ici, ajouter/retirer une case ou changer
	 * sa date n'avait aucun effet sur l'aperçu avant un premier
	 * enregistrement du post, ce qui le rendait trompeur pendant la saisie.
	 * Appelée à chaque changement de jour/date d'une case, et après
	 * ajout/suppression/import (cf. plus bas).
	 */
	/**
	 * Réplique de Saito_CE_Public::mosaic_arrangement() pour l'aperçu : forme
	 * et place de chaque jour, dans l'ordre d'affichage.
	 */
	function mosaicArrangement( days ) {
		var layouts = ( ( window.saitoCeAdmin || {} ).mosaic || {} ).layouts || {};
		days = days.filter( function ( day, index ) {
			return day > 0 && days.indexOf( day ) === index;
		} ).sort( function ( a, b ) {
			return a - b;
		} );

		// Disposition fixe pour un calendrier 1→N quand elle existe (24 cases,
		// semaine de 8), cf. Saito_CE_Public::mosaic_layouts().
		var layout = layouts[ days.length ] || {};
		var isFull = days.length > 0 && !! layouts[ days.length ] && days.every( function ( day, index ) {
			return day === index + 1;
		} );

		if ( isFull ) {
			return Object.keys( layout ).map( function ( day ) {
				var cell = layout[ day ];
				var shape = '';
				if ( 2 === cell[ 2 ] && 2 === cell[ 3 ] ) {
					shape = 'big';
				} else if ( 2 === cell[ 2 ] ) {
					shape = 'tall';
				} else if ( 2 === cell[ 3 ] ) {
					shape = 'wide';
				}
				return { day: parseInt( day, 10 ), row: cell[ 0 ], col: cell[ 1 ], shape: shape, area: cell[ 0 ] + ' / ' + cell[ 1 ] + ' / span ' + cell[ 2 ] + ' / span ' + cell[ 3 ] };
			} ).sort( function ( a, b ) {
				return a.row - b.row || a.col - b.col;
			} );
		}

		return days.map( function ( day, index ) {
			var position = index % 12;
			var shape = '';
			if ( 0 === position || days.length - 1 === index ) {
				shape = 'big';
			} else if ( 3 === position || 8 === position ) {
				shape = 'tall';
			} else if ( 6 === position ) {
				shape = 'wide';
			}
			return { day: day, shape: shape, area: '' };
		} );
	}

	/**
	 * En-tête de l'aperçu : titre du calendrier et sous-titre, comme sur le
	 * site (cf. Saito_CE_Public::header_texts()).
	 */
	function updatePreviewHeader( isMosaic ) {
		var $preview = $( '#saito-ce-preview' );
		var $header = $preview.find( '.saito-ce-header' );
		var show = isMosaic && $( '#saito_ce_show_header' ).is( ':checked' );
		var texts = { title: $( '#title' ).val() || '', sub: $( '#saito_ce_header_subtitle' ).val() || '' };

		$header.empty();
		if ( show ) {
			[ 'title', 'sub' ].forEach( function ( part ) {
				if ( texts[ part ] ) {
					$header.append( $( '<span>' ).addClass( 'saito-ce-header-' + part ).text( texts[ part ] ) );
				}
			} );
		}
		var visible = show && !! ( texts.title || texts.sub );
		$header.prop( 'hidden', ! visible );
		$preview.toggleClass( 'has-header', visible );
	}

	function rebuildPreviewCases() {
		var $grid = $( '#saito-ce-preview .saito-ce-grid-preview' );
		if ( ! $grid.length ) {
			return;
		}

		var mosaic = ( window.saitoCeAdmin || {} ).mosaic || {};
		var isMosaic = ( mosaic.styles || [] ).indexOf( $( '#saito_ce_style' ).val() ) !== -1;
		updatePreviewHeader( isMosaic );

		var today = todayIso();
		var cases = [];
		$( '#saito-ce-cases-modals .saito-ce-admin-modal' ).each( function () {
			var day = parseInt( $( this ).find( '.saito-ce-field-day' ).val(), 10 );
			if ( ! day ) {
				return;
			}
			cases.push( { day: day, date: $( this ).find( '.saito-ce-field-date' ).val() || '' } );
		} );

		if ( ! cases.length ) {
			// Aucune case saisie : on laisse l'exemple générique rendu par
			// PHP plutôt que de vider la grille.
			return;
		}

		cases.sort( function ( a, b ) {
			return a.day - b.day;
		} );

		var cells = {};
		var isFixed = false;
		if ( isMosaic ) {
			var arrangement = mosaicArrangement( cases.map( function ( item ) {
				return item.day;
			} ) );
			var order = {};
			arrangement.forEach( function ( cell, index ) {
				cells[ cell.day ] = cell;
				order[ cell.day ] = index;
			} );
			isFixed = arrangement.length > 0 && '' !== arrangement[ 0 ].area;
			cases.sort( function ( a, b ) {
				return order[ a.day ] - order[ b.day ];
			} );
		}
		var columns = 0;
		if ( isMosaic && isFixed ) {
			Object.keys( cells ).forEach( function ( day ) {
				var parts = cells[ day ].area.split( ' / ' );
				columns = Math.max( columns, parseInt( parts[ 1 ], 10 ) + parseInt( parts[ 3 ].replace( 'span ', '' ), 10 ) - 1 );
			} );
		}
		$( '#saito-ce-preview' )
			.toggleClass( 'saito-ce-mosaic', isMosaic )
			.toggleClass( 'saito-ce-mosaic-fixed', isMosaic && isFixed )
			.toggleClass( 'saito-ce-mosaic-cols-4', isMosaic && isFixed && 4 === columns );

		var html = '';
		cases.forEach( function ( item ) {
			var cell = cells[ item.day ] || { shape: '', area: '' };
			var classes = [ 'saito-ce-case' ];
			// Une case n'est ouvrable que le jour exact de son déblocage
			// (cf. is_unlocked()/is_case_expired() côté serveur) : "locked"
			// avant, "available" (+ "today") exactement ce jour-là, "expired"
			// après — plus de zone "reste ouvrable indéfiniment".
			if ( ! item.date || item.date > today ) {
				classes.push( 'is-locked' );
			} else if ( item.date === today ) {
				classes.push( 'is-available', 'is-today' );
			} else {
				classes.push( 'is-expired' );
			}
			var attrs = ' data-day="' + item.day + '"';
			if ( cell.shape ) {
				attrs += ' data-shape="' + cell.shape + '"';
			}
			if ( cell.area ) {
				attrs += ' style="--saito-ce-area: ' + cell.area + '"';
			}
			if ( item.date === today ) {
				attrs += ' data-today-label="' + ( $( '#saito_ce_text_today' ).val() || mosaic.today || '' ).replace( /"/g, '&quot;' ).replace( /</g, '&lt;' ) + '"';
			}
			html += '<button type="button" class="' + classes.join( ' ' ) + '"' + attrs + ' disabled aria-hidden="true"><span class="saito-ce-case-lock"></span><span class="saito-ce-case-day">' + item.day + '</span></button>';
		} );
		$grid.html( html );
	}

	$( function () {
		$( '.saito-ce-color-field' ).wpColorPicker( {
			// wpColorPicker déclenche ce callback avant que la valeur de
			// l'input texte sous-jacent soit réécrite : on repousse la
			// lecture au tick suivant pour récupérer la couleur choisie.
			change: function () {
				setTimeout( updatePreview, 10 );
			},
			clear: updatePreview,
		} );

		$( document ).on( 'change', '#saito_ce_style', function () {
			updatePreview();
			rebuildPreviewCases();
		} );
		$( document ).on( 'change', '#saito_ce_show_header', rebuildPreviewCases );
		$( document ).on( 'input', '#saito_ce_text_today', rebuildPreviewCases );
		$( document ).on( 'input', '#title, #saito_ce_header_subtitle', rebuildPreviewCases );
		updatePreview();
		rebuildPreviewCases();
		$( document ).on( 'input change', '#saito_ce_radius, #saito_ce_border', updatePreview );
	} );

	/* ------------------------------------------------------------------ *
	 * Onglets (Cases / Apparence / Textes / Utilisation)
	 * ------------------------------------------------------------------ */

	$( document ).on( 'click', '.saito-ce-admin-tab', function () {
		var tab = $( this ).data( 'tab' );

		$( '.saito-ce-admin-tab' ).removeClass( 'is-active' ).attr( 'aria-selected', 'false' );
		$( this ).addClass( 'is-active' ).attr( 'aria-selected', 'true' );

		$( '.saito-ce-admin-tab-panel' ).each( function () {
			$( this ).prop( 'hidden', String( $( this ).data( 'tab-panel' ) ) !== String( tab ) );
		} );
	} );

	/* ------------------------------------------------------------------ *
	 * Grille de cases "bento" + modale par case
	 * ------------------------------------------------------------------ */

	function findCard( index ) {
		return $( '#saito-ce-cases-grid' ).find( '[data-case-index="' + index + '"]' );
	}

	function todayIso() {
		var now = new Date();
		var month = String( now.getMonth() + 1 ).padStart( 2, '0' );
		var day = String( now.getDate() ).padStart( 2, '0' );
		return now.getFullYear() + '-' + month + '-' + day;
	}

	/**
	 * Une fois la date de déblocage passée, le code promo a potentiellement
	 * déjà été distribué aux client·e·s : on verrouille le champ (readonly,
	 * pas disabled, pour que sa valeur reste soumise avec le formulaire) pour
	 * éviter qu'un changement ultérieur ne casse silencieusement une réduction
	 * déjà vue/utilisée. Simple confort admin, pas un contrôle de sécurité :
	 * la comparaison se fait sur l'heure locale du navigateur.
	 */
	function updateCouponLockState( $modal ) {
		var date = $modal.find( '.saito-ce-field-date' ).val();
		var isPast = !! date && date < todayIso();
		$modal.find( '.saito-ce-field-coupon' )
			.prop( 'readonly', isPast )
			.attr( 'title', isPast ? 'Date de déblocage déjà passée : code promo verrouillé.' : '' );
		// La raison du verrouillage doit rester visible sans survol (admin
		// non technique qui ne passerait pas forcément la souris dessus) :
		// un simple attribut title serait trop peu découvrable.
		$modal.find( '.saito-ce-coupon-locked-notice' ).prop( 'hidden', ! isPast );
	}

	/**
	 * Vérifie le code promo saisi contre les codes WooCommerce publiés
	 * (transmis par PHP, cf. Saito_CE_Admin::get_wc_coupons()) : code inconnu,
	 * limite d'utilisation atteinte, ou expiration avant la date de
	 * déblocage de la case. Simple avertissement, n'empêche pas d'enregistrer.
	 */
	function updateCouponCheck( $modal ) {
		var $notice = $modal.find( '.saito-ce-coupon-check' );
		var data = window.saitoCeAdmin || {};
		var coupons = data.coupons;
		var code = String( $modal.find( '.saito-ce-field-coupon' ).val() || '' ).trim().toUpperCase();

		// Pas de code saisi, ou WooCommerce inactif (liste vide côté PHP, et
		// non un objet) : rien à vérifier.
		if ( ! code || ! coupons || Array.isArray( coupons ) ) {
			$notice.prop( 'hidden', true ).text( '' );
			return;
		}

		var i18n = data.i18n || {};
		var coupon = coupons[ code ];
		var date = $modal.find( '.saito-ce-field-date' ).val();
		var message;
		var isWarning = true;

		if ( ! coupon ) {
			message = i18n.couponUnknown;
		} else if ( coupon.usedUp ) {
			message = i18n.couponUsedUp;
		} else if ( coupon.expires && coupon.expires < todayIso() ) {
			message = i18n.couponExpired.replace( '%s', coupon.expiresLabel );
		} else if ( coupon.expires && date && coupon.expires < date ) {
			message = i18n.couponExpiresBefore.replace( '%s', coupon.expiresLabel );
		} else {
			message = i18n.couponOk;
			isWarning = false;
		}

		$notice
			.text( message )
			.toggleClass( 'is-warning', isWarning )
			.toggleClass( 'is-ok', ! isWarning )
			.prop( 'hidden', false );
	}

	$( document ).on( 'input change', '.saito-ce-admin-modal .saito-ce-field-date', function () {
		updateCouponLockState( $( this ).closest( '.saito-ce-admin-modal' ) );
	} );

	$( document ).on( 'input change', '.saito-ce-admin-modal .saito-ce-field-coupon, .saito-ce-admin-modal .saito-ce-field-date', function () {
		updateCouponCheck( $( this ).closest( '.saito-ce-admin-modal' ) );
	} );

	$( document ).on( 'input change', '.saito-ce-admin-modal .saito-ce-field-day, .saito-ce-admin-modal .saito-ce-field-date', rebuildPreviewCases );

	$( function () {
		$( '.saito-ce-admin-modal' ).each( function () {
			updateCouponLockState( $( this ) );
			updateCouponCheck( $( this ) );
		} );
	} );

	/**
	 * Initialise le champ de recherche produit (select2 de WooCommerce) sur
	 * les modales ajoutées après le chargement de la page (ajout de case,
	 * import) : WooCommerce ne traite que les champs présents au départ.
	 */
	function initProductSearch() {
		$( document.body ).trigger( 'wc-enhanced-select-init' );
	}

	/**
	 * Met à jour le résumé affiché sur une carte à partir des champs de sa
	 * modale. Prend directement les deux éléments (plutôt que de
	 * re-chercher la carte via le DOM) pour pouvoir aussi s'en servir sur
	 * une paire carte/modale pas encore insérée dans la page (import JSON).
	 */
	function applyCardFromModal( $card, $modal ) {
		if ( ! $card.length ) {
			return;
		}

		var day = $modal.find( '.saito-ce-field-day' ).val();
		$card.find( '.saito-ce-admin-card-day' ).text( day || '?' );

		var date = $modal.find( '.saito-ce-field-date' ).val();
		$card.find( '.saito-ce-admin-card-date' ).text( date || 'Pas de date' );

		var coupon = $modal.find( '.saito-ce-field-coupon' ).val();
		$card.find( '.saito-ce-admin-card-coupon' ).text( coupon || '' ).prop( 'hidden', ! coupon );

		// Deux badges d'état, cf. la même logique côté PHP dans
		// render_case_card() (rendu initial) : "Expirée" (date strictement
		// passée, case plus ouvrable du tout côté visiteur) et "Date
		// manquante" (coupon saisi sans date, donc jamais débloquable).
		// Recalculés à chaque changement pour rester justes sans recharger
		// la page.
		var isExpired = !! date && date < todayIso();
		$card.find( '.saito-ce-admin-card-expired' ).prop( 'hidden', ! isExpired );
		// Case passée : son code a pu être vu, elle ne se déplace plus (cf.
		// initCaseSorting()).
		$card.toggleClass( 'is-past', isExpired );
		$card.find( '.saito-ce-admin-card-warning' ).prop( 'hidden', ! ( coupon && ! date ) );

		var imageId = $modal.find( '.saito-ce-image-id' ).val();
		var imageSrc = $modal.find( '.saito-ce-image-preview' ).attr( 'src' );
		var $thumb = $card.find( '.saito-ce-admin-card-thumb' );
		if ( imageId && imageSrc ) {
			$thumb.find( 'img' ).attr( 'src', imageSrc );
			$thumb.prop( 'hidden', false );
		} else {
			$thumb.prop( 'hidden', true );
		}
	}

	function syncCardFromModal( $modal ) {
		applyCardFromModal( findCard( $modal.data( 'case-index' ) ), $modal );
	}

	$( document ).on( 'input change', '.saito-ce-admin-modal input, .saito-ce-admin-modal textarea', function () {
		syncCardFromModal( $( this ).closest( '.saito-ce-admin-modal' ) );
	} );

	function openCaseModal( $modal ) {
		if ( ! $modal.length ) {
			return;
		}
		updateCouponLockState( $modal );
		$modal.prop( 'hidden', false );
		$( 'body' ).addClass( 'saito-ce-admin-modal-open' );
		$modal.find( '.saito-ce-field-day' ).trigger( 'focus' );
	}

	function closeCaseModal( $modal ) {
		if ( ! $modal.length ) {
			return;
		}
		$modal.prop( 'hidden', true );
		if ( ! $( '.saito-ce-admin-modal:not([hidden])' ).length ) {
			$( 'body' ).removeClass( 'saito-ce-admin-modal-open' );
		}
	}

	$( document ).on( 'click', '.saito-ce-admin-card', function () {
		// Le relâchement d'un glisser-déposer déclenche aussi un clic sur la
		// carte : il ne doit pas ouvrir sa modale.
		if ( justDragged ) {
			return;
		}
		openCaseModal( $( '#' + $( this ).data( 'modal' ) ) );
	} );

	/* ------------------------------------------------------------------ *
	 * Glisser-déposer : échange du contenu de deux cases
	 * ------------------------------------------------------------------ */

	var justDragged = false;
	var dragSlots = [];

	function findModal( $card ) {
		return $( '#' + $card.data( 'modal' ) );
	}

	/**
	 * Déposer une case sur une autre échange leur contenu (code promo,
	 * produit, accroche, description, image), pas leur jour ni leur date :
	 * le "jour 3" reste le 3 décembre. SortableJS échange les deux cartes
	 * dans la grille (chaque carte emporte sa modale, donc son contenu) ;
	 * on réattribue ensuite à chaque position le jour et la date qu'elle
	 * avait avant le déplacement.
	 *
	 * Les cases dont la date est passée ne bougent pas et ne reçoivent rien :
	 * leur code a peut-être déjà été vu par des client·e·s (cf.
	 * updateCouponLockState()).
	 */
	function initCaseSorting() {
		var grid = document.getElementById( 'saito-ce-cases-grid' );
		if ( ! grid || ! window.Sortable ) {
			return;
		}

		window.Sortable.create( grid, {
			swap: true,
			swapClass: 'is-swap-target',
			ghostClass: 'is-drag-ghost',
			dragClass: 'is-dragging',
			fallbackClass: 'is-dragging',
			// Mode "fallback" (événements souris/tactiles) plutôt que le
			// glisser-déposer natif HTML5, que Firefox refuse sur un <button>.
			forceFallback: true,
			// Un clic sans vrai déplacement reste un clic (ouvre la modale).
			fallbackTolerance: 5,
			// Sur écran tactile, un appui long démarre le déplacement : un
			// simple glissement du doigt continue de faire défiler la page.
			delay: 250,
			delayOnTouchOnly: true,
			filter: '.is-past',
			preventOnFilter: false,
			animation: 150,
			onStart: function () {
				dragSlots = $( grid ).children( '.saito-ce-admin-card' ).map( function () {
					var $modal = findModal( $( this ) );
					return {
						day: $modal.find( '.saito-ce-field-day' ).val(),
						date: $modal.find( '.saito-ce-field-date' ).val(),
					};
				} ).get();
			},
			onMove: function ( event ) {
				return ! $( event.related ).hasClass( 'is-past' );
			},
			onEnd: function ( event ) {
				justDragged = true;
				setTimeout( function () {
					justDragged = false;
				}, 0 );

				if ( ! event.swapItem ) {
					return;
				}

				$( grid ).children( '.saito-ce-admin-card' ).each( function ( position ) {
					var slot = dragSlots[ position ];
					if ( ! slot ) {
						return;
					}
					var $card = $( this );
					var $modal = findModal( $card );
					if ( $modal.find( '.saito-ce-field-day' ).val() === slot.day && $modal.find( '.saito-ce-field-date' ).val() === slot.date ) {
						return;
					}
					$modal.find( '.saito-ce-field-day' ).val( slot.day );
					$modal.find( '.saito-ce-field-date' ).val( slot.date );
					applyCardFromModal( $card, $modal );
					updateCouponLockState( $modal );
					updateCouponCheck( $modal );
				} );

				var $moved = $( event.item ).add( event.swapItem );
				$moved.removeClass( 'is-swapped' );
				// Relance l'animation même si la classe était déjà présente.
				void grid.offsetWidth;
				$moved.addClass( 'is-swapped' ).one( 'animationend', function () {
					$( this ).removeClass( 'is-swapped' );
				} );

				var dayA = findModal( $( event.item ) ).find( '.saito-ce-field-day' ).val();
				var dayB = findModal( $( event.swapItem ) ).find( '.saito-ce-field-day' ).val();
				$( '.saito-ce-dnd-feedback' ).text( 'Contenu des cases ' + dayB + ' et ' + dayA + ' échangé. Pensez à enregistrer.' );
				rebuildPreviewCases();
			},
		} );
	}

	$( initCaseSorting );

	// Onglet Programmation : le sélecteur de date de fin ne propose rien
	// d'antérieur au début (save() remet de toute façon les dates dans
	// l'ordre si elles sont inversées).
	$( document ).on( 'input change', '#saito_ce_schedule_start', function () {
		$( '#saito_ce_schedule_end' ).attr( 'min', $( this ).val() );
	} );

	$( document ).on( 'click', '.saito-ce-admin-modal-close, .saito-ce-admin-modal-done, .saito-ce-admin-modal-overlay', function () {
		closeCaseModal( $( this ).closest( '.saito-ce-admin-modal' ) );
	} );

	$( document ).on( 'keydown', function ( event ) {
		if ( 'Escape' !== event.key ) {
			return;
		}
		var $open = $( '.saito-ce-admin-modal:not([hidden])' ).first();
		if ( $open.length ) {
			closeCaseModal( $open );
		}
	} );

	function nextIndex() {
		var max = -1;
		$( '#saito-ce-cases-modals .saito-ce-admin-modal' ).each( function () {
			var index = parseInt( $( this ).data( 'case-index' ), 10 );
			if ( ! isNaN( index ) ) {
				max = Math.max( max, index );
			}
		} );
		return max + 1;
	}

	function nextDay() {
		var max = 0;
		$( '#saito-ce-cases-modals .saito-ce-field-day' ).each( function () {
			var value = parseInt( $( this ).val(), 10 );
			if ( ! isNaN( value ) ) {
				max = Math.max( max, value );
			}
		} );
		return max + 1;
	}

	/**
	 * Construit une paire carte/modale à partir des deux templates, pour un
	 * index donné et des données de case (partielles ou complètes) : sert
	 * aussi bien à "+ Ajouter une case" (données minimales) qu'à l'import
	 * JSON (données complètes).
	 */
	function buildCase( index, data ) {
		data = data || {};
		var $card = $( $( '#saito-ce-card-template' ).html().replace( /__INDEX__/g, index ) );
		var $modal = $( $( '#saito-ce-modal-template' ).html().replace( /__INDEX__/g, index ) );

		$modal.find( '.saito-ce-field-day' ).val( data.day || '' );
		$modal.find( '.saito-ce-field-date' ).val( data.unlock_date || '' );
		// Date déjà passée (import) : pas de `min`, sinon le formulaire ne
		// pourrait plus être enregistré (cf. render_case_modal()).
		if ( data.unlock_date && data.unlock_date < todayIso() ) {
			$modal.find( '.saito-ce-field-date' ).removeAttr( 'min' );
		}
		$modal.find( '.saito-ce-field-coupon' ).val( data.coupon_code || '' );
		var $product = $modal.find( '.saito-ce-product-id' );
		if ( $product.is( 'select' ) ) {
			// Champ de recherche par nom : une option doit exister pour porter
			// la valeur ; le nom réel n'est pas connu ici (import), d'où "#ID".
			if ( data.product_id ) {
				$product.append( new Option( '#' + data.product_id, data.product_id, true, true ) );
			}
		} else {
			$product.val( data.product_id || '' );
		}
		$modal.find( '.saito-ce-field-title' ).val( data.title || '' );
		$modal.find( '.saito-ce-field-description' ).val( data.description || '' );
		if ( data.image_id ) {
			$modal.find( '.saito-ce-image-id' ).val( data.image_id );
			// L'image ne peut être prévisualisée qu'après enregistrement (l'ID
			// n'est garanti valide que sur le même site que l'export) : on
			// laisse le bouton "Choisir" permettre de la re-sélectionner.
		}

		applyCardFromModal( $card, $modal );
		updateCouponLockState( $modal );
		updateCouponCheck( $modal );

		return { card: $card, modal: $modal };
	}

	$( document ).on( 'click', '#saito-ce-add-case', function () {
		var index = nextIndex();
		var built = buildCase( index, { day: nextDay() } );
		$( '#saito-ce-cases-grid' ).append( built.card );
		$( '#saito-ce-cases-modals' ).append( built.modal );
		initProductSearch();
		openCaseModal( built.modal );
		rebuildPreviewCases();
	} );

	$( document ).on( 'click', '.saito-ce-remove-case', function () {
		var $modal = $( this ).closest( '.saito-ce-admin-modal' );
		var day = $modal.find( '.saito-ce-field-day' ).val();
		var label = day ? 'la case ' + day : 'cette case';

		// Suppression immédiate et sans confirmation jusqu'ici : un clic
		// malheureux effaçait irrémédiablement toute la saisie de la case
		// (date, code, texte, image), sans retour possible une fois le post
		// enregistré.
		if ( ! window.confirm( 'Supprimer ' + label + ' ? Cette action est irréversible une fois le calendrier enregistré.' ) ) {
			return;
		}

		findCard( $modal.data( 'case-index' ) ).remove();
		$modal.remove();
		$( 'body' ).removeClass( 'saito-ce-admin-modal-open' );
		rebuildPreviewCases();
	} );

	$( document ).on( 'click', '#saito-ce-import-cases', function () {
		var fileInput = document.getElementById( 'saito-ce-import-file' );
		var feedback = $( '#saito-ce-import-feedback' );
		feedback.text( '' );

		if ( ! fileInput.files || ! fileInput.files[ 0 ] ) {
			feedback.text( 'Choisis d’abord un fichier .json.' );
			return;
		}

		var reader = new FileReader();
		reader.onload = function ( event ) {
			var data;
			try {
				data = JSON.parse( event.target.result );
			} catch {
				feedback.text( 'Fichier JSON invalide.' );
				return;
			}

			if ( ! data || ! Array.isArray( data.cases ) ) {
				feedback.text( 'Ce fichier ne contient pas de calendrier valide (champ "cases" manquant).' );
				return;
			}

			if ( data.style ) {
				$( '#saito_ce_style' ).val( data.style );
				updatePreview();
			}

			var $grid = $( '#saito-ce-cases-grid' );
			var $modals = $( '#saito-ce-cases-modals' );
			$grid.empty();
			$modals.empty();
			data.cases.forEach( function ( caseData, index ) {
				var built = buildCase( index, caseData );
				$grid.append( built.card );
				$modals.append( built.modal );
			} );

			initProductSearch();
			rebuildPreviewCases();
			feedback.text( data.cases.length + ' case(s) importée(s). Pense à cliquer sur "Publier"/"Mettre à jour" pour enregistrer.' );
		};
		reader.readAsText( fileInput.files[ 0 ] );
	} );

	$( document ).on( 'click', '.saito-ce-select-image', function ( event ) {
		event.preventDefault();
		var button = $( this );
		var cell = button.closest( '.saito-ce-image-cell' );
		var frame = window.wp.media( {
			title: button.text(),
			multiple: false,
			library: { type: 'image' },
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			cell.find( '.saito-ce-image-id' ).val( attachment.id );
			var previewUrl = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
			cell.find( '.saito-ce-image-preview' ).attr( 'src', previewUrl ).show();
			syncCardFromModal( cell.closest( '.saito-ce-admin-modal' ) );
		} );

		frame.open();
	} );

	/* ------------------------------------------------------------------ *
	 * Alerte avant enregistrement sur les cases incomplètes
	 * ------------------------------------------------------------------ */

	/**
	 * Une case avec un code promo mais sans date de déblocage ne sera
	 * jamais débloquable côté visiteur (is_unlocked() exige une date non
	 * vide) : certainement un oubli plutôt qu'un choix. save() ne bloque
	 * pas l'enregistrement pour autant (une case "vide" est juste ignorée
	 * silencieusement côté serveur) : on prévient donc ici, avant l'envoi,
	 * plutôt que de laisser l'admin découvrir plus tard que le code n'a
	 * jamais été proposé à personne.
	 */
	function findIncompleteModals() {
		var incomplete = [];
		$( '#saito-ce-cases-modals .saito-ce-admin-modal' ).each( function () {
			var $modal = $( this );
			if ( $modal.find( '.saito-ce-field-coupon' ).val() && ! $modal.find( '.saito-ce-field-date' ).val() ) {
				incomplete.push( $modal );
			}
		} );
		return incomplete;
	}

	/**
	 * Le navigateur refuse d'envoyer le formulaire si un champ est invalide
	 * (ex. date avant le `min`), même dans un onglet ou une modale masqués —
	 * mais il ne peut alors afficher son message nulle part : "Mettre à
	 * jour" semblait simplement ne rien faire. On amène donc l'admin jusqu'au
	 * premier champ fautif (onglet + modale de la case) et on affiche le
	 * message du navigateur dessus.
	 */
	var revealingInvalid = false;
	document.addEventListener( 'invalid', function ( event ) {
		var field = event.target;
		if ( revealingInvalid || ! field.closest || ! field.closest( '.saito-ce-admin-tabs' ) ) {
			return;
		}
		revealingInvalid = true;

		var $field = $( field );
		var tab = $field.closest( '.saito-ce-admin-tab-panel' ).data( 'tab-panel' );
		$( '.saito-ce-admin-tab[data-tab="' + tab + '"]' ).trigger( 'click' );
		var $modal = $field.closest( '.saito-ce-admin-modal' );
		if ( $modal.length ) {
			openCaseModal( $modal );
		}

		setTimeout( function () {
			field.focus();
			if ( field.reportValidity ) {
				field.reportValidity();
			}
			revealingInvalid = false;
		}, 0 );
	}, true );

	// « Appliquer le modèle » (cf. Saito_CE_Templates::render_picker()) :
	// lien d'application complété avec le modèle et l'année choisis.
	$( document ).on( 'click', '#saito-ce-apply-template', function () {
		var $picker = $( this ).closest( '.saito-ce-template-picker' );
		var i18n = ( window.saitoCeAdmin || {} ).i18n || {};
		if ( '1' === String( $picker.data( 'has-cases' ) ) && ! window.confirm( i18n.templateConfirm || 'Remplacer les cases de ce calendrier ?' ) ) {
			return;
		}
		var url = $picker.data( 'apply-url' ) +
			'&template=' + encodeURIComponent( $( '#saito-ce-template' ).val() || '' ) +
			'&year=' + encodeURIComponent( $( '#saito-ce-template-year' ).val() || '' );
		// Pas d'avertissement « modifications non enregistrées » : voulu ici.
		$( window ).off( 'beforeunload' );
		window.location.href = url;
	} );

	$( document ).on( 'submit', '#post', function ( event ) {
		var incomplete = findIncompleteModals();
		if ( ! incomplete.length ) {
			return;
		}

		var message = incomplete.length > 1
			? incomplete.length + ' cases ont un code promo renseigné mais aucune date de déblocage : elles ne seront jamais proposées aux visiteurs. Enregistrer malgré tout ?'
			: 'Une case a un code promo renseigné mais aucune date de déblocage : elle ne sera jamais proposée aux visiteurs. Enregistrer malgré tout ?';

		if ( window.confirm( message ) ) {
			return;
		}

		event.preventDefault();

		// Amène visuellement jusqu'à la première case fautive plutôt que de
		// laisser l'admin deviner laquelle parmi potentiellement 24+.
		$( '.saito-ce-admin-tab[data-tab="cases"]' ).trigger( 'click' );
		openCaseModal( incomplete[ 0 ] );
	} );
} )( jQuery );
