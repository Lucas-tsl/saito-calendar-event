<?php
/**
 * Modèles de calendrier : cases datées, textes, habillage et programmation
 * prêts à l'emploi (Black Week, calendrier de l'Avent), appliqués à un
 * calendrier depuis l'onglet Cases. Il ne reste qu'à renseigner les codes
 * promo et les produits.
 *
 * @package Saito_Calendar_Event
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Saito_CE_Templates {

	const ACTION = 'saito_ce_apply_template';

	public static function init() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_apply' ) );
	}

	/**
	 * Black Friday d'une année : le lendemain du 4ᵉ jeudi de novembre
	 * (Thanksgiving aux États-Unis).
	 *
	 * @param int $year Année.
	 * @return DateTimeImmutable
	 */
	public static function black_friday( $year ) {
		$first        = new DateTimeImmutable( sprintf( '%04d-11-01', (int) $year ) );
		$to_thursday  = ( 4 - (int) $first->format( 'N' ) + 7 ) % 7;
		$thanksgiving = $first->modify( '+' . ( $to_thursday + 21 ) . ' days' );
		return $thanksgiving->modify( '+1 day' );
	}

	/**
	 * Modèles disponibles (filtre `saito_ce_templates` pour en ajouter).
	 *
	 * Chaque modèle : 'label', 'description' et 'build' (callable recevant
	 * l'année et renvoyant les réglages, cf. black_week()).
	 *
	 * @return array slug => modèle.
	 */
	public static function templates() {
		$templates = array(
			'black-week' => array(
				'label'       => __( 'Black Week (8 jours, du lundi au Cyber Monday)', 'saito-calendar-event' ),
				'description' => __( 'Une case par jour de la semaine du Black Friday, Black Friday et Cyber Monday mis en avant, habillage Black Week.', 'saito-calendar-event' ),
				'build'       => array( __CLASS__, 'black_week' ),
			),
			'advent'     => array(
				'label'       => __( 'Calendrier de l’Avent (24 jours, du 1er au 24 décembre)', 'saito-calendar-event' ),
				'description' => __( 'Une case par jour du 1er au 24 décembre, habillage Noël.', 'saito-calendar-event' ),
				'build'       => array( __CLASS__, 'advent' ),
			),
		);
		return (array) apply_filters( 'saito_ce_templates', $templates );
	}

	/**
	 * Black Week : du lundi précédant le Black Friday au Cyber Monday (8 jours).
	 *
	 * @param int $year Année.
	 * @return array Réglages du calendrier (cf. apply()).
	 */
	public static function black_week( $year ) {
		$friday = self::black_friday( $year );
		$monday = $friday->modify( '-4 days' );
		$titles = array(
			1 => __( 'Lancement de la Black Week', 'saito-calendar-event' ),
			2 => __( 'Offre du mardi', 'saito-calendar-event' ),
			3 => __( 'Offre du mercredi', 'saito-calendar-event' ),
			4 => __( 'Offre du jeudi', 'saito-calendar-event' ),
			5 => __( 'Black Friday', 'saito-calendar-event' ),
			6 => __( 'Offre du samedi', 'saito-calendar-event' ),
			7 => __( 'Offre du dimanche', 'saito-calendar-event' ),
			8 => __( 'Cyber Monday', 'saito-calendar-event' ),
		);
		$descriptions = array(
			1 => __( 'La Black Week commence : une nouvelle offre chaque jour jusqu’au Cyber Monday.', 'saito-calendar-event' ),
			5 => __( 'Le grand jour : notre plus belle offre de la semaine.', 'saito-calendar-event' ),
			8 => __( 'Dernière chance : l’offre du Cyber Monday, valable aujourd’hui seulement.', 'saito-calendar-event' ),
		);
		$cases = array();
		for ( $day = 1; $day <= 8; $day++ ) {
			$cases[] = array(
				'day'         => $day,
				'unlock_date' => $monday->modify( '+' . ( $day - 1 ) . ' days' )->format( 'Y-m-d' ),
				'title'       => $titles[ $day ],
				'description' => isset( $descriptions[ $day ] ) ? $descriptions[ $day ] : __( 'Une offre à saisir aujourd’hui seulement.', 'saito-calendar-event' ),
			);
		}
		return array(
			/* translators: %d: année. */
			'title'    => sprintf( __( 'Black Week %d', 'saito-calendar-event' ), (int) $year ),
			'subtitle' => __( 'Une offre par jour', 'saito-calendar-event' ),
			'style'    => 'black-week',
			'cases'    => $cases,
			'schedule' => array(
				'start'          => $monday->format( 'Y-m-d' ),
				'end'            => $monday->modify( '+7 days' )->format( 'Y-m-d' ),
				/* translators: %s: date de début. */
				'before_message' => __( 'La Black Week commence le %s : revenez chaque jour pour une nouvelle offre !', 'saito-calendar-event' ),
				'after_message'  => __( 'La Black Week est terminée. Merci et à l’année prochaine !', 'saito-calendar-event' ),
			),
		);
	}

	/**
	 * Calendrier de l'Avent : du 1er au 24 décembre (24 jours).
	 *
	 * @param int $year Année.
	 * @return array Réglages du calendrier (cf. apply()).
	 */
	public static function advent( $year ) {
		$cases = array();
		for ( $day = 1; $day <= 24; $day++ ) {
			$cases[] = array(
				'day'         => $day,
				'unlock_date' => sprintf( '%04d-12-%02d', (int) $year, $day ),
				/* translators: %d: numéro du jour. */
				'title'       => sprintf( __( 'Jour %d', 'saito-calendar-event' ), $day ),
				'description' => '',
			);
		}
		return array(
			/* translators: %d: année. */
			'title'    => sprintf( __( 'Calendrier de l’Avent %d', 'saito-calendar-event' ), (int) $year ),
			'subtitle' => __( 'Une surprise chaque jour', 'saito-calendar-event' ),
			'style'    => 'noel',
			'cases'    => $cases,
			'schedule' => array(
				'start'          => sprintf( '%04d-12-01', (int) $year ),
				'end'            => sprintf( '%04d-12-24', (int) $year ),
				/* translators: %s: date de début. */
				'before_message' => __( 'Le calendrier de l’Avent ouvre ses portes le %s, à très vite !', 'saito-calendar-event' ),
				'after_message'  => __( 'Le calendrier de l’Avent est terminé. Joyeuses fêtes !', 'saito-calendar-event' ),
			),
		);
	}

	/**
	 * Construit les réglages d'un modèle pour une année.
	 *
	 * @return array|null Réglages, ou null si le modèle n'existe pas.
	 */
	public static function build( $slug, $year ) {
		$templates = self::templates();
		if ( ! isset( $templates[ $slug ]['build'] ) || ! is_callable( $templates[ $slug ]['build'] ) ) {
			return null;
		}
		return (array) call_user_func( $templates[ $slug ]['build'], (int) $year );
	}

	/**
	 * Applique un modèle à un calendrier : remplace les cases, l'habillage,
	 * le sous-titre et la programmation. Les codes promo, produits et
	 * images sont vides : à renseigner ensuite. Le titre n'est remplacé que
	 * s'il est vide ou provisoire (calendrier tout juste créé).
	 *
	 * @return bool
	 */
	public static function apply( $post_id, $slug, $year ) {
		$settings = self::build( $slug, $year );
		if ( ! $settings ) {
			return false;
		}
		$cases = array();
		foreach ( (array) $settings['cases'] as $case ) {
			$cases[] = array(
				'day'         => (int) $case['day'],
				'unlock_date' => (string) $case['unlock_date'],
				'coupon_code' => '',
				'product_id'  => 0,
				'title'       => isset( $case['title'] ) ? (string) $case['title'] : '',
				'description' => isset( $case['description'] ) ? (string) $case['description'] : '',
				'image_id'    => 0,
			);
		}
		update_post_meta( $post_id, SAITO_CE_CASES_META, $cases );
		update_post_meta( $post_id, SAITO_CE_STYLE_META, isset( $settings['style'] ) ? $settings['style'] : 'noel' );
		update_post_meta( $post_id, SAITO_CE_SUBTITLE_META, isset( $settings['subtitle'] ) ? $settings['subtitle'] : '' );
		if ( isset( $settings['schedule'] ) ) {
			update_post_meta( $post_id, SAITO_CE_SCHEDULE_META, $settings['schedule'] );
		}

		$post     = get_post( $post_id );
		$update   = array( 'ID' => $post_id );
		$is_draft = 'auto-draft' === $post->post_status;
		// Un calendrier tout juste créé porte le titre provisoire « Brouillon
		// auto » de WordPress : remplacé comme un titre vide.
		if ( ( $is_draft || '' === trim( (string) $post->post_title ) ) && ! empty( $settings['title'] ) ) {
			$update['post_title'] = $settings['title'];
		}
		// Calendrier tout juste créé (brouillon automatique) : brouillon normal,
		// sinon WordPress le supprimerait au prochain nettoyage.
		if ( $is_draft ) {
			$update['post_status'] = 'draft';
		}
		if ( count( $update ) > 1 ) {
			wp_update_post( $update );
		}
		return true;
	}

	/**
	 * Lien « Appliquer » d'un modèle (le modèle et l'année sont ajoutés par
	 * admin.js d'après l'encart de l'onglet Cases).
	 */
	public static function apply_url( $post_id ) {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::ACTION . '&post_id=' . (int) $post_id ),
			self::ACTION . '_' . (int) $post_id
		);
	}

	public static function handle_apply() {
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		check_admin_referer( self::ACTION . '_' . $post_id );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'saito-calendar-event' ) );
		}
		$post = get_post( $post_id );
		if ( ! $post || SAITO_CE_POST_TYPE !== $post->post_type ) {
			wp_die( esc_html__( 'Calendrier introuvable.', 'saito-calendar-event' ) );
		}
		$slug = isset( $_GET['template'] ) ? sanitize_key( wp_unslash( $_GET['template'] ) ) : '';
		$year = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : 0;
		if ( $year < 2000 || $year > 2100 ) {
			$year = (int) wp_date( 'Y' );
		}
		if ( ! self::apply( $post_id, $slug, $year ) ) {
			wp_die( esc_html__( 'Modèle introuvable.', 'saito-calendar-event' ) );
		}
		wp_safe_redirect( add_query_arg( 'saito_ce_template_applied', '1', get_edit_post_link( $post_id, 'raw' ) ) );
		exit;
	}

	/**
	 * Encart « Partir d'un modèle » en haut de l'onglet Cases. Ses champs
	 * n'ont pas d'attribut name : ils ne sont pas envoyés avec le
	 * formulaire du calendrier, admin.js construit le lien d'application.
	 */
	public static function render_picker( $post, $has_cases ) {
		$year = (int) wp_date( 'Y' );
		?>
		<div class="saito-ce-template-picker" data-apply-url="<?php echo esc_url( self::apply_url( $post->ID ) ); ?>" data-has-cases="<?php echo $has_cases ? '1' : '0'; ?>">
			<p>
				<label for="saito-ce-template"><strong><?php esc_html_e( 'Partir d’un modèle', 'saito-calendar-event' ); ?></strong></label><br>
				<select id="saito-ce-template">
					<?php foreach ( self::templates() as $slug => $template ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" title="<?php echo esc_attr( isset( $template['description'] ) ? $template['description'] : '' ); ?>"><?php echo esc_html( $template['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<label for="saito-ce-template-year" class="screen-reader-text"><?php esc_html_e( 'Année', 'saito-calendar-event' ); ?></label>
				<input type="number" id="saito-ce-template-year" min="2000" max="2100" value="<?php echo esc_attr( $year ); ?>" class="small-text">
				<button type="button" class="button" id="saito-ce-apply-template"><?php esc_html_e( 'Appliquer le modèle', 'saito-calendar-event' ); ?></button>
			</p>
			<p class="description"><?php esc_html_e( 'Remplit les cases (dates calculées pour l’année choisie, titres, descriptions), l’habillage et la programmation. Les codes promo et produits restent à renseigner. Les modifications non enregistrées de ce calendrier sont perdues.', 'saito-calendar-event' ); ?></p>
		</div>
		<?php
	}
}
