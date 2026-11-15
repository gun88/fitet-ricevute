import { createWriteStream, existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import archiver from 'archiver';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
if (!existsSync(resolve(root, 'assets/app/index.html'))) throw new Error('Eseguire prima npm run build.');
if (!existsSync(resolve(root, 'vendor/autoload.php'))) throw new Error('Eseguire prima composer install --no-dev.');
const version = JSON.parse(readFileSync(resolve(root, 'app/package.json'), 'utf8')).version;
const header = readFileSync(resolve(root, 'fitet-ricevute.php'), 'utf8').replace(/\r\n/g, '\n');
if (!header.includes(`Version: ${version}\n`) || !header.includes(`define('FITET_RICEVUTE_VERSION', '${version}')`)) throw new Error('Versioni PHP e package.json non allineate.');
const metadataPath = resolve(root, 'release/plugin.json');
const metadata = JSON.parse(readFileSync(metadataPath, 'utf8'));
metadata.version = version;
metadata.download_url = `https://raw.githubusercontent.com/gun88/fitet-ricevute/refs/heads/main/release/fitet-ricevute-${version}.zip`;
metadata.last_updated = new Date().toISOString().slice(0,19).replace('T',' ');
mkdirSync(resolve(root, 'release'), { recursive: true });
const target = resolve(root, `release/fitet-ricevute-${version}.zip`);
const output = createWriteStream(target);
const archive = archiver('zip', { zlib: { level: 9 } });
const done = new Promise((resolve, reject) => { output.on('close', resolve); output.on('error', reject); archive.on('error', reject); });
archive.pipe(output);
for (const file of ['fitet-ricevute.php', 'uninstall.php', 'README.md']) archive.file(resolve(root, file), { name: `fitet-ricevute/${file}` });
archive.directory(resolve(root, 'includes'), 'fitet-ricevute/includes');
archive.directory(resolve(root, 'vendor'), 'fitet-ricevute/vendor');
archive.directory(resolve(root, 'docs'), 'fitet-ricevute/docs');
archive.directory(resolve(root, 'assets/app'), 'fitet-ricevute/assets/app');
archive.file(resolve(root, 'assets/admin.css'), { name: 'fitet-ricevute/assets/admin.css' });
await archive.finalize();
await done;
writeFileSync(metadataPath, JSON.stringify(metadata, null, 2) + '\n');
console.log(`Aggiornato ${metadataPath}`);
console.log(`Creato ${target} (${archive.pointer()} byte)`);

const sourceTarget = resolve(root, `release/fitet-ricevute-${version}-source.zip`);
const sourceOutput = createWriteStream(sourceTarget);
const sourceArchive = archiver('zip', { zlib: { level: 9 } });
const sourceDone = new Promise((resolve, reject) => {
  sourceOutput.on('close', resolve); sourceOutput.on('error', reject); sourceArchive.on('error', reject);
});
sourceArchive.pipe(sourceOutput);
sourceArchive.file(metadataPath, { name: 'fitet-ricevute/release/plugin.json' });
sourceArchive.glob('**/*', {
  cwd: root,
  dot: true,
  ignore: ['app/node_modules/**', 'app/.angular/**', 'app/dist/**', '.npm-cache/**', '.composer-cache/**', '.git/**', '.idea/**', '.codex/**', '.agents/**', 'release/**', 'tests/.runtime/**', 'tests/screenshots/**']
}, { prefix: 'fitet-ricevute' });
await sourceArchive.finalize();
await sourceDone;
console.log(`Creato ${sourceTarget} (${sourceArchive.pointer()} byte)`);
