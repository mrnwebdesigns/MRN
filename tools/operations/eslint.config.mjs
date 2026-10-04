export default [{
  files: ['src/**/*.mjs', 'test/**/*.mjs'],
  languageOptions: {
    ecmaVersion: 2024,
    sourceType: 'module',
    globals: Object.fromEntries(['Buffer', 'URL', 'process', 'setTimeout', 'clearTimeout', 'performance', 'fetch'].map(name => [name, 'readonly'])),
  },
  rules: {
    'no-undef': 'error',
    'no-unused-vars': ['error', { argsIgnorePattern: '^_', varsIgnorePattern: '^_' }],
    'no-unreachable': 'error',
    'no-constant-condition': 'error',
    'no-dupe-keys': 'error',
    'no-eval': 'error',
    'eqeqeq': 'error',
  },
}];
