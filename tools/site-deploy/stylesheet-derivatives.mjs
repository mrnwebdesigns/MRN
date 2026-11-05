/** Build opt-in body-context stylesheets without reordering retained rules. */
import path from 'node:path';
import postcss from 'postcss';
import selectorParser from 'postcss-selector-parser';

const bodyClass = /^(?:home|page|single(?:-[a-z0-9_-]+)?|page-id-[0-9]+|parent-pageid-[0-9]+|post-type-archive(?:-[a-z0-9_-]+)?|archive|search|search-results|error404)$/;
const cssName = name => typeof name === 'string' && /^[a-z0-9][a-z0-9_/-]*\.css$/.test(name)
  && path.posix.normalize(name) === name && !name.endsWith('.min.css');

export function validateStylesheetContexts(contexts = {}) {
  if (!contexts || typeof contexts !== 'object' || Array.isArray(contexts)) throw new Error('Invalid stylesheet contexts');
  for (const [name, context] of Object.entries(contexts)) {
    if (!cssName(name) || !context || typeof context !== 'object'
      || Object.keys(context).sort().join(',') !== 'absent_body_classes,required_body_classes') throw new Error('Invalid stylesheet context');
    for (const key of ['required_body_classes', 'absent_body_classes']) {
      if (!Array.isArray(context[key]) || !context[key].length || new Set(context[key]).size !== context[key].length
        || context[key].some(value => typeof value !== 'string' || !bodyClass.test(value))) throw new Error('Invalid stylesheet body classes');
    }
    if (context.required_body_classes.some(value => context.absent_body_classes.includes(value))) throw new Error('Conflicting stylesheet context');
  }
  return contexts;
}

// Only a positive body class in the leftmost compound can prove impossibility.
// Descendants, :not(), :has(), unknown syntax and mixed :is() branches stay.
function impossible(selector, absent) {
  const compound = [];
  for (const node of selector.nodes) {
    if (node.type === 'combinator') break;
    compound.push(node);
  }
  if (compound.some(node => node.type === 'nesting' || node.type === 'tag' && node.value.toLowerCase() !== 'body')) return false;
  return compound.some(node => node.type === 'class' && absent.has(node.value)
    || node.type === 'pseudo' && [':is', ':where'].includes(node.value.toLowerCase())
    && node.nodes?.length && node.nodes.every(branch => !branch.nodes.some(child => child.type === 'combinator') && impossible(branch, absent)));
}

export function bodyContextStylesheet(css, context) {
  const root = postcss.parse(css), absent = new Set(context.absent_body_classes);
  function visit(container) {
    for (const node of [...container.nodes ?? []]) {
      if (node.type === 'rule') {
        // Nested rules and unknown syntax retain the original complete rule.
        if (node.nodes.some(child => child.type === 'rule' || child.type === 'atrule')) continue;
        let selectors;
        try { selectors = selectorParser().astSync(node.selector); } catch { continue; }
        const retained = selectors.nodes.filter(selector => !impossible(selector, absent));
        if (!retained.length) node.remove();
        else if (retained.length !== selectors.nodes.length) node.selector = retained.map(selector => selector.toString().trim()).join(',');
      } else if (node.type === 'atrule' && ['media', 'supports', 'container', 'layer'].includes(node.name.toLowerCase()) && node.nodes) {
        visit(node);
      }
    }
  }
  visit(root);
  return root.toString();
}

export function deriveStylesheets(bodies, declaration = {}) {
  const contexts = validateStylesheetContexts(declaration.stylesheet_contexts);
  const generated = new Set();
  function output(name, sources) {
    if (!cssName(name) || bodies.has(name) || !Array.isArray(sources) || !sources.length
      || sources.some(source => !cssName(source) || !bodies.has(source) || path.posix.dirname(source) !== path.posix.dirname(name))) {
      throw new Error('Invalid derived stylesheet');
    }
    generated.add(name);
  }
  const subsets = declaration.stylesheet_subsets ?? {};
  const bundles = declaration.stylesheet_bundles ?? {};
  for (const value of [subsets, bundles]) if (!value || typeof value !== 'object' || Array.isArray(value)) throw new Error('Invalid stylesheet derivatives');
  for (const [name, config] of Object.entries(subsets)) {
    if (!config || Object.keys(config).sort().join(',') !== 'context,source' || !Object.hasOwn(contexts, config.context)) throw new Error('Invalid stylesheet subset context');
    output(name, [config.source]);
    bodies.set(name, Buffer.from(bodyContextStylesheet(bodies.get(config.source).toString('utf8'), contexts[config.context])));
  }
  for (const [name, sources] of Object.entries(bundles)) {
    output(name, sources);
    const styles = sources.map(source => {
      const css = bodies.get(source).toString('utf8');
      // Concatenation preserves relative URLs only within one directory. An
      // import or charset directive needs a separate qualified bundling recipe.
      postcss.parse(css).walkAtRules(rule => {
        if (['import', 'charset'].includes(rule.name.toLowerCase())) throw new Error('Unsupported stylesheet bundle directive');
      });
      return css;
    });
    bodies.set(name, Buffer.from(styles.join('\n')));
  }
  for (const name of Object.keys(contexts)) if (!bodies.has(name)) throw new Error('Missing contextual stylesheet');
  return {contexts, generated};
}
