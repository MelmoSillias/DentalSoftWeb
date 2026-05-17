import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '..');

function parseArgs(argv) {
    const viteArgs = [];
    let cabinet = '';

    for (let i = 0; i < argv.length; i += 1) {
        const token = argv[i];

        if (token.startsWith('--cabinet=')) {
            cabinet = token.slice('--cabinet='.length);
            continue;
        }

        if (token === '--cabinet') {
            const next = argv[i + 1];
            if (next && !next.startsWith('--')) {
                cabinet = next;
                i += 1;
                continue;
            }
        }

        viteArgs.push(token);
    }

    const resolvedCabinet = cabinet || process.env.CABINET || process.env.npm_config_cabinet || 'default';
    return { cabinet: resolvedCabinet, viteArgs };
}

function readApiBaseUrl(cabinetId) {
    try {
        const configPath = path.join(rootDir, 'cabinet-configs', cabinetId, 'config.json');
        if (!fs.existsSync(configPath)) return null;
        const config = JSON.parse(fs.readFileSync(configPath, 'utf8'));
        return config.apiBaseUrl || null;
    } catch {
        return null;
    }
}

function run(command, args, env) {
    const result = spawnSync(command, args, {
        stdio: 'inherit',
        env,
        shell: false,
        cwd: rootDir,
    });

    if (result.error) throw result.error;
    if (typeof result.status === 'number' && result.status !== 0) {
        process.exit(result.status);
    }
}

function main() {
    const { cabinet, viteArgs } = parseArgs(process.argv.slice(2));
    const apiBaseUrl = readApiBaseUrl(cabinet);
    const env = {
        ...process.env,
        CABINET: cabinet,
        ...(apiBaseUrl ? { VITE_API_BASE_URL: apiBaseUrl } : {}),
    };

    const nodeBin = process.execPath;
    run(nodeBin, [path.join(rootDir, 'scripts', 'select-cabinet.mjs'), `--cabinet=${cabinet}`], env);
    run(nodeBin, [path.join(rootDir, 'node_modules', 'vite', 'bin', 'vite.js'), ...viteArgs], env);
}

main();
