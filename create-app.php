<?php
/**
 * create-app.php -- creates a new app folder with a base PWA structure.
 * POST { name } -> creates <SLUG>/public/{index.html, manifest.json, service-worker.js,
 * offline.html, icons/, css/style.css, js/app.js}, then returns { ok, id, name, url }.
 * The front end then re-fetches apps.php: the logo (auto-generated when missing) shows.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

// CSRF protection (double-submit cookie): the token in the X-CSRF-Token header
// must match the phonefake_csrf cookie issued by apps.php. A cross-site attacker
// can neither read the cookie nor set a custom header, so forged POSTs are blocked.
$cookieToken = $_COOKIE['phonefake_csrf'] ?? '';
$headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if ($cookieToken === '' || !hash_equals($cookieToken, $headerToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'Requête non autorisée (jeton CSRF invalide).']);
    exit;
}

$base = __DIR__;
// Reserved names (technical folders / common app subfolders)
$reserved = ['node_modules', 'git', 'idea', 'vscode', 'vendor', 'memory', 'assets',
             'public', 'src', 'lib', 'data', 'scripts', 'tests', 'certs', 'dist', 'build'];

$rawName = trim((string)($_POST['name'] ?? ''));
if ($rawName === '') {
    echo json_encode(['error' => 'Le nom est vide.']);
    exit;
}
if (mb_strlen($rawName) > 60) $rawName = mb_substr($rawName, 0, 60);

// Slug: transliterate accents (deterministic, locale-independent table),
// keep only [A-Za-z0-9], separate with '-', uppercase.
$accents = [
    'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a','ç'=>'c',
    'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e','ì'=>'i','í'=>'i','î'=>'i','ï'=>'i',
    'ñ'=>'n','ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ø'=>'o',
    'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','ý'=>'y','ÿ'=>'y',
    'ß'=>'ss','æ'=>'ae','œ'=>'oe',
];
$slug = strtr(mb_strtolower($rawName, 'UTF-8'), $accents);
$slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
$slug = strtoupper(trim($slug, '-'));

if ($slug === '') {
    echo json_encode(['error' => 'Nom invalide : utilise au moins une lettre ou un chiffre.']);
    exit;
}
if (in_array(strtolower($slug), $reserved, true)) {
    echo json_encode(['error' => 'Ce nom est réservé, choisis-en un autre.']);
    exit;
}

$path = $base . DIRECTORY_SEPARATOR . $slug;
if (file_exists($path)) {
    echo json_encode(['error' => 'Un dossier « ' . $slug . ' » existe déjà.']);
    exit;
}

// --- Create the folder tree ---
$dirs = [$path, "$path/public", "$path/public/icons", "$path/public/css", "$path/public/js"];
foreach ($dirs as $d) {
    if (!is_dir($d) && !@mkdir($d, 0775, true)) {
        echo json_encode(['error' => 'Impossible de créer le dossier (droits d\'écriture ?).']);
        exit;
    }
}

// --- Template substitutions ---
$nameHtml = htmlspecialchars($rawName, ENT_QUOTES, 'UTF-8');
$nameJs   = json_encode($rawName, JSON_UNESCAPED_UNICODE);
$repl = ['{{NAME}}' => $nameHtml, '{{SLUG}}' => $slug, '{{NAME_JS}}' => $nameJs];
$tpl = function ($s) use ($repl) { return strtr($s, $repl); };

// --- manifest.json (icons point to missing files -> auto-generated logo) ---
$manifest = [
    'name'             => $rawName,
    'short_name'       => $rawName,
    'start_url'        => './',
    'scope'            => './',
    'display'          => 'standalone',
    'background_color' => '#0d0d13',
    'theme_color'      => '#5b6cff',
    'icons'            => [
        ['src' => 'icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
        ['src' => 'icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
    ],
];

// --- Templates (nowdoc: no interpolation, placeholders replaced afterwards) ---
$indexHtml = $tpl(<<<'HTML'
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#5b6cff">
<title>{{NAME}}</title>
<link rel="manifest" href="manifest.json">
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<main class="welcome">
  <div class="check">&#10003;</div>
  <h1>{{NAME}}</h1>
  <p class="lead">La structure de base est cr&eacute;&eacute;e &mdash; vous pouvez commencer &agrave; coder&nbsp;!</p>

  <p class="pwa">&#128241; Cette application est <strong>pr&ecirc;te &agrave; devenir une PWA</strong> : le manifeste, le service worker et la page hors-ligne sont d&eacute;j&agrave; configur&eacute;s. Elle est installable et fonctionne hors connexion une fois charg&eacute;e.</p>

  <section>
    <h2>&#128193; Structure cr&eacute;&eacute;e</h2>
    <pre class="tree">{{SLUG}}/
&#9492;&#9472; public/
   &#9500;&#9472; index.html        &#8592; cette page (votre point d'entr&eacute;e)
   &#9500;&#9472; manifest.json     &#8592; m&eacute;tadonn&eacute;es PWA (nom, ic&ocirc;nes, couleurs)
   &#9500;&#9472; service-worker.js &#8592; cache &amp; mode hors-ligne
   &#9500;&#9472; offline.html      &#8592; page affich&eacute;e sans connexion
   &#9500;&#9472; icons/            &#8592; d&eacute;posez icon-192.png / icon-512.png
   &#9500;&#9472; css/style.css     &#8592; vos styles
   &#9492;&#9472; js/app.js         &#8592; votre JavaScript</pre>
  </section>

  <section>
    <h2>&#128640; Par o&ugrave; commencer</h2>
    <ul>
      <li>Remplacez le contenu de <code>public/index.html</code> par votre interface.</li>
      <li>&Eacute;crivez vos styles dans <code>css/style.css</code> et votre logique dans <code>js/app.js</code>.</li>
      <li>Ajoutez <code>icon-192.png</code> et <code>icon-512.png</code> dans <code>icons/</code> &mdash; sinon PhoneFake affiche un logo g&eacute;n&eacute;r&eacute; au nom du dossier.</li>
      <li>Adaptez <code>manifest.json</code> (nom, couleurs) &agrave; votre projet.</li>
    </ul>
  </section>

  <p class="foot">G&eacute;n&eacute;r&eacute; automatiquement par <strong>PhoneFake</strong>.</p>
</main>
<script src="js/app.js"></script>
</body>
</html>
HTML);

$offlineHtml = $tpl(<<<'HTML'
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{NAME}} &mdash; Hors-ligne</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<main class="welcome">
  <div class="check" style="background:linear-gradient(135deg,#8a93a6,#5a6072);">&#9889;</div>
  <h1>Hors connexion</h1>
  <p class="lead">{{NAME}} n'est pas disponible pour le moment.</p>
  <p class="pwa">V&eacute;rifiez votre connexion r&eacute;seau puis r&eacute;essayez. Les pages d&eacute;j&agrave; visit&eacute;es restent accessibles gr&acirc;ce au cache.</p>
</main>
</body>
</html>
HTML);

$serviceWorker = $tpl(<<<'JS'
// Service worker minimal : pré-cache + repli hors-ligne. Adaptez la liste CORE.
const CACHE = '{{SLUG}}-v1';
const CORE = ['./', './index.html', './manifest.json', './css/style.css', './js/app.js', './offline.html'];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(CORE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  if (e.request.method !== 'GET') return;
  e.respondWith(
    caches.match(e.request).then((cached) =>
      cached || fetch(e.request).catch(() => caches.match('./offline.html'))
    )
  );
});
JS);

$appJs = $tpl(<<<'JS'
// Point d'entrée JavaScript de l'appli. Écrivez votre code ici.
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('service-worker.js').catch(console.error);
  });
}
console.log({{NAME_JS}} + ' — prêt. Éditez public/ pour commencer.');
JS);

$css = <<<'CSS'
:root { color-scheme: dark; }
* { box-sizing: border-box; }
body {
  margin: 0; min-height: 100vh;
  font-family: system-ui, "Segoe UI", Roboto, sans-serif;
  background: radial-gradient(circle at 50% 0%, #1a1a2e, #0d0d13);
  color: #e6eaf3;
  display: flex; align-items: flex-start; justify-content: center;
  padding: 32px 20px;
}
.welcome { max-width: 560px; width: 100%; }
.check {
  width: 56px; height: 56px; border-radius: 50%;
  background: linear-gradient(135deg, #5b6cff, #8a3ffc);
  display: flex; align-items: center; justify-content: center;
  font-size: 28px; color: #fff; margin: 0 auto 16px;
  box-shadow: 0 10px 30px rgba(91, 108, 255, .4);
}
h1 { text-align: center; margin: 0 0 6px; font-size: 1.6rem; }
.lead { text-align: center; color: #b9c2d6; margin: 0 0 20px; font-size: 1.05rem; }
.pwa {
  background: rgba(91, 108, 255, .12);
  border: 1px solid rgba(91, 108, 255, .3);
  border-radius: 12px; padding: 14px 16px; font-size: .92rem; line-height: 1.55;
}
section { margin-top: 22px; }
h2 { font-size: 1.05rem; color: #cbd0dc; margin: 0 0 10px; }
.tree {
  background: #0a0a12; border: 1px solid rgba(255, 255, 255, .08);
  border-radius: 10px; padding: 14px; overflow-x: auto;
  font-size: .82rem; line-height: 1.5; color: #aeb6c8;
}
ul { margin: 0; padding-left: 20px; line-height: 1.7; font-size: .92rem; color: #cbd0dc; }
code { background: rgba(255, 255, 255, .08); padding: 1px 6px; border-radius: 4px; font-size: .85em; }
.foot { text-align: center; color: #7a83a0; font-size: .8rem; margin-top: 28px; }
CSS;

// --- Write the files ---
$files = [
    "$path/public/index.html"        => $indexHtml,
    "$path/public/offline.html"      => $offlineHtml,
    "$path/public/manifest.json"     => json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    "$path/public/service-worker.js" => $serviceWorker,
    "$path/public/css/style.css"     => $css,
    "$path/public/js/app.js"         => $appJs,
];
foreach ($files as $file => $content) {
    if (file_put_contents($file, $content) === false) {
        echo json_encode(['error' => 'Dossier créé mais écriture d\'un fichier impossible.']);
        exit;
    }
}
// Keep icons/ tracked / visible even when empty
@file_put_contents("$path/public/icons/.gitkeep", '');

echo json_encode([
    'ok'   => true,
    'id'   => $slug,
    'name' => $rawName,
    'url'  => $slug . '/public/index.html',
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
