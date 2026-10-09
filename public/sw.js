// Minimal service worker: only here so the system can be installed as an app.
// It caches NOTHING - every request goes to the network as normal, so member data
// is never stored on the device.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));
self.addEventListener('fetch', () => {});
