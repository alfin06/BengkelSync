// Builds the Indonesian user guide (Panduan BengkelSync) with headless Microsoft Edge.
//
//   node docs/panduan/build.mjs capture   screenshots from a DEMO server (fictional data only)
//   node docs/panduan/build.mjs pdf       prints panduan.html to Panduan-BengkelSync.pdf
//
// The capture step must only run against a server on the demo database
// (DB_DATABASE=bengkelsync_demo, filled with `php artisan migrate:fresh --seed`), because
// the screenshots are committed to a public repository. It refuses any other shop.
//
// Options (environment): DEMO_URL (default http://localhost:8124), EDGE (path to msedge.exe).

import { spawn } from 'node:child_process';
import { existsSync, mkdirSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { tmpdir } from 'node:os';

const here = dirname(fileURLToPath(import.meta.url));
const demoUrl = process.env.DEMO_URL ?? 'http://localhost:8124';
const edgePath =
    process.env.EDGE ??
    ['C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', 'C:/Program Files/Microsoft/Edge/Application/msedge.exe'].find((path) => existsSync(path));
const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

/** Starts headless Edge and returns a small DevTools-protocol client for one page. */
async function launch() {
    const port = 9300 + Math.floor(Math.random() * 500);
    const profile = join(tmpdir(), `panduan-edge-${port}`);
    const edge = spawn(edgePath, ['--headless=new', `--remote-debugging-port=${port}`, `--user-data-dir=${profile}`, '--no-first-run', '--hide-scrollbars', 'about:blank'], {
        stdio: 'ignore',
    });

    let target;
    for (let attempt = 0; attempt < 50 && !target; attempt++) {
        await sleep(200);
        target = await fetch(`http://127.0.0.1:${port}/json/list`)
            .then((response) => response.json())
            .then((list) => list.find((item) => item.type === 'page'))
            .catch(() => undefined);
    }
    if (!target) throw new Error('Edge did not start');

    const socket = new WebSocket(target.webSocketDebuggerUrl);
    await new Promise((resolve) => socket.addEventListener('open', resolve, { once: true }));
    let nextId = 0;
    const pending = new Map();
    const listeners = new Map();
    socket.addEventListener('message', (event) => {
        const message = JSON.parse(event.data);
        if (message.id && pending.has(message.id)) {
            const { resolve, reject } = pending.get(message.id);
            pending.delete(message.id);
            message.error ? reject(new Error(message.error.message)) : resolve(message.result);
        } else if (message.method && listeners.has(message.method)) {
            listeners.get(message.method).splice(0).forEach((resolve) => resolve(message.params));
        }
    });

    const send = (method, params = {}) =>
        new Promise((resolve, reject) => {
            const id = ++nextId;
            pending.set(id, { resolve, reject });
            socket.send(JSON.stringify({ id, method, params }));
        });
    const once = (method) => new Promise((resolve) => listeners.set(method, [...(listeners.get(method) ?? []), resolve]));

    await send('Page.enable');
    await send('Runtime.enable');

    return {
        send,
        async goto(url, settle = 1500) {
            const loaded = once('Page.loadEventFired');
            await send('Page.navigate', { url });
            await loaded;
            await sleep(settle);
        },
        async evaluate(expression) {
            const result = await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
            if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description ?? 'evaluate failed');
            return result.result.value;
        },
        async close() {
            socket.close();
            edge.kill();
            await sleep(500);
            rmSync(profile, { recursive: true, force: true });
        },
    };
}

/** Click the first button or link whose visible text contains `text`. */
const clickText = (text) => `(() => {
    const el = [...document.querySelectorAll('button, a')].find((node) => node.innerText.trim().includes(${JSON.stringify(text)}) && node.offsetParent !== null);
    if (!el) throw new Error('No button with text: ' + ${JSON.stringify(text)});
    el.click();
})()`;

async function capture() {
    const page = await launch();
    const outDir = join(here, 'img');
    mkdirSync(outDir, { recursive: true });

    const phone = (height = 844) => page.send('Emulation.setDeviceMetricsOverride', { width: 390, height, deviceScaleFactor: 2, mobile: true });
    const desktop = () => page.send('Emulation.setDeviceMetricsOverride', { width: 1280, height: 800, deviceScaleFactor: 1.5, mobile: false });
    // A taller phone screen for long pages, so the bottom menu bar stays at the bottom.
    const shot = async (name, height) => {
        if (height) {
            await phone(height);
            await sleep(500);
        }
        const { data } = await page.send('Page.captureScreenshot', { format: 'jpeg', quality: 82 });
        writeFileSync(join(outDir, `${name}.jpg`), Buffer.from(data, 'base64'));
        if (height) await phone();
        console.log('  saved', name);
    };

    try {
        await phone();
        await page.goto(`${demoUrl}/login`);
        await shot('01-masuk');

        await page.evaluate(`(() => {
            const set = (selector, value) => {
                const input = document.querySelector(selector);
                input.value = value;
                input.dispatchEvent(new Event('input', { bubbles: true }));
            };
            set('input[type=email]', 'owner@demo.test');
            set('input[type=password]', 'password1');
            document.querySelector('button[type=submit]').click();
        })()`);
        await sleep(3000);

        // Safety: screenshots go into a public repository, so only the fictional demo shop.
        const shop = await page.evaluate(`document.querySelector('header')?.innerText ?? ''`);
        if (!shop.includes('Bengkel Demo')) throw new Error(`Refusing to capture: this server shows "${shop.trim()}", not the demo shop.`);

        const pages = [
            ['02-beranda', '/dashboard', 1500],
            ['03-spk-daftar', '/work-orders'],
            ['04-spk-baru', '/work-orders/create'],
            ['05-spk-detail', '/work-orders/2', 1500],
            ['06-invoice', '/invoices/1', 1500],
            ['08-piutang', '/customer-debts'],
            ['09-penjualan-langsung', '/invoices/create'],
            ['10-pembelian', '/purchases/create'],
            ['11-hutang-supplier', '/supplier-debts'],
            ['13-kas', '/cash'],
            ['14-suku-cadang', '/parts'],
            ['15-toko-cabang', '/settings/shop', 1400],
            ['16-pengguna', '/settings/users'],
            ['17-peran', '/settings/roles'],
        ];
        for (const [name, path, height] of pages) {
            await page.goto(demoUrl + path);
            await shot(name, height);
        }

        await page.goto(`${demoUrl}/invoices/1`);
        await page.evaluate(clickText('Bayar'));
        await sleep(1200);
        await shot('07-bayar');

        await page.goto(`${demoUrl}/expenses`);
        await page.evaluate(clickText('Catat pengeluaran'));
        await sleep(1200);
        await shot('12-pengeluaran');

        await page.goto(`${demoUrl}/settings/roles`);
        await page.evaluate(clickText('Admin Depan'));
        await sleep(2000);
        await shot('18-hak-akses');

        // A dropdown being filtered by typing.
        await page.goto(`${demoUrl}/purchases/create`);
        await page.evaluate(`(() => {
            const input = document.getElementById('supplier_id');
            input.focus();
            input.click();
        })()`);
        await sleep(600);
        await page.send('Input.insertText', { text: 'motor' });
        await sleep(800);
        await shot('19-pilihan-ketik');

        await desktop();
        await page.goto(`${demoUrl}/dashboard`, 2000);
        await shot('00-beranda-desktop');
    } finally {
        await page.close();
    }
}

async function pdf() {
    const page = await launch();
    try {
        await page.goto(pathToFileURL(join(here, 'panduan.html')).href, 1500);
        const { data } = await page.send('Page.printToPDF', {
            printBackground: true,
            preferCSSPageSize: true,
            generateDocumentOutline: true,
            displayHeaderFooter: false,
        });
        const out = join(here, '..', 'Panduan-BengkelSync.pdf');
        writeFileSync(out, Buffer.from(data, 'base64'));
        console.log('saved', out);
    } finally {
        await page.close();
    }
}

if (!edgePath) throw new Error('Microsoft Edge not found; set EDGE to msedge.exe');
const command = process.argv[2];
if (command === 'capture') await capture();
else if (command === 'pdf') await pdf();
else console.log('Usage: node docs/panduan/build.mjs capture|pdf');
