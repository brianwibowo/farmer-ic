// Native CDP + installed Edge; no browser package or real provider needed.
import { spawn } from 'node:child_process';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const origin =
    process.env.NOTIFICATION_BROWSER_ORIGIN ?? 'http://127.0.0.1:9088';
const output = path.resolve('storage/app/notification-browser');
await mkdir(output, { recursive: true });
const debugPort = 9300 + (process.pid % 1000);
const edge = spawn(
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
    [
        '--headless=new',
        '--disable-gpu',
        '--no-first-run',
        '--no-default-browser-check',
        '--disable-extensions',
        '--remote-allow-origins=*',
        `--remote-debugging-port=${debugPort}`,
        `--user-data-dir=${path.join(output, 'profile-' + process.pid)}`,
        'about:blank',
    ],
    { stdio: 'ignore', windowsHide: true },
);
const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
let socket;
let id = 0;
const pending = new Map();
const errors = [];
const results = [];

async function command(method, params = {}) {
    return new Promise((resolve, reject) => {
        const requestId = ++id;
        const timer = setTimeout(() => {
            pending.delete(requestId);
            reject(new Error(`CDP timeout: ${method}`));
        }, 15000);
        pending.set(requestId, { resolve, reject, timer });
        socket.send(
            JSON.stringify({
                id: requestId,
                method,
                params,
            }),
        );
    });
}
async function evaluate(expression) {
    const result = await command('Runtime.evaluate', {
        expression,
        returnByValue: true,
        awaitPromise: true,
    });
    if (result.exceptionDetails) throw new Error(result.exceptionDetails.text);
    return result.result.value;
}
async function until(expression) {
    for (let attempt = 0; attempt < 100; attempt++) {
        if (await evaluate(expression)) return;
        await delay(150);
    }
    await writeFile(
        path.join(output, 'failure.json'),
        JSON.stringify(
            await evaluate(
                `({url:location.href, text:document.body.innerText, errors:document.querySelector('#app')?.getAttribute('data-page')})`,
            ),
            null,
            2,
        ),
    );
    throw new Error(`Browser condition timed out: ${expression}`);
}

async function click(selector) {
    await until(
        `!document.getElementById('startup-screen') && !document.getElementById('app')?.hasAttribute('inert')`,
    );
    const point = await evaluate(
        `(() => { const el=document.querySelector(${JSON.stringify(selector)}); el.scrollIntoView({block:'center',behavior:'instant'}); const r=el.getBoundingClientRect(); return {x:r.x+r.width/2,y:r.y+r.height/2}; })()`,
    );
    await command('Input.dispatchMouseEvent', {
        type: 'mousePressed',
        ...point,
        button: 'left',
        clickCount: 1,
    });
    await command('Input.dispatchMouseEvent', {
        type: 'mouseReleased',
        ...point,
        button: 'left',
        clickCount: 1,
    });
}

try {
    let tabs;
    for (let attempt = 0; attempt < 50; attempt++) {
        try {
            tabs = await fetch(`http://127.0.0.1:${debugPort}/json/list`).then(
                (response) => response.json(),
            );
            break;
        } catch {
            await delay(150);
        }
    }
    if (!tabs) throw new Error('Headless Edge did not start.');
    socket = new WebSocket(
        tabs.find((tab) => tab.type === 'page').webSocketDebuggerUrl,
    );
    await new Promise((resolve, reject) => {
        socket.addEventListener('open', resolve, { once: true });
        socket.addEventListener('error', reject, { once: true });
    });
    socket.addEventListener('message', (event) => {
        const data = JSON.parse(event.data);
        if (process.env.NOTIFICATION_BROWSER_DEBUG)
            console.log(
                JSON.stringify({
                    id: data.id,
                    method: data.method,
                    error: data.error,
                }),
            );
        if (data.id && pending.has(data.id)) {
            const request = pending.get(data.id);
            clearTimeout(request.timer);
            pending.delete(data.id);
            if (data.error)
                request.reject(new Error(JSON.stringify(data.error)));
            else request.resolve(data.result);
        }
        if (data.method === 'Runtime.exceptionThrown')
            errors.push(data.params.exceptionDetails.text);
        if (
            data.method === 'Log.entryAdded' &&
            ['error', 'warning'].includes(data.params.entry.level)
        )
            errors.push(data.params.entry.text);
        if (
            data.method === 'Runtime.consoleAPICalled' &&
            ['error', 'warning'].includes(data.params.type)
        )
            errors.push(JSON.stringify(data.params.args));
    });
    await command('Browser.getVersion');
    await command('Page.enable');
    await command('Runtime.enable');
    await command('Log.enable');
    await command('Network.enable');
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
        await until(
            `location.pathname !== '/login' && !document.querySelector('input[name="password"]')`,
        );
        await command('Page.navigate', { url: `${origin}/notifications` });
        await until(
            role === 'empty'
                ? `document.body?.innerText.includes('Semua notifikasi sudah dibaca')`
                : `Boolean(document.querySelector('[data-rich-inbox]'))`,
        );
        await until(
            `!document.getElementById('startup-screen') && !document.getElementById('app')?.hasAttribute('inert')`,
        );
        if (
            await evaluate(
                `Boolean(document.querySelector('[data-rich-inbox] details, [data-rich-inbox] [role="combobox"], form[aria-label="Filter notifikasi"]'))`,
            )
        )
            throw new Error('Inbox still has filters or dropdown details.');
        if (
            await evaluate(
                `document.querySelectorAll('[aria-label="Filter status notifikasi"] button').length !== 2`,
            )
        )
            throw new Error('Missing status tabs');
        if (role !== 'empty') {
            await click('[data-rich-inbox] header > button');
            await until(
                `document.querySelector('[data-rich-inbox] header > button')?.getAttribute('aria-expanded')==='true'`,
            );
            await click('[data-rich-inbox] header > button');
            await until(
                `document.querySelector('[data-rich-inbox] header > button')?.getAttribute('aria-expanded')==='false'`,
            );
            await click('[data-rich-inbox] header > button');
        }
        if (
            role === 'owner' &&
            (await evaluate(
                `document.querySelector('[data-notification-content="weekly_owner"]').innerText.includes('kabupaten')`,
            ))
        )
            throw new Error('Owner has county breakdown.');
        if (
            role === 'owner' &&
            !(await evaluate(
                `(() => {const header=document.querySelector('[data-rich-inbox="weekly_owner"] header > button'); return header.innerText.includes('Laporan Mingguan Panen') && header.innerText.includes('Periode ') && !header.innerText.includes('Panen mingguan') && !header.innerText.includes('Total seluruh lahan') && !header.querySelector('time');})()`,
            ))
        )
            throw new Error('Owner header is not compact');
        if (role !== 'empty') {
            const selector =
                role === 'petugas'
                    ? '[data-notification-content="task_reminder"] a'
                    : '[data-notification-content="weekly_admin"] a';
            if (role === 'admin' || role === 'petugas') {
                const href = await evaluate(
                    `document.querySelector('${selector}').getAttribute('href')`,
                );
                if (
                    role === 'petugas' &&
                    (!href.includes('block_id=') ||
                        !href.startsWith('/petugas/aktivitas?'))
                )
                    throw new Error('Invalid task link');
                if (role === 'admin' && !href.startsWith('/admin/logbook/'))
                    throw new Error('Invalid logbook link');
                await click(selector);
                await until(
                    role === 'admin'
                        ? `location.pathname.startsWith('/admin/logbook/') && Boolean(document.querySelector('h1'))`
                        : `location.pathname === '/petugas/aktivitas' && Boolean(document.querySelector('[role="combobox"]'))`,
                );
                if (
                    role === 'petugas' &&
                    !(await evaluate(
                        `document.body.innerText.includes('Pemupukan susulan') && !document.body.innerText.includes('Pemeriksaan hama dan kondisi tanaman')`,
                    ))
                )
                    throw new Error('Block filter did not apply');
                await command('Page.navigate', {
                    url: origin + '/notifications',
                });
                await until(
                    `Boolean(document.querySelector('[data-rich-inbox]'))`,
                );
            }
            if (role === 'admin') {
                await click(
                    '[data-rich-inbox="sop_deviation"] header > button',
                );
                await click('[data-notification-content="sop_deviation"] a');
                await until(
                    `location.pathname === '/admin/tasks' && location.search.includes('view=overdue') && Boolean(document.querySelector('h1'))`,
                );
                if (
                    !(await evaluate(
                        `document.body.innerText.includes('Pemupukan dasar') && !document.body.innerText.includes('Pemupukan susulan')`,
                    ))
                )
                    throw new Error('Overdue view did not apply');
                await command('Page.navigate', {
                    url: origin + '/notifications',
                });
                await until(
                    `Boolean(document.querySelector('[data-rich-inbox]'))`,
                );
            }
        }
        await until(
            `!document.getElementById('startup-screen') && !document.getElementById('app')?.hasAttribute('inert')`,
        );
        for (const width of [320, 390, 430, 768, 1280]) {
            await command('Emulation.setDeviceMetricsOverride', {
                width,
                height: 900,
                deviceScaleFactor: 1,
                mobile: width < 768,
            });
            for (const theme of ['light', 'dark']) {
                await evaluate(
                    `document.documentElement.classList.toggle('dark', ${theme === 'dark'})`,
                );
                await delay(100);
                if (
                    await evaluate(
                        `document.documentElement.scrollWidth > innerWidth`,
                    )
                )
                    throw new Error('Inbox overflows');
                const shot = await command('Page.captureScreenshot', {
                    format: 'png',
                });
                await writeFile(
                    path.join(output, `${role}-links-${width}-${theme}.png`),
                    Buffer.from(shot.data, 'base64'),
                );
                results.push({ role, width, theme });
            }
        }
        if (role === 'empty') continue;
        await click(
            '[data-notification-id] button[aria-label="Tandai dibaca"]',
        );
        await until(
            `!document.querySelector('[aria-busy="true"]') && document.body.innerText.includes('Notifikasi ditandai sudah dibaca.')`,
        );
        await evaluate(
            `Array.from(document.querySelectorAll('[aria-label="Filter status notifikasi"] button')).find(b=>b.innerText.trim()==='Sudah dibaca').click()`,
        );
        await until(
            `Array.from(document.querySelectorAll('[aria-label="Filter status notifikasi"] button')).some(b=>b.innerText.trim()==='Sudah dibaca' && b.getAttribute('aria-pressed')==='true') && Boolean(document.querySelector('[data-rich-inbox]'))`,
        );
    }
    await writeFile(
        path.join(output, 'results.json'),
        JSON.stringify({ results, errors }, null, 2),
    );
    console.log(
        JSON.stringify({ screenshots: results.length, errors, output }),
    );
    if (errors.length) process.exitCode = 1;
} finally {
    socket?.close();
    edge.kill();
}
