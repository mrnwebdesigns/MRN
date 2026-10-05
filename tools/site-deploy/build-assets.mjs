#!/usr/bin/env node
/** Build immutable, dependency-complete static assets from an exported theme. */
import {createHash} from 'node:crypto';
import {readdir, readFile, mkdir, writeFile, copyFile} from 'node:fs/promises';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {build, transform, version as esbuildVersion} from 'esbuild';
import {validateStylesheetRoutes} from './stylesheet-routes.mjs';

const hash = data => createHash('sha256').update(data).digest('hex');
const staticExtensions = new Set(['.css', '.js', '.mjs', '.svg', '.png', '.jpg', '.jpeg', '.gif', '.webp', '.avif', '.ico', '.woff', '.woff2', '.ttf', '.otf', '.eot']);
const excluded = new Set(['node_modules', 'vendor', 'tests', 'docs', 'scripts', 'qa']);
async function inventory(root, prefix = '', excludedDirectories = excluded) {
  const files = [];
  for (const entry of (await readdir(path.join(root, prefix), {withFileTypes: true})).sort((a,b) => a.name < b.name ? -1 : 1)) {
    if (entry.name.startsWith('.') || excludedDirectories.has(entry.name)) continue;
    const name = path.posix.join(prefix, entry.name);
    if (entry.isSymbolicLink()) throw new Error('Asset symlink is not allowed: ' + name);
    if (entry.isDirectory()) files.push(...await inventory(root, name, excludedDirectories));
    else if (entry.isFile() && staticExtensions.has(path.extname(name))) files.push(name);
  }
  return files;
}

export async function buildAssets({theme, output, slug, sourceSha, excludedDirectories = excluded}) {
  if (!/^[a-z0-9_-]+$/.test(slug) || !/^[0-9a-f]{40}$/.test(sourceSha)) throw new Error('Invalid release identity');
  theme = path.resolve(theme); output = path.resolve(output);
  if (output === theme || output.startsWith(theme + path.sep)) throw new Error('Build output must be outside the theme');
  const files = await inventory(theme, '', excludedDirectories), bodies = new Map(), entries = {};
  for (const file of files) bodies.set(file, await readFile(path.join(theme, file)));
  // Always regenerate a minified sibling when its editable source exists.
  // Vendor-only minified files remain exact, versioned dependencies.
  for (const file of files) {
    if (!/\.(css|js|mjs)$/.test(file) || /\.min\.(css|js|mjs)$/.test(file)) continue;
    const ext = path.extname(file), emitted = file.slice(0, -ext.length) + '.min' + ext;
    const result = await transform(bodies.get(file).toString('utf8'), {
      loader: ext === '.css' ? 'css' : 'js', minify: true,
      charset: 'utf8', legalComments: 'inline', sourcefile: file,
      // No bundling or browser-target rewriting: preserve existing runtime semantics.
    });
    const bytes = Buffer.from(result.code);
    bodies.set(emitted, bytes);
    entries[file] = {file: emitted, sha256: hash(bytes), bytes: bytes.length};
    entries[emitted] = entries[file];
  }
  for (const [file, bytes] of bodies) {
    if (/\.(css|js|mjs)$/.test(file) && !entries[file]) entries[file] = {file, sha256: hash(bytes), bytes: bytes.length};
    if (/\.css$/.test(file)) {
      // Use the CSS parser, not a URL regex: data SVGs can contain nested url().
      await build({stdin: {contents: bytes.toString('utf8'), loader: 'css', sourcefile: file,
        resolveDir: path.join(theme, path.dirname(file))}, bundle: true, write: false, logLevel: 'silent',
        plugins: [{name: 'verify-owned-css-dependencies', setup(builder) {
          builder.onResolve({filter: /.*/}, args => {
            const url = args.path;
            if (!/^(?:[a-z][a-z0-9+.-]*:|\/|#)/i.test(url)) {
              const dependency = path.posix.normalize(path.posix.join(path.posix.dirname(file), decodeURIComponent(url.split(/[?#]/)[0])));
              if (dependency.startsWith('../') || !bodies.has(dependency)) throw new Error('Missing local CSS dependency: ' + file + ' -> ' + dependency);
            }
            return {path: url, external: true};
          });
        }}]});
    }
  }
  let stylesheetRoutes;
  try {
    const declaration = JSON.parse(await readFile(path.join(theme, 'mrn-asset-routes.json'), 'utf8'));
    if (declaration.schema !== 1) throw new Error('Invalid asset route schema');
    stylesheetRoutes = validateStylesheetRoutes(declaration.stylesheet_routes, entries);
  } catch (error) {
    if (error.code !== 'ENOENT') throw error;
  }
  const staticFiles = Object.fromEntries([...bodies].sort(([a],[b]) => a < b ? -1 : 1).map(([file, bytes]) => [file, {sha256: hash(bytes), bytes: bytes.length}]));
  const generation = hash(JSON.stringify(staticFiles));
  const publicPath = `mrn-assets/${slug}/${generation}`;
  const assetRoot = path.join(output, 'assets', publicPath);
  for (const [file, bytes] of bodies) {
    const destination = path.join(assetRoot, file);
    await mkdir(path.dirname(destination), {recursive: true});
    await writeFile(destination, bytes, {flag: 'wx'});
  }
  const manifest = {schema: 1, scope: 'child-theme', slug, source_sha: sourceSha,
    toolchain: {esbuild: esbuildVersion}, generation, public_path: publicPath,
    assets: Object.fromEntries(Object.entries(entries).sort(([a],[b]) => a < b ? -1 : 1)), static_files: staticFiles,
    ...(stylesheetRoutes ? {stylesheet_routes: stylesheetRoutes} : {})};
  await mkdir(path.join(output, 'theme'), {recursive: true});
  // Minified siblings shipped in the theme are generated from this same build.
  for (const [file, bytes] of bodies) {
    if (!/\.min\.(css|js|mjs)$/.test(file)) continue;
    const destination = path.join(output, 'theme', file);
    await mkdir(path.dirname(destination), {recursive: true});
    await writeFile(destination, bytes, {flag: 'wx'});
  }
  await writeFile(path.join(output, 'theme', 'mrn-assets.json'), JSON.stringify(manifest, null, 2) + '\n', {flag: 'wx'});
  await copyFile(new URL('./release-assets.php', import.meta.url), path.join(output, 'theme', 'mrn-release-assets.php'));
  return manifest;
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const [theme, output, slug, sourceSha] = process.argv.slice(2);
  if (!sourceSha) throw new Error('Usage: build-assets.mjs THEME OUTPUT SLUG SOURCE_SHA');
  const manifest = await buildAssets({theme, output, slug, sourceSha});
  console.log(JSON.stringify({generation: manifest.generation, assets: Object.keys(manifest.assets).length, public_path: manifest.public_path}));
}
