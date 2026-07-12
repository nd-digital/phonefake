<?php
/**
 * gh-me.php -- returns the locally-connected GitHub account, via the gh CLI.
 *
 * Used by the "Auto-fill from my GitHub account" button in the New App modal to
 * prefill the GitHub username + author name. Local only, read-only, no secret
 * (never returns the token). If gh isn't installed/connected, returns ok:false.
 *
 *   GET -> { ok:true, login, name }   or   { ok:false, error }
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!function_exists('exec')) {
    echo json_encode(['ok' => false, 'error' => 'exec-disabled']);
    exit;
}

@exec('gh api user 2>&1', $out, $code);
if ($code !== 0) {
    echo json_encode(['ok' => false, 'error' => 'not-connected']);
    exit;
}

$data = json_decode(implode("\n", $out), true);
if (!is_array($data) || empty($data['login'])) {
    echo json_encode(['ok' => false, 'error' => 'no-data']);
    exit;
}

echo json_encode([
    'ok'    => true,
    'login' => $data['login'],
    'name'  => $data['name'] ?? '',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
