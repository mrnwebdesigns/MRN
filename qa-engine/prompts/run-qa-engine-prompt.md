Use the shared MRN QA engine for this thread.

Steps:
1. Ensure `mrn-qa` is installed and available in PATH.
2. Run `mrn-qa doctor --project-root <repo>`.
3. Run `mrn-qa run --project-root <repo>` with policy:
   - `--mode release` for formal release QA
   - `--run-smoke always --smoke-strict 1` when frontend/auth/security-sensitive behavior changed
4. Return the exact report output from the engine (no reformatting).
5. If the engine reports blockers, stop and ask only the minimum required clarification.

If `mrn-qa` is missing, install from `/Users/khofmeyer/Development/MRN-qa-engine/tools/install.sh` and retry.
