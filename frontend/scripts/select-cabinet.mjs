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

function parseArgs(argv) {
    const data = {};

    for (let i = 0; i < argv.length; i += 1) {
        const token = argv[i];
        if (!token.startsWith('--')) continue;

        const [key, value] = token.split('=');
        if (value !== undefined) {
            data[key.slice(2)] = value;
            continue;
        }

        const next = argv[i + 1];
        if (next && !next.startsWith('--')) {
            data[key.slice(2)] = next;
            i += 1;
        } else {
            data[key.slice(2)] = true;
        }
    }

    return data;
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
    const requiredStringFields = [
        'id',
        'displayName',
        'appTitle',
        'brandName',
        'brandSubtitle',
        'settingsTitle',
        'settingsDescription',
        'smsCabinetName',
        'smsTestMessage',
        'reportCabinetName'
    ];

    for (const field of requiredStringFields) {
        if (typeof config[field] !== 'string' || config[field].trim() === '') {
            throw new Error(`Invalid config for cabinet "${cabinetId}": "${field}" must be a non-empty string.`);
        }
    }

    if (config.id !== cabinetId) {
        throw new Error(`Config id mismatch: expected "${cabinetId}", got "${config.id}".`);
    }

    if (!config.pwa || typeof config.pwa !== 'object') {
        throw new Error(`Invalid config for cabinet "${cabinetId}": "pwa" object is required.`);
    }

    const requiredPwaFields = ['name', 'shortName', 'description', 'themeColor'];
    for (const field of requiredPwaFields) {
        if (typeof config.pwa[field] !== 'string' || config.pwa[field].trim() === '') {
            throw new Error(`Invalid config for cabinet "${cabinetId}": "pwa.${field}" must be a non-empty string.`);
        }
    }

    if (!Array.isArray(config.pwa.icons) || config.pwa.icons.length === 0) {
        throw new Error(`Invalid config for cabinet "${cabinetId}": "pwa.icons" must be a non-empty array.`);
    }

    if (config.pwa.includeAssets !== undefined && !Array.isArray(config.pwa.includeAssets)) {
        throw new Error(`Invalid config for cabinet "${cabinetId}": "pwa.includeAssets" must be an array.`);
    }

    const optionalViteFields = ['viteApiPrefix', 'viteFilePrefix'];
    for (const field of optionalViteFields) {
        if (config[field] !== undefined && (typeof config[field] !== 'string' || config[field].trim() === '')) {
            throw new Error(`Invalid config for cabinet "${cabinetId}": "${field}" must be a non-empty string when provided.`);
        }
    }
}

function copyDirectory(sourceDir, targetDir) {
    if (!fs.existsSync(sourceDir)) {
        throw new Error(`Cabinet public directory not found: ${sourceDir}`);
    }

    const entries = fs.readdirSync(sourceDir, { withFileTypes: true });
    for (const entry of entries) {
        const from = path.join(sourceDir, entry.name);
        const to = path.join(targetDir, entry.name);

        if (entry.isDirectory()) {
            fs.mkdirSync(to, { recursive: true });
            copyDirectory(from, to);
            continue;
        }

        fs.mkdirSync(path.dirname(to), { recursive: true });
        fs.copyFileSync(from, to);
    }
}

function generateModuleSource(config) {
    return `const cabinetConfig = ${JSON.stringify(config, null, 4)};\n\nexport default cabinetConfig;\n`;
}

function main() {
    const args = parseArgs(process.argv.slice(2));
    const fromArgs = typeof args.cabinet === 'string' ? args.cabinet : '';
    const fromEnv = process.env.CABINET || process.env.npm_config_cabinet || '';
    const cabinetId = ensureCabinetId(fromArgs || fromEnv || 'default');

    const cabinetDir = path.join(cabinetsRoot, cabinetId);
    const configPath = path.join(cabinetDir, 'config.json');
    const cabinetPublicDir = path.join(cabinetDir, 'public');

    const config = readJsonFile(configPath);
    validateConfig(cabinetId, config);

    copyDirectory(cabinetPublicDir, publicDir);

    fs.mkdirSync(path.dirname(generatedConfigPath), { recursive: true });
    fs.writeFileSync(generatedConfigPath, generateModuleSource(config), 'utf8');

    console.log(`[cabinet] Active cabinet: ${cabinetId}`);
    console.log(`[cabinet] Generated: ${path.relative(rootDir, generatedConfigPath)}`);
    console.log(`[cabinet] Assets synced from: ${path.relative(rootDir, cabinetPublicDir)} -> public`);
}

main();
