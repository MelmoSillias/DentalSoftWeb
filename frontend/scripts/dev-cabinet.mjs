import process from 'node:process';
import { spawnSync } from 'node:child_process';

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

function run(command, args, env) {
    const result = spawnSync(command, args, {
        stdio: 'inherit',
        env,
        shell: false
    });

    if (result.error) {
        throw result.error;
    }

    if (typeof result.status === 'number' && result.status !== 0) {
        process.exit(result.status);
    }
}

function main() {
    const { cabinet, viteArgs } = parseArgs(process.argv.slice(2));
    const env = { ...process.env, CABINET: cabinet };

    const nodeBin = process.execPath;
    run(nodeBin, ['./scripts/select-cabinet.mjs', `--cabinet=${cabinet}`], env);

    const viteBin = process.platform === 'win32' ? 'npx.cmd' : 'npx';
    run(viteBin, ['vite', ...viteArgs], env);
}

main();