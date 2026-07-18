<?php
/**
 * profile.php -- local "localhost account" for the current user.
 *
 * A single JSON file (.phonefake-user.json, gitignored) that stores the user's
 * recurring settings so they don't have to be re-typed: GitHub username, author
 * name, and — as they grow — ergonomics/accessibility preferences, etc.
 *
 *   GET  -> { ok, profile }                    (read; local only, no secret)
 *   POST -> merge the given keys, returns the updated profile   (CSRF-protected)
 *
 * Nothing here leaves the machine; the file is never committed.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$file = __DIR__ . '/.phonefake-user.json';

function pf_load_profile($file) {
    if (!is_file($file)) return [];
    $raw = @file_get_contents($file);
    $d = $raw ? json_decode($raw, true) : [];
    return is_array($d) ? $d : [];
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    echo json_encode(['ok' => true, 'profile' => pf_load_profile($file)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($method === 'POST') {
    // Same double-submit CSRF check as create-app.php.
    $cookieToken = $_COOKIE['phonefake_csrf'] ?? '';
    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($cookieToken === '' || !hash_equals($cookieToken, $headerToken)) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized request (invalid CSRF token).']);
        exit;
    }

    $body = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($body)) { $body = $_POST; }

    $sanScalar = function ($v) {
        if (is_string($v)) return mb_substr(strip_tags($v), 0, 500);
        return (is_bool($v) || is_int($v) || is_float($v) || $v === null) ? $v : null;
    };

    $profile = pf_load_profile($file);
    foreach ($body as $k => $v) {
        if (!preg_match('~^[a-z0-9_]{1,40}$~', (string)$k)) continue; // safe key names only
        if (is_array($v)) {                                          // one level of nested prefs (a11y, ergonomics…)
            $clean = [];
            foreach ($v as $kk => $vv) {
                if (!preg_match('~^[a-z0-9_]{1,40}$~', (string)$kk)) continue;
                $s = $sanScalar($vv);
                if ($s !== null || $vv === null) $clean[$kk] = $s;
            }
            $profile[$k] = $clean;
        } else {
            $s = $sanScalar($v);
            if ($s !== null || $v === null) $profile[$k] = $s;
        }
    }

    $ok = @file_put_contents($file, json_encode($profile,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
    if ($ok === false) {
        echo json_encode(['error' => 'Could not write the profile (permissions?).']);
        exit;
    }
    echo json_encode(['ok' => true, 'profile' => $profile], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
