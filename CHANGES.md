# Release notes — 1.0.2

The visible title in this block's Try it / Download shortcut list previously
rendered any multilang markup as literal text, even with the site's multilang
filter enabled — a live, user-reported bug on the Exchange. It now renders
through the site's filters, matching `block_oerexchangeshares`'s
already-correct pattern.

Fixing the visible title left the Try it, Download and thumbnail
accessibility labels (`aria-label`) still carrying the raw, unfiltered title,
so a screen reader would read out the literal multilang markup even though
the visible text was already correct. Those three labels are now filtered
too, and a title containing "&" is escaped exactly once in each.

No database or capability changes. No action is required after upgrading.
