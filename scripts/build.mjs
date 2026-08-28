#!/usr/bin/env node

/**
 * HubGo build pipeline.
 *
 * Produces a production-ready plugin package:
 *   1. Build the Vue frontend (app/ -> app/dist/ via Vite).
 *   2. Install production PHP dependencies (admin/vendor via Composer, --no-dev).
 *   3. Generate / compile translations (languages/ -> .pot, .mo, .l10n.php).
 *   4. Stage only the runtime files into release/hubgo/.
 *   5. Zip the staging dir into release/hubgo-<version>.zip.
 *
 * `app/dist` and `admin/vendor` are NOT committed: both are generated here and
 * copied into the zip, which is why steps 1 and 2 are not optional on a clean
 * checkout. {@see assertBuildArtifacts} fails the build rather than shipping a
 * package whose UI silently never loads.
 *
 * Usage:
 *   node scripts/build.mjs [flags]
 *
 * Flags:
 *   --skip-app            Don't rebuild the Vue frontend (reuse existing app/dist).
 *   --skip-composer       Don't run composer install (reuse existing admin/vendor).
 *   --skip-translations   Don't touch translations (reuse existing artifacts).
 *   --translate           Re-translate .po files via AI before compiling (needs API keys).
 *   --engine=<name>       Translation engine for --translate (default: openai).
 *   --no-install          Skip dependency install steps (npm ci / npm install).
 *   --no-zip              Stage files but don't create the .zip.
 */

import { spawnSync } from 'node:child_process';
import { createWriteStream, existsSync } from 'node:fs';
import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import archiver from 'archiver';

const __dirname = path.dirname( fileURLToPath( import.meta.url ) );
const root = path.resolve( __dirname, '..' );
const slug = 'hubgo';

const releaseDir = path.join( root, 'release' );
const stagingDir = path.join( releaseDir, slug );

/* ------------------------------------------------------------------ flags */

const argv = process.argv.slice( 2 );
const hasFlag = ( name ) => argv.includes( name );
const getOpt = ( name, fallback ) => {
    const prefix = `${name}=`;
    const match = argv.find( ( arg ) => arg.startsWith( prefix ) );

    return match ? match.slice( prefix.length ) : fallback;
};

const opts = {
    skipApp: hasFlag('--skip-app'),
    skipComposer: hasFlag('--skip-composer'),
    skipTranslations: hasFlag('--skip-translations'),
    translate: hasFlag('--translate'),
    engine: getOpt( '--engine', 'openai' ),
    install: ! hasFlag('--no-install'),
    zip: ! hasFlag('--no-zip'),
};

/* ---------------------------------------------------------------- helpers */

const log = ( msg ) => console.log( `\x1b[36m▶\x1b[0m ${msg}` );
const ok = ( msg ) => console.log( `\x1b[32m✓\x1b[0m ${msg}` );

/**
 * Run a command, failing the build when it does.
 *
 * @param {string} command Executable.
 * @param {string[]} args Arguments.
 * @param {string} cwd Working directory.
 * @return {void}
 */
function run( command, args, cwd ) {
    const printable = `${command} ${args.join(' ')}`;
    log( `${printable}  (in ${path.relative( root, cwd ) || '.'})` );

    const result = spawnSync( command, args, {
        cwd,
        stdio: 'inherit',
        // shell:true lets Windows resolve npm.cmd / composer.bat from PATH.
        shell: true,
    } );

    if ( result.status !== 0 ) {
        throw new Error( `Command failed (exit ${result.status}): ${printable}` );
    }
}

/**
 * Install a workspace's dependencies.
 *
 * `npm ci` needs a lockfile, and only `app/` ships one — `languages/` keeps its
 * lockfile out of git, so a clean clone has to fall back to `npm install`.
 *
 * @param {string} cwd Workspace directory.
 * @return {void}
 */
function installDependencies( cwd ) {
    if ( ! opts.install ) {
        return;
    }

    const useCi = existsSync( path.join( cwd, 'package-lock.json' ) );

    run( 'npm', useCi ? [ 'ci' ] : [ 'install' ], cwd );
}

/**
 * Copy a directory into the staging tree.
 *
 * @param {string} relSource Path relative to the plugin root.
 * @param {string} relDest Path relative to the staging dir.
 * @param {Function} filter Optional fs.cp filter.
 * @return {Promise<void>}
 */
async function copyDir( relSource, relDest = relSource, filter ) {
    const source = path.join( root, relSource );
    const dest = path.join( stagingDir, relDest );

    if ( ! existsSync( source ) ) {
        return;
    }

    await fs.cp( source, dest, { recursive: true, filter } );
}

/**
 * Copy a single file into the staging tree.
 *
 * @param {string} relSource Path relative to the plugin root.
 * @param {string} relDest Path relative to the staging dir.
 * @return {Promise<void>}
 */
async function copyFile( relSource, relDest = relSource ) {
    const source = path.join( root, relSource );

    if ( ! existsSync( source ) ) {
        return;
    }

    const dest = path.join( stagingDir, relDest );
    await fs.mkdir( path.dirname( dest ), { recursive: true } );
    await fs.copyFile( source, dest );
}

/**
 * Read the version from the plugin header, the value WordPress itself trusts.
 *
 * @return {Promise<string>}
 */
async function getPluginVersion() {
    const file = path.join( root, `${slug}.php` );
    const contents = await fs.readFile( file, 'utf8' );
    const match = contents.match( /^\s*\*\s*Version:\s*(.+)$/m );

    return match ? match[1].trim() : '0.0.0';
}

/**
 * Zip the staging directory, nesting it under the plugin slug.
 *
 * @param {string} sourceDir Directory to archive.
 * @param {string} outPath Destination .zip path.
 * @return {Promise<number>} Bytes written.
 */
function zipDirectory( sourceDir, outPath ) {
    return new Promise( ( resolve, reject ) => {
        const output = createWriteStream( outPath );
        const archive = archiver( 'zip', { zlib: { level: 9 } } );

        output.on( 'close', () => resolve( archive.pointer() ) );
        archive.on( 'warning', ( err ) => ( err.code === 'ENOENT' ? null : reject( err ) ) );
        archive.on( 'error', reject );

        archive.pipe( output );
        // WordPress expects the plugin folder at the archive root.
        archive.directory( sourceDir, slug );
        archive.finalize();
    } );
}

/* ------------------------------------------------------------- copy rules */

// Dev clutter that may sneak into otherwise-shipped directories. The two agent
// documents are listed because Composer packages ship their own copies, and a
// distributed plugin has no business carrying someone else's contributor docs.
const denyList = new Set( [
    'node_modules',
    '.git',
    '.gitignore',
    '.gitattributes',
    '.env',
    '.DS_Store',
    'Thumbs.db',
    'AGENTS.md',
    'CLAUDE.md',
] );

const baseFilter = ( src ) => ! denyList.has( path.basename( src ) );

// languages/: ship only the compiled artifacts, never the Node tooling.
const languageExtensions = new Set( [ '.po', '.mo', '.pot', '.json', '.php' ] );
const languageFilter = ( src ) => {
    const name = path.basename( src );

    // Dotfiles report an empty extname, so they would slip through the
    // directory branch below and end up shipped.
    if ( denyList.has( name ) || name.startsWith('package') || name.startsWith('.') ) {
        return false;
    }

    // Always allow directory entries so their children get evaluated.
    if ( ! path.extname( name ) ) {
        return true;
    }

    // Keep .l10n.php and friends; drop the *-cli.js pipeline scripts.
    return languageExtensions.has( path.extname( name ) );
};

/* ----------------------------------------------------------------- stages */

/**
 * Build the Vue bundles (admin SPA + storefront calculator).
 *
 * @return {void}
 */
function buildFrontend() {
    if ( opts.skipApp ) {
        log('Skipping frontend build (--skip-app).');

        return;
    }

    const appDir = path.join( root, 'app' );

    installDependencies( appDir );
    run( 'npm', [ 'run', 'build' ], appDir );
    ok('Frontend built (app/dist).');
}

/**
 * Install the production PHP dependencies (the MDS SDK lives here).
 *
 * @return {void}
 */
function installPhpDependencies() {
    if ( opts.skipComposer ) {
        log('Skipping Composer install (--skip-composer).');

        return;
    }

    run(
        'composer',
        [ 'install', '--no-dev', '--optimize-autoloader', '--no-interaction', '--no-progress' ],
        path.join( root, 'admin' ),
    );
    ok('Production PHP dependencies installed (admin/vendor).');
}

/**
 * Refresh the .pot and compile the runtime translation artifacts.
 *
 * @return {void}
 */
function buildTranslations() {
    if ( opts.skipTranslations ) {
        log('Skipping translations (--skip-translations).');

        return;
    }

    const langDir = path.join( root, 'languages' );

    installDependencies( langDir );

    // Refresh the template from current source.
    run( 'npm', [ 'run', 'pot' ], langDir );

    if ( opts.translate ) {
        // Re-fill every .po via the chosen engine (needs API keys in languages/.env).
        const script = opts.engine === 'google' ? 'translate' : 'translate:ai';
        run( 'npm', [ 'run', script ], langDir );
    }

    // Compile .po -> .mo and .l10n.php so WordPress can load them at runtime.
    run( 'npm', [ 'run', 'compile:mo' ], langDir );
    run( 'npm', [ 'run', 'compile:php' ], langDir );
    ok('Translations compiled (languages/*.mo, *.l10n.php).');
}

/**
 * Refuse to package a build that is missing a generated artifact.
 *
 * Neither `app/dist` nor `admin/vendor` is committed, so `--skip-app` or
 * `--skip-composer` on a fresh checkout would otherwise produce a zip that
 * installs cleanly and then does nothing: `Core\Scripts` finds no manifest
 * entry, `Core\Assets` enqueues nothing, and the plugin has no UI at all.
 *
 * @return {Promise<void>}
 */
async function assertBuildArtifacts() {
    const required = [
        [ 'app/dist/.vite/manifest.json', '--skip-app' ],
        [ 'admin/vendor/autoload.php', '--skip-composer' ],
    ];

    for ( const [ relPath, flag ] of required ) {
        if ( ! existsSync( path.join( root, relPath ) ) ) {
            throw new Error(
                `${relPath} is missing — run the build without ${flag}.`,
            );
        }
    }

    // Every Vite entry must have made it into the manifest. A new entry that was
    // added to vite.config.js but never built is the failure this catches.
    const manifest = JSON.parse(
        await fs.readFile( path.join( root, 'app/dist/.vite/manifest.json' ), 'utf8' ),
    );

    const entries = [
        'src/entries/settings.js',
        'src/entries/integrations.js',
        'src/entries/license.js',
        'src/entries/storefront.js',
    ];

    const missing = entries.filter( ( entry ) => ! manifest[ entry ] );

    if ( missing.length ) {
        throw new Error( `Vite manifest is missing entries: ${missing.join(', ')}` );
    }

    ok('Build artifacts verified.');
}

/**
 * Copy the runtime files into release/hubgo/.
 *
 * @return {Promise<void>}
 */
async function stageFiles() {
    log('Staging runtime files...');

    await fs.rm( releaseDir, { recursive: true, force: true } );
    await fs.mkdir( stagingDir, { recursive: true } );

    // Top-level files. AGENTS.md, CLAUDE.md and package.json are development
    // documents and deliberately stay out of the package.
    for ( const file of [ `${slug}.php`, 'README.md', 'license.md', 'CHANGELOG.md' ] ) {
        await copyFile( file );
    }

    // PHP backend: source + production autoloader. composer.json stays for reference.
    await copyDir( 'admin/src', 'admin/src', baseFilter );
    await copyDir( 'admin/vendor', 'admin/vendor', baseFilter );
    await copyFile('admin/composer.json');

    // Built frontend (includes .vite/manifest.json that Scripts.php reads).
    await copyDir( 'app/dist', 'app/dist', baseFilter );

    // Static assets and the templates themes may override.
    await copyDir( 'assets', 'assets', baseFilter );
    await copyDir( 'templates', 'templates', baseFilter );

    // Compiled translation artifacts only.
    await copyDir( 'languages', 'languages', languageFilter );

    ok( `Staged at ${path.relative( root, stagingDir )}.` );
}

/**
 * Write the release manifest and archive the staged tree.
 *
 * @param {string} version Plugin version.
 * @return {Promise<void>}
 */
async function packageZip( version ) {
    const manifest = {
        name: slug,
        version,
        generatedAt: new Date().toISOString(),
        zipFile: `${slug}-${version}.zip`,
    };

    await fs.writeFile(
        path.join( releaseDir, 'manifest.json' ),
        `${JSON.stringify( manifest, null, 2 )}\n`,
    );

    if ( ! opts.zip ) {
        log('Skipping zip (--no-zip).');

        return;
    }

    const zipPath = path.join( releaseDir, manifest.zipFile );
    const bytes = await zipDirectory( stagingDir, zipPath );
    ok( `ZIP created: ${path.relative( root, zipPath )} (${( bytes / 1024 / 1024 ).toFixed( 2 )} MB)` );
}

/* -------------------------------------------------------------------- main */

async function main() {
    const version = await getPluginVersion();
    console.log( `\n\x1b[1mBuilding ${slug} v${version}\x1b[0m\n` );

    buildFrontend();
    installPhpDependencies();
    buildTranslations();
    await assertBuildArtifacts();
    await stageFiles();
    await packageZip( version );

    console.log( '\n\x1b[32m\x1b[1mBuild complete.\x1b[0m\n' );
}

main().catch( ( err ) => {
    console.error( `\n\x1b[31m✗ Build failed:\x1b[0m ${err.message}\n` );
    process.exit( 1 );
} );
