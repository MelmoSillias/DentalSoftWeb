import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '../src');
const importLine = "import { logAppError } from '@/utils/appLogger';\n";

function walk(dir, files = []) {
    for (const ent of fs.readdirSync(dir, { withFileTypes: true })) {
        const p = path.join(dir, ent.name);
        if (ent.isDirectory()) walk(p, files);
        else if (/\.(vue|js)$/.test(ent.name)) files.push(p);
    }
    return files;
}

for (const file of walk(root)) {
    let content = fs.readFileSync(file, 'utf8');
    if (!content.includes('logAppError')) continue;
    if (content.includes("from '@/utils/appLogger'")) continue;

    if (/<script setup/.test(content)) {
        content = content.replace(/(<script setup[^>]*>)/, `$1\n${importLine}`);
    } else if (/<script/.test(content)) {
        content = content.replace(/(<script[^>]*>)/, `$1\n${importLine}`);
    } else {
        content = importLine + content;
    }

    fs.writeFileSync(file, content);
    console.log('fixed import', path.relative(root, file));
}
