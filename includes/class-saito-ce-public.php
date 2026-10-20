<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Affichage public (shortcode + bloc) et logique d'ouverture des cases.
 *
 * Sécurité : la date de déblocage de chaque case est vérifiée côté serveur,
 * aussi bien à l'ouverture (révélation du texte/image) qu'à l'application
 * du coupon. Le code promo d'une case n'est jamais envoyé au navigateur tant
 * que sa date n'est pas atteinte, quoi que le client prétende.
 *
 * Une case n'est ouvrable que le jour exact de sa date de déblocage, ni
 * avant (`is_unlocked()`) ni après (`is_case_expired()`) : revenir dessus
 * le lendemain affiche une erreur plutôt que de l'ouvrir à nouveau.
 */
class Saito_CE_Public {

	const NONCE_ACTION = 'saito_ce_public';

	public static function init() {
		add_shortcode( 'saito_calendar_event', array( __CLASS__, 'shortcode' ) );

		add_action( 'init', array( __CLASS__, 'register_block' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_frontend' ) );

		add_action( 'wp_ajax_saito_ce_open_case', array( __CLASS__, 'ajax_open_case' ) );
		add_action( 'wp_ajax_nopriv_saito_ce_open_case', array( __CLASS__, 'ajax_open_case' ) );
		add_action( 'wp_ajax_saito_ce_apply_coupon', array( __CLASS__, 'ajax_apply_coupon' ) );
		add_action( 'wp_ajax_nopriv_saito_ce_apply_coupon', array( __CLASS__, 'ajax_apply_coupon' ) );
	}

	/* ------------------------------------------------------------------ *
	 * Rendu (shortcode + bloc)
	 * ------------------------------------------------------------------ */

	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'saito_calendar_event' );
		return self::render_calendar( (int) $atts['id'] );
	}

	public static function register_block() {
		register_block_type(
			'saito-calendar-event/calendar',
			array(
				'render_callback' => array( __CLASS__, 'render_block' ),
				'attributes'      => array(
					'calendarId' => array(
						'type'    => 'number',
						'default' => 0,
					),
				),
				'editor_script'   => 'saito-ce-block',
			)
		);

		wp_register_script(
			'saito-ce-block',
			SAITO_CE_URL . 'assets/js/block.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
			SAITO_CE_VERSION,
			true
		);

		$calendars = get_posts(
			array(
				'post_type'      => SAITO_CE_POST_TYPE,
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => 'any',
			)
		);
		$choices = array();
		foreach ( $calendars as $calendar ) {
			$choices[] = array(
				'label' => $calendar->post_title ? $calendar->post_title : '#' . $calendar->ID,
				'value' => $calendar->ID,
			);
		}
		wp_localize_script( 'saito-ce-block', 'saitoCeBlockData', array( 'calendars' => $choices ) );
	}

	public static function render_block( $attributes ) {
		$id = isset( $attributes['calendarId'] ) ? (int) $attributes['calendarId'] : 0;
		return self::render_calendar( $id );
	}

	public static function render_calendar( $id ) {
		if ( ! $id ) {
			return '';
		}
		$calendar = get_post( $id );
		if ( ! $calendar || SAITO_CE_POST_TYPE !== $calendar->post_type ) {
			return '';
		}

		// Programmation de la publication : hors de la période d'affichage,
		// les visiteurs ne voient que le message d'attente / de fin (ou rien
		// s'il est vide). Les éditeurs voient toujours le calendrier, avec un
		// bandeau, pour pouvoir le vérifier avant son lancement.
		$schedule       = self::get_schedule( $id );
		$schedule_state = self::get_schedule_state( $schedule );
		$can_edit       = current_user_can( 'edit_post', $id );
		if ( 'live' !== $schedule_state && ! $can_edit ) {
			$message = self::get_schedule_message( $schedule, $schedule_state );
			if ( '' === $message ) {
				return '';
			}
			self::enqueue_frontend_assets();
			return '<div class="saito-ce-schedule-message"><p>' . esc_html( $message ) . '</p></div>';
		}

		$cases = get_post_meta( $id, SAITO_CE_CASES_META, true );
		$cases = is_array( $cases ) ? $cases : array();
		$style = self::normalize_style( get_post_meta( $id, SAITO_CE_STYLE_META, true ) );

		$colors       = get_post_meta( $id, SAITO_CE_COLORS_META, true );
		$colors       = is_array( $colors ) ? $colors : array();
		$style_vars   = self::build_style_declarations( $colors );
		$inline_style = $style_vars ? ' style="' . esc_attr( implode( ';', $style_vars ) ) . '"' : '';

		$valid_cases = array_filter(
			$cases,
			function ( $case ) {
				return ! empty( $case['day'] );
			}
		);

		// Habillages "mosaïque" (portes de tailles variées) : chaque jour a sa
		// forme et, pour un calendrier 1→24, sa place fixe dans la grille. Les
		// cases sont rendues dans l'ordre visuel, pour que la navigation au
		// clavier et les lecteurs d'écran suivent ce que l'on voit.
		$arrangement = self::is_mosaic_style( $style ) ? self::mosaic_arrangement( wp_list_pluck( $valid_cases, 'day' ) ) : array();
		if ( $arrangement ) {
			$position = array_flip( array_keys( $arrangement ) );
			usort(
				$cases,
				function ( $a, $b ) use ( $position ) {
					$pa = isset( $a['day'], $position[ (int) $a['day'] ] ) ? $position[ (int) $a['day'] ] : PHP_INT_MAX;
					$pb = isset( $b['day'], $position[ (int) $b['day'] ] ) ? $position[ (int) $b['day'] ] : PHP_INT_MAX;
					return $pa <=> $pb;
				}
			);
		}
		$first_cell  = $arrangement ? reset( $arrangement ) : null;
		$header      = self::is_mosaic_style( $style ) && '0' !== get_post_meta( $id, SAITO_CE_HEADER_META, true ) ? array_filter( self::header_texts( $id ) ) : array();
		$cal_classes = 'saito-ce-calendar saito-ce-style-' . $style;
		if ( $arrangement ) {
			$cal_classes .= ' saito-ce-mosaic' . ( '' !== $first_cell['area'] ? ' saito-ce-mosaic-fixed' : '' );
		}
		if ( $header ) {
			$cal_classes .= ' has-header';
		}

		self::enqueue_frontend_assets( $style );

		ob_start();

		// Visible seulement par les personnes qui peuvent modifier ce calendrier :
		// évite un cadre vide muet quand aucune case n'a encore été enregistrée
		// (oubli du bouton Publier/Mettre à jour, ou calendrier vidé par erreur).
		if ( empty( $valid_cases ) && current_user_can( 'edit_post', $id ) ) {
			printf(
				'<p style="border:2px dashed #b32d2e;padding:10px;color:#b32d2e;">%s <a href="%s">%s</a></p>',
				esc_html__( 'Saito Calendar Event : ce calendrier n’a aucune case enregistrée (visible par vous uniquement, en tant qu’éditeur).', 'saito-calendar-event' ),
				esc_url( get_edit_post_link( $id, 'raw' ) ),
				esc_html__( 'Modifier le calendrier', 'saito-calendar-event' )
			);
		}

		// Hors période d'affichage, un éditeur voit quand même le calendrier
		// (pour le vérifier) : on lui rappelle que les visiteurs, eux, ne le
		// voient pas.
		if ( 'live' !== $schedule_state ) {
			if ( 'before' === $schedule_state ) {
				/* translators: %s: date de début d'affichage. */
				$notice = sprintf( __( 'Ce calendrier n’est pas encore visible par les visiteurs : il s’affichera à partir du %s. Vous le voyez car vous pouvez le modifier.', 'saito-calendar-event' ), self::format_unlock_date_for_display( $schedule['start'] ) );
			} else {
				/* translators: %s: date de fin d'affichage. */
				$notice = sprintf( __( 'Ce calendrier n’est plus visible par les visiteurs depuis la fin de sa période d’affichage (%s). Vous le voyez car vous pouvez le modifier.', 'saito-calendar-event' ), self::format_unlock_date_for_display( $schedule['end'] ) );
			}
			printf(
				'<p style="border:2px dashed #996800;padding:10px;color:#6b4a00;">%s <a href="%s">%s</a></p>',
				esc_html( $notice ),
				esc_url( get_edit_post_link( $id, 'raw' ) ),
				esc_html__( 'Modifier la programmation', 'saito-calendar-event' )
			);
		}
		?>
		<div class="<?php echo esc_attr( $cal_classes ); ?>" data-calendar-id="<?php echo esc_attr( $id ); ?>"<?php echo $inline_style; // phpcs:ignore -- déjà échappé ci-dessus ?><?php echo self::get_custom_text_attributes( $id ); // phpcs:ignore -- déjà échappé ci-dessous ?>>
			<?php echo self::render_header( $header ); // phpcs:ignore -- échappé dans render_header() ?>
			<div class="saito-ce-grid">
				<?php
				$case_index = 0;
				foreach ( $cases as $case ) :
					$day         = isset( $case['day'] ) ? (int) $case['day'] : 0;
					$unlock_date = isset( $case['unlock_date'] ) ? $case['unlock_date'] : '';
					if ( ! $day ) {
						continue;
					}
					$cell       = isset( $arrangement[ $day ] ) ? $arrangement[ $day ] : array( 'shape' => '', 'area' => '' );
					$case_style = '--saito-ce-i: ' . (int) $case_index++ . ( '' !== $cell['area'] ? '; --saito-ce-area: ' . $cell['area'] : '' );
					?>
					<button
						type="button"
						class="saito-ce-case"
						style="<?php echo esc_attr( $case_style ); ?>"
						data-day="<?php echo esc_attr( $day ); ?>"<?php echo '' !== $cell['shape'] ? ' data-shape="' . esc_attr( $cell['shape'] ) . '"' : ''; ?>
						data-unlock-date="<?php echo esc_attr( $unlock_date ); ?>"
						data-locked-message="<?php echo esc_attr( self::get_locked_message( $id, $unlock_date ) ); ?>"
						aria-label="<?php echo esc_attr( sprintf( /* translators: %d day number */ __( 'Case %d', 'saito-calendar-event' ), $day ) ); ?>"
					>
						<span class="saito-ce-case-lock" aria-hidden="true"></span>
						<span class="saito-ce-case-day"><?php echo esc_html( $day ); ?></span>
						<span class="saito-ce-case-countdown"></span>
					</button>
				<?php endforeach; ?>
			</div>

			<?php if ( count( $valid_cases ) > 1 ) : ?>
				<div class="saito-ce-progress" data-total="<?php echo esc_attr( count( $valid_cases ) ); ?>">
					<div class="saito-ce-progress-track">
						<div class="saito-ce-progress-bar"></div>
					</div>
					<p class="saito-ce-progress-label"></p>
				</div>
			<?php endif; ?>

			<div class="saito-ce-modal" hidden>
				<div class="saito-ce-modal-content" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Récompense du jour', 'saito-calendar-event' ); ?>">
					<button type="button" class="saito-ce-modal-close" aria-label="<?php esc_attr_e( 'Fermer', 'saito-calendar-event' ); ?>">&times;</button>
					<div class="saito-ce-modal-body"></div>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Place fixe de chaque jour d'un calendrier 1→24 sur une grille de 6×6
	 * (habillages mosaïque, sur grand écran) : ligne, colonne, hauteur et
	 * largeur en cellules. Numéros volontairement mélangés comme sur une
	 * vraie boîte ; le 1 (ouverture) et le 24 (veille de Noël, au centre)
	 * sont les deux grandes portes. Couvre exactement les 36 cellules
	 * (vérifié par tests/test-mosaic-layout.php).
	 */
	const MOSAIC_LAYOUT = array(
		1  => array( 1, 1, 2, 2 ),
		17 => array( 1, 3, 1, 1 ),
		8  => array( 1, 4, 1, 2 ),
		12 => array( 1, 6, 2, 1 ),
		5  => array( 2, 3, 1, 1 ),
		21 => array( 2, 4, 1, 1 ),
		3  => array( 2, 5, 1, 1 ),
		19 => array( 3, 1, 2, 1 ),
		10 => array( 3, 2, 1, 1 ),
		24 => array( 3, 3, 2, 2 ),
		14 => array( 3, 5, 1, 2 ),
		2  => array( 4, 2, 1, 1 ),
		7  => array( 4, 5, 1, 1 ),
		22 => array( 4, 6, 1, 1 ),
		6  => array( 5, 1, 1, 2 ),
		15 => array( 5, 3, 1, 1 ),
		9  => array( 5, 4, 1, 1 ),
		20 => array( 5, 5, 2, 1 ),
		4  => array( 5, 6, 1, 1 ),
		13 => array( 6, 1, 1, 1 ),
		23 => array( 6, 2, 1, 1 ),
		11 => array( 6, 3, 1, 1 ),
		18 => array( 6, 4, 1, 1 ),
		16 => array( 6, 6, 1, 1 ),
	);

	/**
	 * Habillages à portes de tailles variées.
	 */
	public static function is_mosaic_style( $style ) {
		return in_array( $style, array( 'sparkle', 'floral-blue', 'floral-gold', 'floral-duo' ), true );
	}

	/**
	 * Feuille de style propre à un habillage (assets/css/styles/*.css),
	 * chargée en plus de frontend.css seulement quand il est utilisé.
	 */
	public static function style_sheet( $style ) {
		if ( 'sparkle' === $style ) {
			return 'sparkle';
		}
		if ( in_array( $style, array( 'floral-blue', 'floral-gold', 'floral-duo' ), true ) ) {
			return 'floral';
		}
		return '';
	}

	/**
	 * Forme et place de chaque jour, dans l'ordre d'affichage.
	 *
	 * Calendrier 1→24 : disposition fixe MOSAIC_LAYOUT, triée ligne par
	 * ligne. Sinon (autre nombre de cases) : motif répété toutes les 12
	 * cases (une grande porte, deux hautes, une large), la dernière case en
	 * grande porte, placement automatique par la grille.
	 *
	 * @param int[] $days Jours des cases.
	 * @return array jour => array( 'shape' => ''|'big'|'tall'|'wide', 'area' => grid-area CSS ou '' ).
	 */
	public static function mosaic_arrangement( $days ) {
		$days = array_values( array_unique( array_filter( array_map( 'intval', (array) $days ) ) ) );
		sort( $days );
		$result = array();

		if ( range( 1, 24 ) === $days ) {
			$layout = self::MOSAIC_LAYOUT;
			uasort(
				$layout,
				function ( $a, $b ) {
					return array( $a[0], $a[1] ) <=> array( $b[0], $b[1] );
				}
			);
			foreach ( $layout as $day => $cell ) {
				list( $row, $col, $rows, $cols ) = $cell;
				$shape = '';
				if ( 2 === $rows && 2 === $cols ) {
					$shape = 'big';
				} elseif ( 2 === $rows ) {
					$shape = 'tall';
				} elseif ( 2 === $cols ) {
					$shape = 'wide';
				}
				$result[ $day ] = array(
					'shape' => $shape,
					'area'  => $row . ' / ' . $col . ' / span ' . $rows . ' / span ' . $cols,
				);
			}
			return $result;
		}

		$count = count( $days );
		foreach ( $days as $i => $day ) {
			$position = $i % 12;
			$shape    = '';
			if ( 0 === $position || $count - 1 === $i ) {
				$shape = 'big';
			} elseif ( 3 === $position || 8 === $position ) {
				$shape = 'tall';
			} elseif ( 6 === $position ) {
				$shape = 'wide';
			}
			$result[ $day ] = array(
				'shape' => $shape,
				'area'  => '',
			);
		}
		return $result;
	}

	/**
	 * Textes de l'en-tête affiché au-dessus de la grille des habillages
	 * mosaïque (masquable dans l'onglet Apparence) : titre du calendrier et
	 * sous-titre optionnel.
	 *
	 * @return array title / sub (vides = non affichés).
	 */
	public static function header_texts( $calendar_id ) {
		return array(
			'title' => get_the_title( $calendar_id ),
			'sub'   => (string) get_post_meta( $calendar_id, SAITO_CE_SUBTITLE_META, true ),
		);
	}

	/**
	 * Balisage de l'en-tête de marque (vide si aucun texte).
	 */
	public static function render_header( $texts ) {
		if ( ! $texts ) {
			return '';
		}
		$html = '<div class="saito-ce-header">';
		foreach ( array( 'title', 'sub' ) as $part ) {
			if ( ! empty( $texts[ $part ] ) ) {
				$html .= '<span class="saito-ce-header-' . $part . '">' . esc_html( $texts[ $part ] ) . '</span>';
			}
		}
		return $html . '</div>';
	}

	/**
	 * Habillage enregistré, "noel" par défaut.
	 */
	public static function normalize_style( $style ) {
		$style = (string) $style;
		return '' !== $style ? $style : 'noel';
	}

	/**
	 * Programmation de la publication d'un calendrier (meta `_saito_ce_schedule`) :
	 * dates de début/fin d'affichage (Y-m-d, incluses, vides = pas de limite)
	 * et messages montrés aux visiteurs avant/après.
	 */
	public static function get_schedule( $calendar_id ) {
		$schedule = get_post_meta( $calendar_id, SAITO_CE_SCHEDULE_META, true );
		return wp_parse_args(
			is_array( $schedule ) ? $schedule : array(),
			array(
				'start'          => '',
				'end'            => '',
				'before_message' => '',
				'after_message'  => '',
			)
		);
	}

	/**
	 * Où en est la période d'affichage aujourd'hui (fuseau du site) :
	 * "before" avant le début, "after" après la fin (date de fin incluse,
	 * comme une date de déblocage de case), "live" sinon.
	 *
	 * @return string
	 */
	public static function get_schedule_state( $schedule ) {
		$schedule = is_array( $schedule ) ? $schedule : array();
		$today    = self::today_midnight();
		$start    = self::get_unlock_datetime( isset( $schedule['start'] ) ? $schedule['start'] : '' );
		$end      = self::get_unlock_datetime( isset( $schedule['end'] ) ? $schedule['end'] : '' );

		if ( $start && $today < $start ) {
			return 'before';
		}
		if ( $end && $today > $end ) {
			return 'after';
		}
		return 'live';
	}

	/**
	 * Message montré aux visiteurs hors période d'affichage. `%s` y est
	 * remplacé par la date de début (avant) ou de fin (après).
	 */
	private static function get_schedule_message( $schedule, $state ) {
		$is_before = 'before' === $state;
		$message   = trim( (string) ( $is_before ? $schedule['before_message'] : $schedule['after_message'] ) );
		if ( '' === $message ) {
			return '';
		}
		$date = self::format_unlock_date_for_display( $is_before ? $schedule['start'] : $schedule['end'] );
		return str_replace( '%s', $date, $message );
	}

	/**
	 * Traduit les couleurs/rayon custom d'un calendrier (meta `_saito_ce_colors`)
	 * en déclarations CSS `--variable:valeur`, utilisées à la fois pour
	 * l'affichage public et pour l'aperçu en direct dans l'admin.
	 *
	 * @return string[]
	 */
	public static function build_style_declarations( $colors ) {
		$colors  = is_array( $colors ) ? $colors : array();
		$vars    = array();
		$var_map = array(
			'accent'      => '--saito-ce-accent',
			'accent_dark' => '--saito-ce-accent-dark',
			'bg'          => '--saito-ce-bg',
			'text'        => '--saito-ce-text',
			'border'      => '--saito-ce-border',
		);
		foreach ( $var_map as $key => $css_var ) {
			if ( ! empty( $colors[ $key ] ) ) {
				$vars[] = $css_var . ':' . $colors[ $key ];
			}
		}
		if ( ! empty( $colors['radius'] ) ) {
			$vars[] = '--saito-ce-radius:' . absint( $colors['radius'] ) . 'px';
		}
		return $vars;
	}

	/**
	 * Message affiché quand une case n'est pas encore ouvrable : personnalisable
	 * par calendrier (réglage BO), avec %s remplacé par la date de déblocage
	 * formatée selon les réglages du site. Sert à la fois de titre (survol) sur
	 * la case et de message d'erreur renvoyé par l'AJAX si elle est cliquée.
	 */
	private static function get_locked_message( $calendar_id, $unlock_date ) {
		$message = get_post_meta( $calendar_id, SAITO_CE_LOCKED_MESSAGE_META, true );
		if ( '' === trim( (string) $message ) ) {
			/* translators: %s: date de déblocage de la case. */
			$message = __( 'Cette case n’est pas encore ouvrable. Reviens le %s !', 'saito-calendar-event' );
		}
		$date = self::format_unlock_date_for_display( $unlock_date );
		// Un message personnalisé sans %s perdait la date : on la rajoute plutôt
		// que d'afficher une phrase incomplète.
		if ( false === strpos( $message, '%s' ) ) {
			return trim( $message ) . ' ' . $date;
		}
		// str_replace plutôt que sprintf() : ce message est un texte libre saisi
		// par un admin (ex. "-10% de réduction"), et un "%" isolé ferait planter
		// sprintf() (ValueError "Unknown format specifier" en PHP 8+).
		return str_replace( '%s', $date, $message );
	}

	/**
	 * Message affiché à la place du bouton "Ajouter au panier" quand la date
	 * de déblocage d'une case est passée (coupon du jour, non réclamable
	 * après coup) : personnalisable comme les autres textes du calendrier
	 * (onglet Réglages), avec repli sur un message par défaut.
	 */
	private static function get_expired_message( $calendar_id ) {
		$texts   = get_post_meta( $calendar_id, SAITO_CE_TEXTS_META, true );
		$texts   = is_array( $texts ) ? $texts : array();
		$message = isset( $texts['expired'] ) ? trim( (string) $texts['expired'] ) : '';
		if ( '' === $message ) {
			$message = __( 'Cette offre n’est plus disponible : la date est passée.', 'saito-calendar-event' );
		}
		return $message;
	}

	/**
	 * Date de déblocage formatée pour l'affichage (réglage "Format de date" du
	 * site), plutôt que la valeur brute "Y-m-d" stockée pour la comparaison.
	 */
	private static function format_unlock_date_for_display( $unlock_date ) {
		$unlock = self::get_unlock_datetime( $unlock_date );
		if ( ! $unlock ) {
			return trim( (string) $unlock_date );
		}
		// Format de repli si le réglage "Format de date" du site est vide : sans
		// ça, la date devenait une chaîne vide et le message affichait "Reviens le !".
		$format = get_option( 'date_format' );
		if ( '' === trim( (string) $format ) ) {
			$format = 'j F Y';
		}
		return wp_date( $format, $unlock->getTimestamp(), wp_timezone() );
	}

	/**
	 * Textes personnalisables par calendrier (bouton d'ajout au panier,
	 * messages de succès/erreur) : rendus en attributs `data-text-*` sur le
	 * conteneur, lus par le JS avec repli sur les valeurs par défaut. Comme
	 * pour `get_locked_message()`, ceci évite de dépendre d'un objet localisé
	 * global qui serait forcément identique pour tous les calendriers d'une
	 * même page.
	 */
	private static function get_custom_text_attributes( $calendar_id ) {
		$texts = get_post_meta( $calendar_id, SAITO_CE_TEXTS_META, true );
		$texts = is_array( $texts ) ? $texts : array();

		$attr_map = array(
			'apply'         => 'data-text-apply',
			'applying'      => 'data-text-applying',
			'appliedButton' => 'data-text-applied-button',
			'applied'       => 'data-text-applied',
			'error'         => 'data-text-error',
			'expired'       => 'data-text-expired',
			'expiresToday'  => 'data-text-expires-today',
			'today'         => 'data-text-today',
		);

		$html = '';
		foreach ( $attr_map as $key => $attr ) {
			if ( ! empty( $texts[ $key ] ) ) {
				$html .= ' ' . $attr . '="' . esc_attr( $texts[ $key ] ) . '"';
			}
		}
		return $html;
	}

	public static function maybe_enqueue_frontend() {
		if ( is_singular() ) {
			global $post;
			if ( $post && ( has_shortcode( $post->post_content, 'saito_calendar_event' ) || has_block( 'saito-calendar-event/calendar', $post ) ) ) {
				self::enqueue_frontend_assets();
				// Feuille de style de l'habillage de chaque calendrier de la page,
				// chargée dès l'en-tête (pas de "flash" sans style). Un calendrier
				// non détecté ici (widget, modèle…) la charge à son rendu.
				foreach ( self::find_calendar_ids( $post ) as $calendar_id ) {
					self::enqueue_frontend_assets( self::normalize_style( get_post_meta( $calendar_id, SAITO_CE_STYLE_META, true ) ) );
				}
			}
		}
	}

	/**
	 * Identifiants des calendriers insérés dans un contenu (shortcode et bloc).
	 *
	 * @return int[]
	 */
	private static function find_calendar_ids( $post ) {
		$ids = array();
		if ( preg_match_all( '/' . get_shortcode_regex( array( 'saito_calendar_event' ) ) . '/', $post->post_content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$atts = shortcode_parse_atts( $match[3] );
				if ( is_array( $atts ) && ! empty( $atts['id'] ) ) {
					$ids[] = (int) $atts['id'];
				}
			}
		}
		$walk = function ( $blocks ) use ( &$walk, &$ids ) {
			foreach ( $blocks as $block ) {
				if ( 'saito-calendar-event/calendar' === $block['blockName'] && ! empty( $block['attrs']['calendarId'] ) ) {
					$ids[] = (int) $block['attrs']['calendarId'];
				}
				if ( ! empty( $block['innerBlocks'] ) ) {
					$walk( $block['innerBlocks'] );
				}
			}
		};
		if ( has_blocks( $post->post_content ) ) {
			$walk( parse_blocks( $post->post_content ) );
		}
		return array_unique( $ids );
	}

	private static function enqueue_frontend_assets( $style = '' ) {
		self::enqueue_base_assets();
		$sheet = self::style_sheet( $style );
		if ( $sheet ) {
			wp_enqueue_style( 'saito-ce-style-' . $sheet, SAITO_CE_URL . 'assets/css/styles/' . $sheet . '.css', array( 'saito-ce-frontend' ), SAITO_CE_VERSION );
		}
	}

	/**
	 * Feuille de base, scripts et données du calendrier (une seule fois).
	 *
	 * Test sur "registered" et non "enqueued" : wp_style_is( 'enqueued' )
	 * répond vrai dès qu'un style en file DÉPEND de cette poignée, même si
	 * elle n'a jamais été enregistrée. Avec un thème à blocs, le shortcode
	 * est rendu avant wp_enqueue_scripts : la feuille d'habillage (qui
	 * dépend de saito-ce-frontend) passait en premier, et le CSS de base et
	 * le JS n'étaient alors jamais chargés.
	 */
	private static function enqueue_base_assets() {
		if ( wp_style_is( 'saito-ce-frontend', 'registered' ) ) {
			return;
		}
		wp_enqueue_style( 'saito-ce-frontend', SAITO_CE_URL . 'assets/css/frontend.css', array(), SAITO_CE_VERSION );

		// Bibliothèques tierces embarquées (pas de CDN externe : évite une
		// dépendance réseau tierce et une fuite d'IP visiteur vers un domaine
		// externe, cf. RGPD). Confettis (canvas-confetti) et effet de bascule
		// 3D au survol (vanilla-tilt.js), toutes deux MIT, ~10 Ko chacune.
		wp_enqueue_script( 'saito-ce-confetti', SAITO_CE_URL . 'assets/js/vendor/confetti.browser.js', array(), SAITO_CE_VERSION, true );
		wp_enqueue_script( 'saito-ce-vanilla-tilt', SAITO_CE_URL . 'assets/js/vendor/vanilla-tilt.min.js', array(), SAITO_CE_VERSION, true );

		wp_enqueue_script( 'saito-ce-frontend', SAITO_CE_URL . 'assets/js/frontend.js', array( 'saito-ce-confetti', 'saito-ce-vanilla-tilt' ), SAITO_CE_VERSION, true );
		wp_localize_script(
			'saito-ce-frontend',
			'saitoCeFrontend',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
				'today'   => current_time( 'Y-m-d' ),
				'i18n'    => array(
					'error'         => __( 'Une erreur est survenue, réessaie.', 'saito-calendar-event' ),
					'applied'       => __( 'Code ajouté à ton panier !', 'saito-calendar-event' ),
					'appliedButton' => __( 'Code ajouté ✓', 'saito-calendar-event' ),
					'applying'      => __( 'Ajout en cours…', 'saito-calendar-event' ),
					'apply'         => __( 'Ajouter la réduction à mon panier', 'saito-calendar-event' ),
					'expired'       => __( 'Cette offre n’est plus disponible : la date est passée.', 'saito-calendar-event' ),
					'expiresToday'  => __( 'Dernier jour !', 'saito-calendar-event' ),
					'today'         => __( 'Aujourd’hui', 'saito-calendar-event' ),
					'copy'        => __( 'Copier', 'saito-calendar-event' ),
					'copied'        => __( 'Copié !', 'saito-calendar-event' ),
					'copyLabel'     => __( 'Copier le code promo', 'saito-calendar-event' ),
					/* translators: %1$s: nombre de cases ouvertes, %2$s: nombre total de cases. */
					'progress'      => __( '%1$s / %2$s cases ouvertes', 'saito-calendar-event' ),
					/* translators: %s: nombre de jours restants. */
					'countdown'     => __( 'J-%s', 'saito-calendar-event' ),
				),
			)
		);
	}

	/* ------------------------------------------------------------------ *
	 * AJAX
	 * ------------------------------------------------------------------ */

	/**
	 * Retrouve la case d'un calendrier pour un jour donné, en validant au
	 * passage la requête (nonce, existence du calendrier).
	 *
	 * @return array|WP_Error
	 */
	private static function get_validated_case() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$calendar_id = isset( $_POST['calendar_id'] ) ? absint( $_POST['calendar_id'] ) : 0;
		$day         = isset( $_POST['day'] ) ? absint( $_POST['day'] ) : 0;

		$calendar = $calendar_id ? get_post( $calendar_id ) : null;
		if ( ! $calendar || SAITO_CE_POST_TYPE !== $calendar->post_type ) {
			return new WP_Error( 'invalid_calendar', __( 'Calendrier introuvable.', 'saito-calendar-event' ) );
		}

		// Hors période d'affichage, le calendrier n'est pas rendu pour les
		// visiteurs : on refuse aussi ses requêtes AJAX (requête forgée, ou
		// page restée ouverte après la date de fin). Les éditeurs, qui le
		// voient pour le vérifier, peuvent l'utiliser normalement.
		if ( 'live' !== self::get_schedule_state( self::get_schedule( $calendar_id ) ) && ! current_user_can( 'edit_post', $calendar_id ) ) {
			return new WP_Error( 'not_scheduled', __( 'Ce calendrier n’est pas disponible pour le moment.', 'saito-calendar-event' ) );
		}

		$cases = get_post_meta( $calendar_id, SAITO_CE_CASES_META, true );
		$cases = is_array( $cases ) ? $cases : array();

		foreach ( $cases as $case ) {
			if ( isset( $case['day'] ) && (int) $case['day'] === $day ) {
				return $case;
			}
		}

		return new WP_Error( 'invalid_day', __( 'Case introuvable.', 'saito-calendar-event' ) );
	}

	/**
	 * Date de déblocage d'une case normalisée en DateTime à minuit (fuseau du
	 * site), ou null si absente/invalide. Centralise l'analyse du format pour
	 * `is_unlocked()` et `is_case_expired()`, qui n'ont ensuite plus qu'à
	 * comparer à "aujourd'hui".
	 */
	private static function get_unlock_datetime( $unlock_date ) {
		$unlock_date = trim( (string) $unlock_date );
		if ( '' === $unlock_date ) {
			return null;
		}

		$tz = wp_timezone();

		// Le champ <input type="date"> soumet toujours du Y-m-d, mais on
		// accepte aussi un format saisi/importé différent (ex. 17/09/2026)
		// plutôt que de bloquer silencieusement la case par une comparaison
		// de texte qui ne matcherait jamais.
		$unlock = DateTime::createFromFormat( 'Y-m-d', $unlock_date, $tz );
		if ( ! $unlock ) {
			$timestamp = strtotime( $unlock_date );
			if ( ! $timestamp ) {
				return null;
			}
			$unlock = new DateTime( '@' . $timestamp );
			$unlock->setTimezone( $tz );
		}
		$unlock->setTime( 0, 0, 0 );

		return $unlock;
	}

	private static function today_midnight() {
		$today = new DateTime( 'now', wp_timezone() );
		$today->setTime( 0, 0, 0 );
		return $today;
	}

	/**
	 * true si la date de déblocage de la case est atteinte (fuseau du site).
	 */
	private static function is_unlocked( $case ) {
		$unlock = self::get_unlock_datetime( isset( $case['unlock_date'] ) ? $case['unlock_date'] : '' );
		if ( ! $unlock ) {
			return false; // Pas de date configurée (ou invalide) = jamais déblocable, par sécurité.
		}

		return self::today_midnight() >= $unlock;
	}

	/**
	 * true si la date de déblocage est strictement passée (pas juste
	 * "aujourd'hui") : une case ne reste ouvrable que le jour exact de son
	 * déblocage, ni avant ni après — la revoir le lendemain affiche l'erreur
	 * "la date est passée" plutôt que de l'ouvrir (`ajax_open_case`), et son
	 * code promo n'est de toute façon plus proposable au panier
	 * (`ajax_apply_coupon`, défense en profondeur si une requête AJAX est
	 * rejouée après coup depuis une modale restée ouverte).
	 */
	private static function is_case_expired( $case ) {
		$unlock = self::get_unlock_datetime( isset( $case['unlock_date'] ) ? $case['unlock_date'] : '' );
		if ( ! $unlock ) {
			return false;
		}

		return self::today_midnight() > $unlock;
	}

	/**
	 * Limite le nombre d'appels par IP et par minute sur un endpoint AJAX
	 * donné (`open_case` / `apply_coupon`), pour éviter qu'un script puisse
	 * bourrer ces requêtes (brute-force de codes, sondage de dates, charge
	 * inutile). Volontairement simple (transient), suffisant pour la charge
	 * d'un calendrier promotionnel.
	 */
	private static function check_rate_limit( $bucket ) {
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key   = 'saito_ce_rl_' . $bucket . '_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= 20 ) {
			return false;
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Incrémente le compteur d'ouvertures d'un calendrier (tous visiteurs
	 * confondus, affiché dans le BO). Écriture SQL directe pour rester
	 * correct sous requêtes concurrentes : `update_post_meta()` seul ferait
	 * un "lire puis écrire" non atomique, avec un risque de perdre des
	 * incréments en cas d'ouvertures simultanées.
	 */
	private static function increment_open_count( $calendar_id ) {
		global $wpdb;
		// Incrément atomique (cf. ci-dessus) : pas d'équivalent dans l'API des métas,
		// et aucune mise en cache souhaitable pour un compteur.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value = meta_value + 1 WHERE post_id = %d AND meta_key = %s",
				$calendar_id,
				SAITO_CE_OPEN_COUNT_META
			)
		);
		if ( ! $updated ) {
			add_post_meta( $calendar_id, SAITO_CE_OPEN_COUNT_META, 1, true );
		}
	}

	public static function ajax_open_case() {
		if ( ! self::check_rate_limit( 'open_case' ) ) {
			wp_send_json_error( array( 'message' => __( 'Trop de tentatives, réessaie dans une minute.', 'saito-calendar-event' ) ), 429 );
		}

		$case = self::get_validated_case();
		if ( is_wp_error( $case ) ) {
			wp_send_json_error( array( 'message' => $case->get_error_message() ), 404 );
		}

		$calendar_id = isset( $_POST['calendar_id'] ) ? absint( $_POST['calendar_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- jeton vérifié par get_validated_case() (check_ajax_referer).

		if ( ! self::is_unlocked( $case ) ) {
			wp_send_json_error(
				array(
					'message' => self::get_locked_message( $calendar_id, isset( $case['unlock_date'] ) ? $case['unlock_date'] : '' ),
				),
				403
			);
		}

		// Une case n'est ouvrable que le jour exact de son déblocage : la
		// revoir le lendemain affiche l'erreur "la date est passée" plutôt
		// que de l'ouvrir, au lieu de rester consultable indéfiniment.
		if ( self::is_case_expired( $case ) ) {
			wp_send_json_error( array( 'message' => self::get_expired_message( $calendar_id ) ), 403 );
		}

		self::increment_open_count( $calendar_id );

		// Arrivé ici, la case est forcément datée d'aujourd'hui (ni avant ni
		// après les deux gardes ci-dessus) : son code promo est donc toujours
		// proposable au panier tant qu'il existe. `is_case_expired()` est
		// quand même rappelé ici (plutôt qu'un simple `false`) pour que ces
		// deux champs restent corrects si la logique de garde évolue un jour.
		$has_coupon     = ! empty( $case['coupon_code'] ) && saito_ce_is_woocommerce_active();
		$coupon_expired = $has_coupon && self::is_case_expired( $case );

		wp_send_json_success(
			array(
				'title'          => isset( $case['title'] ) ? $case['title'] : '',
				'description'    => isset( $case['description'] ) ? $case['description'] : '',
				'image_url'      => ! empty( $case['image_id'] ) ? wp_get_attachment_image_url( (int) $case['image_id'], 'medium' ) : '',
				'coupon_code'    => isset( $case['coupon_code'] ) ? $case['coupon_code'] : '',
				'can_apply'      => $has_coupon && ! $coupon_expired,
				'coupon_expired' => $coupon_expired,
				'product'        => self::get_case_product_data( $case ),
			)
		);
	}

	/**
	 * Fiche produit condensée (nom, lien, image, prix) à afficher dans la
	 * modale plutôt qu'un simple code promo brut : plus vendeur, et permet
	 * au visiteur de voir tout de suite à quel parfum la case correspond.
	 *
	 * @return array|null
	 */
	private static function get_case_product_data( $case ) {
		if ( empty( $case['product_id'] ) || ! saito_ce_is_woocommerce_active() ) {
			return null;
		}

		$product = wc_get_product( (int) $case['product_id'] );
		if ( ! $product || ! $product->exists() ) {
			return null;
		}

		$image_id = $product->get_image_id();

		return array(
			'name'  => $product->get_name(),
			'url'   => get_permalink( $product->get_id() ),
			'image' => $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '',
			'price' => html_entity_decode( wp_strip_all_tags( wc_price( $product->get_price() ) ), ENT_QUOTES, 'UTF-8' ),
		);
	}

	public static function ajax_apply_coupon() {
		if ( ! self::check_rate_limit( 'apply_coupon' ) ) {
			wp_send_json_error( array( 'message' => __( 'Trop de tentatives, réessaie dans une minute.', 'saito-calendar-event' ) ), 429 );
		}

		$case = self::get_validated_case();
		if ( is_wp_error( $case ) ) {
			wp_send_json_error( array( 'message' => $case->get_error_message() ), 404 );
		}

		if ( ! self::is_unlocked( $case ) ) {
			$calendar_id = isset( $_POST['calendar_id'] ) ? absint( $_POST['calendar_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- jeton vérifié par get_validated_case() (check_ajax_referer).
			wp_send_json_error(
				array( 'message' => self::get_locked_message( $calendar_id, isset( $case['unlock_date'] ) ? $case['unlock_date'] : '' ) ),
				403
			);
		}

		// Défense en profondeur : ajax_open_case() rejette déjà l'ouverture
		// d'une case expirée, mais une requête "Ajouter au panier" rejouée
		// depuis une modale restée ouverte (cache local, onglet laissé
		// ouvert après minuit) pourrait encore atteindre cet endpoint — on
		// revérifie donc aussi ici.
		if ( self::is_case_expired( $case ) ) {
			$calendar_id = isset( $_POST['calendar_id'] ) ? absint( $_POST['calendar_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- jeton vérifié par get_validated_case() (check_ajax_referer).
			wp_send_json_error( array( 'message' => self::get_expired_message( $calendar_id ) ), 403 );
		}

		$coupon_code = isset( $case['coupon_code'] ) ? trim( $case['coupon_code'] ) : '';
		if ( '' === $coupon_code ) {
			wp_send_json_error( array( 'message' => __( 'Aucun code promo associé à cette case.', 'saito-calendar-event' ) ) );
		}

		if ( ! saito_ce_is_woocommerce_active() ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce est requis pour appliquer ce code.', 'saito-calendar-event' ) ) );
		}

		if ( function_exists( 'wc_load_cart' ) && ( ! WC()->cart || ! WC()->session ) ) {
			wc_load_cart();
		}
		if ( ! WC()->cart ) {
			wp_send_json_error( array( 'message' => __( 'Panier indisponible pour le moment.', 'saito-calendar-event' ) ) );
		}

		if ( WC()->cart->has_discount( $coupon_code ) ) {
			wp_send_json_success( array( 'message' => __( 'Ce code est déjà appliqué à ton panier.', 'saito-calendar-event' ) ) );
		}

		wc_clear_notices();
		$applied = WC()->cart->apply_coupon( $coupon_code );

		if ( ! $applied ) {
			$messages = array();
			foreach ( wc_get_notices( 'error' ) as $notice ) {
				// Les messages de WooCommerce contiennent du HTML (wc_price() pour un
				// minimum de dépense, &nbsp;, &euro;…) : la modale les affiche en
				// texte brut (textContent), d'où le décodage des entités.
				$messages[] = html_entity_decode( wp_strip_all_tags( $notice['notice'] ), ENT_QUOTES, 'UTF-8' );
			}
			wc_clear_notices();
			wp_send_json_error(
				array(
					'message' => $messages ? implode( ' ', $messages ) : __( 'Ce code n’a pas pu être appliqué.', 'saito-calendar-event' ),
				)
			);
		}

		wc_clear_notices();
		wp_send_json_success( array( 'message' => __( 'Code ajouté à ton panier !', 'saito-calendar-event' ) ) );
	}
}
