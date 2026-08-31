# CRMM Trim Catalog

WordPress plugin for the Cache River Mill & MetalWorks trim profile registry and public catalog.

## Responsibilities

- Registers the `crmm_trim_profile` custom post type.
- Registers fixed category and profile-type taxonomies from the legacy catalog.
- Registers ACF field groups in PHP so the schema is version controlled.
- Maintains an immutable, concurrency-safe part-number ledger.
- Imports the legacy `trim_profiles` CSV without publishing records automatically.
- Keeps shop records, verification fields, and internal notes out of public ACF REST data.
- Provides a catalog-manager role, readiness checks, admin filters, and configurable part-number notifications.

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

On upgrade to 0.3.0, administrators receive the new catalog capabilities automatically. A **Trim Catalog Manager** role is also created for day-to-day profile management without granting the broad `manage_options` capability.

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

## Profile Manager

The native **Trim Profiles** list includes profile/render thumbnails, permanent part number, classification, dimensions, shop status, approval, verification, and readiness. It also provides filters and saved queues for unnumbered profiles, missing images, verification work, discrepancies, and profiles that appear ready to publish.

Admin search includes titles, current and legacy part numbers, category, profile type, and style. Part-number sorting uses the stored numeric sequence instead of lexical string order.

Publishing is blocked when a profile does not have a valid registry-backed number and matching classification, marketing approval, verification, catalog dimensions, and a primary profile image.

## Part-number notifications

Open **Trim Profiles → Settings** to configure notification recipients and the subject prefix. Notifications are emitted only after a new WordPress-assigned number has committed successfully. Repeated assignment requests and legacy imports do not send them.

If WordPress mail delivery fails, the permanent number remains assigned and the assigning user receives an admin warning. Use the settings screen to send a test notification after configuring the site's mail transport.

## Classification integrity

Category and profile type are locked after permanent number assignment. Server-side term enforcement restores the registry-backed classification if a REST or programmatic update attempts to change it. The editor may display an unreserved expected-number preview, but only the locked registry transaction reserves a number.

## Current limitations

- The legacy GitHub repository contains only a Laravel scaffold. No catalog model, controller, migration, or historical numbering implementation was committed, so the numbering behavior is reconstructed from the exported database and confirmed business rules.
- Public catalog templates and filtering UI belong to the theme/site implementation and are not included in this data plugin.
- Readiness queues use indexed post-meta conditions for efficient filtering; the readiness badge remains the authoritative per-record calculation.
