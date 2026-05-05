<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$base = __DIR__;
$excluded = ['node_modules', '.git', '.idea', '.vscode', 'vendor', 'memory', 'assets'];

$manifestCandidates = [
    'manifest.json', 'manifest.webmanifest',
    'public/manifest.json', 'public/manifest.webmanifest',
    'dist/manifest.json', 'build/manifest.json',
];

$iconCandidates = [
    'icon.png', 'icon.svg', 'logo.png', 'logo.svg',
    'apple-touch-icon.png', 'favicon.png', 'favicon.ico',
    'public/icon.png', 'public/logo.png',
    'public/apple-touch-icon.png', 'public/favicon.png', 'public/favicon.ico',
    'public/img/logo.png', 'public/img/icon.png',
    'public/images/logo.png', 'public/images/icon.png',
    'public/assets/logo.png', 'public/assets/icon.png',
];

// Order matters: /public first (the real app), then welcome/home as common PWA landings,
// then root index.* last (often a redirect shim to a Node.js dev server).
$entryCandidates = [
    'public/index.html',
    'public/welcome.html',
    'public/home.html',
    'dist/index.html',
    'build/index.html',
    'index.html',
    'index.php',
    'public/index.php',
];

/**
 * If an HTML file is just a <meta http-equiv="refresh"> shim, return the target URL.
 * Lets us skip past root redirectors like "index.html → http://localhost:3000".
 */
function detectMetaRefresh($htmlPath) {
    $raw = @file_get_contents($htmlPath, false, null, 0, 4096);
    if ($raw === false) return null;
    if (preg_match('/<meta[^>]*http-equiv=["\']?refresh["\']?[^>]*content=["\'][^"\']*;\s*url=([^"\'\s>]+)/i', $raw, $m)) {
        return trim($m[1]);
    }
    return null;
}

function findIconByGlob($path, $baseDir) {
    $dirs = ['', 'public', 'public/img', 'public/images', 'public/assets', 'public/assets/img', 'public/icons'];
    foreach ($dirs as $d) {
        $full = $path . ($d ? DIRECTORY_SEPARATOR . $d : '');
        if (!is_dir($full)) continue;
        foreach (glob($full . DIRECTORY_SEPARATOR . '*.{png,svg,jpg,jpeg,webp,ico}', GLOB_BRACE) ?: [] as $f) {
            $name = strtolower(basename($f));
            if (preg_match('/(logo|icon|favicon|apple-touch)/', $name)) {
                $rel = substr($f, strlen($baseDir) + 1);
                return str_replace('\\', '/', $rel);
            }
        }
    }
    return null;
}

/**
 * Parse a PWA manifest.json and return ['icon' => relPath|null, 'name' => string|null].
 * Picks the smallest icon >= 128px (best for a 60px display tile), falls back to the first icon.
 * Resolves icon src against the manifest's directory (absolute "/x" paths are treated as app-root-relative).
 */
function readManifest($manifestPath, $entry, $baseDir) {
    $raw = @file_get_contents($manifestPath);
    if ($raw === false) return ['icon' => null, 'name' => null];
    $data = json_decode($raw, true);
    if (!is_array($data)) return ['icon' => null, 'name' => null];

    $name = $data['short_name'] ?? $data['name'] ?? null;

    $icon = null;
    if (!empty($data['icons']) && is_array($data['icons'])) {
        $icons = array_values(array_filter($data['icons'], function ($i) {
            return is_array($i) && !empty($i['src']);
        }));
        usort($icons, function ($a, $b) {
            $score = function ($i) {
                $s = $i['sizes'] ?? '';
                if (preg_match('/(\d+)x\d+/', $s, $m)) {
                    $n = (int)$m[1];
                    return $n >= 128 ? $n : 10000 + (128 - $n); // prefer >=128, then larger of small
                }
                return 99999;
            };
            return $score($a) - $score($b);
        });
        if (!empty($icons)) {
            $src = $icons[0]['src'];
            // Root of the manifest on disk (e.g. .../<app>/public)
            $manifestDir = dirname($manifestPath);
            if ($src[0] === '/') {
                // Absolute app path — relative to the manifest's directory (served as app root)
                $iconFile = $manifestDir . str_replace('/', DIRECTORY_SEPARATOR, $src);
            } else {
                $iconFile = $manifestDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $src);
            }
            if (file_exists($iconFile)) {
                $rel = substr($iconFile, strlen($baseDir) + 1);
                $icon = str_replace('\\', '/', $rel);
            }
        }
    }

    return ['icon' => $icon, 'name' => $name];
}

$apps = [];

foreach (scandir($base) as $entry) {
    if ($entry === '.' || $entry === '..') continue;
    $path = $base . DIRECTORY_SEPARATOR . $entry;
    if (!is_dir($path)) continue;
    if (in_array($entry, $excluded, true)) continue;
    if ($entry[0] === '.') continue;

    // 1) PWA manifest (preferred)
    $icon = null;
    $manifestName = null;
    foreach ($manifestCandidates as $c) {
        $cPath = $path . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $c);
        if (file_exists($cPath)) {
            $m = readManifest($cPath, $entry, $base);
            if ($m['icon']) $icon = $m['icon'];
            if ($m['name']) $manifestName = $m['name'];
            if ($icon) break;
        }
    }

    // 2) Conventional icon filenames
    if (!$icon) {
        foreach ($iconCandidates as $c) {
            $cPath = $path . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $c);
            if (file_exists($cPath)) {
                $icon = $entry . '/' . $c;
                break;
            }
        }
    }

    // 3) Glob fallback
    if (!$icon) {
        $found = findIconByGlob($path, $base);
        if ($found) $icon = $found;
    }

    // Entry URL detection — supports a per-app override via phonefake.json.
    // Override format: { "url": "http://localhost:3000", "name": "...", "icon": "..." }
    $entryUrl = null;
    $configPath = $path . DIRECTORY_SEPARATOR . 'phonefake.json';
    $override = null;
    if (file_exists($configPath)) {
        $raw = @file_get_contents($configPath);
        $override = $raw ? json_decode($raw, true) : null;
        if (is_array($override) && !empty($override['url'])) {
            $entryUrl = $override['url'];
        }
    }

    if (!$entryUrl) {
        $redirectUrl = null;
        foreach ($entryCandidates as $c) {
            $cPath = $path . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $c);
            if (!file_exists($cPath)) continue;
            // Skip redirect shims unless no other entry is found
            $redirect = detectMetaRefresh($cPath);
            if ($redirect) {
                if (!$redirectUrl) $redirectUrl = $redirect;
                continue;
            }
            // Point directly at the file so Apache serves it (no directory index needed)
            $entryUrl = $entry . '/' . str_replace('\\', '/', $c);
            break;
        }
        if (!$entryUrl && $redirectUrl) $entryUrl = $redirectUrl;
        if (!$entryUrl) $entryUrl = $entry . '/';
    }

    // Friendly name — manifest short_name > phonefake.json name > folder name
    $name = ($override['name'] ?? null)
        ?: ($manifestName ?: ucwords(strtolower(str_replace(['-', '_'], ' ', $entry))));

    // Icon override
    if (is_array($override) && !empty($override['icon'])) {
        $icon = ltrim($override['icon'], '/');
        if (strpos($icon, $entry . '/') !== 0) $icon = $entry . '/' . $icon;
    }

    // Cache-busting: append file mtime so icon changes bypass browser cache
    if ($icon) {
        $iconAbs = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $icon);
        if (file_exists($iconAbs)) {
            $icon .= '?v=' . filemtime($iconAbs);
        }
    }

    $apps[] = [
        'id'   => $entry,
        'name' => $name,
        'icon' => $icon,
        'url'  => $entryUrl,
    ];
}

echo json_encode($apps, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
