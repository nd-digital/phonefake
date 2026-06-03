<div align="center">

# 📱 PhoneFake

### Teste tes applis web dans un vrai cadre mobile — en local, sans rien installer.

[![License: MIT](https://img.shields.io/badge/License-MIT-5b6cff.svg)](LICENSE)
[![Version](https://img.shields.io/badge/version-1.0.2-8a3ffc.svg)](https://github.com/nd-digital/phonefake/releases)
[![Vanilla JS](https://img.shields.io/badge/vanilla-JS%20%2B%20PHP-success.svg)]()
[![No build](https://img.shields.io/badge/build-aucun-blue.svg)]()

<img src="docs/demo.gif" alt="Démo animée de PhoneFake" width="800">

</div>

---

## Pourquoi PhoneFake ?

Tester le rendu mobile d'une appli web, c'est souvent : redimensionner la fenêtre à la main, jongler avec les devtools, ou déployer pour voir sur son téléphone. **PhoneFake** te donne un **appareil mobile réaliste directement dans ton navigateur** : notch, barre d'état, navigation, multitâche — et tes applis tournent dedans, en vrai, via des `<iframe>`.

- 🔌 **Zéro installation** — un dossier, un serveur local, c'est tout
- 🏠 **100 % local** — aucun service en ligne, rien ne sort de ta machine
- 🆓 **Open-source (MIT)** — utilise, modifie, partage

---

## ✨ Fonctionnalités

| | |
|---|---|
| 📱 **4 appareils** | iPhone (notch), Android (punch-hole), iPad, tablette Android — dimensions natives |
| 🔄 **Rotation** | Bascule portrait ↔ paysage en un clic |
| ⇆ **Comparaison** | Deux appareils côte à côte, synchronisés |
| ➕ **Création d'appli** | Génère un squelette PWA complet depuis l'interface |
| 🎨 **Logos auto-générés** | Une appli sans icône ? Un logo est créé à partir de son nom |
| 🌍 **5 langues** | FR · EN · ES · IT · DE |
| ♿ **Accessibilité** | Taille texte, contraste, police dyslexie, animations réduites |
| ⌨️ **Raccourcis** | `1`-`4` appareils · `R` rotation · `C` comparer · `H` aide · `A` accessibilité |

---

## 🖼️ Aperçu

<div align="center">

| Appli ouverte | Mode comparaison |
|:---:|:---:|
| <img src="docs/02-app-open-weather.png" alt="Une appli ouverte dans le simulateur" width="380"> | <img src="docs/03-compare-mode.png" alt="Mode comparaison, deux appareils côte à côte" width="380"> |
| **Création d'appli** | **Accessibilité** |
| <img src="docs/04-create-app-modal.png" alt="Modale de création d'une nouvelle appli" width="380"> | <img src="docs/05-accessibility.png" alt="Panneau des réglages d'accessibilité" width="380"> |

</div>

---

## 🚀 Démarrage

1. Place le dossier à la racine de ton serveur web local (Laragon, MAMP, XAMPP, serveur Node…)
2. Ajoute tes applis dans des sous-dossiers à côté de `index.html` (chacune avec son point d'entrée)
3. Ouvre `index.html` dans ton navigateur — tes applis apparaissent automatiquement

> 💡 Pas d'icône dans ton appli ? PhoneFake génère un logo à partir du nom du dossier.
> Tu peux aussi cliquer **➕ Appli** pour générer un squelette PWA prêt à coder.

### Pré-requis
- Un serveur servant `apps.php` (PHP, pour lister les sous-dossiers)
- Un navigateur récent (Chrome 105+, Firefox 121+, Safari 16+ — `:has()` & container queries)

---

## 🧩 Comment ça marche

- **`apps.php`** scanne les sous-dossiers et renvoie la liste des applis (nom, icône, point d'entrée) en JSON. Détection intelligente : manifeste PWA, icônes conventionnelles, redirections, override via `phonefake.json`.
- **`index.html`** est le simulateur complet (HTML/CSS/JS vanilla, zéro dépendance, zéro build) : il met chaque appli dans une `<iframe>` mise à l'échelle, avec un mini-OS mobile (accueil, multitâche, navigation).

---

## 📄 Licence

[MIT](LICENSE) — fais-en ce que tu veux.

---

<div align="center">

Créé par **Nicolas Degabriel**
🌐 [nicolas-degabriel.digital](https://nicolas-degabriel.digital) · 🐙 [github.com/nd-digital](https://github.com/nd-digital)

⭐ Si PhoneFake t'est utile, mets une étoile au repo !

</div>
