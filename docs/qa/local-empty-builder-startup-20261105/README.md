# Empty Hero / After Content startup regression — 2026-11-05

The Initial Content catalog callbacks were globally registered on `acf/load_field/type=flexible_content` but absent from the conservative empty-builder callback allowlist. Their presence prevented the existing public fast path even though these callbacks leave the eligible Hero and After Content fields unchanged.

The fix admits only those two known native callbacks. It does not expand eligible builder fields or bypass defaults, populated values, alternate references, cached values, unknown callbacks, previews, AJAX or admin requests. Initial Content catalog behavior is unchanged.

## Verification

- New regression harness uses the actual registered editor-startup functions: 21 assertions pass on PHP 8.2 and 8.5; the original source fails the registered-hook fast-path assertion.
- All 29 standalone parent PHP tests pass on both interpreters (58 runs).
- Installed WordPress/ACF regression fails before the fix and passes after it, including native custom/default/cache/metadata/preview fallback checks.
- Empty Hero hydration falls from 5,911 added local field definitions to zero; empty After Content falls from 2,403 to zero. Both still return false and emit zero bytes.
- Diagnostic samples were 468.711 → 16.511 ms and 79.756 → 0.068 ms respectively. These single-request timings do not establish whole-site latency improvements; local proxy/VM jitter remains measurable.
- Main Home and Thank You markup is unchanged after normalizing only declared PHP unique IDs and matching references. Header/footer comparisons normalize only request timestamps and equivalent validated email obfuscation. See [proof.json](proof.json).
- Source gate passes PHP lint, security PHPCS, Semgrep, compatibility and diff checks. PHPStan remains advisory because the isolated gate snapshot lacks `wp-cli-stubs.php`; this limitation is not cleared by the runtime proof.

## Local scope and rollback

Adopted only `inc/builder/performance.php` on `mrnwebdesigns-stack-migration.localhost`. Parent file SHA-256: `a65db920d120269cd0cf044879bbc2a864b235281c50637eba334ea7a634c61e`. Original file SHA-256: `f75fa392983452986d86608129bed28113e3442a5daf504f17fbc2d0d8785c16` is retained privately for rollback.

Before adoption, a private 128,619,717-byte SQL export was checked for footer, all 70 runtime tables, mode 0600 and equal host/VM SHA-256 (`e49df6b6cd4491ef73ebabdc40945be05a5aa4870e9f538bece0a03c6fcb232f`). All 79 authored states, 65 routes, four Site Styles records, 63 full legacy snapshots and 63 candidate hashes were preserved through this source adoption.

The shared VM stopped and resumed during earlier diagnosis, without an agent restart command; cross-boot CPU samples are not claimed as a controlled before/after benchmark. Full native-page runtime qualification is recorded in the site migration checkpoint. No main merge, release/version pin, Fleet promotion or remote deployment is authorized by this source checkpoint.
