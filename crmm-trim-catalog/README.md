# CRMM Trim Catalog

WordPress plugin for the Cache River Mill & MetalWorks trim profile registry and public catalog.

## Responsibilities

- Registers the `crmm_trim_profile` custom post type.
- Registers fixed category and profile-type taxonomies from the legacy catalog.
- Registers ACF field groups in PHP so the schema is version controlled.
- Maintains an immutable, concurrency-safe part-number ledger.
- Imports the legacy `trim_profiles` CSV without publishing records automatically.
- Keeps shop records, verification fields, and internal notes out of public ACF REST data.

Onshape remains the source of truth for profile geometry. WordPress owns part-number assignment and the curated marketing/specification catalog.

## Requirements

- Current supported WordPress release
- PHP 8.1 or newer
- Advanced Custom Fields, free or Pro (current supported release)
- MySQL or MariaDB with `GET_LOCK()` support

## Part-number rules

Part numbers use this format:

```text
{CATEGORY_CODE}-{PROFILE_TYPE_CODE}-{SEQUENCE}
```

Example: `1-CM-1000`.

- Codes and complete part numbers are always uppercase.
- Each category/profile-type pair has an independent sequence.
- New assignment uses the greatest reserved sequence plus one, with 1000 as the initial value.
- Gaps are preserved and never filled.
- Deleting a profile marks its registry entry void; the number is never reused.
- Imported numbers are validated against the CSV category and subcategory columns. A sequence-column mismatch preserves the assigned part number and automatically flags the record for verification.

## Installation

1. Install and activate Advanced Custom Fields.
2. Copy `crmm-trim-catalog` to `wp-content/plugins/` or install a ZIP of the folder.
3. Activate **CRMM Trim Catalog**.
4. Open **Trim Profiles → Import Legacy CSV** for a legacy data import.

Run the first import without downloading images. The importer retains each legacy image URL internally so media migration can be handled separately or retried later.

## Migration behavior

- All new imported profiles are drafts.
- Re-running an import updates records using their legacy database ID.
- Completely blank values are not stored.
- `TRUE`, `FALSE`, `yes`, `no`, `Y`, and `N` are normalized.
- Part numbers are normalized to uppercase.
- Existing number gaps remain reserved because allocation always uses `MAX(sequence) + 1`.
- The abandoned binder fields are intentionally not imported.

## Internal fields

The following fields are restricted to WordPress administrators and excluded from public ACF REST data:

- Old CRMM number
- Original/source part number
- Template status
- Knife status and location
- Board dimensions
- Internal notes
- Verification status, user, date, and discrepancy notes

## Current limitations

- The legacy GitHub repository contains only a Laravel scaffold. No catalog model, controller, migration, or historical numbering implementation was committed, so the numbering behavior is reconstructed from the exported database and confirmed business rules.
- Public catalog templates and filtering UI belong to the theme/site implementation and are not included in this data plugin.
