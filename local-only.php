<?php
/**
 * local-only.php -- refuses any request that does not come from this computer.
 *
 * Local web servers usually listen on every interface and answer any Host, so a
 * device on the same network, or a web page using DNS rebinding, could otherwise
 * drive these endpoints (they run git/gh and write files with the user's rights).
 * The CSRF cookie does not stop that: a non-browser client simply sets its own.
 */
function pf_local_only() {
    $peer = $_SERVER['REMOTE_ADDR'] ?? '';
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $host = trim(preg_replace('~:\d+$~', '', $host), '[]');

    $peerIsLocal = in_array($peer, ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true);
    // A rebinding page always sends its own public domain as Host, so a local name is proof enough.
    $hostIsLocal = in_array($host, ['localhost', '127.0.0.1', '::1'], true)
        || preg_match('~^[a-z0-9-]+$~', $host)
        || preg_match('~\.(test|localhost|local|lan|internal|home\.arpa)$~', $host);

    if ($peerIsLocal && $hostIsLocal) return;

    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'PhoneFake only answers requests made from this computer.']);
    exit;
}
pf_local_only();
