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
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// CSRF protection (double-submit cookie): the token in the X-CSRF-Token header
// must match the phonefake_csrf cookie issued by apps.php. A cross-site attacker
// can neither read the cookie nor set a custom header, so forged POSTs are blocked.
$cookieToken = $_COOKIE['phonefake_csrf'] ?? '';
$headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if ($cookieToken === '' || !hash_equals($cookieToken, $headerToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized request (invalid CSRF token).']);
    exit;
}

$base = __DIR__;
// Reserved names (technical folders / common app subfolders)
$reserved = ['node_modules', 'git', 'idea', 'vscode', 'vendor', 'memory', 'assets',
             'public', 'src', 'lib', 'data', 'scripts', 'tests', 'certs', 'dist', 'build'];

// Normalise any user text to valid UTF-8. A single stray non-UTF-8 byte (e.g. a
// Windows-1252 "é") would make the generated README invalid, tricking GitHub into
// Latin-1 rendering = mojibake on the (correct) UTF-8 template text.
function pf_utf8($s) {
    $s = (string)$s;
    return mb_check_encoding($s, 'UTF-8') ? $s : mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
}
// Return the full LICENSE text for a license key. MIT is embedded (offline-safe);
// the others are fetched from GitHub's public Licenses API, with a short notice as
// a graceful fallback when offline / the request fails.
function pf_license_text($key, $year, $author, $displayName) {
    if ($key === 'mit') {
        $tpl = <<<'TXT'
MIT License

Copyright (c) {{YEAR}} {{AUTHOR}}

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
TXT;
        return strtr($tpl, ['{{YEAR}}' => $year, '{{AUTHOR}}' => $author]);
    }
    $ctx = stream_context_create(['http' => [
        'method'  => 'GET',
        'header'  => "User-Agent: PhoneFake\r\nAccept: application/vnd.github+json\r\n",
        'timeout' => 6,
    ]]);
    $raw = @file_get_contents('https://api.github.com/licenses/' . rawurlencode($key), false, $ctx);
    if ($raw !== false) {
        $data = json_decode($raw, true);
        if (is_array($data) && !empty($data['body'])) {
            return str_replace(
                ['[year]', '[fullname]', '[email]', '<year>', '<name of author>', '[yyyy]', '[name of copyright owner]'],
                [$year, $author, '', $year, $author, $year, $author],
                $data['body']
            );
        }
    }
    return "$displayName License\n\nCopyright (c) $year $author\n\n"
        . "This project is licensed under the $displayName license.\n"
        . "Full text: https://choosealicense.com/licenses/$key/\n";
}
$rawName = trim(pf_utf8($_POST['name'] ?? ''));
if ($rawName === '') {
    echo json_encode(['error' => 'The name is empty.']);
    exit;
}
if (mb_strlen($rawName) > 60) $rawName = mb_substr($rawName, 0, 60);

// Optional project fields (feed the README / LICENSE / git). All are sanitised.
$rawDesc   = trim(strip_tags(pf_utf8($_POST['description'] ?? '')));
if (mb_strlen($rawDesc) > 200) $rawDesc = mb_substr($rawDesc, 0, 200);
$rawLongDesc = trim(strip_tags(pf_utf8($_POST['long_description'] ?? '')));
if (mb_strlen($rawLongDesc) > 2000) $rawLongDesc = mb_substr($rawLongDesc, 0, 2000);
$rawAuthor = trim(strip_tags(pf_utf8($_POST['author'] ?? '')));
if (mb_strlen($rawAuthor) > 80) $rawAuthor = mb_substr($rawAuthor, 0, 80);
$license   = strtolower(trim((string)($_POST['license'] ?? 'none')));
// Supported licenses (keys match GitHub's Licenses API: api.github.com/licenses/<key>).
$LICENSES = [
    'mit' => 'MIT', 'apache-2.0' => 'Apache 2.0', 'gpl-3.0' => 'GNU GPL v3',
    'gpl-2.0' => 'GNU GPL v2', 'lgpl-3.0' => 'GNU LGPL v3', 'agpl-3.0' => 'GNU AGPL v3',
    'mpl-2.0' => 'Mozilla Public License 2.0', 'bsd-3-clause' => 'BSD 3-Clause',
    'bsd-2-clause' => 'BSD 2-Clause', 'isc' => 'ISC', 'unlicense' => 'The Unlicense',
    'bsl-1.0' => 'Boost Software License 1.0', 'cc0-1.0' => 'CC0 1.0 (public domain)',
];
if ($license !== 'none' && !isset($LICENSES[$license])) $license = 'none';
// GitHub account (owner) and project (repo) name — kept SEPARATE so the user can
// fill just the project name if they have no account yet / aren't connected.
$ghUser   = preg_replace('~[^A-Za-z0-9-]~', '', (string)($_POST['github_user'] ?? ''));
$ghUser   = mb_substr($ghUser, 0, 39); // GitHub username max length
$repoName = preg_replace('~[^A-Za-z0-9._-]~', '', (string)($_POST['repo'] ?? ''));
$repoName = preg_replace('~\.{2,}~', '.', $repoName);      // collapse ".." (no traversal-looking names)
$repoName = mb_substr(trim($repoName, '/.'), 0, 100);
// Composed slug for gh / README (owner/name when the account is known, else name).
$rawRepo  = $repoName !== '' ? ($ghUser !== '' ? "$ghUser/$repoName" : $repoName) : '';
$ghCreate  = ((string)($_POST['github_create'] ?? '') === '1');
$ghVisibility = ((string)($_POST['github_visibility'] ?? 'private') === 'public') ? 'public' : 'private';

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
    echo json_encode(['error' => 'Invalid name: use at least one letter or digit.']);
    exit;
}
if (in_array(strtolower($slug), $reserved, true)) {
    echo json_encode(['error' => 'That name is reserved, choose another one.']);
    exit;
}

$path = $base . DIRECTORY_SEPARATOR . $slug;
if (file_exists($path)) {
    echo json_encode(['error' => 'A folder "' . $slug . '" already exists.']);
    exit;
}

// --- Create the folder tree ---
$dirs = [$path, "$path/public", "$path/public/icons", "$path/public/css", "$path/public/js"];
foreach ($dirs as $d) {
    if (!is_dir($d) && !@mkdir($d, 0775, true)) {
        echo json_encode(['error' => 'Could not create the folder (write permissions?).']);
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
    'description'      => $rawDesc,
    'start_url'        => './',
    'scope'            => './',
    'display'          => 'standalone',
    'background_color' => '#0d0d13',
    'theme_color'      => '#5b6cff',
    'icons'            => [
        ['src' => 'icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => 'icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => 'icons/icon-512-maskable.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
];

// --- App icon generation: real PNGs for mobile "Add to Home Screen"
//     (iOS apple-touch-icon + Android/PWA manifest, incl. maskable) + an SVG.
//     Same gradient + name design as the launcher placeholder, so a freshly
//     created app already has a proper icon on iPhone, Android and in the tab.
//     Icons are SQUARE on purpose: iOS/Android apply their own rounding/mask.
//     Degrades gracefully if GD or a TrueType font is unavailable. ---
function pf_hue_from_name($name) {
    $h = 0; $len = strlen($name);
    for ($i = 0; $i < $len; $i++) { $h = ($h * 31 + ord($name[$i])) & 0xFFFFFFFF; }
    return $h % 360;
}
function pf_hsl_rgb($h, $s, $l) {
    $c = (1 - abs(2 * $l - 1)) * $s;
    $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
    $m = $l - $c / 2;
    if ($h < 60)      { $r = $c; $g = $x; $b = 0; }
    elseif ($h < 120) { $r = $x; $g = $c; $b = 0; }
    elseif ($h < 180) { $r = 0; $g = $c; $b = $x; }
    elseif ($h < 240) { $r = 0; $g = $x; $b = $c; }
    elseif ($h < 300) { $r = $x; $g = 0; $b = $c; }
    else              { $r = $c; $g = 0; $b = $x; }
    return [(int) round(($r + $m) * 255), (int) round(($g + $m) * 255), (int) round(($b + $m) * 255)];
}
function pf_font_path() {
    foreach ([
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
        'C:/Windows/Fonts/segoeuib.ttf',
        'C:/Windows/Fonts/arialbd.ttf',
        'C:/Windows/Fonts/arial.ttf',
        '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
    ] as $f) { if (@is_file($f)) return $f; }
    return null;
}
function pf_icon_words($name) {
    $words = preg_split('/\s+/', trim($name)) ?: [$name];
    if (count($words) > 3) $words = array_merge(array_slice($words, 0, 2), [implode(' ', array_slice($words, 2))]);
    return $words;
}
function pf_png_icon($rawName, $size, $font, $withText = true) {
    $hue = pf_hue_from_name($rawName); $hue2 = ($hue + 40) % 360;
    list($r1, $g1, $b1) = pf_hsl_rgb($hue, 0.70, 0.55);
    list($r2, $g2, $b2) = pf_hsl_rgb($hue2, 0.65, 0.40);
    $im = imagecreatetruecolor($size, $size);
    imagealphablending($im, true); imagesavealpha($im, true);
    for ($y = 0; $y < $size; $y++) {                       // vertical gradient
        $t = $y / max(1, $size - 1);
        $c = imagecolorallocate($im,
            (int) round($r1 + ($r2 - $r1) * $t),
            (int) round($g1 + ($g2 - $g1) * $t),
            (int) round($b1 + ($b2 - $b1) * $t));
        imageline($im, 0, $y, $size, $y, $c);
    }
    if ($withText && $font) {                              // app name (multi-line), centered
        $white = imagecolorallocate($im, 255, 255, 255);
        $words = pf_icon_words($rawName);
        $lines = count($words);
        $usable = $size * 0.68;
        $maxLen = max(1, max(array_map('strlen', $words)));
        $fs = max(8, min($size * 0.42, $usable / ($maxLen * 0.62), $usable / ($lines * 1.35)));
        $lh = $fs * 1.28;
        $y0 = ($size - $lh * $lines) / 2 + $fs;            // baseline of first line
        foreach ($words as $i => $w) {
            $bb = imagettfbbox($fs, 0, $font, $w);
            $tw = $bb[2] - $bb[0];
            imagettftext($im, $fs, 0, (int) (($size - $tw) / 2 - $bb[0]), (int) ($y0 + $i * $lh), $white, $font, $w);
        }
    }
    ob_start(); imagepng($im); $png = ob_get_clean(); imagedestroy($im);
    return $png;
}
function pf_svg_icon($rawName) {
    $hue = pf_hue_from_name($rawName); $hue2 = ($hue + 40) % 360;
    $words = pf_icon_words($rawName);
    $lines = count($words); $maxLen = max(1, max(array_map('strlen', $words)));
    $S = 240; $pad = 28; $usable = $S - 2 * $pad;
    $fs = (int) max(20, min(96, $usable / ($maxLen * 0.62), $usable / ($lines * 1.25)));
    $lh = $fs * 1.2; $startY = ($S - $lh * $lines) / 2 + $fs * 0.78;
    $texts = '';
    foreach ($words as $i => $w) {
        $y = round($startY + $i * $lh, 1);
        $texts .= '<text x="' . ($S / 2) . '" y="' . $y . '" text-anchor="middle" font-family="system-ui,Segoe UI,Roboto,sans-serif" font-weight="700" font-size="' . $fs . '" fill="#fff">' . htmlspecialchars($w, ENT_QUOTES) . '</text>';
    }
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $S . ' ' . $S . '">'
        . '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
        . '<stop offset="0" stop-color="hsl(' . $hue . ',70%,55%)"/>'
        . '<stop offset="1" stop-color="hsl(' . $hue2 . ',65%,40%)"/></linearGradient></defs>'
        . '<rect width="' . $S . '" height="' . $S . '" rx="48" fill="url(#g)"/>' . $texts . '</svg>';
}

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
<link rel="icon" type="image/svg+xml" href="icons/favicon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="icons/favicon-32.png">
<link rel="apple-touch-icon" href="icons/apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{NAME}}">
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
   &#9500;&#9472; icons/            &#8592; ic&ocirc;nes g&eacute;n&eacute;r&eacute;es (iOS + Android + favicon)
   &#9500;&#9472; css/style.css     &#8592; vos styles
   &#9492;&#9472; js/app.js         &#8592; votre JavaScript</pre>
  </section>

  <section>
    <h2>&#128640; Par o&ugrave; commencer</h2>
    <ul>
      <li>Remplacez le contenu de <code>public/index.html</code> par votre interface.</li>
      <li>&Eacute;crivez vos styles dans <code>css/style.css</code> et votre logique dans <code>js/app.js</code>.</li>
      <li>Les <strong>ic&ocirc;nes</strong> (iOS &laquo;&nbsp;ajouter &agrave; l'&eacute;cran&nbsp;&raquo;, Android/PWA maskable, favicon) sont <strong>g&eacute;n&eacute;r&eacute;es automatiquement</strong> dans <code>icons/</code> &mdash; remplacez-les par les v&ocirc;tres quand vous voulez.</li>
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
const CORE = ['./', './index.html', './manifest.json', './css/style.css', './js/app.js', './offline.html',
  './icons/icon-192.png', './icons/icon-512.png', './icons/apple-touch-icon.png', './icons/favicon.svg'];

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
    "$path/public/manifest.json"     => json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
    "$path/public/service-worker.js" => $serviceWorker,
    "$path/public/css/style.css"     => $css,
    "$path/public/js/app.js"         => $appJs,
];
foreach ($files as $file => $content) {
    if (file_put_contents($file, $content) === false) {
        echo json_encode(['error' => 'Folder created but a file could not be written.']);
        exit;
    }
}
// Generate real app icons (mobile "Add to Home Screen" + PWA) when GD is available.
// Falls back to an empty icons/ (PhoneFake then shows its auto placeholder logo).
$iconsOk = false;
if (extension_loaded('gd') && function_exists('imagecreatetruecolor')) {
    $font = pf_font_path();
    $icons = [
        "$path/public/icons/icon-192.png"          => pf_png_icon($rawName, 192, $font),
        "$path/public/icons/icon-512.png"          => pf_png_icon($rawName, 512, $font),
        "$path/public/icons/icon-512-maskable.png" => pf_png_icon($rawName, 512, $font),
        "$path/public/icons/apple-touch-icon.png"  => pf_png_icon($rawName, 180, $font),
        "$path/public/icons/favicon-32.png"        => pf_png_icon($rawName, 32, $font, false),
        "$path/public/icons/favicon.svg"           => pf_svg_icon($rawName),
    ];
    $iconsOk = true;
    foreach ($icons as $f => $data) {
        if ($data === null || $data === false || file_put_contents($f, $data) === false) { $iconsOk = false; break; }
    }
}
if (!$iconsOk) { @file_put_contents("$path/public/icons/.gitkeep", ''); }

// =====================================================================
//  Project-root files (README GitHub-ready, .gitignore, LICENSE),
//  local git repo (+ first commit), and optional GitHub repo + push.
// =====================================================================
$year   = date('Y');
$author = $rawAuthor !== '' ? $rawAuthor : $rawName;
$descLine = $rawDesc !== '' ? $rawDesc : 'Application web générée avec PhoneFake.';

$licenseName = $license !== 'none' ? ($LICENSES[$license] ?? '') : '';
$badgeMd = $licenseName !== ''
    ? "\n[![License](https://img.shields.io/badge/license-" . rawurlencode(str_replace('-', ' ', $licenseName)) . "-blue.svg)](LICENSE)\n"
    : '';
$aboutMd = $rawLongDesc !== '' ? "\n## À propos\n\n$rawLongDesc\n" : '';
$repoMd = '';
if ($repoName !== '') {
    $ownerShown = $ghUser !== '' ? $ghUser : '<votre-compte>';
    $repoMd = "\n## Dépôt\n\n```bash\ngit clone https://github.com/$ownerShown/$repoName.git\n```\n";
}
$licenseMd = $licenseName !== ''
    ? "Sous licence **$licenseName** — voir [LICENSE](LICENSE)."
    : "Aucune licence définie pour l'instant.";
$authorMd = $rawAuthor !== '' ? "\n---\n\nAuteur : **$author**\n" : '';

$readmeTpl = <<<'MD'
# {{NAME}}

> {{DESC}}
{{BADGE}}{{ABOUT}}
## Démarrage

Servez le dossier `public/` avec un serveur web local (Laragon, MAMP, XAMPP, `php -S`, serveur Node…), puis ouvrez `public/index.html`.

## Structure

```
{{SLUG}}/
└─ public/            ← racine web
   ├─ index.html      ← point d'entrée
   ├─ manifest.json   ← métadonnées PWA
   ├─ service-worker.js
   ├─ offline.html
   ├─ icons/          ← icônes (iOS + Android + favicon)
   ├─ css/style.css
   └─ js/app.js
```

## PWA

L'appli est installable (« ajouter à l'écran d'accueil ») et fonctionne hors-ligne une fois chargée.
{{REPO}}
## Licence

{{LICENSE}}
{{AUTHOR}}
MD;
@file_put_contents("$path/README.md", strtr($readmeTpl, [
    '{{NAME}}' => $rawName, '{{DESC}}' => $descLine, '{{BADGE}}' => $badgeMd, '{{ABOUT}}' => $aboutMd,
    '{{SLUG}}' => $slug, '{{REPO}}' => $repoMd, '{{LICENSE}}' => $licenseMd, '{{AUTHOR}}' => $authorMd,
]));

$gitignore = "# Dependencies\nnode_modules/\nvendor/\n\n# Env / secrets\n.env\n.env.*\n*.local\n\n# OS / editor\n.DS_Store\nThumbs.db\n.vscode/\n.idea/\n\n# Logs\n*.log\n";
@file_put_contents("$path/.gitignore", $gitignore);

if ($license !== 'none') {
    $licenseText = pf_license_text($license, $year, $author, $licenseName);
    if (is_string($licenseText) && $licenseText !== '') {
        @file_put_contents("$path/LICENSE", $licenseText);
    }
}

// --- Local git repo + first commit (best-effort; never blocks app creation) ---
$git = ['init' => false, 'committed' => false];
$repoUrl  = null;
$warnings = [];
if (function_exists('exec')) {
    $g = 'git -C ' . escapeshellarg($path) . ' '; // -C is portable (Windows, Linux, macOS)
    @exec($g . 'init -b main 2>&1', $o1, $c1);
    if (!is_dir("$path/.git")) { @exec($g . 'init 2>&1', $o1b); } // older git without -b
    if (is_dir("$path/.git")) {
        $git['init'] = true;
        @exec($g . 'add -A 2>&1', $o2);
        @exec($g . 'commit -m "Initial commit (generated by PhoneFake)" 2>&1', $o3, $c3);
        $git['committed'] = ($c3 === 0);
        if ($c3 !== 0) $warnings[] = 'git commit failed (is your git identity configured?).';
        // Optional: create the GitHub repo and push.
        if ($ghCreate) {
            if ($git['committed']) {
                $repoArg = $rawRepo !== '' ? $rawRepo : strtolower($slug);
                $vis = $ghVisibility === 'public' ? '--public' : '--private';
                @exec('gh repo create ' . escapeshellarg($repoArg) . ' ' . $vis
                    . ' --source=' . escapeshellarg($path) . ' --remote=origin --push 2>&1', $go, $gc);
                if ($gc === 0) {
                    foreach ($go as $line) {
                        if (preg_match('~https://github\.com/\S+~', $line, $m)) { $repoUrl = rtrim($m[0], '.'); break; }
                    }
                    if (!$repoUrl) $repoUrl = 'https://github.com/' . $repoArg;
                } else {
                    $warnings[] = 'GitHub creation failed: ' . trim(implode(' ', array_slice($go, -3)));
                }
            } else {
                $warnings[] = 'GitHub repo not created (no initial commit).';
            }
        }
    } else {
        $warnings[] = 'git unavailable: repository not initialised (files ready).';
    }
} else {
    $warnings[] = 'exec() disabled: git repository not initialised (files ready).';
}

echo json_encode([
    'ok'       => true,
    'id'       => $slug,
    'name'     => $rawName,
    'url'      => $slug . '/public/index.html',
    'git'      => $git,
    'repoUrl'  => $repoUrl,
    'warnings' => $warnings,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
