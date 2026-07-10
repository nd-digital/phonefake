<?php
/**
 * home.php — target of the "↩ index localhost" button.
 *
 * If the localhost web root (PhoneFake's parent folder) already serves an index
 * page, we just forward there. Otherwise — instead of a bare 404 — we show a
 * friendly page inviting the visitor to install INDEX_LARAGON, the local
 * dashboard PhoneFake integrates with, with a link to its GitHub repository.
 */

$rootDir = dirname(__DIR__); // the folder "../" resolves to (localhost web root)
$hasIndex = false;
foreach (['index.php', 'index.html', 'index.htm'] as $f) {
    if (is_file($rootDir . DIRECTORY_SEPARATOR . $f)) { $hasIndex = true; break; }
}
if ($hasIndex) {
    header('Location: ../');
    exit;
}

// --- Not installed: render the invitation page (localised) ---
$repo = 'https://github.com/nd-digital/INDEX_LARAGON';

$lang = strtolower((string)($_GET['lang'] ?? ''));
if (!in_array($lang, ['fr', 'en', 'es', 'it', 'de'], true)) {
    // Fall back to the browser's preferred language, then French.
    $al = strtolower(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', 0, 2));
    $lang = in_array($al, ['fr', 'en', 'es', 'it', 'de'], true) ? $al : 'fr';
}

$T = [
    'fr' => [
        'title'   => 'Index localhost introuvable',
        'lead'    => "Aucun tableau de bord n'est installé à la racine de ton serveur local.",
        'body'    => "PhoneFake s'intègre à <strong>INDEX_LARAGON</strong>, un tableau de bord open-source qui liste tous tes projets locaux (Laragon, MAMP, XAMPP…) sur une seule page. Installe-le à la racine de ton <code>www</code> pour que ce bouton t'y ramène.",
        'install' => 'Installer INDEX_LARAGON',
        'back'    => '↩ Retour à PhoneFake',
    ],
    'en' => [
        'title'   => 'No localhost index found',
        'lead'    => 'No dashboard is installed at the root of your local server.',
        'body'    => "PhoneFake integrates with <strong>INDEX_LARAGON</strong>, an open-source dashboard that lists all your local projects (Laragon, MAMP, XAMPP…) on a single page. Install it at the root of your <code>www</code> folder and this button will take you there.",
        'install' => 'Install INDEX_LARAGON',
        'back'    => '↩ Back to PhoneFake',
    ],
    'es' => [
        'title'   => 'Índice de localhost no encontrado',
        'lead'    => 'No hay ningún panel instalado en la raíz de tu servidor local.',
        'body'    => "PhoneFake se integra con <strong>INDEX_LARAGON</strong>, un panel de código abierto que lista todos tus proyectos locales (Laragon, MAMP, XAMPP…) en una sola página. Instálalo en la raíz de tu carpeta <code>www</code> y este botón te llevará allí.",
        'install' => 'Instalar INDEX_LARAGON',
        'back'    => '↩ Volver a PhoneFake',
    ],
    'it' => [
        'title'   => 'Indice di localhost non trovato',
        'lead'    => 'Nessuna dashboard è installata nella radice del tuo server locale.',
        'body'    => "PhoneFake si integra con <strong>INDEX_LARAGON</strong>, una dashboard open-source che elenca tutti i tuoi progetti locali (Laragon, MAMP, XAMPP…) in un'unica pagina. Installala nella radice della cartella <code>www</code> e questo pulsante ti ci porterà.",
        'install' => 'Installa INDEX_LARAGON',
        'back'    => '↩ Torna a PhoneFake',
    ],
    'de' => [
        'title'   => 'Kein localhost-Index gefunden',
        'lead'    => 'Im Stammverzeichnis deines lokalen Servers ist kein Dashboard installiert.',
        'body'    => "PhoneFake ist mit <strong>INDEX_LARAGON</strong> verzahnt, einem Open-Source-Dashboard, das all deine lokalen Projekte (Laragon, MAMP, XAMPP…) auf einer Seite auflistet. Installiere es im Stammverzeichnis deines <code>www</code>-Ordners, und dieser Button bringt dich dorthin.",
        'install' => 'INDEX_LARAGON installieren',
        'back'    => '↩ Zurück zu PhoneFake',
    ],
];
$t = $T[$lang];
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($t['title']) ?> — PhoneFake</title>
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<link rel="stylesheet" href="home.css">
</head>
<body>
<main class="invite">
  <div class="invite-badge">🧭</div>
  <h1><?= htmlspecialchars($t['title']) ?></h1>
  <p class="invite-lead"><?= htmlspecialchars($t['lead']) ?></p>
  <p class="invite-body"><?= $t['body'] ?></p>
  <div class="invite-actions">
    <a class="invite-btn primary" href="<?= htmlspecialchars($repo) ?>" target="_blank" rel="noopener">
      <?= htmlspecialchars($t['install']) ?> ↗
    </a>
    <a class="invite-btn" href="index.html"><?= htmlspecialchars($t['back']) ?></a>
  </div>
</main>
</body>
</html>
