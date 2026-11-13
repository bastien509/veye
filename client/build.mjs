// Builds the web bundle and the Chrome extension, then zips the extension
// for download from petiht.com/veye/extension.php.
//   node build.mjs            production (https://petiht.com/veye)
//   VEYE_BASE=http://localhost:8130 node build.mjs   local dev
import { build } from 'esbuild';
import { cpSync, mkdirSync, rmSync, existsSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const root = join(here, '..');
const base = process.env.VEYE_BASE || 'https://petiht.com/veye';
const common = { bundle: true, minify: true, sourcemap: false, target: ['chrome120'], legalComments: 'none', logLevel: 'info' };

await build({ ...common, entryPoints: [join(here, 'src/web.js')], outfile: join(root, 'web/assets/veye-app.js'), format: 'esm' });
await build({ ...common, entryPoints: [join(here, 'src/ext-panel.js')], outfile: join(root, 'extension/dist/sidepanel.js'), format: 'esm', define: { __VEYE_BASE__: JSON.stringify(base) } });
await build({ ...common, entryPoints: [join(here, 'src/ext-content.js')], outfile: join(root, 'extension/dist/content.js'), format: 'iife' });

cpSync(join(root, 'web/assets/app.css'), join(root, 'extension/app.css'));

// Package the extension (only what Chrome needs).
const stage = join(here, '.stage', 'veye-extension');
rmSync(join(here, '.stage'), { recursive: true, force: true });
mkdirSync(stage, { recursive: true });
for (const f of ['manifest.json', 'background.js', 'sidepanel.html', 'app.css', 'panel.css', 'dist', 'icons']) {
  if (existsSync(join(root, 'extension', f))) cpSync(join(root, 'extension', f), join(stage, f), { recursive: true });
}
const out = join(root, 'web/download/veye-extension.zip');
mkdirSync(dirname(out), { recursive: true });
rmSync(out, { force: true });
execFileSync('powershell.exe', ['-NoProfile', '-Command', `Compress-Archive -Path '${stage}' -DestinationPath '${out}' -Force`], { stdio: 'inherit' });
console.log(`Extension zip: ${out} (API base ${base})`);
