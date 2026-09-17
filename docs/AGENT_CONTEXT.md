# Agent Context — CRMM WordPress Plugins

Read `docs/SYSTEM_OVERVIEW.md` and `crmm-trim-catalog/README.md` before changing this repository.

## Boundaries

This repository owns durable CRMM WordPress application/data behavior. The separate `CacheRiverMill/crmm-ollie-child` repository owns site presentation and must be inspected independently for template, styling, navigation, and public-catalog UI changes.

Do not move code between these repositories without an explicit architectural reason and human approval.

## Trim catalog rules that must not be guessed or casually refactored

- Onshape is the geometry source of truth.
- WordPress owns permanent part-number assignment and the curated public/specification catalog.
- Part numbers are uppercase and permanent.
- Number gaps are preserved.
- Voided/deleted numbers are never reused.
- Category/profile type is locked after permanent number allocation.
- Internal/shop fields must remain excluded from public output/REST exposure as designed.
- Abandoned binder fields are intentionally excluded from the migrated schema.
- Legacy imports should not automatically publish profiles.

## Change discipline

For a requested maintenance change:

1. Identify the owning repository/component before editing.
2. Read the relevant implementation and documentation.
3. Change only what is required.
4. Do not refactor unrelated working code.
5. Inspect the complete diff.
6. Run applicable syntax/static checks.
7. Commit with a specific message.
8. Deploy only through an explicitly authorized/documented route.
9. Verify the deployed behavior/file rather than assuming commit = deployment.
10. Stop after verification.

Do not use Browser/Computer Use for a Git/SSH/file task unless the task actually requires browser interaction.

If a human maintainer has already supplied an observed production fact, do not spend time interrogating production merely to rediscover it unless verification is part of the request.
