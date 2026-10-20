<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Écran d'administration d'un calendrier : une case par jour, avec sa date
 * de déblocage, son code promo WooCommerce et le texte/image révélés au clic.
 */
class Saito_CE_Admin {

	const NONCE_ACTION = 'saito_ce_save_cases';
	const NONCE_NAME   = 'saito_ce_cases_nonce';

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_' . SAITO_CE_POST_TYPE, array( __CLASS__, 'save' ) );
		// Priorité 20 : après WooCommerce (10), qui enregistre les scripts de
		// recherche de produit (wc-enhanced-select) que l'on réutilise.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
		add_action( 'admin_post_saito_ce_export_cases', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_saito_ce_duplicate_calendar', array( __CLASS__, 'handle_duplicate' ) );
		add_action( 'wp_ajax_saito_ce_search_products', array( __CLASS__, 'ajax_search_products' ) );
	}

	/**
	 * Recherche de produits pour le champ "Produit lié" : comme la recherche
	 * native de WooCommerce, mais renvoie aussi miniature, référence, prix et
	 * stock, affichés dans la liste de résultats.
	 */
	public static function ajax_search_products() {
		check_ajax_referer( 'saito_ce_search_products', 'security' );
		if ( ! current_user_can( 'edit_products' ) || ! saito_ce_is_woocommerce_active() ) {
			wp_send_json_error( array(), 403 );
		}

		$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
		if ( '' === $term ) {
			wp_send_json_success( array() );
		}

		$data_store = WC_Data_Store::load( 'product' );
		$ids        = array_filter( array_map( 'absint', $data_store->search_products( $term, '', true, false, 20 ) ) );

		$results = array();
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product || ! $product->exists() ) {
				continue;
			}
			$image_id  = $product->get_image_id();
			$results[] = array(
				'id'      => $product->get_id(),
				'text'    => wp_strip_all_tags( $product->get_name() ),
				'sku'     => (string) $product->get_sku(),
				'price'   => '' !== $product->get_price() ? html_entity_decode( wp_strip_all_tags( wc_price( $product->get_price() ) ), ENT_QUOTES, 'UTF-8' ) : '',
				'inStock' => $product->is_in_stock(),
				'image'   => $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : wc_placeholder_img_src( 'thumbnail' ),
			);
		}

		wp_send_json_success( $results );
	}

	public static function enqueue( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || SAITO_CE_POST_TYPE !== $screen->post_type ) {
			return;
		}
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		// Nécessaire pour que l'aperçu en direct rende les cases avec les
		// mêmes styles que le front (habillages + variables custom).
		wp_enqueue_style( 'saito-ce-frontend', SAITO_CE_URL . 'assets/css/frontend.css', array(), SAITO_CE_VERSION );
		// Tous les habillages pour l'aperçu, qui change en direct quand on en
		// choisit un autre (sur le site, seul celui du calendrier est chargé).
		wp_enqueue_style( 'saito-ce-style-sparkle', SAITO_CE_URL . 'assets/css/styles/sparkle.css', array( 'saito-ce-frontend' ), SAITO_CE_VERSION );
		wp_enqueue_style( 'saito-ce-style-floral', SAITO_CE_URL . 'assets/css/styles/floral.css', array( 'saito-ce-frontend' ), SAITO_CE_VERSION );
		wp_enqueue_style( 'saito-ce-admin', SAITO_CE_URL . 'assets/css/admin.css', array( 'saito-ce-frontend' ), SAITO_CE_VERSION );
		// SortableJS (MIT, embarqué dans assets/js/vendor) : glisser-déposer des
		// cases de la grille, souris comme tactile (cf. admin.js).
		wp_register_script( 'saito-ce-sortablejs', SAITO_CE_URL . 'assets/js/vendor/Sortable.min.js', array(), '1.15.7', true );
		$deps = array( 'jquery', 'wp-color-picker', 'saito-ce-sortablejs' );
		if ( self::use_product_search() ) {
			// Recherche de produit par nom : selectWoo (le select2 embarqué par
			// WooCommerce), branché sur notre propre recherche AJAX qui renvoie
			// miniature, référence, prix et stock (cf. ajax_search_products()).
			$deps[] = 'selectWoo';
			wp_enqueue_style( 'woocommerce_admin_styles' );
		}
		wp_enqueue_script( 'saito-ce-admin', SAITO_CE_URL . 'assets/js/admin.js', $deps, SAITO_CE_VERSION, true );
		wp_enqueue_script( 'saito-ce-admin-pickers', SAITO_CE_URL . 'assets/js/admin-pickers.js', array( 'saito-ce-admin' ), SAITO_CE_VERSION, true );
		wp_localize_script(
			'saito-ce-admin',
			'saitoCeAdmin',
			array(
				'coupons'     => self::get_wc_coupons(),
				// Même disposition que sur le site, pour l'aperçu.
				'mosaic'      => array(
					'styles'  => array_values( array_filter( array( 'sparkle', 'floral-blue', 'floral-gold', 'floral-duo' ), array( 'Saito_CE_Public', 'is_mosaic_style' ) ) ),
					'layout'  => Saito_CE_Public::MOSAIC_LAYOUT,
					'today'   => __( 'Aujourd’hui', 'saito-calendar-event' ),
				),
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'searchNonce' => wp_create_nonce( 'saito_ce_search_products' ),
				'i18n'        => array(
					'couponValid'        => __( 'Valide', 'saito-calendar-event' ),
					'couponStatusExpired' => __( 'Expiré', 'saito-calendar-event' ),
					'couponStatusUsedUp' => __( 'Épuisé', 'saito-calendar-event' ),
					/* translators: %s: date d'expiration. */
					'couponExpiresOn'    => __( 'expire le %s', 'saito-calendar-event' ),
					'couponNoExpiry'     => __( 'sans date d’expiration', 'saito-calendar-event' ),
					'couponNoMatch'      => __( 'Aucun code WooCommerce ne correspond. Vérifiez qu’il est créé et publié dans Marketing → Codes promo.', 'saito-calendar-event' ),
					/* translators: %d: nombre de codes. */
					'couponCount'        => __( '%d code(s) WooCommerce', 'saito-calendar-event' ),
					'productTooShort'    => __( 'Tapez au moins 2 lettres du nom du produit…', 'saito-calendar-event' ),
					'productSearching'   => __( 'Recherche…', 'saito-calendar-event' ),
					'productNoResults'   => __( 'Aucun produit trouvé.', 'saito-calendar-event' ),
					'productInStock'     => __( 'En stock', 'saito-calendar-event' ),
					'productOutOfStock'  => __( 'Rupture', 'saito-calendar-event' ),
					'couponUnknown' => __( 'Ce code n’existe pas dans WooCommerce (ou n’est pas publié) : le bouton « Ajouter la réduction » échouera.', 'saito-calendar-event' ),
					/* translators: %s: date d'expiration du code promo. */
					'couponExpired' => __( 'Ce code a expiré dans WooCommerce le %s.', 'saito-calendar-event' ),
					/* translators: %s: date d'expiration du code promo. */
					'couponExpiresBefore' => __( 'Ce code expire le %s dans WooCommerce, avant la date de déblocage de la case.', 'saito-calendar-event' ),
					'couponUsedUp'  => __( 'Ce code a atteint sa limite d’utilisation dans WooCommerce.', 'saito-calendar-event' ),
					'couponOk'      => __( 'Code trouvé dans WooCommerce.', 'saito-calendar-event' ),
				),
			)
		);
	}

	/**
	 * Codes promo WooCommerce publiés, indexés par code en majuscules (comme
	 * les cases les enregistrent, cf. save()) : alimentent la liste de
	 * suggestions du champ "Code promo" et sa vérification (existence,
	 * expiration, limite d'utilisation). Mis en cache pour la requête.
	 *
	 * @return array<string, array{expires: string, expiresLabel: string, usedUp: bool}>
	 */
	private static function get_wc_coupons() {
		static $coupons = null;
		if ( null !== $coupons ) {
			return $coupons;
		}
		$coupons = array();
		if ( ! saito_ce_is_woocommerce_active() ) {
			return $coupons;
		}

		$posts = get_posts(
			array(
				'post_type'      => 'shop_coupon',
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);

		$date_format = get_option( 'date_format' );
		if ( '' === trim( (string) $date_format ) ) {
			$date_format = 'j F Y';
		}

		foreach ( $posts as $coupon_post ) {
			$code = strtoupper( trim( $coupon_post->post_title ) );
			if ( '' === $code ) {
				continue;
			}
			$expires_ts  = (int) get_post_meta( $coupon_post->ID, 'date_expires', true );
			$usage_limit = (int) get_post_meta( $coupon_post->ID, 'usage_limit', true );
			$usage_count = (int) get_post_meta( $coupon_post->ID, 'usage_count', true );

			$amount = (float) get_post_meta( $coupon_post->ID, 'coupon_amount', true );
			$type   = (string) get_post_meta( $coupon_post->ID, 'discount_type', true );
			$value  = '';
			if ( $amount > 0 ) {
				$value = 'percent' === $type
					? '-' . wc_format_localized_decimal( $amount ) . ' %'
					: '-' . html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' );
			}

			$coupons[ $code ] = array(
				'expires'      => $expires_ts ? wp_date( 'Y-m-d', $expires_ts ) : '',
				'expiresLabel' => $expires_ts ? wp_date( $date_format, $expires_ts ) : '',
				'usedUp'       => $usage_limit > 0 && $usage_count >= $usage_limit,
				'value'        => $value,
				'description'  => wp_strip_all_tags( $coupon_post->post_excerpt ),
			);
		}

		return $coupons;
	}

	/**
	 * Recherche de produit par nom disponible : WooCommerce actif et son script
	 * de recherche enregistré. Sinon, repli sur l'ancien champ "ID produit".
	 */
	private static function use_product_search() {
		return saito_ce_is_woocommerce_active() && wp_script_is( 'selectWoo', 'registered' );
	}

	public static function add_meta_boxes() {
		add_meta_box(
			'saito_ce_tabs',
			__( 'Calendrier', 'saito-calendar-event' ),
			array( __CLASS__, 'render_tabs_box' ),
			SAITO_CE_POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Les écrans vivaient chacun dans leur propre encadré empilé — dont
	 * "Utilisation" dans la colonne latérale, loin de "Cases" qu'il
	 * concerne pourtant directement. Un seul encadré à onglets les
	 * regroupe : tout reste dans le même formulaire (les champs des
	 * onglets non actifs restent soumis, cf. chaque onglet), seul du JS
	 * bascule la visibilité (admin.js).
	 *
	 * "Réglages" mélangeait à l'origine habillage visuel, textes visiteur
	 * et aperçu dans un seul long défilement : scindé en "Apparence"
	 * (habillage + couleurs + aperçu) et "Textes" (message de case
	 * verrouillée + libellés des boutons/messages), chacun avec un objet
	 * clair plutôt qu'un fourre-tout.
	 */
	public static function render_tabs_box( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		$tabs = array(
			'cases'      => __( 'Cases', 'saito-calendar-event' ),
			'appearance' => __( 'Apparence', 'saito-calendar-event' ),
			'texts'      => __( 'Textes', 'saito-calendar-event' ),
			'schedule'   => __( 'Programmation', 'saito-calendar-event' ),
			'usage'      => __( 'Utilisation', 'saito-calendar-event' ),
		);
		?>
		<div class="saito-ce-admin-tabs">
			<div class="saito-ce-admin-tabs-nav" role="tablist">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<button
						type="button"
						class="saito-ce-admin-tab<?php echo 'cases' === $slug ? ' is-active' : ''; ?>"
						data-tab="<?php echo esc_attr( $slug ); ?>"
						role="tab"
						aria-selected="<?php echo 'cases' === $slug ? 'true' : 'false'; ?>"
					><?php echo esc_html( $label ); ?></button>
				<?php endforeach; ?>
			</div>
			<div class="saito-ce-admin-tab-panel" data-tab-panel="cases">
				<?php self::render_cases_tab( $post ); ?>
			</div>
			<div class="saito-ce-admin-tab-panel" data-tab-panel="appearance" hidden>
				<?php self::render_appearance_tab( $post ); ?>
			</div>
			<div class="saito-ce-admin-tab-panel" data-tab-panel="texts" hidden>
				<?php self::render_texts_tab( $post ); ?>
			</div>
			<div class="saito-ce-admin-tab-panel" data-tab-panel="schedule" hidden>
				<?php self::render_schedule_tab( $post ); ?>
			</div>
			<div class="saito-ce-admin-tab-panel" data-tab-panel="usage" hidden>
				<?php self::render_usage_tab( $post ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Période d'affichage du calendrier sur le site (cf.
	 * Saito_CE_Public::get_schedule_state()) : permet de tout préparer à
	 * l'avance et de le laisser apparaître / disparaître tout seul.
	 */
	private static function render_schedule_tab( $post ) {
		$schedule = Saito_CE_Public::get_schedule( $post->ID );
		$state    = Saito_CE_Public::get_schedule_state( $schedule );
		$labels   = array(
			'before' => __( 'Pas encore visible par les visiteurs.', 'saito-calendar-event' ),
			'live'   => __( 'Visible par les visiteurs (si le calendrier est publié et placé sur une page).', 'saito-calendar-event' ),
			'after'  => __( 'Plus visible par les visiteurs : période d’affichage terminée.', 'saito-calendar-event' ),
		);
		?>
		<p class="saito-ce-schedule-status is-<?php echo esc_attr( $state ); ?>">
			<strong><?php esc_html_e( 'Aujourd’hui :', 'saito-calendar-event' ); ?></strong>
			<?php echo esc_html( $labels[ $state ] ); ?>
			<span class="description"><?php esc_html_e( '(selon les dates enregistrées)', 'saito-calendar-event' ); ?></span>
		</p>
		<table class="form-table">
			<tr>
				<th><label for="saito_ce_schedule_start"><?php esc_html_e( 'Afficher à partir du', 'saito-calendar-event' ); ?></label></th>
				<td>
					<input type="date" id="saito_ce_schedule_start" name="saito_ce_schedule[start]" value="<?php echo esc_attr( $schedule['start'] ); ?>">
					<p class="description"><?php esc_html_e( 'Vide = visible dès la publication.', 'saito-calendar-event' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="saito_ce_schedule_end"><?php esc_html_e( 'Jusqu’au (inclus)', 'saito-calendar-event' ); ?></label></th>
				<td>
					<input type="date" id="saito_ce_schedule_end" name="saito_ce_schedule[end]" value="<?php echo esc_attr( $schedule['end'] ); ?>" min="<?php echo esc_attr( $schedule['start'] ); ?>">
					<p class="description"><?php esc_html_e( 'Vide = visible sans limite de fin.', 'saito-calendar-event' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="saito_ce_schedule_before"><?php esc_html_e( 'Message avant le début', 'saito-calendar-event' ); ?></label></th>
				<td>
					<?php /* translators: %s: date de début d'affichage (exemple de message). */ ?>
					<input type="text" class="large-text" id="saito_ce_schedule_before" name="saito_ce_schedule[before_message]" value="<?php echo esc_attr( $schedule['before_message'] ); ?>" placeholder="<?php esc_attr_e( 'Le calendrier de l’Avent ouvre ses portes le %s, à très vite !', 'saito-calendar-event' ); ?>">
					<p class="description"><?php /* translators: %s: marqueur littéral à recopier dans le message, à ne pas traduire. */ esc_html_e( 'Affiché à la place du calendrier avant son ouverture. %s est remplacé par la date de début. Vide = rien n’est affiché.', 'saito-calendar-event' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="saito_ce_schedule_after"><?php esc_html_e( 'Message après la fin', 'saito-calendar-event' ); ?></label></th>
				<td>
					<input type="text" class="large-text" id="saito_ce_schedule_after" name="saito_ce_schedule[after_message]" value="<?php echo esc_attr( $schedule['after_message'] ); ?>" placeholder="<?php esc_attr_e( 'Le calendrier est terminé, merci d’avoir joué ! Rendez-vous l’année prochaine.', 'saito-calendar-event' ); ?>">
					<p class="description"><?php /* translators: %s: marqueur littéral à recopier dans le message, à ne pas traduire. */ esc_html_e( 'Affiché à la place du calendrier une fois la période terminée. %s est remplacé par la date de fin. Vide = rien n’est affiché.', 'saito-calendar-event' ); ?></p>
				</td>
			</tr>
		</table>
		<p class="description"><?php esc_html_e( 'En tant qu’éditeur, vous voyez toujours le calendrier sur le site, avec un bandeau qui rappelle s’il est masqué aux visiteurs.', 'saito-calendar-event' ); ?></p>
		<?php
	}

	private static function render_appearance_tab( $post ) {
		$style  = Saito_CE_Public::normalize_style( get_post_meta( $post->ID, SAITO_CE_STYLE_META, true ) );
		$colors = get_post_meta( $post->ID, SAITO_CE_COLORS_META, true );
		$colors = wp_parse_args(
			is_array( $colors ) ? $colors : array(),
			array(
				'accent'      => '',
				'accent_dark' => '',
				'bg'          => '',
				'text'        => '',
				'radius'      => '',
				'border'      => '',
			)
		);
		?>
		<p>
			<label for="saito_ce_style"><strong><?php esc_html_e( 'Habillage visuel de base', 'saito-calendar-event' ); ?></strong></label><br>
			<select name="saito_ce_style" id="saito_ce_style">
				<option value="noel" <?php selected( $style, 'noel' ); ?>><?php esc_html_e( 'Noël (rouge et vert)', 'saito-calendar-event' ); ?></option>
				<option value="neutre" <?php selected( $style, 'neutre' ); ?>><?php esc_html_e( 'Neutre', 'saito-calendar-event' ); ?></option>
				<option value="editorial" <?php selected( $style, 'editorial' ); ?>><?php esc_html_e( 'Éditorial (aplats, typographie à empattements, cases de tailles variées)', 'saito-calendar-event' ); ?></option>
				<option value="sparkle" <?php selected( $style, 'sparkle' ); ?>><?php esc_html_e( 'Étincelles (rouge corail et or, portes en mosaïque)', 'saito-calendar-event' ); ?></option>
				<option value="floral-blue" <?php selected( $style, 'floral-blue' ); ?>><?php esc_html_e( 'Floral bleu et argent (portes en mosaïque)', 'saito-calendar-event' ); ?></option>
				<option value="floral-gold" <?php selected( $style, 'floral-gold' ); ?>><?php esc_html_e( 'Floral blanc et or (portes en mosaïque)', 'saito-calendar-event' ); ?></option>
				<option value="floral-duo" <?php selected( $style, 'floral-duo' ); ?>><?php esc_html_e( 'Floral duo : portes bleu et argent, blanc et or (mosaïque)', 'saito-calendar-event' ); ?></option>
			</select>
		</p>
		<p>
			<input type="hidden" name="saito_ce_show_header" value="0">
			<label>
				<input type="checkbox" id="saito_ce_show_header" name="saito_ce_show_header" value="1" <?php checked( '0' !== get_post_meta( $post->ID, SAITO_CE_HEADER_META, true ) ); ?>>
				<?php esc_html_e( 'Afficher un en-tête au-dessus de la grille', 'saito-calendar-event' ); ?>
			</label>
			<br><span class="description"><?php esc_html_e( 'Habillages en mosaïque uniquement. L’en-tête reprend le titre du calendrier ; décochez si la page affiche déjà un titre.', 'saito-calendar-event' ); ?></span>
		</p>
		<p>
			<label for="saito_ce_header_subtitle"><strong><?php esc_html_e( 'Sous-titre de l’en-tête (optionnel)', 'saito-calendar-event' ); ?></strong></label><br>
			<input type="text" id="saito_ce_header_subtitle" name="saito_ce_header_subtitle" value="<?php echo esc_attr( get_post_meta( $post->ID, SAITO_CE_SUBTITLE_META, true ) ); ?>" style="width:100%;max-width:480px;" placeholder="<?php esc_attr_e( 'Calendrier de l’Avent', 'saito-calendar-event' ); ?>">
		</p>

		<p class="description">
			<?php esc_html_e( 'Les champs ci-dessous sont optionnels et remplacent l’habillage de base quand ils sont renseignés. Laissez vide pour garder les couleurs par défaut de l’habillage choisi.', 'saito-calendar-event' ); ?>
		</p>
		<table class="form-table">
			<tr>
				<th><label for="saito_ce_color_accent"><?php esc_html_e( 'Couleur des cases', 'saito-calendar-event' ); ?></label></th>
				<td><input type="text" class="saito-ce-color-field" id="saito_ce_color_accent" name="saito_ce_colors[accent]" value="<?php echo esc_attr( $colors['accent'] ); ?>" data-default-color=""></td>
			</tr>
			<tr>
				<th><label for="saito_ce_color_accent_dark"><?php esc_html_e( 'Couleur des cases ouvertes', 'saito-calendar-event' ); ?></label></th>
				<td><input type="text" class="saito-ce-color-field" id="saito_ce_color_accent_dark" name="saito_ce_colors[accent_dark]" value="<?php echo esc_attr( $colors['accent_dark'] ); ?>" data-default-color=""></td>
			</tr>
			<tr>
				<th><label for="saito_ce_color_bg"><?php esc_html_e( 'Couleur de fond de la grille', 'saito-calendar-event' ); ?></label></th>
				<td><input type="text" class="saito-ce-color-field" id="saito_ce_color_bg" name="saito_ce_colors[bg]" value="<?php echo esc_attr( $colors['bg'] ); ?>" data-default-color=""></td>
			</tr>
			<tr>
				<th><label for="saito_ce_color_text"><?php esc_html_e( 'Couleur du texte des cases', 'saito-calendar-event' ); ?></label></th>
				<td><input type="text" class="saito-ce-color-field" id="saito_ce_color_text" name="saito_ce_colors[text]" value="<?php echo esc_attr( $colors['text'] ); ?>" data-default-color=""></td>
			</tr>
			<tr>
				<th><label for="saito_ce_radius"><?php esc_html_e( 'Arrondi des coins (px)', 'saito-calendar-event' ); ?></label></th>
				<td><input type="number" min="0" max="100" id="saito_ce_radius" name="saito_ce_colors[radius]" value="<?php echo esc_attr( $colors['radius'] ); ?>" placeholder="6" style="width:100px;"></td>
			</tr>
			<tr>
				<th><label for="saito_ce_border"><?php esc_html_e( 'Bordure des cases (CSS)', 'saito-calendar-event' ); ?></label></th>
				<td>
					<input type="text" id="saito_ce_border" name="saito_ce_colors[border]" value="<?php echo esc_attr( $colors['border'] ); ?>" placeholder="2px solid #ffffff" style="width:220px;">
					<p class="description"><?php esc_html_e( 'Ex. : 2px solid #ffffff', 'saito-calendar-event' ); ?></p>
				</td>
			</tr>
		</table>

		<hr>
		<p><strong><?php esc_html_e( 'Aperçu', 'saito-calendar-event' ); ?></strong></p>
		<?php
		$preview_style      = Saito_CE_Public::build_style_declarations( $colors );
		$preview_style_attr = $preview_style ? implode( ';', $preview_style ) : '';

		// Reflète les vraies cases déjà enregistrées (jour + état réel :
		// verrouillée / ouvrable aujourd'hui uniquement / expirée) plutôt que
		// des cases fictives : sans ça, l'aperçu ne représentait jamais le
		// calendrier réellement configuré (nombre de cases, numéros, dates).
		// Une case n'est ouvrable que le jour exact de son déblocage (cf.
		// Saito_CE_Public::is_unlocked()/is_case_expired()) : comparaison
		// simplifiée en chaînes "Y-m-d" ici, suffisant pour un aperçu, la
		// vérification faisant foi côté front restant celle du serveur.
		$preview_cases = get_post_meta( $post->ID, SAITO_CE_CASES_META, true );
		$preview_cases = is_array( $preview_cases ) ? array_values( array_filter( $preview_cases, function ( $case ) {
			return ! empty( $case['day'] );
		} ) ) : array();
		$preview_today = current_time( 'Y-m-d' );
		?>
		<div
			id="saito-ce-preview"
			class="saito-ce-calendar saito-ce-style-<?php echo esc_attr( $style ); ?>"
			style="<?php echo esc_attr( $preview_style_attr ); ?>"
		>
			<?php // En-tête de marque, rempli par admin.js selon l'habillage choisi. ?>
			<div class="saito-ce-header" hidden></div>
			<div class="saito-ce-grid saito-ce-grid-preview">
				<?php if ( $preview_cases ) : ?>
					<?php foreach ( $preview_cases as $preview_case ) : ?>
						<?php
						$preview_unlock_date = isset( $preview_case['unlock_date'] ) ? trim( (string) $preview_case['unlock_date'] ) : '';
						if ( '' === $preview_unlock_date || $preview_unlock_date > $preview_today ) {
							$preview_state_class = 'is-locked';
						} elseif ( $preview_unlock_date === $preview_today ) {
							$preview_state_class = 'is-available';
						} else {
							$preview_state_class = 'is-expired';
						}
						?>
						<button type="button" class="saito-ce-case <?php echo esc_attr( $preview_state_class ); ?>" disabled aria-hidden="true"><span class="saito-ce-case-lock"></span><span class="saito-ce-case-day"><?php echo esc_html( $preview_case['day'] ); ?></span></button>
					<?php endforeach; ?>
				<?php else : ?>
					<button type="button" class="saito-ce-case is-locked" disabled aria-hidden="true"><span class="saito-ce-case-lock"></span><span class="saito-ce-case-day">1</span></button>
					<button type="button" class="saito-ce-case is-available" disabled aria-hidden="true"><span class="saito-ce-case-lock"></span><span class="saito-ce-case-day">2</span></button>
					<button type="button" class="saito-ce-case is-opened" disabled aria-hidden="true"><span class="saito-ce-case-lock"></span><span class="saito-ce-case-day">3</span></button>
					<button type="button" class="saito-ce-case is-expired" disabled aria-hidden="true"><span class="saito-ce-case-lock"></span><span class="saito-ce-case-day">4</span></button>
					<button type="button" class="saito-ce-case is-locked" disabled aria-hidden="true"><span class="saito-ce-case-lock"></span><span class="saito-ce-case-day">5</span></button>
				<?php endif; ?>
			</div>
		</div>
		<p class="description">
			<?php if ( $preview_cases ) : ?>
				<?php esc_html_e( 'Aperçu basé sur les cases déjà enregistrées (verrouillée / ouvrable aujourd’hui / expirée, selon la date du jour — une case n’est ouvrable que le jour exact de son déblocage). L’habillage et les couleurs se mettent à jour en direct ; un ajout/retrait de case doit d’abord être enregistré pour apparaître ici.', 'saito-calendar-event' ); ?>
			<?php else : ?>
				<?php esc_html_e( 'Aucune case enregistrée pour le moment : exemple générique ci-dessus. L’habillage et les couleurs se mettent à jour en direct pendant que vous modifiez les réglages.', 'saito-calendar-event' ); ?>
			<?php endif; ?>
		</p>
		<?php
	}

	/**
	 * Tous les textes visibles par les visiteurs (message de case verrouillée,
	 * libellés des boutons, messages de succès/erreur) : regroupés ici plutôt
	 * que mélangés avec l'habillage visuel, dont ils ne dépendent pas.
	 */
	private static function render_texts_tab( $post ) {
		$locked_message = get_post_meta( $post->ID, SAITO_CE_LOCKED_MESSAGE_META, true );
		?>
		<p>
			<label for="saito_ce_locked_message"><strong><?php esc_html_e( 'Message affiché sur une case pas encore ouvrable', 'saito-calendar-event' ); ?></strong></label><br>
			<input
				type="text"
				id="saito_ce_locked_message"
				name="saito_ce_locked_message"
				value="<?php echo esc_attr( $locked_message ); ?>"
				style="width:100%;max-width:480px;"
				placeholder="<?php /* translators: %s: date de déblocage de la case. */ esc_attr_e( 'Cette case n’est pas encore ouvrable. Reviens le %s !', 'saito-calendar-event' ); ?>"
			>
		</p>
		<p class="description">
			<?php /* translators: %s: marqueur littéral à recopier dans le message, à ne pas traduire. */ esc_html_e( 'Laissez vide pour garder le message par défaut. Le marqueur %s sera remplacé par la date de déblocage (ex. 20 septembre 2026) ; sans ce marqueur, la date est ajoutée à la fin du message.', 'saito-calendar-event' ); ?>
		</p>

		<?php
		$texts = get_post_meta( $post->ID, SAITO_CE_TEXTS_META, true );
		$texts = wp_parse_args(
			is_array( $texts ) ? $texts : array(),
			array(
				'apply'         => '',
				'applying'      => '',
				'appliedButton' => '',
				'applied'       => '',
				'error'         => '',
				'expired'       => '',
				'expiresToday'  => '',
				'today'         => '',
			)
		);
		?>
		<p><strong><?php esc_html_e( 'Textes personnalisés (optionnel)', 'saito-calendar-event' ); ?></strong></p>
		<p class="description">
			<?php esc_html_e( 'Laissez un champ vide pour garder le texte par défaut correspondant.', 'saito-calendar-event' ); ?>
		</p>
		<table class="form-table">
			<tr>
				<th><label for="saito_ce_text_apply"><?php esc_html_e( 'Bouton "Ajouter au panier"', 'saito-calendar-event' ); ?></label></th>
				<td><input type="text" id="saito_ce_text_apply" name="saito_ce_texts[apply]" value="<?php echo esc_attr( $texts['apply'] ); ?>" style="width:100%;" placeholder="<?php esc_attr_e( 'Ajouter la réduction à mon panier', 'saito-calendar-event' ); ?>"></td>
			</tr>
			<tr>
				<th><label for="saito_ce_text_applying"><?php esc_html_e( 'Bouton pendant l’ajout', 'saito-calendar-event' ); ?></label></th>
				<td><input type="text" id="saito_ce_text_applying" name="saito_ce_texts[applying]" value="<?php echo esc_attr( $texts['applying'] ); ?>" style="width:100%;" placeholder="<?php esc_attr_e( 'Ajout en cours…', 'saito-calendar-event' ); ?>"></td>
			</tr>
			<tr>
				<th><label for="saito_ce_text_applied_button"><?php esc_html_e( 'Bouton après ajout réussi', 'saito-calendar-event' ); ?></label></th>
				<td><input type="text" id="saito_ce_text_applied_button" name="saito_ce_texts[appliedButton]" value="<?php echo esc_attr( $texts['appliedButton'] ); ?>" style="width:100%;" placeholder="<?php esc_attr_e( 'Code ajouté ✓', 'saito-calendar-event' ); ?>"></td>
			</tr>
			<tr>
				<th><label for="saito_ce_text_applied"><?php esc_html_e( 'Message de succès', 'saito-calendar-event' ); ?></label></th>
				<td><input type="text" id="saito_ce_text_applied" name="saito_ce_texts[applied]" value="<?php echo esc_attr( $texts['applied'] ); ?>" style="width:100%;" placeholder="<?php esc_attr_e( 'Code ajouté à ton panier !', 'saito-calendar-event' ); ?>"></td>
			</tr>
			<tr>
				<th><label for="saito_ce_text_error"><?php esc_html_e( 'Message d’erreur générique', 'saito-calendar-event' ); ?></label></th>
				<td><input type="text" id="saito_ce_text_error" name="saito_ce_texts[error]" value="<?php echo esc_attr( $texts['error'] ); ?>" style="width:100%;" placeholder="<?php esc_attr_e( 'Une erreur est survenue, réessaie.', 'saito-calendar-event' ); ?>"></td>
			</tr>
			<tr>
				<th><label for="saito_ce_text_expired"><?php esc_html_e( 'Message si la date d’une case est passée', 'saito-calendar-event' ); ?></label></th>
				<td>
					<input type="text" id="saito_ce_text_expired" name="saito_ce_texts[expired]" value="<?php echo esc_attr( $texts['expired'] ); ?>" style="width:100%;" placeholder="<?php esc_attr_e( 'Cette offre n’est plus disponible : la date est passée.', 'saito-calendar-event' ); ?>">
					<p class="description"><?php esc_html_e( 'Une case n’est ouvrable que le jour exact de son déblocage : passé ce jour, cliquer dessus affiche ce message dans une modale au lieu de l’ouvrir. Sert aussi de message d’erreur si un visiteur tente malgré tout d’ajouter le code promo d’une case déjà revue (cache local).', 'saito-calendar-event' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="saito_ce_text_expires_today"><?php esc_html_e( 'Repère sur la case du jour', 'saito-calendar-event' ); ?></label></th>
				<td>
					<input type="text" id="saito_ce_text_expires_today" name="saito_ce_texts[expiresToday]" value="<?php echo esc_attr( $texts['expiresToday'] ); ?>" style="width:100%;" placeholder="<?php esc_attr_e( 'Dernier jour !', 'saito-calendar-event' ); ?>">
					<p class="description"><?php esc_html_e( 'Affiché sur la case du jour pour rappeler que son offre expire à minuit. Gardez ce texte court : la case est un petit carré.', 'saito-calendar-event' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="saito_ce_text_today"><?php esc_html_e( 'Badge de la case du jour', 'saito-calendar-event' ); ?></label></th>
				<td>
					<input type="text" id="saito_ce_text_today" name="saito_ce_texts[today]" value="<?php echo esc_attr( $texts['today'] ); ?>" style="width:100%;" placeholder="<?php esc_attr_e( 'Aujourd’hui', 'saito-calendar-event' ); ?>">
					<p class="description"><?php esc_html_e( 'Petite étiquette en haut de la case du jour. Un ou deux mots.', 'saito-calendar-event' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	private static function render_usage_tab( $post ) {
		$cases       = get_post_meta( $post->ID, SAITO_CE_CASES_META, true );
		$cases       = is_array( $cases ) ? $cases : array();
		$valid_count = count( array_filter( $cases, function ( $case ) {
			return ! empty( $case['day'] );
		} ) );
		$export_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=saito_ce_export_cases&post_id=' . $post->ID ),
			'saito_ce_export_' . $post->ID
		);
		$duplicate_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=saito_ce_duplicate_calendar&post_id=' . $post->ID ),
			'saito_ce_duplicate_' . $post->ID
		);
		?>
		<p><?php esc_html_e( 'Shortcode à coller sur une page :', 'saito-calendar-event' ); ?></p>
		<p><code>[saito_calendar_event id="<?php echo (int) $post->ID; ?>"]</code></p>
		<p><?php esc_html_e( 'Ou insérez le bloc « Calendrier à cases » et choisissez ce calendrier dans son réglage.', 'saito-calendar-event' ); ?></p>
		<hr>
		<p>
			<?php
			if ( $valid_count > 0 ) {
				printf(
					/* translators: %d: number of saved cases */
					esc_html( _n( '%d case enregistrée sur cette version publiée.', '%d cases enregistrées sur cette version publiée.', $valid_count, 'saito-calendar-event' ) ),
					(int) $valid_count
				);
			} else {
				esc_html_e( 'Aucune case enregistrée pour le moment : renseignez les cases ci-contre puis cliquez sur « Publier »/« Mettre à jour », sinon rien ne s’affichera sur le site.', 'saito-calendar-event' );
			}
			?>
		</p>
		<?php $open_count = (int) get_post_meta( $post->ID, SAITO_CE_OPEN_COUNT_META, true ); ?>
		<p>
			<?php
			printf(
				esc_html(
					/* translators: %d: nombre total d'ouvertures de case */
					_n(
						'%d ouverture de case au total (tous visiteurs confondus).',
						'%d ouvertures de case au total (tous visiteurs confondus).',
						$open_count,
						'saito-calendar-event'
					)
				),
				(int) $open_count
			);
			?>
		</p>
		<p>
			<a href="<?php echo esc_url( $export_url ); ?>" class="button"><?php esc_html_e( 'Exporter ce calendrier (JSON)', 'saito-calendar-event' ); ?></a>
		</p>
		<p>
			<label for="saito-ce-import-file" class="screen-reader-text"><?php esc_html_e( 'Fichier à importer', 'saito-calendar-event' ); ?></label>
			<input type="file" id="saito-ce-import-file" accept="application/json,.json">
			<button type="button" class="button" id="saito-ce-import-cases"><?php esc_html_e( 'Importer', 'saito-calendar-event' ); ?></button>
			<br>
			<span class="description"><?php esc_html_e( 'Remplace toutes les cases par celles du fichier importé. Pensez à enregistrer ensuite.', 'saito-calendar-event' ); ?></span>
			<br>
			<span id="saito-ce-import-feedback"></span>
		</p>
		<p>
			<a href="<?php echo esc_url( $duplicate_url ); ?>" class="button">
				<?php esc_html_e( 'Dupliquer ce calendrier', 'saito-calendar-event' ); ?>
			</a>
			<span class="description"><?php esc_html_e( '(pratique pour réutiliser un calendrier d’une année sur l’autre)', 'saito-calendar-event' ); ?></span>
		</p>
		<?php
	}

	/**
	 * Duplique un calendrier (réglages + cases) en un nouveau brouillon, pour
	 * réutiliser une configuration d'une année sur l'autre sans repasser par
	 * l'export/import JSON manuel.
	 */
	public static function handle_duplicate() {
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		check_admin_referer( 'saito_ce_duplicate_' . $post_id );

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'saito-calendar-event' ) );
		}

		$source = get_post( $post_id );
		if ( ! $source || SAITO_CE_POST_TYPE !== $source->post_type ) {
			wp_die( esc_html__( 'Calendrier introuvable.', 'saito-calendar-event' ) );
		}

		$new_id = wp_insert_post(
			array(
				'post_type'   => SAITO_CE_POST_TYPE,
				'post_status' => 'draft',
				/* translators: %s: titre du calendrier d'origine. */
				'post_title'  => sprintf( __( '%s (copie)', 'saito-calendar-event' ), $source->post_title ),
			),
			true
		);

		if ( is_wp_error( $new_id ) ) {
			wp_die( esc_html( $new_id->get_error_message() ) );
		}

		update_post_meta( $new_id, SAITO_CE_CASES_META, get_post_meta( $post_id, SAITO_CE_CASES_META, true ) );
		update_post_meta( $new_id, SAITO_CE_STYLE_META, get_post_meta( $post_id, SAITO_CE_STYLE_META, true ) );
		update_post_meta( $new_id, SAITO_CE_COLORS_META, get_post_meta( $post_id, SAITO_CE_COLORS_META, true ) );
		update_post_meta( $new_id, SAITO_CE_LOCKED_MESSAGE_META, get_post_meta( $post_id, SAITO_CE_LOCKED_MESSAGE_META, true ) );
		update_post_meta( $new_id, SAITO_CE_TEXTS_META, get_post_meta( $post_id, SAITO_CE_TEXTS_META, true ) );
		// Le compteur d'ouvertures n'est volontairement pas dupliqué : la copie
		// est un nouveau calendrier, pas encore ouvert par personne.

		wp_safe_redirect( get_edit_post_link( $new_id, 'raw' ) );
		exit;
	}

	/**
	 * Télécharge la configuration (style + cases) d'un calendrier en JSON,
	 * pour la réimporter dans un autre calendrier (même site ou un autre
	 * site utilisant ce plugin) sans tout ressaisir à la main.
	 */
	public static function handle_export() {
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		check_admin_referer( 'saito_ce_export_' . $post_id );

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'saito-calendar-event' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post || SAITO_CE_POST_TYPE !== $post->post_type ) {
			wp_die( esc_html__( 'Calendrier introuvable.', 'saito-calendar-event' ) );
		}

		$cases = get_post_meta( $post_id, SAITO_CE_CASES_META, true );
		$style = get_post_meta( $post_id, SAITO_CE_STYLE_META, true );

		$payload = array(
			'plugin'  => 'saito-calendar-event',
			'version' => 1,
			'title'   => $post->post_title,
			'style'   => $style ? $style : 'noel',
			'cases'   => is_array( $cases ) ? array_values( $cases ) : array(),
		);

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="calendrier-' . sanitize_title( $post->post_title ) . '.json"' );
		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/**
	 * Grille de cases façon "bento" + une modale par case pour la saisie
	 * complète : un grand tableau à 8 colonnes était illisible sur petit
	 * écran (le reproche initial). Chaque carte n'affiche qu'un résumé
	 * (jour, date, code, miniature) ; cliquer dessus ouvre sa modale, qui
	 * contient les véritables champs du formulaire (mêmes `name`, donc
	 * toujours soumis avec le reste du post même quand la modale est
	 * fermée/masquée).
	 */
	private static function render_cases_tab( $post ) {
		$cases = get_post_meta( $post->ID, SAITO_CE_CASES_META, true );
		if ( ! is_array( $cases ) || empty( $cases ) ) {
			$cases = self::default_cases();
		}
		?>
		<p class="description">
			<?php esc_html_e( 'Une case par jour. Cliquez sur une case pour renseigner sa date de déblocage, son code promo, etc. La case n’est révélable par les visiteurs qu’à partir de sa date (vérifié côté serveur). Le code promo doit déjà exister dans WooCommerce : ce plugin se contente de l’appliquer au panier au clic.', 'saito-calendar-event' ); ?>
		</p>
		<p class="description saito-ce-dnd-hint">
			<?php esc_html_e( 'Astuce : glissez une case sur une autre pour échanger leur contenu (code promo, produit, textes, image). Les jours et les dates restent à leur place. Les cases dont la date est passée ne se déplacent pas.', 'saito-calendar-event' ); ?>
		</p>
		<div class="saito-ce-admin-grid" id="saito-ce-cases-grid">
			<?php foreach ( $cases as $index => $case ) : ?>
				<?php self::render_case_card( $index, $case ); ?>
			<?php endforeach; ?>
		</div>
		<p class="saito-ce-dnd-feedback" role="status" aria-live="polite"></p>
		<p>
			<button type="button" class="button" id="saito-ce-add-case"><?php esc_html_e( '+ Ajouter une case', 'saito-calendar-event' ); ?></button>
		</p>

		<div id="saito-ce-cases-modals">
			<?php foreach ( $cases as $index => $case ) : ?>
				<?php self::render_case_modal( $index, $case ); ?>
			<?php endforeach; ?>
		</div>

		<script type="text/html" id="saito-ce-card-template">
			<?php self::render_case_card( '__INDEX__', array() ); ?>
		</script>
		<script type="text/html" id="saito-ce-modal-template">
			<?php self::render_case_modal( '__INDEX__', array() ); ?>
		</script>
		<?php
	}

	/**
	 * Vignette cliquable résumant une case dans la grille (jour, date, code,
	 * miniature). Tenue à jour en direct par le JS quand les champs de la
	 * modale correspondante changent.
	 */
	private static function render_case_card( $index, $case ) {
		$case        = wp_parse_args( $case, self::empty_case( $index ) );
		$modal_id    = 'saito-ce-modal-' . $index;
		$image_url   = $case['image_id'] ? wp_get_attachment_image_url( (int) $case['image_id'], 'thumbnail' ) : '';
		$unlock_date = trim( (string) $case['unlock_date'] );
		$coupon_code = trim( (string) $case['coupon_code'] );

		// Deux états valent la peine d'être visibles sans ouvrir la modale :
		// - "Expirée" : date strictement passée, donc case plus ouvrable du
		//   tout côté visiteur (cf. Saito_CE_Public::is_case_expired()) ;
		//   comparaison simplifiée en chaînes "Y-m-d", comme pour l'aperçu
		//   plus bas.
		// - "Date manquante" : un code promo a été saisi mais sans date, donc
		//   une case qui ne sera jamais débloquable (is_unlocked() exige une
		//   date non vide) — probablement un oubli plutôt qu'un choix.
		$today         = current_time( 'Y-m-d' );
		$is_expired    = '' !== $unlock_date && $unlock_date < $today;
		$is_incomplete = '' !== $coupon_code && '' === $unlock_date;
		?>
		<button
			type="button"
			class="saito-ce-admin-card<?php echo $is_expired ? ' is-past' : ''; ?>"
			data-case-index="<?php echo esc_attr( $index ); ?>"
			data-modal="<?php echo esc_attr( $modal_id ); ?>"
		>
			<span class="saito-ce-admin-card-grip" aria-hidden="true"></span>
			<span class="saito-ce-admin-card-day"><?php echo esc_html( '' !== (string) $case['day'] ? $case['day'] : '?' ); ?></span>
			<span class="saito-ce-admin-card-thumb"<?php echo $image_url ? '' : ' hidden'; ?>>
				<img src="<?php echo esc_url( $image_url ); ?>" alt="">
			</span>
			<span class="saito-ce-admin-card-date"><?php echo $unlock_date ? esc_html( $unlock_date ) : esc_html__( 'Pas de date', 'saito-calendar-event' ); ?></span>
			<span class="saito-ce-admin-card-coupon"<?php echo $coupon_code ? '' : ' hidden'; ?>><?php echo esc_html( $coupon_code ); ?></span>
			<span class="saito-ce-admin-card-expired"<?php echo $is_expired ? '' : ' hidden'; ?>><?php esc_html_e( 'Expirée', 'saito-calendar-event' ); ?></span>
			<span class="saito-ce-admin-card-warning"<?php echo $is_incomplete ? '' : ' hidden'; ?>><?php esc_html_e( 'Date manquante', 'saito-calendar-event' ); ?></span>
		</button>
		<?php
	}

	/**
	 * Modale (masquée par défaut) portant les vrais champs d'une case. Les
	 * `name="saito_ce_cases[N][...]"` sont strictement ceux de l'ancien
	 * tableau : le format soumis au serveur (et `save()`) ne change pas.
	 */
	private static function render_case_modal( $index, $case ) {
		$case      = wp_parse_args( $case, self::empty_case( $index ) );
		$name      = 'saito_ce_cases[' . $index . ']';
		$modal_id  = 'saito-ce-modal-' . $index;
		$image_url = $case['image_id'] ? wp_get_attachment_image_url( (int) $case['image_id'], 'thumbnail' ) : '';
		$product   = ( $case['product_id'] && saito_ce_is_woocommerce_active() ) ? wc_get_product( (int) $case['product_id'] ) : null;
		?>
		<div class="saito-ce-admin-modal" id="<?php echo esc_attr( $modal_id ); ?>" data-case-index="<?php echo esc_attr( $index ); ?>" hidden>
			<div class="saito-ce-admin-modal-overlay"></div>
			<div class="saito-ce-admin-modal-content" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Réglages de la case', 'saito-calendar-event' ); ?>">
				<button type="button" class="saito-ce-admin-modal-close" aria-label="<?php esc_attr_e( 'Fermer', 'saito-calendar-event' ); ?>">&times;</button>
				<table class="form-table saito-ce-case-fields">
					<tr>
						<th><label><?php esc_html_e( 'Jour', 'saito-calendar-event' ); ?></label></th>
						<td><input type="number" min="1" step="1" class="saito-ce-field-day" name="<?php echo esc_attr( $name ); ?>[day]" value="<?php echo esc_attr( $case['day'] ); ?>"></td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Date de déblocage', 'saito-calendar-event' ); ?></label></th>
						<td>
							<?php
							// `min` empêche de choisir une date déjà passée via le
							// sélecteur natif. Il n'est PAS posé sur une case qui a
							// déjà une date passée (ex. jour 1 d'un calendrier en
							// cours) : le navigateur valide aussi les champs masqués
							// (modale fermée) et bloquait alors silencieusement
							// "Mettre à jour", sans pouvoir afficher son message
							// sur un champ invisible.
							$today_ymd    = current_time( 'Y-m-d' );
							$unlock_ymd   = trim( (string) $case['unlock_date'] );
							$date_is_past = '' !== $unlock_ymd && $unlock_ymd < $today_ymd;
							?>
							<input type="date" class="saito-ce-field-date" name="<?php echo esc_attr( $name ); ?>[unlock_date]" value="<?php echo esc_attr( $case['unlock_date'] ); ?>"<?php echo $date_is_past ? '' : ' min="' . esc_attr( $today_ymd ) . '"'; ?>>
						</td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Code promo', 'saito-calendar-event' ); ?></label></th>
						<td>
							<input type="text" class="saito-ce-field-coupon" name="<?php echo esc_attr( $name ); ?>[coupon_code]" value="<?php echo esc_attr( $case['coupon_code'] ); ?>" placeholder="AVENT-01" autocomplete="off">
							<p class="description saito-ce-coupon-check" hidden></p>
							<p class="description saito-ce-coupon-locked-notice" hidden><?php esc_html_e( 'Date de déblocage déjà passée : code verrouillé pour éviter de casser un code peut-être déjà vu par des client·e·s.', 'saito-calendar-event' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Produit lié', 'saito-calendar-event' ); ?></label></th>
						<td>
							<?php if ( self::use_product_search() ) : ?>
								<select
									class="saito-ce-product-search saito-ce-product-id"
									name="<?php echo esc_attr( $name ); ?>[product_id]"
									data-placeholder="<?php esc_attr_e( 'Rechercher un produit par son nom ou sa référence…', 'saito-calendar-event' ); ?>"
									style="width:100%;"
								>
									<option value=""></option>
									<?php if ( $case['product_id'] ) : ?>
										<?php $selected_image = $product && $product->get_image_id() ? wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) : ''; ?>
										<option value="<?php echo esc_attr( $case['product_id'] ); ?>" data-image="<?php echo esc_url( $selected_image ); ?>" selected>
											<?php echo esc_html( $product ? wp_strip_all_tags( $product->get_formatted_name() ) : sprintf( /* translators: %d: ID produit. */ __( '#%d (introuvable)', 'saito-calendar-event' ), $case['product_id'] ) ); ?>
										</option>
									<?php endif; ?>
								</select>
							<?php else : ?>
								<input type="number" min="1" step="1" class="saito-ce-product-id" name="<?php echo esc_attr( $name ); ?>[product_id]" value="<?php echo esc_attr( $case['product_id'] ? $case['product_id'] : '' ); ?>" placeholder="<?php esc_attr_e( 'ID produit', 'saito-calendar-event' ); ?>">
							<?php endif; ?>
							<p class="description saito-ce-product-hint">
								<?php esc_html_e( 'Optionnel : affiché dans la modale à la place du code brut.', 'saito-calendar-event' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Accroche (révélée)', 'saito-calendar-event' ); ?></label></th>
						<td><input type="text" class="saito-ce-field-title" name="<?php echo esc_attr( $name ); ?>[title]" value="<?php echo esc_attr( $case['title'] ); ?>" placeholder="-10% sur tout le site"></td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Description', 'saito-calendar-event' ); ?></label></th>
						<td><textarea class="saito-ce-field-description" name="<?php echo esc_attr( $name ); ?>[description]" rows="3"><?php echo esc_textarea( $case['description'] ); ?></textarea></td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Image', 'saito-calendar-event' ); ?></label></th>
						<td class="saito-ce-image-cell">
							<input type="hidden" class="saito-ce-image-id" name="<?php echo esc_attr( $name ); ?>[image_id]" value="<?php echo esc_attr( $case['image_id'] ); ?>">
							<img src="<?php echo esc_url( $image_url ); ?>" class="saito-ce-image-preview" style="max-width:60px;max-height:60px;<?php echo $image_url ? '' : 'display:none;'; ?>">
							<button type="button" class="button saito-ce-select-image"><?php esc_html_e( 'Choisir', 'saito-calendar-event' ); ?></button>
						</td>
					</tr>
				</table>
				<p class="saito-ce-admin-modal-actions">
					<button type="button" class="button button-primary saito-ce-admin-modal-done"><?php esc_html_e( 'Terminé', 'saito-calendar-event' ); ?></button>
					<button type="button" class="button-link saito-ce-remove-case"><?php esc_html_e( 'Supprimer cette case', 'saito-calendar-event' ); ?></button>
				</p>
			</div>
		</div>
		<?php
	}

	private static function empty_case( $index ) {
		return array(
			'day'         => is_numeric( $index ) ? (int) $index + 1 : '',
			'unlock_date' => '',
			'coupon_code' => '',
			'product_id'  => 0,
			'title'       => '',
			'description' => '',
			'image_id'    => 0,
		);
	}

	private static function default_cases() {
		$cases = array();
		for ( $index = 0; $index < 24; $index++ ) {
			$cases[] = self::empty_case( $index );
		}
		return $cases;
	}

	public static function save( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$style = isset( $_POST['saito_ce_style'] ) ? sanitize_key( wp_unslash( $_POST['saito_ce_style'] ) ) : 'noel';
		update_post_meta( $post_id, SAITO_CE_STYLE_META, $style );

		$locked_message = isset( $_POST['saito_ce_locked_message'] ) ? sanitize_text_field( wp_unslash( $_POST['saito_ce_locked_message'] ) ) : '';
		update_post_meta( $post_id, SAITO_CE_LOCKED_MESSAGE_META, $locked_message );

		$raw_texts = isset( $_POST['saito_ce_texts'] ) && is_array( $_POST['saito_ce_texts'] ) ? map_deep( wp_unslash( $_POST['saito_ce_texts'] ), 'sanitize_text_field' ) : array();
		$texts     = array();
		$subtitle = isset( $_POST['saito_ce_header_subtitle'] ) ? sanitize_text_field( wp_unslash( $_POST['saito_ce_header_subtitle'] ) ) : '';
		update_post_meta( $post_id, SAITO_CE_SUBTITLE_META, $subtitle );
		update_post_meta( $post_id, SAITO_CE_HEADER_META, isset( $_POST['saito_ce_show_header'] ) && '0' === $_POST['saito_ce_show_header'] ? '0' : '1' );

		foreach ( array( 'apply', 'applying', 'appliedButton', 'applied', 'error', 'expired', 'expiresToday', 'today' ) as $text_key ) {
			$texts[ $text_key ] = isset( $raw_texts[ $text_key ] ) ? sanitize_text_field( $raw_texts[ $text_key ] ) : '';
		}
		update_post_meta( $post_id, SAITO_CE_TEXTS_META, $texts );

		$raw_schedule = isset( $_POST['saito_ce_schedule'] ) && is_array( $_POST['saito_ce_schedule'] ) ? map_deep( wp_unslash( $_POST['saito_ce_schedule'] ), 'sanitize_text_field' ) : array();
		$schedule     = array(
			'start'          => isset( $raw_schedule['start'] ) ? self::sanitize_date( $raw_schedule['start'] ) : '',
			'end'            => isset( $raw_schedule['end'] ) ? self::sanitize_date( $raw_schedule['end'] ) : '',
			'before_message' => isset( $raw_schedule['before_message'] ) ? sanitize_text_field( $raw_schedule['before_message'] ) : '',
			'after_message'  => isset( $raw_schedule['after_message'] ) ? sanitize_text_field( $raw_schedule['after_message'] ) : '',
		);
		// Fin avant le début (saisie inversée) : le calendrier ne serait
		// jamais visible ; on échange plutôt que d'enregistrer un non-sens.
		if ( $schedule['start'] && $schedule['end'] && $schedule['end'] < $schedule['start'] ) {
			list( $schedule['start'], $schedule['end'] ) = array( $schedule['end'], $schedule['start'] );
		}
		update_post_meta( $post_id, SAITO_CE_SCHEDULE_META, $schedule );

		$raw_colors = isset( $_POST['saito_ce_colors'] ) && is_array( $_POST['saito_ce_colors'] ) ? map_deep( wp_unslash( $_POST['saito_ce_colors'] ), 'sanitize_text_field' ) : array();
		update_post_meta(
			$post_id,
			SAITO_CE_COLORS_META,
			array(
				'accent'      => self::sanitize_color( isset( $raw_colors['accent'] ) ? $raw_colors['accent'] : '' ),
				'accent_dark' => self::sanitize_color( isset( $raw_colors['accent_dark'] ) ? $raw_colors['accent_dark'] : '' ),
				'bg'          => self::sanitize_color( isset( $raw_colors['bg'] ) ? $raw_colors['bg'] : '' ),
				'text'        => self::sanitize_color( isset( $raw_colors['text'] ) ? $raw_colors['text'] : '' ),
				'radius'      => isset( $raw_colors['radius'] ) && '' !== $raw_colors['radius'] ? absint( $raw_colors['radius'] ) : '',
				'border'      => isset( $raw_colors['border'] ) ? self::sanitize_css_value( $raw_colors['border'] ) : '',
			)
		);

		$raw_cases = isset( $_POST['saito_ce_cases'] ) && is_array( $_POST['saito_ce_cases'] ) ? wp_unslash( $_POST['saito_ce_cases'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- chaque champ est nettoyé ci-dessous selon son type (texte, texte multiligne, entier).
		$cases     = array();

		foreach ( $raw_cases as $raw_case ) {
			$day = isset( $raw_case['day'] ) ? absint( $raw_case['day'] ) : 0;
			if ( ! $day ) {
				continue; // Ligne vide (ajoutée puis non remplie) : on l'ignore.
			}
			$cases[] = array(
				'day'         => $day,
				'unlock_date' => isset( $raw_case['unlock_date'] ) ? sanitize_text_field( $raw_case['unlock_date'] ) : '',
				'coupon_code' => isset( $raw_case['coupon_code'] ) ? strtoupper( sanitize_text_field( $raw_case['coupon_code'] ) ) : '',
				'product_id'  => isset( $raw_case['product_id'] ) ? absint( $raw_case['product_id'] ) : 0,
				'title'       => isset( $raw_case['title'] ) ? sanitize_text_field( $raw_case['title'] ) : '',
				'description' => isset( $raw_case['description'] ) ? sanitize_textarea_field( $raw_case['description'] ) : '',
				'image_id'    => isset( $raw_case['image_id'] ) ? absint( $raw_case['image_id'] ) : 0,
			);
		}

		usort(
			$cases,
			function ( $a, $b ) {
				return $a['day'] <=> $b['day'];
			}
		);

		update_post_meta( $post_id, SAITO_CE_CASES_META, $cases );
	}

	/**
	 * Date "Y-m-d" valide (format soumis par <input type="date">) ou chaîne vide.
	 */
	private static function sanitize_date( $value ) {
		$value = trim( (string) $value );
		$date  = DateTime::createFromFormat( '!Y-m-d', $value );
		return $date && $date->format( 'Y-m-d' ) === $value ? $value : '';
	}

	/**
	 * Couleur hexa valide (#fff / #ffffff) ou chaîne vide (= "non défini",
	 * on retombe alors sur l'habillage de base côté front).
	 */
	private static function sanitize_color( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		$sanitized = sanitize_hex_color( $value );
		return $sanitized ? $sanitized : '';
	}

	/**
	 * Valeur CSS libre (ex. déclaration "border") saisie par un admin de
	 * confiance : on retire seulement ce qui permettrait de sortir de
	 * l'attribut style="" ou d'injecter une nouvelle règle/balise.
	 */
	private static function sanitize_css_value( $value ) {
		$value = trim( (string) $value );
		return preg_replace( '/[^a-zA-Z0-9#%.,\s-]/', '', $value );
	}
}
