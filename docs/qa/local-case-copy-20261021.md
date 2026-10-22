# Local native case-study text/grid controls — October 21

Shared Stack changes in this site thread were explicitly authorized earlier.
The candidate extends optional Body Text fields with bounded responsive heading
sizes/line heights (900px/560px), paragraph spacing, signed tracking and preserved
line breaks. Cards adds optional 900px/560px views, responsive row/column gaps and
section-label typography. Existing choices and blank fields retain existing
behavior. Outer section spacing remains in native row presets.

Selected Body Text font/line-height fields now govern direct paragraph children
instead of being overridden by the global paragraph rule. The site mappings
retain 21px Solutions lines and the homepage Tabs' effective 16px/25.6px type.

The native motion catalog adds `heading` (Heading Text Only) for Body Text fields.
Its frontend selector is `.mrn-ui__heading`; existing `header` still targets the
whole heading area. Text reveal skips preparation when this explicit target has
no heading, preserving readable labels and a saved choice for later copy. Unknown
extension fields retain the full compatible target catalog.

Proof: 576 native assertions, 153 case and 62 home source/persistence/refusal checks, both converter
suites (32 groups / 49 removed fixtures), 24 standalone PHP regressions, direct
static checks for 38 PHP files and five JS files, zero shared Semgrep findings,
and full-page accessibility/keyboard/API smoke. CWV has no poor samples, but NAA
candidate CLS 0.12474 and a paired legacy homepage CLS 0.13610 still need work;
performance acceptance is not claimed. Actual viewport checks cover
1280/1024/900/560/390 with no overflow. No reduced-motion or JS-disabled browser
emulation, installed adoption, editor acceptance or release clearance is claimed.

The site converter is 20261021.8. Six Problem and six Solutions rows encode all
30 solution items. The owner-approved Amish Mike wrapper cleanup changes one
source field only, preserving every word and all other fields; no authored native
conversion occurs. Six Overview rows now explicitly report their saved unused
CTA/taxonomy-label mapping gap. Problem/Solutions now preserve effective legacy
None inheritance and explicit zero, with the existing 120px/0px responsive side
preset. Their measured desktop geometry matches the legacy rows. Other layout
families and complete pages still need spacing review.

The QA orchestration/commit-gate runner still stalls before a report; direct checks
are separately recorded. Source work remains local/uncommitted. Managed parent,
Stack lock, RBL and Config Helper are unchanged. No push, release, remote install
or production change occurred. Remote parity/deploy checks were not invoked.

Full evidence: `/Users/khofmeyer/.codex/worktrees/stack-migration/mrnwebdesigns/docs/stack-migration-2026-10-21/LOCAL_CASE_COPY.md`.
