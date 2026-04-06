# Plugin Config Checklist

Use this to classify each plugin configuration path so bootstrap is reliable.

## Scriptable (`wp option update` / CLI)

- Simple scalar options (booleans, strings, numbers)
- Settings with stable keys and no site-specific IDs

## Import/Export Preferred

- Large structured settings provided by plugin export tools
- Builder templates (forms, blocks, layout presets)
- Rulesets with many nested options

## Usually Manual or Secret-Driven

- License keys
- API tokens
- Site/domain-bound values
- Webhook endpoints

## Process

1. List all plugins in `manifests/plugins.txt`.
2. For each plugin, pick one path: Scriptable, Import/Export, or Manual.
3. If import/export is needed, add an executable script in `configs/importers/`.
4. Keep secrets out of Git; read them from environment variables on server.
