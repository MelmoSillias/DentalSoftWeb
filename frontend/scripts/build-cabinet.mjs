import process from 'node:process';
import { spawnSync } from 'node:child_process';

function parseArgs(argv) {
    for (let i = 0; i < argv.length; i += 1) {
        const token = argv[i];
        if (!token.startsWith('--cabinet')) continue;

        if (token.includes('=')) {
            return token.split('=')[1];
        }

        const next = argv[i + 1];
        if (next && !next.startsWith('--')) {
            return next;
        }
    }

    return process.env.CABINET || process.env.npm_config_cabinet || 'default';
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
    const cabinet = parseArgs(process.argv.slice(2));
    const env = { ...process.env, CABINET: cabinet };

    const nodeBin = process.execPath;
    run(nodeBin, ['./scripts/select-cabinet.mjs', `--cabinet=${cabinet}`], env);

    const viteBin = process.platform === 'win32' ? 'npx.cmd' : 'npx';
    run(viteBin, ['vite', 'build'], env);
}

main();
