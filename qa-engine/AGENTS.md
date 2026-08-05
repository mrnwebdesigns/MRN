# AGENTS.md - MRN QA Engine

## Purpose
This repository is the shared QA engine used across MRN projects when release/security QA is requested.

## Rules
- Keep engine scripts path-agnostic and transportable.
- Prefer clear blocking conditions over silent assumptions.
- Preserve strict output format compatibility.
- Do not hardcode project-specific behavior unless guarded behind profile/config.

## Safety
- Never auto-deploy.
- Never mutate project repos during QA unless explicitly requested.
- Run expensive checks only when scope or policy requires them.
