import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '..');
const cabinetsRoot = path.join(rootDir, 'cabinet-configs');
const publicDir = path.join(rootDir, 'public');
const srcAssetsDir = path.join(rootDir, 'src', 'assets');
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
        'reportCabinetName',
        'cabinetPhone'
    ];

    for (const field of requiredStringFields) {
        if (typeof config[field] !== 'string' || config[field].trim() === '') {
            throw new Error(`Invalid config for cabinet "${cabinetId}": "${field}" must be a non-empty string.`);
        }
    }

    if (config.id !== cabinetId) {
        // Keep cabinet id consistent with selected folder to avoid runtime mismatch.
        console.warn(`[cabinet] Config id mismatch in ${cabinetId}/config.json: got "${config.id}", using "${cabinetId}".`);
        config.id = cabinetId;
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

function fileExists(filePath) {
    return fs.existsSync(filePath) && fs.statSync(filePath).isFile();
}

function normalizeRelativePath(value) {
    return String(value || '').replace(/\\/g, '/').replace(/^\/+/, '').trim();
}

function isSafeRelativePath(value) {
    return value !== '' && !value.startsWith('..') && !value.includes('/../');
}

function copyRelativeFile(sourceRoot, targetRoot, sourceRelativePath, targetRelativePath = sourceRelativePath) {
    const sourceRel = normalizeRelativePath(sourceRelativePath);
    const targetRel = normalizeRelativePath(targetRelativePath);

    if (!isSafeRelativePath(sourceRel) || !isSafeRelativePath(targetRel)) {
        return false;
    }

    const from = path.join(sourceRoot, sourceRel);
    if (!fileExists(from)) {
        return false;
    }

    const to = path.join(targetRoot, targetRel);
    fs.mkdirSync(path.dirname(to), { recursive: true });
    fs.copyFileSync(from, to);
    return true;
}

function collectBrandingFiles(config) {
    const values = Object.values(config.brandingAssets || {});
    return values
        .filter((value) => typeof value === 'string')
        .map((value) => normalizeRelativePath(value))
        .filter((value) => isSafeRelativePath(value));
}

function expandPatternEntries(cabinetPublicDir, entries) {
    const resolvedFiles = new Set();

    for (const rawEntry of entries) {
        if (typeof rawEntry !== 'string') continue;
        const entry = normalizeRelativePath(rawEntry);
        if (!isSafeRelativePath(entry)) continue;

        if (!entry.includes('*')) {
            resolvedFiles.add(entry);
            continue;
        }

        const slashIndex = entry.lastIndexOf('/');
        const dirPart = slashIndex >= 0 ? entry.slice(0, slashIndex) : '';
        const filePattern = slashIndex >= 0 ? entry.slice(slashIndex + 1) : entry;
        const escaped = filePattern
            .replace(/[.+?^${}()|[\]\\]/g, '\\$&')
            .replace(/\*/g, '.*');
        const matcher = new RegExp(`^${escaped}$`);

        const absoluteDir = path.join(cabinetPublicDir, dirPart);
        if (!fs.existsSync(absoluteDir) || !fs.statSync(absoluteDir).isDirectory()) {
            continue;
        }

        for (const child of fs.readdirSync(absoluteDir, { withFileTypes: true })) {
            if (!child.isFile()) continue;
            if (!matcher.test(child.name)) continue;
            const relativeFile = dirPart ? `${dirPart}/${child.name}` : child.name;
            resolvedFiles.add(relativeFile);
        }
    }

    return resolvedFiles;
}

function syncCabinetAssets(config, cabinetPublicDir) {
    if (!fs.existsSync(cabinetPublicDir) || !fs.statSync(cabinetPublicDir).isDirectory()) {
        throw new Error(`Cabinet public directory not found: ${cabinetPublicDir}`);
    }

    const brandingFiles = collectBrandingFiles(config);
    const pwaInclude = Array.isArray(config?.pwa?.includeAssets) ? config.pwa.includeAssets : [];
    const defaultPublicPatterns = ['header*'];
    const pwaIconFiles = Array.isArray(config?.pwa?.icons)
        ? config.pwa.icons
            .map((icon) => (icon && typeof icon.src === 'string' ? normalizeRelativePath(icon.src) : ''))
            .filter((value) => isSafeRelativePath(value))
        : [];

    const publicTargets = new Set([
        ...brandingFiles,
        ...pwaIconFiles,
        ...Array.from(expandPatternEntries(cabinetPublicDir, [...pwaInclude, ...defaultPublicPatterns])),
        'manifest.webmanifest'
    ]);

    let copiedToPublic = 0;
    for (const relativeFile of publicTargets) {
        if (copyRelativeFile(cabinetPublicDir, publicDir, relativeFile)) {
            copiedToPublic += 1;
        }
    }

    const assetTargets = new Set(brandingFiles);
    let copiedToAssets = 0;
    for (const relativeFile of assetTargets) {
        if (copyRelativeFile(cabinetPublicDir, srcAssetsDir, relativeFile)) {
            copiedToAssets += 1;
        }
    }

    // Les vues utilisent encore '@/assets/illustration.png': fallback depuis landing-illustration.png.
    if (!copyRelativeFile(cabinetPublicDir, srcAssetsDir, 'illustration.png', 'illustration.png')) {
        if (copyRelativeFile(cabinetPublicDir, srcAssetsDir, 'landing-illustration.png', 'illustration.png')) {
            copiedToAssets += 1;
        }
    } else {
        copiedToAssets += 1;
    }

    return { copiedToPublic, copiedToAssets };
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

    const syncResult = syncCabinetAssets(config, cabinetPublicDir);

    fs.mkdirSync(path.dirname(generatedConfigPath), { recursive: true });
    fs.writeFileSync(generatedConfigPath, generateModuleSource(config), 'utf8');

    console.log(`[cabinet] Active cabinet: ${cabinetId}`);
    console.log(`[cabinet] Generated: ${path.relative(rootDir, generatedConfigPath)}`);
    console.log(`[cabinet] Assets synced (selected): ${path.relative(rootDir, cabinetPublicDir)} -> public (${syncResult.copiedToPublic} files)`);
    console.log(`[cabinet] Assets synced (selected): ${path.relative(rootDir, cabinetPublicDir)} -> src/assets (${syncResult.copiedToAssets} files)`);
}

main();
