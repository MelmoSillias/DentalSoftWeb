import { readdir, readFile, stat } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

export async function checkArchitecture(root) {
    const src = path.join(root, 'src');
    const errors = [];
    const files = [];
    async function walk(dir) {
        for (const entry of await readdir(dir, { withFileTypes: true })) {
            const file = path.join(dir, entry.name);
            if (entry.isDirectory()) await walk(file);
            else if (/\.(m?js|vue|css)$/.test(file)) files.push(file);
        }
    }
    await walk(src);
    for (const old of ['views', 'stores', 'services', 'components', 'composables', 'router', 'layout']) {
        if (await stat(path.join(src, old)).catch(() => null)) errors.push(`Ancien dossier src/${old}`);
    }
    for (const file of files) {
        const name = path.relative(src, file).replaceAll('\\', '/');
        const text = await readFile(file, 'utf8');
        if (name.startsWith('domains/') && /(?:views|components)\//.test(name) && /(?:['"`]\/api\/|\bfetch\s*\(|\bapiRequest\s*\()/.test(text)) errors.push(`${name}: HTTP dans une vue/composant`);
        const specs = [...text.matchAll(/(?:\bfrom\s*|\bimport\s*\(?\s*)['"]([^'"]+)['"]/g)].map((m) => m[1]);
        for (const spec of specs) {
            if (!spec.startsWith('.') && !spec.startsWith('@/')) continue;
            const target = spec.startsWith('@/') ? path.resolve(src, spec.slice(2)) : path.resolve(path.dirname(file), spec);
            const dest = path.relative(src, target).replaceAll('\\', '/');
            if (dest.startsWith('../') || path.isAbsolute(dest)) { errors.push(`${name}: import hors src ${spec}`); continue; }
            const exists = await Promise.all([target, `${target}.js`, `${target}/index.js`].map((p) => stat(p).catch(() => null)));
            if (!exists.some((s) => s?.isFile())) errors.push(`${name}: import introuvable ${spec}`);
            if (name.startsWith('shared/') && /^(app|domains)\//.test(dest)) errors.push(`${name}: shared dépend de ${dest}`);
            const domain = name.match(/^domains\/([^/]+)\//)?.[1];
            const other = dest.match(/^domains\/([^/]+)\//)?.[1];
            if (domain && dest.startsWith('app/')) errors.push(`${name}: domaine dépend de app`);
            if (domain && other && domain !== other && dest !== `domains/${other}/index.js`) errors.push(`${name}: import interne du domaine ${other}`);
        }
    }
    return errors;
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
    const errors = await checkArchitecture(fileURLToPath(new URL('..', import.meta.url)));
    if (errors.length) { console.error(errors.join('\n')); process.exitCode = 1; }
    else console.log('Architecture patient : imports résolus et frontières respectées.');
}
