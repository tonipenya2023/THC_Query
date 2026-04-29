import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawn } from 'node:child_process';
import { spawnSync } from 'node:child_process';

const args = Object.fromEntries(process.argv.slice(2).map((arg) => {
  const idx = arg.indexOf('=');
  if (idx === -1) return [arg.replace(/^--/, ''), '1'];
  return [arg.slice(2, idx), arg.slice(idx + 1)];
}));

const browser = String(args.browser || 'edge').toLowerCase();
const output = String(args.output || '');
const timeoutMs = Number(args.timeoutMs || 20000);

if (!output) {
  console.error('Usage: node src/import_thehunter_cookie_browser.mjs --output=... [--browser=edge|chrome]');
  process.exit(1);
}

const homeDir = os.homedir();
const browserProfiles = {
  edge: {
    exe: process.env.THC_EDGE_PATH || 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
    userData: path.join(homeDir, 'AppData', 'Local', 'Microsoft', 'Edge', 'User Data'),
  },
  chrome: {
    exe: process.env.THC_CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    userData: path.join(homeDir, 'AppData', 'Local', 'Google', 'Chrome', 'User Data'),
  },
};

const selected = browserProfiles[browser];
if (!selected) {
  console.error(`Unsupported browser: ${browser}`);
  process.exit(1);
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
      socket.send(JSON.stringify({ id: msgId, method, params }));
      return await new Promise((resolve, reject) => pending.set(msgId, { resolve, reject }));
    },
    close() {
      try { socket.close(); } catch {}
    },
  };
}

function ensurePathExists(targetPath, label) {
  if (!fs.existsSync(targetPath)) {
    throw new Error(`${label} not found: ${targetPath}`);
  }
}

function tryCopyFile(source, target) {
  if (!fs.existsSync(source)) {
    return false;
  }
  fs.mkdirSync(path.dirname(target), { recursive: true });
  try {
    fs.copyFileSync(source, target);
    return true;
  } catch {
    const psScript = `
      $src = [System.IO.Path]::GetFullPath(${JSON.stringify(source)});
      $dst = [System.IO.Path]::GetFullPath(${JSON.stringify(target)});
      [System.IO.Directory]::CreateDirectory([System.IO.Path]::GetDirectoryName($dst)) | Out-Null
      $in = [System.IO.File]::Open($src, [System.IO.FileMode]::Open, [System.IO.FileAccess]::Read, [System.IO.FileShare]::ReadWrite)
      try {
        $out = [System.IO.File]::Open($dst, [System.IO.FileMode]::Create, [System.IO.FileAccess]::Write, [System.IO.FileShare]::None)
        try {
          $in.CopyTo($out)
        } finally {
          $out.Dispose()
        }
      } finally {
        $in.Dispose()
      }
    `;
    const result = spawnSync('powershell', ['-NoProfile', '-Command', psScript], {
      stdio: 'ignore',
      windowsHide: true,
    });
    return result.status === 0 && fs.existsSync(target);
  }
}

function copyProfileToTemp(sourceUserDataDir, tempUserDataDir) {
  ensurePathExists(sourceUserDataDir, 'User data dir');
  const defaultDir = path.join(sourceUserDataDir, 'Default');
  const localState = path.join(sourceUserDataDir, 'Local State');
  ensurePathExists(defaultDir, 'Default profile');
  ensurePathExists(localState, 'Local State');

  fs.mkdirSync(tempUserDataDir, { recursive: true });
  fs.copyFileSync(localState, path.join(tempUserDataDir, 'Local State'));
  fs.mkdirSync(path.join(tempUserDataDir, 'Default', 'Network'), { recursive: true });

  const copiedCookieDb = tryCopyFile(
    path.join(defaultDir, 'Network', 'Cookies'),
    path.join(tempUserDataDir, 'Default', 'Network', 'Cookies'),
  );
  tryCopyFile(
    path.join(defaultDir, 'Network', 'Cookies-journal'),
    path.join(tempUserDataDir, 'Default', 'Network', 'Cookies-journal'),
  );
  tryCopyFile(
    path.join(defaultDir, 'Preferences'),
    path.join(tempUserDataDir, 'Default', 'Preferences'),
  );
  tryCopyFile(
    path.join(defaultDir, 'Secure Preferences'),
    path.join(tempUserDataDir, 'Default', 'Secure Preferences'),
  );

  if (!copiedCookieDb) {
    throw new Error('Could not copy browser cookie database from local profile');
  }
}

function cookiesToHeader(cookies) {
  return cookies
    .filter((c) => c && typeof c.name === 'string' && typeof c.value === 'string' && c.name && c.value)
    .sort((a, b) => a.name.localeCompare(b.name))
    .map((c) => `${c.name}=${c.value}`)
    .join('; ');
}

async function main() {
  ensurePathExists(selected.exe, 'Browser executable');

  const tempRoot = fs.mkdtempSync(path.join(os.tmpdir(), `thc-${browser}-cookie-`));
  const tempUserData = path.join(tempRoot, 'User Data');
  const debugPort = 9427 + Math.floor(Math.random() * 200);
  let browserProcess = null;
  let client = null;

  try {
    copyProfileToTemp(selected.userData, tempUserData);

    browserProcess = spawn(selected.exe, [
      `--user-data-dir=${tempUserData}`,
      '--profile-directory=Default',
      '--headless=new',
      `--remote-debugging-port=${debugPort}`,
      '--disable-gpu',
      '--no-first-run',
      '--no-default-browser-check',
      'https://www.thehunter.com/',
    ], { stdio: 'ignore' });

    await waitForJson(`http://127.0.0.1:${debugPort}/json/version`, 10000);
    const list = await waitForJson(`http://127.0.0.1:${debugPort}/json/list`, 10000);
    const page = Array.isArray(list) ? list.find((x) => x.type === 'page') : null;
    if (!page?.webSocketDebuggerUrl) {
      throw new Error('No page target found in browser');
    }

    client = await cdp(page.webSocketDebuggerUrl);
    await client.send('Page.enable');
    await client.send('Network.enable');
    await client.send('Page.navigate', { url: 'https://www.thehunter.com/' });
    await sleep(4000);

    const result = await client.send('Network.getCookies', {
      urls: ['https://www.thehunter.com/', 'https://api.thehunter.com/'],
    });

    const cookies = Array.isArray(result?.cookies) ? result.cookies : [];
    const filtered = cookies.filter((cookie) => {
      const domain = String(cookie.domain || '').toLowerCase();
      return domain.includes('thehunter.com');
    });
    const header = cookiesToHeader(filtered);
    const hasHunter = filtered.some((cookie) => String(cookie.name || '') === 'hunter');

    fs.writeFileSync(output, JSON.stringify({
      ok: header !== '' && hasHunter,
      browser,
      cookie: header,
      cookie_count: filtered.length,
      has_hunter_cookie: hasHunter,
    }), 'utf8');
  } finally {
    try { client?.close(); } catch {}
    await sleep(300);
    try { browserProcess?.kill(); } catch {}
    try { fs.rmSync(tempRoot, { recursive: true, force: true }); } catch {}
  }
}

main().catch((err) => {
  try {
    fs.writeFileSync(output, JSON.stringify({
      ok: false,
      browser,
      error: String(err?.stack || err || 'Unknown error'),
    }), 'utf8');
  } catch {}
  console.error(err?.stack || String(err));
  process.exit(1);
});
