# Stack Baseline - MRN Workspace

## Baseline Snapshot
- Date pinned: 2026-08-18
- Workspace path: `/Users/khofmeyer/Development/MRN`
- Stack release: `2026.08.16-reusable-block-library-stack-profile`
- Current theme package: `mrn-base-stack` `1.2.106`
- Intended integration target: MRN stack and local platform runtime
- Current release model: coordinated stack workspace

## Why This File Exists
This workspace follows MRN QA Engine discovery standards so stack-wide QA can identify the active baseline without relying on optional/missing-file warnings.

## Source Of Truth
- Stack baseline details live in `stack/STACK_VERSION.md`.
- Release and rollout guidance lives in `stack/ROLLOUT_CHECKLIST.md` and `stack/STACK_OPERATIONS.md`.
- Built artifacts live under `releases/` and should be rebuilt from source.

## Update Process
1. Update stack source, theme/plugin versions, and release artifacts.
2. Update `stack/STACK_VERSION.md` when the stack baseline changes.
3. Run `mrn-qa run --project-root /Users/khofmeyer/Development/MRN`.
4. Update `stack.lock` and this file when baseline metadata changes.
