#!/usr/bin/env node
/**
 * One-command local start: the website (fast PHP dev server) and the Reverb live-update server.
 *
 *   npm run serve               site on http://127.0.0.1:8000, Reverb on ws://127.0.0.1:8080
 *   npm run serve -- --lan      listen on every network interface so phones can connect
 *   npm run serve -- --vite     also run the Vite dev server (only needed while editing CSS/JS)
 *
 * Why not `php artisan serve`? One command starts everything, and the PHP server is launched with
 * OPcache enabled and, where the platform supports it (not Windows), several workers so one slow
 * request cannot block the others. Measured on Windows this made no difference to page speed; the
 * big speed-up for tapping "Add" came from the optimistic UI in the menu (see TableMenu.php).
 */
import { spawn } from 'node:child_process';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const publicDir = join(root, 'public');

const args = new Set(process.argv.slice(2));
const host = args.has('--lan') ? '0.0.0.0' : '127.0.0.1';
const port = process.env.PORT ?? '8000';
const reverbPort = process.env.REVERB_PORT ?? '8080';

const children = [];

function run(name, command, commandArgs, env = {}, cwd = root) {
    // Through a shell, arguments are not quoted for us, and this project lives in a path with spaces.
    const quoted = process.platform === 'win32'
        ? commandArgs.map((a) => (/\s/.test(a) ? `"${a}"` : a))
        : commandArgs;

    const child = spawn(command, quoted, {
        cwd,
        // stdin must stay open: Reverb shuts itself down when its stdin is closed.
        stdio: ['pipe', 'pipe', 'pipe'],
        env: { ...process.env, ...env },
        // On Windows `php` and `npm` are .bat/.cmd shims, which can only be started through a shell.
        shell: process.platform === 'win32',
    });
    child.on('error', (error) => {
        console.error(`[${name}] could not start: ${error.message}`);
        shutdown();
    });
    const tag = `[${name}]`.padEnd(9);
    const pipe = (stream) => stream.on('data', (chunk) => {
        for (const line of chunk.toString().split(/\r?\n/)) {
            if (line.trim()) console.log(`${tag} ${line}`);
        }
    });
    pipe(child.stdout);
    pipe(child.stderr);
    child.on('exit', (code) => {
        console.log(`${tag} exited (${code})`);
        if (name === 'site') shutdown();
        // The app keeps working without Reverb or Vite (screens just refresh by polling), so only
        // the website itself ends the session.
    });
    children.push(child);
}

let closing = false;
function shutdown() {
    if (closing) return;
    closing = true;
    for (const child of children) {
        if (process.platform === 'win32' && child.pid) {
            // The shell wrapper hides the real php process; kill the whole tree.
            spawn('taskkill', ['/pid', String(child.pid), '/T', '/F'], { stdio: 'ignore' });
        } else {
            child.kill();
        }
    }
    setTimeout(() => process.exit(0), 500);
}
process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);

run('site', 'php', [
    '-d', 'opcache.enable=1',
    '-d', 'opcache.enable_cli=1',
    '-d', 'opcache.validate_timestamps=1',
    '-d', 'opcache.revalidate_freq=1',
    '-S', `${host}:${port}`,
    '-t', publicDir,
    // Laravel's own router script; like `artisan serve` it must run from the public directory.
    join(root, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'),
], process.platform === 'win32' ? {} : { PHP_CLI_SERVER_WORKERS: '4' }, publicDir); // workers are not supported on Windows

run('reverb', 'php', ['artisan', 'reverb:start', `--host=${host}`, `--port=${reverbPort}`]);

if (args.has('--vite')) {
    run('vite', 'npm', ['run', 'dev']);
}

console.log(`\nSite    http://127.0.0.1:${port}\nKitchen http://127.0.0.1:${port}/kitchen\nAdmin   http://127.0.0.1:${port}/admin\nPress Ctrl+C to stop everything.\n`);
