// Service worker for PhoneFake itself (makes it an installable, offline-capable PWA).
// IMPORTANT: it only handles PhoneFake's OWN shell files. Everything else — the
// tested apps in subfolders and apps.php / create-app.php — is left to the network,
// so the simulator never serves stale content for the apps it hosts.

const CACHE = 'phonefake-shell-v7';
const ROOTPATH = new URL('./', self.location).pathname; // e.g. "/" or "/APPLI/"
const SHELL = ['./', './index.html', './styles.css', './favicon.svg', './manifest.json', './icon-192.png', './icon-512.png', './phonefake-sync.js'];
const SHELL_FILES = ['', 'index.html', 'styles.css', 'favicon.svg', 'manifest.json', 'icon-192.png', 'icon-512.png', 'phonefake-sync.js'];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.map((k) => (k !== CACHE ? caches.delete(k) : null))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  const file = url.pathname.slice(ROOTPATH.length); // path relative to PhoneFake's root
  const isOwnShell = url.pathname === ROOTPATH
    || (file.indexOf('/') === -1 && SHELL_FILES.includes(file));
  if (!isOwnShell) return; // apps, apps.php, iframes… → default network

  // Network-first (so PhoneFake updates show), with cache fallback when offline.
  e.respondWith(
    fetch(req).then((res) => {
      const copy = res.clone();
      caches.open(CACHE).then((c) => c.put(req, copy)).catch(() => {});
      return res;
    }).catch(() => caches.match(req).then((c) => c || caches.match('./index.html')))
  );
});
