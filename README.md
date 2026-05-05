# PhoneFake

Simulateur mobile local pour tester ses applications web (responsive, PWA, etc.) en iPhone, Android, iPad ou tablette Android — sans rien installer.

## Utilisation

1. Pose ce dossier à la racine de ton serveur web local (Laragon, MAMP, XAMPP, serveur Node, etc.)
2. Ajoute tes propres applis dans des sous-dossiers à côté de `index.html` (chacune avec son `index.html`)
3. Ouvre `index.html` dans ton navigateur — les applis apparaissent dans le sélecteur

Aucun service en ligne, tout est local.

## Fonctionnalités

- **4 formats d'appareil** : iPhone (notch), Android (punch-hole), iPad, Tablette Android — dimensions natives
- **Rotation** portrait / paysage
- **Mode comparaison** : 2 appareils côte à côte synchronisés
- **Mode desktop** : ouvrir une appli dans un nouvel onglet du navigateur, en pleine largeur
- **Multilingue** : 🇫🇷 FR · 🇬🇧 EN · 🇪🇸 ES · 🇮🇹 IT · 🇩🇪 DE
- **Accessibilité** : taille texte, contraste élevé, police dyslexie (OpenDyslexic), réduction des animations, focus renforcé
- **Raccourcis clavier** : `1`/`2`/`3`/`4` (modèles), `R` (rotation), `C` (comparer), `H` (aide), `A` (accessibilité), `Échap` (fermer)

## Pré-requis

- Un serveur web local servant `apps.php` (PHP requis pour lister automatiquement les sous-dossiers)
- Navigateur récent (Chrome 105+, Firefox 121+, Safari 16+ — pour `:has()` et `container queries`)

## Licence

MIT — fais-en ce que tu veux.
