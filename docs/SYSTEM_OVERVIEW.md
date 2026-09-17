# CRMM WordPress System Overview

> Status: v0.1. This document describes the current WordPress architecture. Unverified production details are marked **TODO: VERIFY**.

## Architectural boundary

Cache River Mill & MetalWorks separates **content/data ownership** from **site presentation**.

- `CacheRiverMill/CRMM-Wordpress-Plugins` contains application/data functionality that should survive a theme change.
- `CacheRiverMill/crmm-ollie-child` contains CRMM-specific presentation, block-theme templates, styling, navigation behavior, public catalog UI, and other site-level presentation integrations.
- `CacheRiverMill/crm-trim-catalog-admin` is a separate repository associated with the migrated trim-catalog administration/application history and must be documented independently rather than assumed to be part of the WordPress plugin.
- `CacheRiverMill/CRM-Website` is legacy site/application code and must not be treated as the current WordPress architecture without explicit verification.

This separation is intentional. Do not move presentation behavior into the trim data plugin merely because both participate in the public catalog.

## Current core plugin package

At present the `CRMM-Wordpress-Plugins` repository contains the `crmm-trim-catalog` package.

### CRMM Trim Catalog

The plugin owns the WordPress trim-profile registry and curated catalog data layer. Its responsibilities include:

- `crmm_trim_profile` custom post type.
- Fixed trim category/profile-type taxonomies reconstructed from the legacy catalog.
- Version-controlled ACF field definitions.
- Permanent part-number registry and allocation rules.
- Legacy CSV import.
- Internal/shop-only metadata controls.
- Catalog-manager permissions.
- Readiness/integrity checks and admin queues.
- Part-number notifications.

### Source-of-truth boundary

**Onshape remains the source of truth for profile geometry.** WordPress owns permanent part-number assignment and the curated marketing/specification catalog.

Do not invent manufacturing data in WordPress simply because a public catalog field exists. The catalog is the marketing/specification and registry layer, not the manufacturing CAD authority.

## Part-number invariants

Part numbers are operational identifiers and must be treated as immutable records, not cosmetic titles.

- Format: `{CATEGORY_CODE}-{PROFILE_TYPE_CODE}-{SEQUENCE}`.
- Codes and full part numbers are uppercase.
- Each category/profile-type pair has its own sequence.
- Initial sequence is 1000.
- New allocation uses the greatest reserved sequence plus one.
- Gaps remain gaps; they are never backfilled.
- Deleted/voided profiles do not release their numbers for reuse.
- Classification is locked after permanent assignment.

Any change to these rules is a business-process change and requires explicit human approval.

## Public catalog ownership

The data plugin intentionally does **not** own the public archive/single templates or filtering presentation. Those belong to the CRMM site/theme implementation.

The current Ollie child theme contains templates for:

- `archive-crmm_trim_profile.html`
- `single-crmm_trim_profile.html`

and contains public catalog controls/filtering in `functions.php`.

See the child-theme repository documentation for those responsibilities.

## Related documentation

- Plugin: `crmm-trim-catalog/README.md`
- Plugin docs: `docs/AGENT_CONTEXT.md`
- Child theme: `CacheRiverMill/crmm-ollie-child`
- Child-theme docs: `docs/THEME_ARCHITECTURE.md` in that repository's documentation branch.

## TODO: VERIFY

- Exact production and staging plugin deployment paths.
- Current ACF version/activation configuration on staging and production.
- Current SMTP/mail transport used for part-number notifications.
- Whether any additional CRMM-specific production plugins exist outside this repository and their responsibility/activation status.
