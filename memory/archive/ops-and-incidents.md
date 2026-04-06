# Ops And Incidents

Use this file for server behavior, deployment guardrails, live-site incidents, and rollout troubleshooting history.

Full source of record remains:
[/Users/khofmeyer/Development/MRN/memory/archive/2026-04-03-memory-full.md](/Users/khofmeyer/Development/MRN/memory/archive/2026-04-03-memory-full.md)

## Summary
- Covers CloudPanel and stack-manager operating rules, ownership and permission issues, bootstrap/runtime repairs, and live-site recovery notes.
- Use this first when a task involves rsync strategy, file ownership, bootstrap behavior, or a production incident.

## Key Historical Milestones
- `2026-04-02`: `default-configs.mrndev.io` outage traced to unreadable gallery theme files with mode `670`; recovery required selective re-sync and permission normalization.
- `2026-03-27`: server rollout QA after rebuild verified manifests, packages, ownership model, and executable script modes.
- `2026-03-20` to `2026-03-10`: major bootstrap reliability work covered plugin activation order, import persistence, progress tracking, license wiring, and notification flow.
- `2026-03-03`: shared-access ACL and CloudPanel access stabilization work.

## Thread Map
- `2026-03-27 Server Rollout QA After Rebuild Recovery`
- `2026-03-26 Theme Manifest Repair For Stack Rollout`
- `2026-03-20 Stack Bootstrap Repair For default-configs.mrndev.io`
- `2026-03-20 default-configs License Recovery`
- `2026-03-20 Plugin Manifest Rebuild`
- `2026-03-20 Stack Plugin Exclusions Update`
- `2026-03-17 Stack ACF Pro License Wiring`
- `2026-03-13 Stack Server Access Improvements`
- `2026-03-12 MRN Plugin Package Version Audit + Stack Sync`
- `2026-03-12 14.mrndev.io AME Compare + Role Repair`
- `2026-03-12 Bootstrap wp-config Development Flag`
- `2026-03-10 Core Reading Setting on Bootstrap`
- `2026-03-10 Progress Fix Deployed to Server`
- `2026-03-09 SearchWP License Bootstrap`
- `2026-03-09 Plugin Activation Before Licenses/Imports`
- `2026-03-09 1.mrndev.io Theme + AME Roles Fixes`
- `2026-03-09 AME Roles Follow-up Fix for 2.mrndev.io`
- `2026-03-09 3.mrndev.io AME Roles Regression`
- `2026-03-09 6.mrndev.io SearchWP + Admin UI Follow-up`
- `2026-03-09 Slack Notifications for Bootstrap`
- `2026-03-09 Bootstrap Progress Indicator Reliability Fix`
- `2026-03-09 Progress UI + Slack Start Notification`
- `2026-03-06 Bootstrap Discovery Miss for mrndev-strapped-strap`
- `2026-03-06 OTP Rollback/Restore`
- `2026-03-06 AME Partial Import on strapped.mrndev.io`
- `2026-03-06 Upload Button 400 Fix`
- `2026-03-06 Export Upload Failure (ame-config-container.json write)`
- `2026-03-06 Importer Manifest Write Failure Hardening`
- `2026-03-06 Bootstrap Progress Reliability via Runtime Status File`
- `2026-03-05 AME + Theme Rollout Regression Triage`
- `2026-03-05 Bootstrap Auto-Configure for Post Types Order`
- `2026-03-05 Bootstrap WPForms License Verify Automation`
- `2026-03-05 Stack Bootstrap Rollback Package`
- `2026-03-05 Bootstrap Updraft Premium Connect Automation`
- `2026-03-03 Config/License Import Wiring`
- `2026-03-03 CloudPanel Shared Access ACL Stabilization`
- `2026-03-02 Bootstrap Run-Now Arg + Notify Wiring Fix`
- `2026-03-02 Bootstrap Resilience + Warning Notifications`
- `2026-02-28 stack-manager + bootstrap workflow`
- `2026-02-28 SFTP/FTP CloudPanel on DO`

## Related Files
- Active operational caveats:
  [/Users/khofmeyer/Development/MRN/memory/current.md](/Users/khofmeyer/Development/MRN/memory/current.md)
- Deployment conventions:
  [/Users/khofmeyer/Development/MRN/memory/spec/conventions.md](/Users/khofmeyer/Development/MRN/memory/spec/conventions.md)
