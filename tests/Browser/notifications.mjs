// Native CDP + installed Edge; no browser package or real provider needed.
import { spawn } from 'node:child_process';
import { mkdir, writeFile, readFile } from 'node:fs/promises';
import path from 'node:path';

const origin = 'http://127.0.0.1:9088';
const output = path.resolve('storage/app/notification-browser');
await mkdir(output, { recursive: true });
const fixture = JSON.parse(await readFile(path.join(output, 'fixture-users.json'), 'utf8'));
const edge = spawn('C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe', [
    '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
    '--disable-extensions', '--remote-allow-origins=*',
    '--remote-debugging-port=9228', `--user-data-dir=${path.join(output, 'profile')}`, 'about:blank',
], { stdio: 'ignore', windowsHide: true });
const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
let socket;
let id = 0;
let targetSession;
const pending = new Map();
const errors = [];
const results = [];

async function command(method, params = {}) {
    return new Promise((resolve, reject) => {
        const requestId = ++id;
        const timer = setTimeout(() => { pending.delete(requestId); reject(new Error(`CDP timeout: ${method}`)); }, 15000);
        pending.set(requestId, { resolve, reject, timer });
        socket.send(JSON.stringify({ id: requestId, method, params, ...(targetSession ? { sessionId: targetSession } : {}) }));
    });
}
async function evaluate(expression) {
    const result = await command('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
    if (result.exceptionDetails) throw new Error(result.exceptionDetails.text);
    return result.result.value;
}
async function until(expression) {
    for (let attempt = 0; attempt < 100; attempt++) {
        if (await evaluate(expression)) return;
        await delay(150);
    }
    throw new Error(`Browser condition timed out: ${expression}`);
}

try {
    let tabs;
    for (let attempt = 0; attempt < 50; attempt++) {
        try { tabs = await fetch('http://127.0.0.1:9228/json/list').then((response) => response.json()); break; }
        catch { await delay(150); }
    }
    if (!tabs) throw new Error('Headless Edge did not start.');
    socket = new WebSocket(tabs.find((tab) => tab.type === 'page').webSocketDebuggerUrl);
    await new Promise((resolve, reject) => {
        socket.addEventListener('open', resolve, { once: true });
        socket.addEventListener('error', reject, { once: true });
    });
    socket.addEventListener('message', (event) => {
        const data = JSON.parse(event.data);
        if (process.env.NOTIFICATION_BROWSER_DEBUG) console.log(JSON.stringify({ id: data.id, method: data.method, error: data.error }));
        if (data.id && pending.has(data.id)) {
            const request = pending.get(data.id);
            clearTimeout(request.timer); pending.delete(data.id);
            if (data.error) request.reject(new Error(JSON.stringify(data.error)));
            else request.resolve(data.result);
        }
        if (data.method === 'Runtime.exceptionThrown') errors.push(data.params.exceptionDetails.text);
        if (data.method === 'Log.entryAdded' && ['error', 'warning'].includes(data.params.entry.level)) errors.push(data.params.entry.text);
        if (data.method === 'Runtime.consoleAPICalled' && ['error', 'warning'].includes(data.params.type)) errors.push(JSON.stringify(data.params.args));
    });
    await command('Browser.getVersion');
    const target = await command('Target.createTarget', { url: 'about:blank' });
    const attached = await command('Target.attachToTarget', { targetId: target.targetId, flatten: true });
    targetSession = attached.sessionId;
    await command('Runtime.runIfWaitingForDebugger');
    await command('Page.enable'); await command('Runtime.enable'); await command('Log.enable'); await command('Network.enable');
    for (const role of ['admin', 'owner', 'petugas', 'empty']) {
        await command('Network.clearBrowserCookies');
        await command('Page.navigate', { url: `${origin}/login` });
        await until(`Boolean(document.querySelector('input[name="email"]'))`);
        await evaluate(`(() => {
            const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set;
            for (const [name, value] of [['email','${role}@notifications-ui.example'],['password','Notification-UI-Only-42!']]) {
                const input = document.querySelector('input[name="'+name+'"]'); setter.call(input,value);
                input.dispatchEvent(new Event('input',{bubbles:true}));
            }
            document.querySelector('form').requestSubmit();
        })()`);
        await until(`location.pathname !== '/login' && !document.querySelector('input[name="password"]')`);
        await command('Page.navigate', { url: `${origin}/notifications` });
        await until(role === 'empty' ? `document.body?.innerText.includes('Semua notifikasi sudah dibaca')` : `Boolean(document.querySelector('details'))`);
        await until(`!document.getElementById('startup-screen') && !document.getElementById('app')?.hasAttribute('inert')`);
        if (await evaluate(`Boolean(document.querySelector('form[aria-label="Filter notifikasi"]'))`)) throw new Error('Unread tab unexpectedly shows filters.');
        if (await evaluate(`document.querySelectorAll('div[aria-label="Filter status notifikasi"] button').length !== 2`)) throw new Error('Expected exactly two statuses.');
        if (role !== 'empty' && await evaluate(`document.querySelectorAll('details[open]').length !== 0 || document.querySelectorAll('[aria-label="Daftar notifikasi berdasarkan tanggal"] > section').length < 2`)) throw new Error('Unread history is not grouped or cards are not collapsed.');
        if (role === 'admin' && await evaluate(`document.body.innerText.includes('Tenant Browser Notifikasi')`)) throw new Error('Admin tenant copy leaked.');
        for (const width of [320, 390, 430, 1280]) {
            await command('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: width < 768 });
            for (const theme of ['light', 'dark']) {
                await evaluate(`document.documentElement.classList.toggle('dark', ${theme === 'dark'})`);
                await delay(150);
                const check = await evaluate(`({overflow:document.documentElement.scrollWidth > innerWidth, width:innerWidth, theme:getComputedStyle(document.querySelector('h1')).color})`);
                if (check.overflow) throw new Error(`Page overflow: ${role}/${width}/${theme}`);
                const screenshot = await command('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
                await writeFile(path.join(output, `${role}-${width}-${theme}.png`), Buffer.from(screenshot.data, 'base64'));
                results.push({ role, width, theme, overflow: check.overflow, foreground: check.theme });
            }
        }
        if (role === 'empty') continue;
        await evaluate(`document.querySelector('details summary').click()`);
        await until(`document.querySelector('details')?.open`);
        const noticeId = await evaluate(`document.querySelector('[data-notification-id]').dataset.notificationId`);
        await evaluate(`Array.from(document.querySelectorAll('details[open] button')).find(button=>button.innerText.includes('Tandai dibaca')).click()`);
        await until(`!document.querySelector('[aria-busy="true"]') && !document.querySelector('[data-notification-id="${noticeId}"]')`);
        await evaluate(`Array.from(document.querySelectorAll('button')).find(button=>button.innerText.trim()==='Semua').click()`);
        await until(`Boolean(document.getElementById('notification-date-from')) && !document.querySelector('[aria-busy="true"]')`);
        if (await evaluate(`document.getElementById('notification-date-from').value !== '${fixture.today}' || Boolean(document.getElementById('notification-tenant')) !== ${role === 'owner'}`)) throw new Error('Incorrect all-tab filters.');
        // Date changes apply automatically, without submitting a form.
        await evaluate(`(() => {const input=document.getElementById('notification-date-from'); Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value').set.call(input,'${fixture.date_from}'); input.dispatchEvent(new Event('input',{bubbles:true}));})()`);
        await until(`!document.querySelector('[aria-busy="true"]') && Boolean(document.querySelector('nav[aria-label="Halaman notifikasi"]'))`);
        await evaluate(`Array.from(document.querySelectorAll('button')).find(button=>button.innerText==='Berikutnya').click()`);
        await until(`!document.querySelector('[aria-busy="true"]') && document.querySelector('nav[aria-label="Halaman notifikasi"] [aria-current="page"]')?.innerText.startsWith('2')`);
        if (await evaluate(`document.getElementById('notification-date-from').value !== '${fixture.date_from}' || location.search !== ''`)) throw new Error('Pagination lost active filters or URL persisted.');
        if (role === 'owner') {
            await evaluate(`(() => {const select=document.getElementById('notification-tenant');select.value='${fixture.second_team_id}';select.dispatchEvent(new Event('change',{bubbles:true}));})()`);
            await until(`!document.querySelector('[aria-busy="true"]') && document.querySelectorAll('[data-notification-id]').length===1 && document.querySelector('summary')?.innerText.includes('Tenant Kebun Bandung')`);
        }
        for (const width of [320, 1280]) {
            await command('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: width < 768 });
            for (const theme of ['light', 'dark']) {
                await evaluate(`document.documentElement.classList.toggle('dark', ${theme === 'dark'})`);
                await delay(100);
                if (await evaluate(`document.documentElement.scrollWidth > innerWidth`)) throw new Error(`All tab overflow ${role}/${width}/${theme}`);
                const shot = await command('Page.captureScreenshot', { format: 'png' });
                await writeFile(path.join(output, `${role}-all-${width}-${theme}.png`), Buffer.from(shot.data, 'base64'));
                results.push({ role, tab: 'all', width, theme });
            }
        }
        await command('Page.reload');
        await until(`Boolean(document.querySelector('details')) && !document.querySelector('form[aria-label="Filter notifikasi"]') && !document.querySelector('[aria-busy="true"]')`);
    }
    await writeFile(path.join(output, 'results.json'), JSON.stringify({ results, errors }, null, 2));
    console.log(JSON.stringify({ screenshots: results.length, errors, output }));
    if (errors.length) process.exitCode = 1;
} finally {
    socket?.close();
    edge.kill();
}
