const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../assets/js/app.js'), 'utf8');
let count = 0;
function check(ok, message) { assert.ok(ok, message); count++; console.log('PASS - ' + message); }
function page(cookie = '', blockedStorage = false) {
    const listeners = {};
    const removed = [];
    const classes = new Set(['hidden']);
    const banner = { classList: { remove(name) { classes.delete(name); } } };
    const buttons = ['accepted', 'rejected'].map(decision => ({
        dataset: { consent: decision }, addEventListener(event, fn) { listeners[decision] = fn; }
    }));
    const document = {
        cookie,
        addEventListener(event, fn) { fn(); },
        getElementById(id) { return id === 'consent-banner' ? banner : { addEventListener(event, fn) { listeners.settings = fn; } }; },
        querySelectorAll() { return buttons; }
    };
    let nextUrl;
    vm.runInNewContext(source, {
        document, URL,
        location: { href: 'https://example.test/review.php?campaign=demo&gclid=PrivateId', protocol: 'https:', replace(url) { nextUrl = url; } },
        localStorage: { removeItem(key) { if (blockedStorage) throw new Error('blocked'); removed.push(key); } }
    });
    return { document, listeners, classes, removed, next: () => nextUrl };
}
let current = page();
check(!current.classes.has('hidden'), 'no choice shows banner');
check(current.document.cookie === '', 'page load never creates consent');
check(current.removed.includes('presell_tracking'), 'legacy tracking removed');
current.listeners.rejected();
check(current.document.cookie.includes('presell_consent=rejected'), 'reject writes rejection');
check(!current.next().includes('gclid') && current.next().includes('campaign=demo'), 'reject removes tracking and preserves campaign');
check(current.document.cookie.includes('Secure') && current.document.cookie.includes('SameSite=Lax'), 'HTTPS consent cookie protected');
current = page();
current.listeners.accepted();
check(current.document.cookie.includes('presell_consent=accepted'), 'explicit accept writes acceptance');
check(current.next().includes('gclid=PrivateId'), 'accept reload preserves parameters for server gate');
current = page('presell_consent=rejected');
check(current.classes.has('hidden'), 'stored rejection respected');
current.listeners.settings();
check(!current.classes.has('hidden'), 'settings reopen choice');
current = page('presell_consent=accepted', true);
check(current.classes.has('hidden'), 'blocked localStorage does not break stored choice');
console.log(`${count} consent checks passed`);
