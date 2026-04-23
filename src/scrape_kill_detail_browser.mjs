import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawn } from 'node:child_process';

const edgePath = process.env.THC_EDGE_PATH || 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const args = Object.fromEntries(process.argv.slice(2).map((arg) => {
  const idx = arg.indexOf('=');
  if (idx === -1) return [arg.replace(/^--/, ''), '1'];
  return [arg.slice(2, idx), arg.slice(idx + 1)];
}));

const url = args.url || '';
const input = args.input || '';
let cookie = args.cookie || '';
const cookieFile = args.cookieFile || '';
const cookieKey = args.cookieKey || '';
const output = args.output || '';
const timeoutMs = Number(args.timeoutMs || 25000);
const settleMs = Number(args.settleMs || 800);

if (!cookie && cookieFile && cookieKey) {
  try {
    const raw = fs.readFileSync(cookieFile, 'utf8');
    const parsed = JSON.parse(raw);
    if (parsed && typeof parsed[cookieKey] === 'string') {
      cookie = parsed[cookieKey].trim();
    }
  } catch {}
}

if ((!url && !input) || !cookie || !output) {
  console.error('Usage: node src/scrape_kill_detail_browser.mjs (--url=... | --input=...) (--cookie=... | --cookieFile=... --cookieKey=...) --output=...');
  process.exit(1);
}

const profileDir = fs.mkdtempSync(path.join(os.tmpdir(), 'thc-edge-'));
const debugPort = 9227 + Math.floor(Math.random() * 200);

function parseCookieString(cookieString) {
  return cookieString
    .split(';')
    .map((part) => part.trim())
    .filter(Boolean)
    .map((part) => {
      const eq = part.indexOf('=');
      return {
        name: part.slice(0, eq).trim(),
        value: part.slice(eq + 1).trim(),
      };
    })
    .filter((c) => c.name && c.value);
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function waitForJson(urlToFetch, maxWait = 10000) {
  const start = Date.now();
  while (Date.now() - start < maxWait) {
    try {
      const res = await fetch(urlToFetch);
      if (res.ok) return await res.json();
    } catch {}
    await sleep(250);
  }
  throw new Error(`Timeout waiting for ${urlToFetch}`);
}

async function cdp(wsUrl) {
  const socket = new WebSocket(wsUrl);
  await new Promise((resolve, reject) => {
    socket.onopen = resolve;
    socket.onerror = reject;
  });
  let id = 0;
  const pending = new Map();
  socket.onmessage = (event) => {
    const msg = JSON.parse(event.data.toString());
    if (msg.id && pending.has(msg.id)) {
      const { resolve, reject } = pending.get(msg.id);
      pending.delete(msg.id);
      if (msg.error) reject(new Error(msg.error.message || 'CDP error'));
      else resolve(msg.result);
    }
  };
  return {
    async send(method, params = {}) {
      const msgId = ++id;
      const payload = JSON.stringify({ id: msgId, method, params });
      socket.send(payload);
      return await new Promise((resolve, reject) => pending.set(msgId, { resolve, reject }));
    },
    close() {
      try { socket.close(); } catch {}
    },
  };
}

function loadJobs() {
  if (input) {
    const raw = fs.readFileSync(input, 'utf8');
    const parsed = JSON.parse(raw);
    if (!Array.isArray(parsed)) {
      throw new Error('Batch input must be a JSON array');
    }
    return parsed
      .filter((item) => item && typeof item === 'object')
      .map((item) => ({
        kill_id: Number(item.kill_id || 0),
        player_name: String(item.player_name || ''),
        url: String(item.url || ''),
      }))
      .filter((item) => item.kill_id > 0 && item.player_name && item.url);
  }
  return [{ kill_id: 0, player_name: '', url }];
}

async function readPagePayload(client) {
  const startedAt = Date.now();
  let lastParsed = null;
  while (Date.now() - startedAt < timeoutMs) {
    await sleep(1000);
    const evalRes = await client.send('Runtime.evaluate', {
      expression: `(() => {
        const bodyText = document.body ? document.body.innerText : '';
        const title = document.title || '';
        const html = document.documentElement ? document.documentElement.outerHTML : '';
        const ready =
          html.includes('scoretable shots') ||
          html.includes('species-title') ||
          html.includes('var killData =') ||
          bodyText.includes('Cazador:') ||
          bodyText.includes('Distancia del Disparo');
        return JSON.stringify({
          title,
          bodyText,
          html,
          url: location.href,
          ready
        });
      })()`,
      returnByValue: true,
    });
    const raw = evalRes?.result?.value || '{}';
    try {
      lastParsed = JSON.parse(raw);
    } catch {
      lastParsed = { raw, ready: false };
    }
    if (lastParsed && lastParsed.ready) {
      await sleep(settleMs);
      return lastParsed;
    }
  }
  return lastParsed || { title: '', bodyText: '', html: '', url: '', ready: false };
}

async function navigateAndExtract(client, currentUrl) {
  await client.send('Page.navigate', { url: currentUrl });
  return await readPagePayload(client);
}

async function main() {
  const jobs = loadJobs();
  const browser = spawn(edgePath, [
    `--user-data-dir=${profileDir}`,
    '--headless=new',
    `--remote-debugging-port=${debugPort}`,
    '--disable-gpu',
    '--no-first-run',
    '--no-default-browser-check',
    'about:blank',
  ], { stdio: 'ignore' });

  try {
    await waitForJson(`http://127.0.0.1:${debugPort}/json/version`, 10000);
    const list = await waitForJson(`http://127.0.0.1:${debugPort}/json/list`, 10000);
    const page = Array.isArray(list) ? list.find((x) => x.type === 'page') : null;
    if (!page?.webSocketDebuggerUrl) {
      throw new Error('No page target found in Edge');
    }

    const client = await cdp(page.webSocketDebuggerUrl);
    await client.send('Page.enable');
    await client.send('Runtime.enable');
    await client.send('DOM.enable');
    await client.send('Network.enable');

    for (const c of parseCookieString(cookie)) {
      await client.send('Network.setCookie', {
        name: c.name,
        value: c.value,
        domain: '.thehunter.com',
        path: '/',
        secure: true,
        httpOnly: false,
      });
    }

    if (input) {
      const results = [];
      for (const job of jobs) {
        try {
          const payload = await navigateAndExtract(client, job.url);
          results.push({
            kill_id: job.kill_id,
            player_name: job.player_name,
            ok: Boolean(payload && payload.ready),
            payload,
          });
        } catch (error) {
          results.push({
            kill_id: job.kill_id,
            player_name: job.player_name,
            ok: false,
            error: String(error?.stack || error || 'Unknown error'),
          });
        }
      }
      fs.writeFileSync(output, JSON.stringify(results), 'utf8');
    } else {
      const payload = await navigateAndExtract(client, url);
      fs.writeFileSync(output, JSON.stringify(payload), 'utf8');
    }

    client.close();
  } finally {
    await sleep(500);
    try { browser.kill(); } catch {}
    try { fs.rmSync(profileDir, { recursive: true, force: true }); } catch {}
  }
}

main().catch((err) => {
  console.error(err?.stack || String(err));
  process.exit(1);
});
