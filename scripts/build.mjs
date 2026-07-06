#!/usr/bin/env node

/**
 * HubGo build orchestrator.
 *
 * Installs the Vue app dependencies (when needed) and produces the Vite build
 * consumed by inc/Core/Scripts.php (app/dist/.vite/manifest.json).
 *
 * Usage:
 *   node scripts/build.mjs             # install (if needed) + build app
 *   node scripts/build.mjs --no-install
 */

import { spawnSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname( fileURLToPath( import.meta.url ) );
const rootDir = resolve( __dirname, '..' );
const appDir = resolve( rootDir, 'app' );

const args = process.argv.slice( 2 );
const skipInstall = args.includes( '--no-install' );

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

if ( ! skipInstall && ! existsSync( resolve( appDir, 'node_modules' ) ) ) {
    run( 'npm', [ 'install' ], appDir );
}

run( 'npm', [ 'run', 'build' ], appDir );

console.log( '\nHubGo app build complete → app/dist/.vite/manifest.json' );
