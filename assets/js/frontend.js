( function () {
	'use strict';

	if ( typeof saitoCeFrontend === 'undefined' ) {
		return;
	}

	var COPY_ICON = '<svg class="saito-ce-copy-icon" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><rect x="9" y="9" width="11" height="11" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
	var CHECK_ICON = '<svg class="saito-ce-copy-icon" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';

	/**
	 * Copie dans le presse-papiers. L'API Clipboard exige une page en HTTPS
	 * (ou localhost) : repli sur un textarea + execCommand sinon.
	 */
	function copyText( text ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard.writeText( text );
		}
		return new Promise( function ( resolve, reject ) {
			var area = document.createElement( 'textarea' );
			area.value = text;
			area.setAttribute( 'readonly', '' );
			area.style.position = 'fixed';
			area.style.opacity = '0';
			document.body.appendChild( area );
			area.select();
			var ok = false;
			try {
				ok = document.execCommand( 'copy' );
			} catch {
				ok = false;
			}
			document.body.removeChild( area );
			if ( ok ) {
				resolve();
			} else {
				reject();
			}
		} );
	}

	function storageKey( calendarId ) {
		return 'saito_ce_opened_' + calendarId;
	}

	function getOpened( calendarId ) {
		try {
			var raw = window.localStorage.getItem( storageKey( calendarId ) );
			return raw ? JSON.parse( raw ) : {};
		} catch {
			return {};
		}
	}

	function setOpened( calendarId, day, data ) {
		try {
			var opened = getOpened( calendarId );
			opened[ day ] = data;
			window.localStorage.setItem( storageKey( calendarId ), JSON.stringify( opened ) );
		} catch {
			// Stockage indisponible (navigation privée…) : tant pis, on refera
			// juste un appel serveur à la prochaine visite.
		}
	}

	function postAjax( action, payload ) {
		var body = new window.FormData();
		body.append( 'action', action );
		body.append( 'nonce', saitoCeFrontend.nonce );
		Object.keys( payload ).forEach( function ( key ) {
			body.append( key, payload[ key ] );
		} );

		return window.fetch( saitoCeFrontend.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body,
		} ).then( function ( response ) {
			return response.json();
		} );
	}

	/**
	 * Nombre de jours (entier, peut être négatif) entre aujourd'hui (fuseau
	 * du site, fourni par le serveur) et une date "Y-m-d" donnée.
	 */
	function daysUntil( dateStr ) {
		var target = dateStr.split( '-' );
		var today = saitoCeFrontend.today.split( '-' );
		var targetUtc = Date.UTC( target[ 0 ], target[ 1 ] - 1, target[ 2 ] );
		var todayUtc = Date.UTC( today[ 0 ], today[ 1 ] - 1, today[ 2 ] );
		return Math.round( ( targetUtc - todayUtc ) / 86400000 );
	}

	/**
	 * Pluie de confettis à l'ouverture d'une case, via canvas-confetti
	 * (vendored dans assets/js/vendor/, cf. saito-ce-confetti) : rendu physique
	 * réaliste (gravité, dispersion) qu'un système de particules CSS fait
	 * maison ne reproduit pas bien. Trois tirs (un en éventail vers la
	 * gauche, un vers la droite, un plus large au centre) plutôt qu'un seul
	 * jet uniforme : un classique "réaliste" de canvas-confetti, nettement
	 * plus généreux qu'un tir unique pour marquer le moment. `disableFor-
	 * ReducedMotion` est un réglage natif de la librairie ; le check
	 * matchMedia reste en repli si jamais elle n'a pas pu se charger.
	 */
	/**
	 * Animation "porte qui s'ouvre" à l'ouverture d'une case : un battant
	 * reprenant l'apparence de la case fermée (couleurs, numéro, petite
	 * poignée) pivote sur son bord gauche et dévoile la case ouverte
	 * derrière. Le battant est retiré à la fin de l'animation.
	 *
	 * @return {number} délai (ms) avant d'afficher la modale : le temps de
	 *                  voir la porte s'ouvrir, 0 si les animations sont
	 *                  réduites.
	 */
	function openDoor( button ) {
		var reducedMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		if ( reducedMotion ) {
			return 0;
		}

		var computed = window.getComputedStyle( button );
		var dayEl = button.querySelector( '.saito-ce-case-day' );
		var door = document.createElement( 'span' );
		door.className = 'saito-ce-case-door';
		door.setAttribute( 'aria-hidden', 'true' );
		door.style.backgroundColor = computed.backgroundColor;
		door.style.backgroundImage = computed.backgroundImage;
		door.style.color = computed.color;

		var doorDay = document.createElement( 'span' );
		doorDay.className = 'saito-ce-case-door-day';
		doorDay.textContent = dayEl ? dayEl.textContent : '';
		door.appendChild( doorDay );

		var knob = document.createElement( 'span' );
		knob.className = 'saito-ce-case-door-knob';
		door.appendChild( knob );

		// Gerbe de rayons derrière la porte (stylée par les habillages qui le
		// souhaitent, ex. Étincelles ; invisible sinon).
		var burst = document.createElement( 'span' );
		burst.className = 'saito-ce-case-burst';
		burst.setAttribute( 'aria-hidden', 'true' );

		var cleaned = false;
		function cleanUp() {
			if ( cleaned ) {
				return;
			}
			cleaned = true;
			door.remove();
			burst.remove();
			button.classList.remove( 'is-door-opening' );
		}

		button.classList.add( 'is-door-opening' );
		button.appendChild( burst );
		button.appendChild( door );
		door.addEventListener( 'animationend', cleanUp, { once: true } );
		// Filet de sécurité : la porte ne doit jamais rester devant la case
		// si l'événement de fin d'animation ne se déclenche pas.
		setTimeout( cleanUp, 1500 );

		return 450;
	}

	/**
	 * "a, b, c" (valeur d'une variable CSS) → [ 'a', 'b', 'c' ].
	 */
	function cssList( value ) {
		return String( value || '' ).split( ',' ).map( function ( item ) {
			return item.trim();
		} ).filter( Boolean );
	}

	/**
	 * Une fois une case ouverte, sa récompense s'y affiche en photo (image
	 * de la case, sinon celle du produit lié), comme en découvrant le
	 * produit dans une vraie case. Le numéro reste visible en pastille.
	 */
	function showReward( button, data ) {
		var url = data && ( data.image_url || ( data.product && data.product.image ) );
		if ( ! url || button.querySelector( '.saito-ce-case-reward' ) ) {
			return;
		}
		var reward = document.createElement( 'span' );
		reward.className = 'saito-ce-case-reward';
		reward.setAttribute( 'aria-hidden', 'true' );
		reward.style.backgroundImage = 'url("' + String( url ).replace( /["\\\n\r]/g, encodeURIComponent ) + '")';
		button.insertBefore( reward, button.firstChild );
		button.classList.add( 'has-reward' );
	}

	function burstConfetti( originEl ) {
		if ( typeof window.confetti !== 'function' ) {
			return;
		}
		if ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
			return;
		}

		var rect = originEl.getBoundingClientRect();
		var origin = {
			x: ( rect.left + rect.width / 2 ) / window.innerWidth,
			y: ( rect.top + rect.height / 2 ) / window.innerHeight,
		};
		// Couleurs et formes propres à l'habillage (variables CSS
		// --saito-ce-confetti / --saito-ce-confetti-shapes), repli sur la palette
		// d'origine.
		var calendarStyle = window.getComputedStyle( originEl.closest( '.saito-ce-calendar' ) || originEl );
		var colors = cssList( calendarStyle.getPropertyValue( '--saito-ce-confetti' ) );
		var shapes = cssList( calendarStyle.getPropertyValue( '--saito-ce-confetti-shapes' ) );
		var shared = {
			origin: origin,
			colors: colors.length ? colors : [ '#d8613c', '#5d7256', '#cfcabe', '#c2a990' ],
			disableForReducedMotion: true,
		};
		if ( shapes.length ) {
			shared.shapes = shapes;
		}

		window.confetti( Object.assign( {}, shared, { particleCount: 35, angle: 55, spread: 50, startVelocity: 42 } ) );
		window.confetti( Object.assign( {}, shared, { particleCount: 35, angle: 125, spread: 50, startVelocity: 42 } ) );
		window.confetti( Object.assign( {}, shared, { particleCount: 45, spread: 100, startVelocity: 28, scalar: 1.1, gravity: 1.1 } ) );
	}

	/**
	 * Effet de bascule 3D au survol (vanilla-tilt.js, vendored) sur les cases
	 * de l'habillage "Éditorial" : réservé à cet habillage "premium", les
	 * autres (Noël, Neutre) restent volontairement sobres. Initialisation
	 * manuelle (plutôt que l'auto-init [data-tilt] de la librairie) pour
	 * pouvoir la désactiver entièrement si "mouvement réduit" est demandé.
	 */
	function initTilt( calendarEl ) {
		if ( ! calendarEl.classList.contains( 'saito-ce-style-editorial' ) ) {
			return;
		}
		if ( typeof window.VanillaTilt === 'undefined' ) {
			return;
		}
		if ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
			return;
		}

		window.VanillaTilt.init( calendarEl.querySelectorAll( '.saito-ce-case' ), {
			max: 6,
			speed: 400,
			scale: 1.02,
			glare: true,
			'max-glare': 0.15,
			gyroscope: false,
		} );
	}

	/**
	 * Lueur chaude qui suit le curseur sur la grille "Éditorial" (cf.
	 * ::after sur .saito-ce-grid en CSS) : ne fait que mettre à jour deux
	 * variables CSS de position, tout le rendu (dégradé, mix-blend-mode,
	 * apparition/disparition) reste en CSS. Coupé sur tactile (pas de
	 * curseur qui "suit" au doigt) et si "mouvement réduit" est demandé.
	 */
	function initGlow( calendarEl ) {
		if ( ! calendarEl.classList.contains( 'saito-ce-style-editorial' ) ) {
			return;
		}
		if ( window.matchMedia && ( window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches || window.matchMedia( '(hover: none)' ).matches ) ) {
			return;
		}

		var grid = calendarEl.querySelector( '.saito-ce-grid' );
		if ( ! grid ) {
			return;
		}

		grid.addEventListener( 'mousemove', function ( event ) {
			var rect = grid.getBoundingClientRect();
			grid.style.setProperty( '--saito-ce-mx', ( ( event.clientX - rect.left ) / rect.width * 100 ) + '%' );
			grid.style.setProperty( '--saito-ce-my', ( ( event.clientY - rect.top ) / rect.height * 100 ) + '%' );
		} );
	}

	function initCalendar( calendarEl ) {
		var calendarId = calendarEl.getAttribute( 'data-calendar-id' );
		var modal = calendarEl.querySelector( '.saito-ce-modal' );
		var modalBody = calendarEl.querySelector( '.saito-ce-modal-body' );
		var modalClose = modal.querySelector( '.saito-ce-modal-close' );
		var opened = getOpened( calendarId );

		// Textes personnalisables par calendrier (réglage BO), rendus en
		// attributs sur le conteneur ; repli sur les valeurs par défaut
		// globales quand rien n'a été personnalisé pour ce calendrier.
		var texts = {
			apply: calendarEl.getAttribute( 'data-text-apply' ) || saitoCeFrontend.i18n.apply,
			applying: calendarEl.getAttribute( 'data-text-applying' ) || saitoCeFrontend.i18n.applying,
			appliedButton: calendarEl.getAttribute( 'data-text-applied-button' ) || saitoCeFrontend.i18n.appliedButton,
			applied: calendarEl.getAttribute( 'data-text-applied' ) || saitoCeFrontend.i18n.applied,
			error: calendarEl.getAttribute( 'data-text-error' ) || saitoCeFrontend.i18n.error,
			expired: calendarEl.getAttribute( 'data-text-expired' ) || saitoCeFrontend.i18n.expired,
			expiresToday: calendarEl.getAttribute( 'data-text-expires-today' ) || saitoCeFrontend.i18n.expiresToday,
			today: calendarEl.getAttribute( 'data-text-today' ) || saitoCeFrontend.i18n.today,
		};
		// Case sur laquelle le visiteur a cliqué : permet de lui rendre le
		// focus clavier à la fermeture, plutôt que de le perdre sur <body>.
		var lastTrigger = null;

		function openModal( trigger ) {
			lastTrigger = trigger || null;
			modal.hidden = false;
			// Force le navigateur à appliquer l'état "fermé" avant d'ajouter
			// la classe qui déclenche la transition d'entrée (sinon, comme
			// `hidden` vient tout juste d'être retiré, le panneau apparaît
			// directement ouvert sans animation).
			void modal.offsetWidth;
			modal.classList.add( 'is-open' );
			modalClose.focus();
		}

		function closeModal() {
			modal.classList.remove( 'is-open' );

			var panel = modal.querySelector( '.saito-ce-modal-content' );
			var hide = function () {
				modal.hidden = true;
				if ( lastTrigger ) {
					lastTrigger.focus();
				}
			};

			// Les habillages sans transition définie (Noël, Neutre) n'émettent
			// jamais 'transitionend' : on masque alors immédiatement.
			if ( panel && parseFloat( window.getComputedStyle( panel ).transitionDuration ) > 0 ) {
				panel.addEventListener( 'transitionend', hide, { once: true } );
			} else {
				hide();
			}
		}

		function getFocusable() {
			var selector = 'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
			return Array.prototype.slice.call( modal.querySelectorAll( selector ) );
		}

		modalClose.addEventListener( 'click', closeModal );
		modal.addEventListener( 'click', function ( event ) {
			if ( event.target === modal ) {
				closeModal();
			}
		} );
		modal.addEventListener( 'keydown', function ( event ) {
			if ( modal.hidden ) {
				return;
			}
			if ( 'Escape' === event.key ) {
				closeModal();
				return;
			}
			// Piège le focus dans le panneau tant qu'il est ouvert : sans ça,
			// Tab/Maj+Tab laisse le clavier ressortir sur le reste de la page
			// pendant qu'une fenêtre modale est censée avoir tout le focus.
			if ( 'Tab' !== event.key ) {
				return;
			}
			var focusable = getFocusable();
			if ( ! focusable.length ) {
				return;
			}
			var first = focusable[ 0 ];
			var last = focusable[ focusable.length - 1 ];
			if ( event.shiftKey && document.activeElement === first ) {
				event.preventDefault();
				last.focus();
			} else if ( ! event.shiftKey && document.activeElement === last ) {
				event.preventDefault();
				first.focus();
			}
		} );

		function renderModal( day, data, trigger ) {
			// `data` peut venir du cache local (ouverture d'un jour antérieur,
			// potentiellement depuis expirée) : on ne fait jamais confiance à
			// `can_apply`/`coupon_expired` tels que figés lors du premier
			// fetch, et on les recalcule contre la date du jour avant tout
			// rendu. Sans ça, une case ouverte quand son offre était encore
			// valable continuait d'afficher le bouton "Ajouter au panier"
			// après son expiration, le clic échouant alors côté serveur avec
			// le message "offre expirée" — bouton et message d'erreur
			// affichés ensemble, source de confusion.
			var unlockDateAttr = trigger ? trigger.getAttribute( 'data-unlock-date' ) : '';
			// Le bouton n'a de sens que le jour exact du déblocage : une case future
			// (cache local d'une date modifiée depuis) ou passée ne l'affiche jamais.
			var isTodayNow = !! unlockDateAttr && unlockDateAttr === saitoCeFrontend.today;
			var isExpiredNow = !! unlockDateAttr && unlockDateAttr < saitoCeFrontend.today;
			data.can_apply = data.can_apply && isTodayNow;
			data.coupon_expired = data.coupon_expired || isExpiredNow;

			var html = '';
			if ( data.image_url ) {
				html += '<img class="saito-ce-modal-image" src="' + data.image_url + '" alt="">';
			}
			if ( data.title ) {
				html += '<h3 class="saito-ce-modal-title">' + escapeHtml( data.title ) + '</h3>';
			}
			if ( data.description ) {
				html += '<p class="saito-ce-modal-description">' + escapeHtml( data.description ) + '</p>';
			}
			if ( data.product ) {
				html += '<a class="saito-ce-modal-product" href="' + data.product.url + '">';
				if ( data.product.image ) {
					html += '<img src="' + data.product.image + '" alt="">';
				}
				html += '<span class="saito-ce-modal-product-info">';
				html += '<span class="saito-ce-modal-product-name">' + escapeHtml( data.product.name ) + '</span>';
				if ( data.product.price ) {
					html += '<span class="saito-ce-modal-product-price">' + escapeHtml( data.product.price ) + '</span>';
				}
				html += '</span></a>';
			}
			if ( data.coupon_code ) {
				html += '<div class="saito-ce-modal-code-row">';
				html += '<p class="saito-ce-modal-code">' + escapeHtml( data.coupon_code ) + '</p>';
				html += '<button type="button" class="saito-ce-copy-button" aria-label="' + escapeHtml( saitoCeFrontend.i18n.copyLabel ) + '">';
				html += COPY_ICON + '<span class="saito-ce-copy-text">' + escapeHtml( saitoCeFrontend.i18n.copy ) + '</span>';
				html += '</button></div>';
			}
			if ( data.can_apply ) {
				html += '<button type="button" class="saito-ce-apply-button" data-day="' + day + '">' + escapeHtml( texts.apply ) + '</button>';
			} else if ( data.coupon_expired ) {
				// Case consultable (jour déjà passé), mais son offre du jour
				// n'est plus réclamable : pas de bouton, juste une explication
				// pour ne pas laisser croire à un bug.
				html += '<p class="saito-ce-modal-expired">' + escapeHtml( texts.expired ) + '</p>';
			}
			html += '<p class="saito-ce-modal-feedback" role="status"></p>';
			modalBody.innerHTML = html;

			var copyButton = modalBody.querySelector( '.saito-ce-copy-button' );
			if ( copyButton ) {
				var copyResetTimer = null;
				copyButton.addEventListener( 'click', function () {
					copyText( data.coupon_code ).then( function () {
						copyButton.classList.add( 'is-copied' );
						copyButton.setAttribute( 'aria-label', saitoCeFrontend.i18n.copied );
						copyButton.innerHTML = CHECK_ICON + '<span class="saito-ce-copy-text">' + escapeHtml( saitoCeFrontend.i18n.copied ) + '</span>';
						window.clearTimeout( copyResetTimer );
						copyResetTimer = window.setTimeout( function () {
							copyButton.classList.remove( 'is-copied' );
							copyButton.setAttribute( 'aria-label', saitoCeFrontend.i18n.copyLabel );
							copyButton.innerHTML = COPY_ICON + '<span class="saito-ce-copy-text">' + escapeHtml( saitoCeFrontend.i18n.copy ) + '</span>';
						}, 2000 );
					} ).catch( function () {
						// Copie impossible (navigateur trop restrictif) : on sélectionne
						// le code pour que le visiteur puisse le copier à la main.
						var codeEl = modalBody.querySelector( '.saito-ce-modal-code' );
						if ( codeEl && window.getSelection ) {
							var range = document.createRange();
							range.selectNodeContents( codeEl );
							var selection = window.getSelection();
							selection.removeAllRanges();
							selection.addRange( range );
						}
					} );
				} );
			}

			var applyButton = modalBody.querySelector( '.saito-ce-apply-button' );
			if ( applyButton ) {
				var originalLabel = applyButton.textContent;
				applyButton.addEventListener( 'click', function () {
					applyButton.disabled = true;
					applyButton.textContent = texts.applying;
					postAjax( 'saito_ce_apply_coupon', { calendar_id: calendarId, day: day } )
						.then( function ( response ) {
							var feedback = modalBody.querySelector( '.saito-ce-modal-feedback' );
							if ( response.success ) {
								feedback.textContent = response.data.message || texts.applied;
								feedback.classList.add( 'is-success' );
								applyButton.textContent = texts.appliedButton;
								if ( window.jQuery ) {
									window.jQuery( document.body ).trigger( 'wc_fragment_refresh' );
								}
							} else {
								feedback.textContent = ( response.data && response.data.message ) || texts.error;
								feedback.classList.add( 'is-error' );
								applyButton.disabled = false;
								applyButton.textContent = originalLabel;
							}
						} )
						.catch( function () {
							applyButton.disabled = false;
							applyButton.textContent = originalLabel;
						} );
				} );
			}

			openModal( trigger );
		}

		/**
		 * Affiche un message d'erreur (case pas encore ouvrable, erreur
		 * réseau...) dans notre propre modale plutôt qu'une alerte native du
		 * navigateur (window.alert) : cohérent avec l'habillage du calendrier,
		 * et ne bloque pas le fil d'exécution JS comme le ferait une alerte
		 * native. Réutilise la même modale que renderModal() (mêmes
		 * ouverture/fermeture/piège de focus), juste avec un contenu minimal.
		 */
		function renderErrorModal( message, trigger ) {
			modalBody.innerHTML = '<p class="saito-ce-modal-feedback is-error" role="status">' + escapeHtml( message ) + '</p>';
			openModal( trigger );
		}

		function escapeHtml( str ) {
			var div = document.createElement( 'div' );
			div.textContent = str;
			return div.innerHTML;
		}

		var progressEl = calendarEl.querySelector( '.saito-ce-progress' );

		/**
		 * Nombre de cases ouvertes stocké en local uniquement : ne reflète
		 * que l'avancement sur cet appareil, mais suffit pour une barre de
		 * progression indicative (aucune donnée serveur équivalente, l'état
		 * "ouvert" n'étant pas lié à un compte visiteur).
		 */
		function updateProgress() {
			if ( ! progressEl ) {
				return;
			}
			var total = parseInt( progressEl.getAttribute( 'data-total' ), 10 ) || 0;
			var count = Math.min( Object.keys( getOpened( calendarId ) ).length, total );
			var bar = progressEl.querySelector( '.saito-ce-progress-bar' );
			var label = progressEl.querySelector( '.saito-ce-progress-label' );
			if ( bar ) {
				bar.style.width = ( total ? ( count / total * 100 ) : 0 ) + '%';
			}
			if ( label ) {
				label.textContent = saitoCeFrontend.i18n.progress.replace( '%1$s', count ).replace( '%2$s', total );
			}
		}

		var buttons = calendarEl.querySelectorAll( '.saito-ce-case' );
		buttons.forEach( function ( button ) {
			var day = button.getAttribute( 'data-day' );
			var unlockDate = button.getAttribute( 'data-unlock-date' );
			// Une case n'est ouvrable que le jour exact de son déblocage, ni
			// avant ni après (cf. is_unlocked()/is_case_expired() côté
			// serveur, seul juge de vérité) : "available" ne couvre donc plus
			// qu'aujourd'hui, et une case déjà passée devient "expired"
			// plutôt que de rester "available" indéfiniment.
			var isToday = !! unlockDate && unlockDate === saitoCeFrontend.today;
			var isExpired = !! unlockDate && unlockDate < saitoCeFrontend.today;
			// Une case future n'est jamais "ouverte", même si une ancienne ouverture
			// est en cache local (date modifiée depuis) : le cache ne prime pas sur la date.
			var isFuture = ! unlockDate || unlockDate > saitoCeFrontend.today;

			if ( opened[ day ] && ! isFuture ) {
				button.classList.add( 'is-opened' );
				showReward( button, opened[ day ] );
			} else if ( isToday ) {
				button.classList.add( 'is-available', 'is-today' );
				button.setAttribute( 'aria-current', 'date' );
				// Texte du badge (personnalisable, onglet Textes), lu par le CSS.
				button.setAttribute( 'data-today-label', texts.today );

				// Son offre expire à minuit (cf. is_case_expired() côté
				// serveur) : la case du jour n'avait jusqu'ici aucun indice de
				// cette urgence, contrairement aux cases verrouillées qui
				// affichent un compte à rebours ("J-3").
				var todayCountdownEl = button.querySelector( '.saito-ce-case-countdown' );
				if ( todayCountdownEl ) {
					todayCountdownEl.textContent = texts.expiresToday;
				}
			} else if ( isExpired ) {
				// Case déjà passée sans avoir été ouverte : plus accessible
				// du tout (cf. is_case_expired()), un clic affichera l'erreur
				// "la date est passée" dans notre modale plutôt que de
				// l'ouvrir. Simple style atténué ici, pas de compte à rebours
				// (rien à attendre : c'est définitivement fermé).
				button.classList.add( 'is-expired' );
			} else {
				button.classList.add( 'is-locked' );
				if ( unlockDate ) {
					// Message personnalisable par calendrier (réglage BO), déjà
					// formaté côté serveur (date localisée) : on l'utilise tel quel.
					button.title = button.getAttribute( 'data-locked-message' ) || '';
					var daysLeft = daysUntil( unlockDate );
					var countdownEl = button.querySelector( '.saito-ce-case-countdown' );
					if ( countdownEl && daysLeft > 0 ) {
						countdownEl.textContent = saitoCeFrontend.i18n.countdown.replace( '%s', daysLeft );
					}
				}
			}

			button.addEventListener( 'click', function () {
				var cached = getOpened( calendarId )[ day ];
				if ( cached ) {
					// Même règle que le serveur, appliquée aussi au cache local :
					// une case n'est visible que le jour exact de son déblocage.
					if ( isFuture ) {
						renderErrorModal( button.getAttribute( 'data-locked-message' ) || texts.error, button );
						return;
					}
					if ( isExpired ) {
						renderErrorModal( texts.expired, button );
						return;
					}
					renderModal( day, cached, button );
					return;
				}

				postAjax( 'saito_ce_open_case', { calendar_id: calendarId, day: day } )
					.then( function ( response ) {
						if ( response.success ) {
							setOpened( calendarId, day, response.data );
							// La porte est créée avant le changement de classes
							// pour reprendre l'apparence de la case fermée.
							var doorDelay = openDoor( button );
							button.classList.remove( 'is-locked', 'is-available', 'is-expired' );
							button.classList.add( 'is-opened' );
							burstConfetti( button );
							updateProgress();
							showReward( button, response.data );
							// Laisse voir la porte s'ouvrir avant d'afficher la récompense.
							setTimeout( function () {
								renderModal( day, response.data, button );
							}, doorDelay );
						} else {
							renderErrorModal( ( response.data && response.data.message ) || texts.error, button );
						}
					} )
					.catch( function () {
						renderErrorModal( texts.error, button );
					} );
			} );
		} );

		updateProgress();
		initTilt( calendarEl );
		initGlow( calendarEl );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.saito-ce-calendar' ).forEach( initCalendar );
	} );
} )();
