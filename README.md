# Saito Calendar Event

Plugin WordPress : calendriers à cases déblocables par date (calendrier de
l'Avent, compte à rebours avant un lancement, opération « 12 jours »…).
Chaque case révèle, le jour J uniquement, un titre, une description, une
image, un produit lié et — avec WooCommerce — un code promo à copier ou à
ajouter au panier en un clic.

Troisième plugin de la famille Saito, après
[Saito Navi](https://wordpress.org/plugins/saito-navi/) et
[Saito FAQ](https://wordpress.org/plugins/saito-faq/).

## Fonctionnalités

- **Ouverture au jour exact, vérifiée côté serveur** : le contenu et le code
  promo d'une case ne sont jamais envoyés au navigateur avant sa date ;
  après sa date, la case est définitivement fermée. Code revérifié à l'ajout
  au panier, requêtes limitées en débit.
- **Visiteur** : porte qui s'ouvre, confettis aux couleurs de l'habillage,
  modale avec bouton « Copier » et « Ajouter la réduction à mon panier »,
  photo de la récompense dans les cases ouvertes, barre de progression,
  navigation clavier, respect du « mouvement réduit ».
- **Sept habillages** : Noël, Neutre, Éditorial, Étincelles (rouge et or) et
  trois Floral (bleu et argent, blanc et or, duo). Les quatre derniers ont des
  portes de tailles variées (le 1 et le 24 en grandes portes, disposition
  fixe pour un calendrier de 24 cases) et un en-tête optionnel (titre du
  calendrier + sous-titre). Couleurs, arrondis et bordures personnalisables,
  aperçu en direct.
- **Éditeur** : grille de cases, une modale par case (date, code promo,
  produit recherché par nom, textes, image), glisser-déposer pour échanger le
  contenu de deux cases, liste et vérification des codes promo WooCommerce,
  programmation de la période d'affichage, textes visiteur personnalisables,
  export/import JSON, duplication d'un calendrier.
- **Insertion** : bloc « Calendrier à cases » ou shortcode
  `[saito_calendar_event id="123"]`.

WooCommerce est optionnel : sans lui, les cases révèlent leur texte et leur
image.

## Installation

1. Copier le dossier `saito-calendar-event/` dans `wp-content/plugins/`.
2. Activer le plugin depuis le menu **Extensions**.

## Développement

```bash
composer install && vendor/bin/phpcs          # WordPress Coding Standards
for t in tests/test-*.php; do php "$t"; done  # tests sans WordPress
npm install && npm run lint                   # ESLint + Stylelint
./scripts/build-wporg-release.sh              # zip WordPress.org (+ Plugin Check si la stack Docker de navi-wordpress tourne)
```

Les visuels de la fiche WordPress.org (bannières, icônes, captures) sont dans
`.wordpress-org/assets/`, à copier dans le dossier `assets/` du dépôt SVN.

## Licence

GPL v2 ou ultérieure. Bibliothèques et polices embarquées : voir la section
« Third-party libraries and fonts » de `readme.txt`.
