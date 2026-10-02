// Exact-path stylesheet alternatives declared by the reviewed theme release.
export function validateStylesheetRoutes(routes, assets) {
  if (!routes || typeof routes !== 'object' || Array.isArray(routes)) throw new Error('Invalid stylesheet routes');
  for (const [route, sources] of Object.entries(routes)) {
    const url = new URL(route, 'https://release.invalid');
    if (!route.startsWith('/') || route.startsWith('//') || url.origin !== 'https://release.invalid'
      || url.pathname !== route || url.search || url.hash || /[%\\\s]/.test(route)
      || !Array.isArray(sources) || !sources.length || new Set(sources).size !== sources.length
      || sources.some(source => typeof source !== 'string' || !source.endsWith('.css') || !Object.hasOwn(assets, source))) {
      throw new Error('Invalid stylesheet route: ' + route);
    }
  }
  return routes;
}

export function stylesheetFiles(manifest, url) {
  const routes = validateStylesheetRoutes(manifest.stylesheet_routes ?? {}, manifest.assets);
  return (routes[new URL(url).pathname] ?? ['style.css']).map(source => manifest.assets[source].file);
}
