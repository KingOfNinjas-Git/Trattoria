// Stands in for laravel-echo and pusher-js when the site is built without websockets
// (VITE_REALTIME=polling, see vite.config.js). resources/js/echo.js never creates an Echo in that
// case, so these libraries (about 45 KB) would only be dead weight in the page.
export default class {}
