export const operationsScope = 'mrn:operations';
export const operationsWriteScope = 'mrn:operations:write';
export const operationsScopes = Object.freeze([operationsScope, operationsWriteScope]);

// Effects such as fresh synchronization and saving inspection reports do not
// grant authority to prepare, approve, execute or recover website changes.
const writeTools = new Set(['prepare_repair', 'prepare_rollback', 'prepare_code_edit', 'prepare_content_edit',
  'approve_code_source', 'approve_operation', 'execute_operation', 'reconcile_operation']);
export const toolNeedsWriteScope = name => writeTools.has(name);
export const toolSecuritySchemes = name => [{ type: 'oauth2', scopes: toolNeedsWriteScope(name) ? [...operationsScopes] : [operationsScope] }];
