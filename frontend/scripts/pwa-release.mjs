import { execFileSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import { writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { validateRelease } from '../src/utils/pwaRelease.mjs';

export function releaseFromHistory(records, buildId = randomUUID()) {
    if (!records.length) throw new Error('Empty release history');
    let minimum = null;
    let policy = 'optional';
    records.forEach((record, index) => {
        const values = record.trailers
            .split(/\s+/)
            .filter(Boolean)
            .map((value) => value.toLowerCase());
        if (values.length > 1 || values.some((value) => !['required', 'optional'].includes(value))) throw new Error(`Invalid PWA-Update trailer: ${record.revision}`);
        policy = values[0] || 'optional';
        if (policy === 'required') minimum = { revision: record.revision, generation: index + 1 };
    });
    const revision = records.at(-1).revision;
    return validateRelease({ schema: 1, revision, generation: records.length, lineage: records[0].revision, buildId: `${revision}-${buildId}`, policy, minimum });
}

export function captureRelease({ cwd = process.cwd(), env = process.env, gitReader } = {}) {
    const checkRevision = (result) => {
        if (env.PWA_RELEASE_REVISION && env.PWA_RELEASE_REVISION !== result.revision) throw new Error('Source revision does not match PWA_RELEASE_REVISION');
        return result;
    };
    if (env.PWA_RELEASE_METADATA) {
        return checkRevision(validateRelease(JSON.parse(Buffer.from(env.PWA_RELEASE_METADATA, 'base64').toString('utf8'))));
    }
    const git = gitReader || ((...args) => execFileSync('git', ['--no-pager', ...args], { cwd, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'], maxBuffer: 32 * 1024 * 1024 }).trim());
    try {
        if (git('rev-parse', '--is-shallow-repository') !== 'false') throw new Error('Shallow Git history: fetch --unshallow first');
        const records = git('log', '--first-parent', '--reverse', '--format=%H%x1f%(trailers:key=PWA-Update,valueonly)%x1e', 'HEAD')
            .split('\x1e')
            .map((record) => record.trim())
            .filter(Boolean)
            .map((record) => {
                const [revision, trailers = ''] = record.split('\x1f');
                return { revision, trailers };
            });
        const result = releaseFromHistory(records, env.PWA_BUILD_ID || randomUUID());
        return checkRevision(result);
    } catch (error) {
        throw new Error(`Cannot capture reliable PWA release metadata. Supply PWA_RELEASE_METADATA from a full checkout, or include full .git in the Docker context. ${error.message}`);
    }
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
    const release = captureRelease();
    if (process.argv[2] === '--base64') process.stdout.write(Buffer.from(JSON.stringify(release)).toString('base64') + '\n');
    else if (process.argv[2] === '--output' && process.argv[3]) writeFileSync(process.argv[3], JSON.stringify(release) + '\n');
    else process.stdout.write(JSON.stringify(release, null, 2) + '\n');
}
