import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '..');
const cabinetsRoot = path.join(rootDir, 'cabinet-configs');
const publicDir = path.join(rootDir, 'public');
const generatedConfigPath = path.join(rootDir, 'src', 'generated', 'cabinet-config.generated.js');

// Admin frontend cabinet-configs (for logo fallback)
const adminCabinetsRoot = path.resolve(rootDir, '..', 'frontend', 'cabinet-configs');

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

function ensureCabinetId(value) {
    if (!value || typeof value !== 'string') {
        throw new Error('Missing cabinet id. Use --cabinet=<id>.');
    }

    const safe = value.trim();
    if (!/^[a-zA-Z0-9_-]+$/.test(safe)) {
        throw new Error(`Invalid cabinet id "${value}". Allowed chars: a-z, A-Z, 0-9, _, -`);
    }

    return safe;
}

function readJsonFile(filePath) {
    if (!fs.existsSync(filePath)) {
        throw new Error(`File not found: ${filePath}`);
    }

    const raw = fs.readFileSync(filePath, 'utf8');
    return JSON.parse(raw);
}

function validateConfig(cabinetId, config) {
    const required = ['id', 'displayName', 'brandName', 'logo', 'apiBaseUrl'];
    for (const field of required) {
        if (typeof config[field] !== 'string' || config[field].trim() === '') {
            throw new Error(`Invalid config for cabinet "${cabinetId}": "${field}" must be a non-empty string.`);
        }
    }
}

function tryFileSources(sources) {
    for (const src of sources) {
        if (fs.existsSync(src) && fs.statSync(src).isFile()) {
            return src;
        }
    }
    return null;
}

function syncLogo(cabinetId, config) {
    const logoFile = config.logo || 'logo.png';
    const favicoFile = 'favicon.ico';

    // Logo
    const logoSource = tryFileSources([
        path.join(cabinetsRoot, cabinetId, 'public', logoFile),
        path.join(adminCabinetsRoot, cabinetId, 'public', logoFile),
        path.join(adminCabinetsRoot, 'default', 'public', logoFile),
    ]);

    if (logoSource) {
        fs.mkdirSync(publicDir, { recursive: true });
        fs.copyFileSync(logoSource, path.join(publicDir, logoFile));
        console.log(`[cabinet] Logo copied: ${path.relative(rootDir, logoSource)} -> public/${logoFile}`);
    } else {
        console.warn(`[cabinet] Logo not found for cabinet "${cabinetId}": ${logoFile}`);
    }

    // Favicon
    const faviconSource = tryFileSources([
        path.join(cabinetsRoot, cabinetId, 'public', favicoFile),
        path.join(adminCabinetsRoot, cabinetId, 'public', favicoFile),
        path.join(adminCabinetsRoot, 'default', 'public', favicoFile),
    ]);

    if (faviconSource) {
        fs.copyFileSync(faviconSource, path.join(publicDir, favicoFile));
        console.log(`[cabinet] Favicon copied: ${path.relative(rootDir, faviconSource)} -> public/${favicoFile}`);
    }
}

function generateModuleSource(config) {
    return `const cabinetConfig = ${JSON.stringify(config, null, 4)};\n\nexport default cabinetConfig;\n`;
}

function main() {
    const cabinetId = ensureCabinetId(parseArgs(process.argv.slice(2)));

    const cabinetDir = path.join(cabinetsRoot, cabinetId);
    const configPath = path.join(cabinetDir, 'config.json');

    if (!fs.existsSync(configPath)) {
        throw new Error(`Cabinet config not found: ${configPath}`);
    }

    const config = readJsonFile(configPath);
    validateConfig(cabinetId, config);

    if (config.id !== cabinetId) {
        console.warn(`[cabinet] Config id mismatch: got "${config.id}", using "${cabinetId}".`);
        config.id = cabinetId;
    }

    syncLogo(cabinetId, config);

    fs.mkdirSync(path.dirname(generatedConfigPath), { recursive: true });
    fs.writeFileSync(generatedConfigPath, generateModuleSource(config), 'utf8');

    console.log(`[cabinet] Active cabinet: ${cabinetId}`);
    console.log(`[cabinet] Generated: ${path.relative(rootDir, generatedConfigPath)}`);
}

main();
