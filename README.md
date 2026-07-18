# block_oerexchangequicklinks

A Dashboard block for the **OER Exchange** platform — an open-educational-
resources sharing platform built on Moodle. Shows Try it / Download
shortcuts for resources you've recently launched a sandbox trial for, so
you don't have to go back through the full catalogue page to reach them
again.

Requires the companion catalogue plugin
[`local_oerexchange`](https://github.com/adamjenkins/moodle-local_oerexchange),
which must already be installed on the same site — this block only reads
its data and links to its pages.

## What it does

- Lists a handful of resources you've most recently tried in the sandbox,
  most recent first, with each resource shown once even if you've tried it
  more than once.
- **Try it** relaunches the sandbox trial for that resource.
- **Download** links to the current latest available version of the
  resource — not necessarily the version you originally tried, if a newer
  one has since been published.
- Resources that are no longer published (hidden or removed since your
  trial) are left off the list.
- Add it to your Dashboard from the block drawer while editing is turned
  on.

## Requirements

- Moodle 5.0–5.2 (`$plugin->supported`).
- `local_oerexchange` installed on the same site.
- PHP as required by the target Moodle version.

## Installation

```bash
git clone https://github.com/adamjenkins/moodle-block_oerexchangequicklinks.git blocks/oerexchangequicklinks
php admin/cli/upgrade.php
```

## License

GPL-3.0-or-later, see [LICENSE](LICENSE).
