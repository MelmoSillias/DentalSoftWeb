import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const frontendRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const eslintBin = path.join(frontendRoot, 'node_modules', 'eslint', 'bin', 'eslint.js');

execFileSync(process.execPath, [eslintBin, '--fix', '.', '--ext', '.vue,.js,.jsx,.cjs,.mjs', '--ignore-path', '.gitignore'], {
    cwd: frontendRoot,
    stdio: 'inherit',
    env: {
        ...process.env,
        ESLINT_USE_FLAT_CONFIG: 'false',
        NODE_NO_WARNINGS: '1'
    }
});
