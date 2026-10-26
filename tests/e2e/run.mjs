#!/usr/bin/env node
/**
 * End-to-end checks in a real browser: ordering from a phone, the kitchen board, live updates
 * through Reverb, the shared table cart, the bill, languages and accessibility (axe-core).
 *
 *   npm run serve        # in another terminal: site + Reverb must be running
 *   npm run e2e
 *
 * It needs a freshly seeded database (php artisan migrate:fresh --seed) and a Chromium-based
 * browser. Set CHROME_PATH if yours is not found automatically.
 */
import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import puppeteer from 'puppeteer-core';

const root = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const BASE = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

function findBrowser() {
    const candidates = [
        process.env.CHROME_PATH,
        'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
        'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
        '/usr/bin/google-chrome',
        '/usr/bin/chromium',
        '/usr/bin/chromium-browser',
        '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        '/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge',
    ].filter(Boolean);
    const found = candidates.find((p) => existsSync(p));
    if (!found) throw new Error('No Chrome or Edge found. Set CHROME_PATH to the browser executable.');
    return found;
}

function tableTokens() {
    const out = execFileSync('php', ['artisan', 'tables:tokens'], { cwd: root, encoding: 'utf8', shell: process.platform === 'win32' });
    return out.trim().split(/\r?\n/).pop().split(',');
}

const axeSource = readFileSync(join(root, 'node_modules', 'axe-core', 'axe.min.js'), 'utf8');
const executablePath = findBrowser();
const browsers = [];
const results = [];
const jsErrors = [];

function check(name, ok, detail = '') {
    results.push(ok);
    console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? '  ' + detail : ''}`);
}

/** One browser per simulated device, so no tab is throttled in the background. */
async function device(width, height) {
    const browser = await puppeteer.launch({ executablePath, headless: 'new', args: ['--no-sandbox'], protocolTimeout: 60000 });
    browsers.push(browser);
    const page = await browser.newPage();
    await page.setViewport({ width, height, deviceScaleFactor: 1 });
    page.on('pageerror', (e) => jsErrors.push(`${e.message}`));
    return page;
}

async function clickText(page, selector, text) {
    for (const handle of await page.$$(selector)) {
        if ((await handle.evaluate((el) => el.innerText.trim())).includes(text)) {
            await handle.click();
            return;
        }
    }
    throw new Error(`Nothing to click: ${selector} containing "${text}"`);
}

async function waitFor(page, fn, arg, timeout = 5000) {
    try {
        await page.waitForFunction(fn, { timeout, polling: 100 }, arg);
        return true;
    } catch {
        return false;
    }
}

const hasText = (page, text, timeout) => waitFor(page, (t) => document.body.innerText.toLowerCase().includes(t.toLowerCase()), text, timeout);

async function axeViolations(page) {
    await page.evaluate(axeSource);
    const res = await page.evaluate(async () => await axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] } }));
    return res.violations.map((v) => {
        const summary = (v.nodes[0].failureSummary || '').split('\n')[1] ?? '';
        return `${v.id} (${v.nodes.length}): ${v.nodes[0].target.join(' ')} ${summary}`.slice(0, 260);
    });
}

async function login(page) {
    await page.goto(`${BASE}/kitchen`, { waitUntil: 'networkidle0' });
    await page.type('input[type=email]', 'admin@trattoria.test');
    await page.type('input[type=password]', 'password');
    await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 60000 }), page.keyboard.press('Enter')]);
    await page.goto(`${BASE}/kitchen`, { waitUntil: 'networkidle0' });
}

try {
    const tokens = tableTokens();
    check('database is seeded with tables', tokens.length >= 3, `${tokens.length} tables`);

    // ---------- Staff opens the kitchen board ----------
    const kitchen = await device(1280, 800);
    await kitchen.goto(`${BASE}/kitchen`, { waitUntil: 'networkidle0' });
    check('kitchen redirects guests to the login', kitchen.url().includes('/admin/login'));
    await login(kitchen);
    check('kitchen board opens after login', kitchen.url().endsWith('/kitchen'));
    await sleep(1200);
    check('kitchen has a live websocket', await kitchen.evaluate(() => window.Echo?.connector?.pusher?.connection?.state) === 'connected');
    const kitchenViolations = await axeViolations(kitchen);
    check('kitchen has no accessibility violations', kitchenViolations.length === 0, kitchenViolations.join(' | '));

    // ---------- A guest orders from their phone ----------
    const phone = await device(390, 844);
    await phone.goto(`${BASE}/t/${tokens[0]}`, { waitUntil: 'networkidle0' });
    check('menu shows prices and descriptions', await phone.evaluate(() => document.body.innerText.includes('\u20AC9,00') && document.body.innerText.includes('San Marzano tomato')));
    const menuViolations = await axeViolations(phone);
    check('menu has no accessibility violations', menuViolations.length === 0, menuViolations.join(', '));

    // quick add updates instantly, and persists after a reload
    const addButton = await (async () => {
        for (const handle of await phone.$$('#main-content li button')) {
            if ((await handle.evaluate((el) => el.innerText.trim())) === 'Add') return handle;
        }
        throw new Error('no Add button');
    })();
    const before = Date.now();
    await addButton.click();
    const instant = await waitFor(phone, () => document.querySelector('li span[aria-live]')?.innerText === '1', null, 400);
    check('tapping Add updates the screen within 400 ms', instant, `${Date.now() - before} ms`);
    await sleep(900);

    // dish popup with options
    await phone.evaluate(() => document.querySelector('[aria-label*="Margherita"]').scrollIntoView({ block: 'center' }));
    await sleep(300);
    await phone.click('[aria-label*="Margherita"]');
    await phone.waitForSelector('[role=dialog]');
    await sleep(700);
    const popupViolations = await axeViolations(phone);
    check('dish popup has no accessibility violations', popupViolations.length === 0, popupViolations.join(', '));
    await clickText(phone, '[role=dialog] button', 'Large');
    await clickText(phone, '[role=dialog] button', 'Extra mozzarella');
    await sleep(500);
    check('options update the popup price', await hasText(phone, '\u20AC14,50', 2000));
    await clickText(phone, '[role=dialog] button', 'Add to order');
    check('popup closes after adding', await waitFor(phone, () => !document.querySelector('[role=dialog]'), null, 3000));

    // cart drawer and sending the order
    await clickText(phone, 'button', 'View order');
    await phone.waitForSelector('[aria-label="Your order"]');
    await sleep(500);
    const cartViolations = await axeViolations(phone);
    check('cart has no accessibility violations', cartViolations.length === 0, cartViolations.join(', '));
    await phone.type('#order-notes', 'No basil please');
    await clickText(phone, '[aria-label="Your order"] button', 'Send order to the kitchen');
    await phone.waitForFunction(() => location.pathname.includes('/order/'), { timeout: 15000 });
    const orderUrl = phone.url().split('?')[0];
    check('guest lands on the order tracking page', await hasText(phone, 'Order sent'));

    // ---------- Kitchen sees it live, and the guest sees progress live ----------
    check('kitchen received the order live', await hasText(kitchen, 'No basil please', 5000));
    const statusPage = await device(390, 844);
    await statusPage.goto(orderUrl, { waitUntil: 'networkidle0' });
    const statusViolations = await axeViolations(statusPage);
    check('order page has no accessibility violations', statusViolations.length === 0, statusViolations.join(', '));
    await clickText(kitchen, 'button', 'Start preparing');
    check('guest sees "Preparing" live', await waitFor(statusPage, () => document.querySelector('[aria-current="step"]')?.innerText.includes('Preparing'), null, 5000));

    // ---------- Waiter call and acknowledgement ----------
    const other = await device(390, 844);
    await other.goto(`${BASE}/t/${tokens[1]}`, { waitUntil: 'networkidle0' });
    await clickText(other, 'button', 'Call a waiter');
    check('kitchen sees the waiter request live', await hasText(kitchen, 'needs a waiter', 5000));
    await clickText(kitchen, 'button', 'On my way');
    check('guest is told the waiter is on the way', await hasText(other, 'Waiter on the way', 5000));
    await clickText(kitchen, 'button', 'Done');
    check('request clears on the guest phone', await hasText(other, 'Call a waiter', 5000));

    // ---------- Shared cart between two phones ----------
    const a = await device(390, 844);
    const b = await device(390, 844);
    await a.goto(`${BASE}/t/${tokens[2]}`, { waitUntil: 'networkidle0' });
    await b.goto(`${BASE}/t/${tokens[2]}`, { waitUntil: 'networkidle0' });
    await clickText(a, '#main-content li button', 'Add');
    check('second phone at the table sees the item live', await hasText(b, 'View order', 5000));

    // ---------- Bill and closing the table ----------
    await phone.goto(`${BASE}/t/${tokens[0]}/bill`, { waitUntil: 'networkidle0' });
    const billViolations = await axeViolations(phone);
    check('bill has no accessibility violations', billViolations.length === 0, billViolations.join(', '));
    await clickText(phone, 'button', 'Request the bill');
    check('kitchen sees the bill request live', await hasText(kitchen, 'wants the bill', 5000));
    kitchen.on('dialog', (d) => d.accept());
    await clickText(kitchen, 'button', 'Paid, close table');
    await sleep(1200);
    await phone.reload({ waitUntil: 'networkidle0' });
    check('a closed table starts with an empty bill', await hasText(phone, 'You have not ordered anything yet.', 3000));

    // ---------- Sold-out dishes reach open menus ----------
    await kitchen.goto(`${BASE}/kitchen?tab=menu`, { waitUntil: 'networkidle0' });
    await (await kitchen.$('[aria-label="Available: Caprese"]')).click();
    await sleep(1200);
    await other.reload({ waitUntil: 'networkidle0' });
    check('a dish switched off by the kitchen shows as sold out', await hasText(other, 'sold out', 3000));
    await (await kitchen.$('[aria-label="Available: Caprese"]')).click();

    // ---------- Languages ----------
    const italian = await device(390, 844);
    await italian.goto(`${BASE}/lang/it`, { waitUntil: 'networkidle0' });
    await italian.goto(`${BASE}/t/${tokens[0]}`, { waitUntil: 'networkidle0' });
    check('Italian menu', await italian.evaluate(() => document.documentElement.lang === 'it' && document.body.innerText.includes('Antipasti') && document.body.innerText.includes('Chiama un cameriere')));

    // ---------- Small phone: no sideways scrolling ----------
    const tiny = await device(320, 640);
    await tiny.goto(`${BASE}/t/${tokens[0]}`, { waitUntil: 'networkidle0' });
    check('no horizontal scrolling on a 320 px wide phone', await tiny.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth));

    check('no JavaScript errors on any page', jsErrors.length === 0, jsErrors.join(' | '));
} catch (error) {
    console.error('\nTest run aborted:', error.message);
    results.push(false);
} finally {
    await Promise.all(browsers.map((b) => b.close().catch(() => {})));
}

const failed = results.filter((ok) => !ok).length;
console.log(failed ? `\n${failed} of ${results.length} checks failed` : `\nAll ${results.length} checks passed`);
process.exit(failed ? 1 : 0);
