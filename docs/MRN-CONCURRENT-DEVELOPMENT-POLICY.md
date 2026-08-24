# MRN Concurrent Development and Stack Promotion Policy

## Purpose

MRN development assumes that several agents, tasks, and repositories may be in
progress at the same time. A change in one task must not make an unrelated task
uncommittable, and isolating feature work must not allow the shared stack or the
fleet to drift behind merged source.

This policy separates two gates that solve different problems:

- the **task acceptance gate** proves that one task's proposed change is safe;
- the **stack promotion gate** proves that merged deployable source forms one
  coherent, reproducible stack release and has reached its intended runtimes.

Passing either gate never implies that the other gate passed.

## Concurrent Task Isolation

1. One task uses one branch and one dedicated Git worktree.
2. A task must not develop in another task's worktree or in a dirty canonical
   checkout. The canonical checkout is a coordination and recovery surface, not
   a shared feature workspace.
3. Existing changes in another worktree belong to that task. Do not stage,
   amend, revert, clean, move, or include them in the current task.
4. Worktree names and branches must identify the task. Agent-created branches
   use the agent's required prefix.
5. A worktree may be removed only after its branch is merged or intentionally
   abandoned and the worktree is clean.
6. Repositories that participate in one cross-repository change use separate,
   corresponding task branches/worktrees. A clean repository must not be used
   to hide an uncommitted dependency in another repository.

## Task Acceptance Gate

The task acceptance gate answers: "Is the staged change from this task ready to
commit and review?"

- The mechanical commit gate evaluates an index snapshot, not the mutable
  working directory.
- Static source analysis is fixed to the staged changed-file list. Inherited
  shell or project settings must not broaden the commit gate to the monorepo.
- Unstaged or untracked work from another task is outside the proof and must not
  block the commit.
- Ignored machine-local data, nested task worktrees, release snapshots, QA
  snapshots, generated reports, and build output are not product source and
  must not be traversed by full-source scanners.
- `always` policies that intentionally request repository-wide analysis belong
  to an explicit full QA or promotion run; they are neutralized by the commit
  gate.
- The commit gate is a static, task-isolation proof. It does not replace
  applicable task-scoped browser, accessibility, API runtime, performance, or
  integration QA. Those checks must target the project/site changed by the
  task, never an unrelated shared runtime route.
- A failure in changed task code blocks the task. A pre-existing finding outside
  the task is baseline debt: track and fix it separately, but do not silently
  attach it to every unrelated feature commit.

Pull-request code gates use the same changed-file boundary. A PR that modifies
the QA workflow or policy may test those files without inheriting unrelated
monorepo findings.

The separate `Stack promotion drift` workflow runs after merges to `main`, on a
daily schedule, and on explicit dispatch. It is not a pull-request task gate.
Its failure records visible release debt and preserves the machine-readable
inventory while isolated feature work continues normally.

## Component and Full QA

The owner or release process may explicitly request broader QA:

- **component QA** may inspect the complete plugin, theme, MU plugin, service,
  or other release unit changed by the task;
- **repository QA** may inspect the complete repository;
- **runtime QA** may inspect a named site/runtime;
- **release QA** may inspect all components and runtimes in a promotion.

Broader QA findings are valid and must be reported. They do not retroactively
change the scope of an unrelated task acceptance proof. They block the broader
operation for which they were requested, such as component release or stack
promotion.

## Stack Promotion Gate

The stack promotion gate answers: "Does clean, merged source define one complete
release, and has that exact release reached the approved targets?"

Only a dedicated release task may promote the stack. It must:

1. Start from a clean, current checkout of merged `main`; feature worktrees and
   unmerged branches are not release inputs.
2. Compare `main` with the previous immutable stack release lock and inventory
   every changed deployable theme, plugin, MU plugin, runtime component, and
   deployment contract. This inventory prevents a merged component from being
   omitted merely because its original task ended.
   `stack/scripts/qa-stack-promotion.py --mode audit` is the canonical
   machine-readable merged-source inventory for this step.
3. Synchronize component versions, catalog records, manifests, stack version,
   and release notes for the complete promotion scope.
4. Run required full component, repository, contract, runtime, accessibility,
   performance, and rollout QA for that release. Baseline debt that was
   non-blocking for a feature commit is blocking when it affects the promoted
   release or its required runtime checks.
5. Generate `stack/manifests/stack-release.lock.json` only after all release
   source commits are final. Never generate a release lock from a dirty tree.
   Commit the lock separately, then run
   `stack/scripts/qa-stack-promotion.py --mode candidate` to reconcile it with
   current merged `origin/main` and the prior committed lock.
6. Build deterministic artifacts from the exact lock and preserve their
   checksums, source commits, plan, and rollout identity.
7. Deploy through the approved backup and authorization gates.
8. Read back installed versions/hashes and compare runtime inventory with the
   release lock. A successful transport is not deployment verification.
9. Mark the release current only when the source, catalog, lock, artifacts,
   deployment evidence, and target inventory agree.

If promotion is blocked, feature development may continue in isolated
worktrees. The blocked release remains visibly unreleased; do not relabel the
fleet or stack baseline to conceal drift.

## Status Language

Use these states precisely:

- **in progress**: task work is uncommitted or unreviewed;
- **accepted**: task QA passed and the change is committed/reviewable;
- **merged**: source is on `main`, but may not be in a stack release;
- **release candidate**: clean merged source has a complete lock and artifacts,
  but deployment verification is incomplete;
- **current**: the immutable release is verified on every declared target;
- **drifted**: target inventory differs from the declared release lock.

Never use "all up to date," "rolled out," "current," or "production ready"
based only on a commit, merge, QA pass, package upload, or deployment command.

## Ownership and Recovery

- Each task records its branch, worktree, repositories, required QA, and release
  impact in its handoff or pull request.
- A release task owns the cross-repository inventory and promotion evidence; an
  individual feature task does not need to remain open until fleet rollout.
- Emergency/checkpoint commits follow the canonical exception policy and are
  explicitly not accepted or release-ready.
- If task ownership is unclear, stop before modifying the affected worktree.
  Continue other isolated work that does not depend on that decision.
