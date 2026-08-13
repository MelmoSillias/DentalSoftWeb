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

const SUPPORTED_CONFIG_ENVS = new Set(['dev', 'prod', 'local-offline', 'xampp-lan', 'xampp-lan-http']);

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

function ensureConfigEnv(value) {
    const safe = String(value || '').trim().toLowerCase();
    if (!SUPPORTED_CONFIG_ENVS.has(safe)) {
        throw new Error(`Invalid config env "${value}". Use dev, prod, local-offline, xampp-lan or xampp-lan-http.`);
    }

    return safe;
}

function resolveConfigEnv(args) {
    const fromArgs = typeof args.env === 'string' ? args.env : '';
    const fromEnv = process.env.CABINET_ENV || process.env.BUILD_ENV || '';
    return ensureConfigEnv(fromArgs || fromEnv || 'dev');
}

function resolveConfigPath(cabinetDir, configEnv) {
    const envConfigPath = path.join(cabinetDir, `config.${configEnv}.json`);
    const legacyConfigPath = path.join(cabinetDir, 'config.json');

    if (fs.existsSync(envConfigPath)) {
        return envConfigPath;
    }

    if (fs.existsSync(legacyConfigPath)) {
        console.warn(
            `[cabinet] config.${configEnv}.json not found in ${path.basename(cabinetDir)}, falling back to config.json.`
        );
        return legacyConfigPath;
    }

    throw new Error(
        `Config not found for cabinet "${path.basename(cabinetDir)}" (env=${configEnv}). `
        + `Expected ${envConfigPath} or ${legacyConfigPath}.`
    );
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

    if (config.printProfile !== undefined) {
        const profile = config.printProfile;
        if (!profile || typeof profile !== 'object') {
            throw new Error(`Invalid config for cabinet "${cabinetId}": "printProfile" must be an object.`);
        }
        if (typeof profile.name !== 'string' || profile.name.trim() === '') {
            throw new Error(`Invalid config for cabinet "${cabinetId}": "printProfile.name" must be a non-empty string.`);
        }
        if (profile.addressLines !== undefined && !Array.isArray(profile.addressLines)) {
            throw new Error(`Invalid config for cabinet "${cabinetId}": "printProfile.addressLines" must be an array.`);
        }
        if (profile.phones !== undefined && !Array.isArray(profile.phones)) {
            throw new Error(`Invalid config for cabinet "${cabinetId}": "printProfile.phones" must be an array.`);
        }
        if (profile.email !== undefined && typeof profile.email !== 'string') {
            throw new Error(`Invalid config for cabinet "${cabinetId}": "printProfile.email" must be a string.`);
        }
        if (profile.website !== undefined && typeof profile.website !== 'string') {
            throw new Error(`Invalid config for cabinet "${cabinetId}": "printProfile.website" must be a string.`);
        }
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

function copyDirectory(sourceDir, targetDir) {
    if (!fs.existsSync(sourceDir) || !fs.statSync(sourceDir).isDirectory()) {
        return 0;
    }

    fs.mkdirSync(targetDir, { recursive: true });
    let copied = 0;

    for (const entry of fs.readdirSync(sourceDir, { withFileTypes: true })) {
        const from = path.join(sourceDir, entry.name);
        const to = path.join(targetDir, entry.name);

        if (entry.isDirectory()) {
            copied += copyDirectory(from, to);
            continue;
        }

        fs.copyFileSync(from, to);
        copied += 1;
    }

    return copied;
}

function resetSyncedPublicDir() {
    const preserve = new Set(['.htaccess']);

    if (!fs.existsSync(publicDir)) {
        return;
    }

    for (const entry of fs.readdirSync(publicDir, { withFileTypes: true })) {
        if (preserve.has(entry.name)) {
            continue;
        }

        fs.rmSync(path.join(publicDir, entry.name), { recursive: true, force: true });
    }
}

function validateRequiredAssets(cabinetId, config, cabinetPublicDir) {
    const missing = [];

    for (const icon of config.pwa.icons) {
        const relativePath = normalizeRelativePath(icon?.src);
        if (!relativePath || !isSafeRelativePath(relativePath)) {
            continue;
        }

        if (!fileExists(path.join(cabinetPublicDir, relativePath))) {
            missing.push(relativePath);
        }
    }

    for (const [key, value] of Object.entries(config.brandingAssets || {})) {
        if (typeof value !== 'string') {
            continue;
        }

        const relativePath = normalizeRelativePath(value);
        if (!relativePath || !isSafeRelativePath(relativePath)) {
            continue;
        }

        if (!fileExists(path.join(cabinetPublicDir, relativePath))) {
            missing.push(`brandingAssets.${key} (${relativePath})`);
        }
    }

    if (missing.length > 0) {
        throw new Error(
            `Missing required assets for cabinet "${cabinetId}": ${missing.join(', ')}. `
            + `Add them under cabinet-configs/${cabinetId}/public/.`
        );
    }
}

function syncCabinetAssets(config, cabinetPublicDir) {
    if (!fs.existsSync(cabinetPublicDir) || !fs.statSync(cabinetPublicDir).isDirectory()) {
        throw new Error(`Cabinet public directory not found: ${cabinetPublicDir}`);
    }

    resetSyncedPublicDir();

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

    copiedToPublic += copyDirectory(
        path.join(cabinetPublicDir, 'demo'),
        path.join(publicDir, 'demo')
    );

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
    const configEnv = resolveConfigEnv(args);

    const cabinetDir = path.join(cabinetsRoot, cabinetId);
    const configPath = resolveConfigPath(cabinetDir, configEnv);
    const cabinetPublicDir = path.join(cabinetDir, 'public');

    const config = readJsonFile(configPath);
    validateConfig(cabinetId, config);
    validateRequiredAssets(cabinetId, config, cabinetPublicDir);

    const syncResult = syncCabinetAssets(config, cabinetPublicDir);

    fs.mkdirSync(path.dirname(generatedConfigPath), { recursive: true });
    fs.writeFileSync(generatedConfigPath, generateModuleSource(config), 'utf8');

    console.log(`[cabinet] Active cabinet: ${cabinetId} (${configEnv})`);
    console.log(`[cabinet] Config source: ${path.relative(rootDir, configPath)}`);
    console.log(`[cabinet] Generated: ${path.relative(rootDir, generatedConfigPath)}`);
    console.log(`[cabinet] Assets synced (selected): ${path.relative(rootDir, cabinetPublicDir)} -> public (${syncResult.copiedToPublic} files)`);
    console.log(`[cabinet] Assets synced (selected): ${path.relative(rootDir, cabinetPublicDir)} -> src/assets (${syncResult.copiedToAssets} files)`);
}

main();
