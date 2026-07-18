# Changelog

All notable changes to this project are documented in this file, in
[Keep a Changelog](https://keepachangelog.com/) format.

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
