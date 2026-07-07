import process from 'node:process';
import { spawnSync } from 'node:child_process';

function parseArgs(argv) {
    const viteArgs = [];
    let cabinet = '';
    let configEnv = '';

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

        if (token.startsWith('--env=')) {
            configEnv = token.slice('--env='.length);
            continue;
        }

        if (token === '--env') {
            const next = argv[i + 1];
            if (next && !next.startsWith('--')) {
                configEnv = next;
                i += 1;
                continue;
            }
        }

        viteArgs.push(token);
    }

    const resolvedCabinet = cabinet || process.env.CABINET || process.env.npm_config_cabinet || 'default';
    const resolvedEnv = configEnv || process.env.CABINET_ENV || process.env.BUILD_ENV || 'dev';
    return { cabinet: resolvedCabinet, configEnv: resolvedEnv, viteArgs };
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
    const { cabinet, configEnv, viteArgs } = parseArgs(process.argv.slice(2));
    const env = {
        ...process.env,
        CABINET: cabinet,
        CABINET_ENV: configEnv,
        BUILD_ENV: configEnv
    };

    const nodeBin = process.execPath;
    run(nodeBin, ['./scripts/select-cabinet.mjs', `--cabinet=${cabinet}`, `--env=${configEnv}`], env);

    run(nodeBin, ['./node_modules/vite/bin/vite.js', ...viteArgs], env);
}

main();