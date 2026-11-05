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

The native **Trim Profiles** list includes profile/render thumbnails, permanent part number, classification, dimensions, shop status, approval, verification, and readiness. It also provides filters and saved queues for unnumbered profiles, missing images, verification work, discrepancies, and profiles that appear ready to publish. Legacy style terms remain stored for compatibility but are no longer exposed as an editing control.

Admin search includes titles, current and legacy part numbers, category, profile type, and style. Part-number sorting uses the stored numeric sequence instead of lexical string order.

Publishing is blocked when a profile does not have a valid registry-backed number and matching classification, marketing approval, verification, catalog dimensions, and a primary profile image.

The editor's **Publishing requirements** panel shows all five saved requirements, with the exact saved verification status and an action for each unmet requirement. The profile list displays only the unmet requirement names beneath its readiness badge; detailed actions stay in the editor. Optional files and knife availability are explicitly identified as nonblocking.

Use **All readiness states**, **Ready to publish**, or **Has blockers** to filter the list. The filter and Ready to Publish queue evaluate the same five requirements as the badge, before pagination; search, category, status, and other selected filters still apply. Profile/render thumbnails contain the uncropped source image in a fixed box so the entire profile remains identifiable.

Correct the indicated fields and **Save draft**, then **Publish**. Requirements use saved values; changing a dropdown alone does not update the publishing decision. The panel marks unsaved field edits and refreshes after a successful block-editor save, including the metabox save phase. **Refresh saved checks** is available if needed. A blocked block-editor publish request returns the actual reasons as a visible error instead of silently reverting to draft. These interface changes do not alter approval/verification criteria or any live review records.

## Part-number notifications

Open **Trim Profiles → Settings** to configure notification recipients and the subject prefix. Notifications are emitted only after a new WordPress-assigned number has committed successfully. Repeated assignment requests and legacy imports do not send them.

If WordPress mail delivery fails, the permanent number remains assigned and the assigning user receives an admin warning. Use the settings screen to send a test notification after configuring the site's mail transport.

## Classification integrity

Category and profile type are locked after permanent number assignment. Server-side term enforcement restores the registry-backed classification if a REST or programmatic update attempts to change it. Selecting both fields displays an unreserved expected-number preview in the editor; the first valid save reserves the authoritative number through the locked registry transaction.

Existing numbered profiles always display their permanent registry-backed number. Next-number previews are only for unnumbered profiles and never populate the Part Number or title. The preview endpoint returns an existing assignment before considering requested classification. Older requests are cancelled and their callbacks ignored when a selection changes or an assignment becomes available.

Locked classification controls remain disabled. Before ACF validates the submitted values, the plugin checks the saved taxonomy terms against the permanent number, then supplies those authoritative IDs for omitted/empty controls. Nonempty attempts to change them are rejected. Required and category/type compatibility checks remain active for unnumbered profiles, including omitted fields. Existing update and taxonomy integrity protections remain in place.

### Regression tests and staging checks

Run `php tests/profile-identity.php` and `node --test tests/admin-classification.test.cjs` from the repository root. These dependency-free unit suites exercise production PHP/JavaScript with WordPress/ACF/MySQL and jQuery/editor test doubles. CI runs the PHP tests on PHP 8.1–8.4 and JavaScript tests on Node 22.

Before deployment, verify on a disposable WordPress/ACF staging site with both the block and classic editors:

- Open an existing numbered profile: editor title, Part Number, and registry panel agree; no next-number request is made. Save an unrelated edit and reload; number, slug, and classification remain unchanged.
- Save valid locked classifications, including disabled controls with empty hidden inputs. Invalid saved terms and tampered requests must report errors without reclassifying the profile.
- For an unnumbered profile, missing or incompatible classifications fail validation. A valid selection shows an unreserved preview, and its first valid save assigns one permanent number. Subsequent saves preserve it.
- Rapidly change selections and save while a preview is pending; old responses cannot change the latest preview or permanent number.

### Repeated attachment saves

Some imported profiles contain duplicate rows for a single attachment field. WordPress's REST no-change check handles only one row; updating multiple identical rows to the same value can affect zero database rows and return `rest_meta_database_error`, interrupting the editor save before ACF metabox edits persist.

During REST requests, the plugin acknowledges an unchanged attachment update only when the user can edit that metadata, all existing rows are identical to the incoming sanitized value, and there are at least two rows. This covers profile images, renders, isometric views and downloadable attachments. It does not delete duplicate data. Real changes, deletions, conflicting values, conditional updates, other filters and non-attachment fields retain their normal save behavior and errors.

Run `php tests/attachment-meta.php` and `NON_REST_TEST=1 php tests/attachment-meta.php`. Set `WP_REST_META_FIELDS_SOURCE` to an official WordPress `class-wp-rest-meta-fields.php` file to additionally reproduce the original failure and test the safeguard through WordPress's actual REST update method with a database double. Full WordPress/ACF integration still requires an editor save, a second unchanged-image save, and a reload confirming the intended approval/verification edits persisted. No local full-site test is included.

### External drawing-review identity contract

This repository stores verification fields and audit events; it does not contain the drawing-review automation. That external process must identify each record by **WordPress post ID plus permanent registry number**, not by an editor title, next-number preview, slug, or list position. Before comparison and immediately before writing results, re-read the assignment and require the source drawing identity to match. Stop and flag mismatches or changed assignments for review; never carry a result to a different profile.

For the reported case, post 309's permanent identity is `4-CR-1009`, with the review referencing `4-CR-1009.pdf`. An erroneous `4-CR-1015` preview is not grounds for changing registry assignments, slugs, review notes, or verification decisions. This fix includes no live-data migration or review-record changes.

The expected number is displayed as the post title while editing. After allocation, the committed permanent number becomes the authoritative post title, and later title changes are automatically restored to that number.

## Current limitations

- The legacy GitHub repository contains only a Laravel scaffold. No catalog model, controller, migration, or historical numbering implementation was committed, so the numbering behavior is reconstructed from the exported database and confirmed business rules.
- Public catalog templates and filtering UI belong to the theme/site implementation and are not included in this data plugin.
- Readiness queues use indexed post-meta conditions for efficient filtering; the readiness badge remains the authoritative per-record calculation.

