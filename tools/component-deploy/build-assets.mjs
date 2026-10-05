#!/usr/bin/env node
/** Extend the shared compiler with explicit component dependency checks. */
import {readFile, writeFile} from 'node:fs/promises';
import path from 'node:path';
import {parse} from 'acorn';
import {build} from 'esbuild';
import {buildAssets} from '../site-deploy/build-assets.mjs';

const [source, output, slug, sourceSha] = process.argv.slice(2);
if (!sourceSha) throw new Error('Usage: build-assets.mjs SOURCE OUTPUT SLUG COMMIT');
const manifest = await buildAssets({theme: source, output, slug, sourceSha,
  excludedDirectories: new Set(['node_modules'])});
const dependencies = {}, external = {};

function dependency(file, value, module = false) {
  if (typeof value !== 'string') throw new Error('Computed asset dependency requires a qualified adapter: ' + file);
  if (/^(?:https?:|data:|\/\/|#)/i.test(value)) {
    external[file].add(value);
    return;
  }
  if (value.startsWith('/') || /^[a-z][a-z0-9+.-]*:/i.test(value)
      || (module && !value.startsWith('./') && !value.startsWith('../'))) {
    throw new Error('Non-relative owned dependency requires an adapter: ' + file + ' -> ' + value);
  }
  const name = path.posix.normalize(path.posix.join(path.posix.dirname(file), decodeURIComponent(value.split(/[?#]/)[0])));
  if (name.startsWith('../') || !manifest.static_files[name]) throw new Error('Missing asset dependency: ' + file + ' -> ' + name);
  dependencies[file].add(name);
}

function inspect(node, file) {
  if (!node || typeof node !== 'object') return;
  if (['ImportDeclaration', 'ExportAllDeclaration', 'ExportNamedDeclaration'].includes(node.type) && node.source) {
    dependency(file, node.source.value, true);
  }
  if (node.type === 'ImportExpression') {
    dependency(file, node.source.type === 'Literal' ? node.source.value : null, true);
  }
  if ((node.type === 'CallExpression' && node.callee.type === 'Identifier' && node.callee.name === 'require')
      || (node.type === 'NewExpression' && node.callee.type === 'Identifier' && ['Worker', 'SharedWorker'].includes(node.callee.name))) {
    throw new Error('Runtime module/worker loader requires a qualified adapter: ' + file);
  }
  if (node.type === 'NewExpression' && node.callee.type === 'Identifier' && node.callee.name === 'URL'
      && node.arguments[1]?.type === 'MemberExpression' && node.arguments[1].object.type === 'MetaProperty') {
    dependency(file, node.arguments[0]?.type === 'Literal' ? node.arguments[0].value : null);
  }
  for (const child of Object.values(node)) {
    if (Array.isArray(child)) child.forEach(value => inspect(value, file));
    else if (child && typeof child === 'object') inspect(child, file);
  }
}

for (const file of Object.keys(manifest.static_files)) {
  dependencies[file] = new Set(); external[file] = new Set();
  const body = await readFile(path.join(output, 'assets', manifest.public_path, file), 'utf8');
  if (/\.(?:m?js)$/.test(file)) {
    let ast;
    try { ast = parse(body, {ecmaVersion: 'latest', sourceType: 'module'}); }
    catch { ast = parse(body, {ecmaVersion: 'latest', sourceType: 'script'}); }
    inspect(ast, file);
  } else if (/\.css$/.test(file)) {
    await build({stdin: {contents: body, loader: 'css', sourcefile: file}, bundle: true, write: false, logLevel: 'silent',
      plugins: [{name: 'component-dependencies', setup(builder) {
        builder.onResolve({filter: /.*/}, args => {
          dependency(file, args.path);
          return {path: args.path, external: true};
        });
      }}]});
  }
}
manifest.dependencies = Object.fromEntries(Object.entries(dependencies).map(([file, refs]) => [file, [...refs].sort()]));
manifest.external_dependencies = Object.fromEntries(Object.entries(external).filter(([, refs]) => refs.size).map(([file, refs]) => [file, [...refs].sort()]));
manifest.toolchain.acorn = '8.15.0';
// Arbitrary DOM-created URLs and optimizer output still require consumer QA.
manifest.dependency_coverage = 'css-and-literal-es-modules';
await writeFile(path.join(output, 'theme/mrn-assets.json'), JSON.stringify(manifest, null, 2) + '\n');
