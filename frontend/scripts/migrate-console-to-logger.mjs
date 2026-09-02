import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '../src');
const skip = new Set(['main.js', 'appLogger.js', 'clientErrorReport.js']);

function walk(dir, files = []) {
    for (const ent of fs.readdirSync(dir, { withFileTypes: true })) {
        const p = path.join(dir, ent.name);
        if (ent.isDirectory()) walk(p, files);
        else if (/\.(vue|js)$/.test(ent.name) && !skip.has(ent.name)) files.push(p);
    }
    return files;
}

const importLine = "import { logAppError } from '@/utils/appLogger';\n";

for (const file of walk(root)) {
    let content = fs.readFileSync(file, 'utf8');
    if (!/console\.(error|warn|log|debug)/.test(content)) continue;

    const base = path.basename(file, path.extname(file));

    content = content.replace(/console\.error\(\s*'([^']+)'\s*,\s*(error|err|e)\s*\)/g, "logAppError('$1', $2)");
    content = content.replace(/console\.error\(\s*"([^"]+)"\s*,\s*(error|err|e)\s*\)/g, 'logAppError("$1", $2)');
    content = content.replace(/console\.error\(\s*(error|err|e)\s*\)/g, `logAppError('${base}', $1)`);
    content = content.replace(/console\.warn\(\s*'([^']+)'\s*,\s*(error|err|e)\s*\)/g, "logAppError('$1', $2)");
    content = content.replace(/console\.warn\(\s*"([^"]+)"\s*,\s*(error|err|e)\s*\)/g, 'logAppError("$1", $2)');

    if (!content.includes('logAppError')) continue;

    if (!content.includes("from '@/utils/appLogger'")) {
        if (/<script setup/.test(content)) {
            content = content.replace(/(<script setup[^>]*>\n)/, `$1${importLine}`);
        } else if (/<script/.test(content)) {
            content = content.replace(/(<script[^>]*>\n)/, `$1${importLine}`);
        } else {
            content = importLine + content;
        }
    }

    fs.writeFileSync(file, content);
    console.log('updated', path.relative(root, file));
}
