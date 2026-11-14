import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { parse } from '@vue/compiler-sfc';

const root = fileURLToPath(new URL('../', import.meta.url));
const src = path.join(root, 'src');
const baseline = JSON.parse(fs.readFileSync(path.join(root, 'architecture-debt.json'), 'utf8'));
const allowed = new Set(baseline.dependencies.map(({ source, target }) => `${source} -> ${target}`));
const unresolved = new Set(baseline.unresolved.map(({ source, target }) => `${source} -> ${target}`));
const missing = new Set();
const errors = [];
const used = new Set();
let count = 0;
function walk(dir) {
    return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
        const full = path.join(dir, entry.name);
        return entry.isDirectory() ? walk(full) : [full];
    });
}
function resolveModule(base) {
    return [base, `${base}.js`, `${base}.mjs`, `${base}.vue`, path.join(base, 'index.js')].find((file) => fs.existsSync(file) && fs.statSync(file).isFile());
}
const normalize = (file) => path.relative(src, file).split(path.sep).join('/');
for (const oldRoot of ['views', 'components', 'composables', 'services', 'service', 'stores', 'router', 'layout', 'utils']) {
    if (fs.existsSync(path.join(src, oldRoot))) errors.push(`Ancien répertoire technique réintroduit : src/${oldRoot}`);
}
for (const file of walk(src).filter((file) => /\.(vue|[cm]?js|css|scss)$/.test(file))) {
    count++;
    const source = normalize(file);
    let text = fs.readFileSync(file, 'utf8');
    if (file.endsWith('.vue')) {
        const { descriptor } = parse(text);
        text = [descriptor.script?.content, descriptor.scriptSetup?.content, ...descriptor.styles.map((style) => (style.src ? `import '${style.src}'` : style.content))].filter(Boolean).join('\n');
    }
    // Match static local module/asset references, including lazy imports and style src.
    const references = text.matchAll(/['"](@\/[^'"\r\n]+|\.\.?\/[^'"\r\n]+)['"]/g);
    for (const [, reference] of references) {
        // Relative strings can also be URLs; only module imports need resolution.
        if (!reference.startsWith('@/') && !new RegExp(`(?:from\\s*|import\\s*\\(\\s*|import\\s*)['"]${reference.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}['"]`).test(text)) continue;
        const clean = reference.split('?')[0];
        const base = clean.startsWith('@/') ? path.join(src, clean.slice(2)) : path.resolve(path.dirname(file), clean);
        if (!base.startsWith(`${src}${path.sep}`)) {
            errors.push(`${source} : import hors de src interdit (${reference})`);
            continue;
        }
        const resolved = resolveModule(base);
        if (!resolved) {
            const edge = `${source} -> ${reference}`;
            if (!unresolved.has(edge)) errors.push(`${source} : référence introuvable (${reference})`);
            missing.add(edge);
            continue;
        }
        const target = normalize(resolved);
        if (source.startsWith('shared/') && /^(app|domains)\//.test(target)) {
            const edge = `${source} -> ${target}`;
            if (!allowed.has(edge)) errors.push(`Nouvelle dépendance métier dans shared : ${edge}`);
            used.add(edge);
        }
    }
}
for (const exception of allowed) {
    if (!used.has(exception)) errors.push(`Dette résolue : retirer l'exception ${exception}`);
}
for (const exception of unresolved) {
    if (!missing.has(exception)) errors.push(`Import historique réparé : retirer l'exception ${exception}`);
}
assert.equal(errors.length, 0, errors.join('\n'));
if (missing.size) console.warn(`Attention : ${missing.size} import historique manquant dans un écran non routé, voir architecture-debt.json.`);
console.log(`Architecture : ${count} fichiers contrôlés, imports locaux résolus, ${used.size} dépendances historiques explicites (aucune nouvelle autorisée).`);
