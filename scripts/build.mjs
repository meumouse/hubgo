#!/usr/bin/env node

/**
 * HubGo build orchestrator.
 *
 * 1. Build the Vue frontend (app/ -> app/dist/ via Vite).
 * 2. Generate / translate / compile translations (languages/).
 *
 * Usage:
 *   node scripts/build.mjs                 # build app + refresh + compile translations
 *   node scripts/build.mjs --translate     # also AI-translate every locale (needs API key)
 *   node scripts/build.mjs --skip-app
 *   node scripts/build.mjs --skip-translations
 *   node scripts/build.mjs --engine=google # translation engine for --translate (default: openai)
 *   node scripts/build.mjs --no-install    # skip npm install steps
 */

import { spawnSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname( fileURLToPath( import.meta.url ) );
const rootDir = resolve( __dirname, '..' );
const appDir = resolve( rootDir, 'app' );
const langDir = resolve( rootDir, 'languages' );

const args = process.argv.slice( 2 );
const hasFlag = ( name ) => args.includes( name );
const getOpt = ( name, fallback ) => {
    const match = args.find( ( arg ) => arg.startsWith( `${name}=` ) );
    return match ? match.slice( name.length + 1 ) : fallback;
};

const opts = {
    skipApp: hasFlag( '--skip-app' ),
    skipTranslations: hasFlag( '--skip-translations' ),
    translate: hasFlag( '--translate' ),
    engine: getOpt( '--engine', 'openai' ),
    install: ! hasFlag( '--no-install' ),
};

function run( command, commandArgs, cwd ) {
    console.log( `\n> ${command} ${commandArgs.join( ' ' )}  (cwd: ${cwd})` );

    const result = spawnSync( command, commandArgs, {
        cwd,
        stdio: 'inherit',
        shell: process.platform === 'win32',
    } );

    if ( result.status !== 0 ) {
        console.error( `\nCommand failed: ${command} ${commandArgs.join( ' ' )}` );
        process.exit( result.status ?? 1 );
    }
}

function ensureInstalled( cwd ) {
    if ( opts.install && ! existsSync( resolve( cwd, 'node_modules' ) ) ) {
        run( 'npm', [ 'install' ], cwd );
    }
}

function buildFrontend() {
    if ( opts.skipApp ) {
        console.log( '\nSkipping frontend build (--skip-app).' );
        return;
    }

    ensureInstalled( appDir );
    run( 'npm', [ 'run', 'build' ], appDir );
    console.log( '\nFrontend built → app/dist/.vite/manifest.json' );
}

function buildTranslations() {
    if ( opts.skipTranslations ) {
        console.log( '\nSkipping translations (--skip-translations).' );
        return;
    }

    ensureInstalled( langDir );

    // Refresh the .pot template from current source.
    run( 'npm', [ 'run', 'pot' ], langDir );

    if ( opts.translate ) {
        // Re-fill every .po via the chosen engine (needs API keys in languages/.env).
        const script = opts.engine === 'google' ? 'translate' : 'translate:ai';
        run( 'npm', [ 'run', script ], langDir );
    }

    // Compile .po -> .mo and .l10n.php so WordPress can load them at runtime.
    run( 'npm', [ 'run', 'compile:mo' ], langDir );
    run( 'npm', [ 'run', 'compile:php' ], langDir );
    console.log( '\nTranslations compiled → languages/*.mo, *.l10n.php' );
}

buildFrontend();
buildTranslations();

console.log( '\nHubGo build complete.' );
