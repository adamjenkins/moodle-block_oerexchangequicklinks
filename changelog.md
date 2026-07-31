# Changelog

All notable changes to this project are documented in this file, in
[Keep a Changelog](https://keepachangelog.com/) format.

## [1.0.2] - 2026-07-31

### Fixed

- The visible resource title rendered multilang markup as literal text even
  with the site's multilang filter enabled. Now uses `format_string()` with a
  system context, matching `block_oerexchangeshares`'s already-correct
  pattern.
- The Try it / Download / thumbnail `aria-label`s still interpolated the raw,
  unfiltered title after the fix above, so a screen reader announced literal
  multilang markup. All three labels now use the filtered title, decoded once
  so `html_writer`'s own attribute escaping is not doubled.

## [1.0.1] - 2026-07-29

### Changed

- The camp release-publishing workflow now uses the registry's current
  tokenless template (OIDC trusted publishing, camp-tools v0.2.35). The
  previous template pinned camp-tools v0.2.25, whose index-entry schema
  predates the registry's `source-repo-id` field, so publication of v1.0.0
  could not succeed. No change to the plugin itself.

## [1.0.0] - 2026-07-29

First stable release. `$plugin->maturity` is now `MATURITY_STABLE`.

No functional change since 0.1.2 — the whole OER Exchange suite moves to 1.0.0
together, so a site never has a stable plugin depending on an alpha one.

## [0.1.2] - 2026-07-27

### Added

- Each listed resource leads with its cover-image thumbnail
  (`local_oerexchange\local\cover_image::listitem()`), with a neutral
  equally sized panel where a resource has no cover so rows stay aligned.
- The thumbnail links to the resource's detail page — the block previously
  offered Try it and Download only, with no route to the page itself. It
  carries its own `aria-label` (new `viewresourcefor` string, EN + JA) since
  the title beside it is not a link.

### Changed

- Rows are laid out thumbnail-left, text-right.
- Thumbnail URLs for the whole block are resolved in one batch query.

## [0.1.1] - 2026-07-27

### Fixed

- "Try it" links now mirror `sandbox_launch.php`'s gates: none is rendered
  when the sandbox is disabled/unconfigured, when the author opted the
  resource out of trials, or for data resources. Each of those cases used
  to render a link that could only produce an error page.
- A recently tried resource with no ready version no longer consumes one
  of the five slots — eligibility is filtered before the limit applies.
- `$plugin->requires` corrected from Moodle 4.5 to 5.0 (2025041400),
  matching `$plugin->supported`, composer.json and the CI matrix.

### Changed

- Try it / Download links carry per-resource `aria-label`s (new
  `tryitfor`/`downloadfor` strings, EN+JA) so screen-reader link lists can
  tell them apart, and the Try it link gains `rel="noopener"`.
- Dropped the unjustified `RISK_SPAM | RISK_XSS` bitmask on `addinstance`
  (no instance config, no user-authored HTML) and the redundant
  `'site' => false` in `applicable_formats()`.

### Added

- PHPUnit coverage of the rendering path (`get_content()`): federated
  title escaping, sandbox-off, author opt-out and data-resource gating;
  plus content_builder tests for the slot-filling and `trydisabled`
  passthrough. A second Behat scenario adds the block as an ordinary user.

## [0.1.0] - 2026-07-18

### Added

- Dashboard block (`block_oerexchangequicklinks`) listing Try it / Download
  shortcuts for resources the current user recently launched a sandbox
  trial for.
- `classes/local/content_builder.php`: scopes trials to the current user,
  dedups by resource keeping the most recent trial time, excludes
  resources no longer `published`, and resolves Download to the current
  latest `ready` version rather than the trial's original version id.
- `$plugin->dependencies` on `local_oerexchange` — the real Moodle
  mechanism enforcing the parent plugin's presence, since block types have
  no subplugin relationship.
- Standard block capabilities (`addinstance`, `myaddinstance`).
- Null privacy provider — the block stores no data of its own.
