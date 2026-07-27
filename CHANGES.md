# Release notes — 0.1.1

Review-round hardening. The "Try it" button now honours the Exchange's own
rules instead of being offered unconditionally: it disappears when the
sandbox is switched off or unconfigured, when the resource's author has
opted out of trials, and for data resources (which can never be tried) —
previously each of those cases was a guaranteed error page. A recently
tried resource with no usable version no longer wastes one of the five
slots. Every Try it / Download link now carries a distinct accessible name
for screen readers, and the installation floor is corrected to Moodle 5.0
(the plugin was never tested on 4.5, which the old value permitted).
