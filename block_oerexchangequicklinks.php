<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Class definition for the OER Exchange: quick links block.
 *
 * @package    block_oerexchangequicklinks
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_oerexchangequicklinks\local\content_builder;

/**
 * Shows Try it / Download shortcuts for resources the current user has
 * recently launched a sandbox trial for, skipping a return trip through
 * resource.php.
 *
 * @package    block_oerexchangequicklinks
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_oerexchangequicklinks extends block_base {
    /**
     * Initialize class member variables.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_oerexchangequicklinks');
    }

    /**
     * Locations where the block can be displayed.
     *
     * @return array
     */
    public function applicable_formats() {
        return ['my' => true];
    }

    /**
     * Returns the contents.
     *
     * @return stdClass contents of block
     */
    public function get_content() {
        global $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->footer = '';

        if (!isloggedin() || isguestuser()) {
            $this->content->text = '';
            return $this->content;
        }

        $rows = content_builder::get_recent_trials_for_user((int) $USER->id, 5);

        if (empty($rows)) {
            $this->content->text = html_writer::tag(
                'p',
                get_string('noquicklinks', 'block_oerexchangequicklinks'),
                ['class' => 'small text-muted']
            );
            return $this->content;
        }

        // Mirror sandbox_launch.php's own gates: the whole sandbox may be
        // switched off (or unconfigured), and an author can opt a resource
        // out — a Try it link in either case is a guaranteed error page.
        $sandboxok = (bool) get_config('local_oerexchange', 'sandboxenabled')
            && get_config('local_oerexchange', 'sandboxbaseurl');

        // One query for every thumbnail on show, not one per row.
        $coverurls = \local_oerexchange\local\cover_image::urls_for(array_map(fn($r) => $r->resourceid, $rows));

        $items = '';
        foreach ($rows as $row) {
            $dlurl = new moodle_url('/local/oerexchange/download.php', ['id' => $row->versionid]);
            $resourceurl = new moodle_url('/local/oerexchange/resource.php', ['id' => $row->resourceid]);

            // Filtered once per row: $formattedtitle is escaped HTML for the
            // visible title (placed directly as tag content below).
            // $plaintitle decodes that back to plain text for use inside
            // aria-label attributes — html_writer escapes attribute values
            // itself, so feeding it already-escaped output would double
            // escape (e.g. "&" -> "&amp;amp;") and be read aloud wrongly.
            $formattedtitle = format_string($row->title, true, ['context' => \core\context\system::instance()]);
            $plaintitle = html_entity_decode($formattedtitle, ENT_QUOTES, 'UTF-8');

            $links = '';
            if ($sandboxok && empty($row->trydisabled) && $row->type !== 'data') {
                $tryurl = new moodle_url('/local/oerexchange/sandbox_launch.php', ['id' => $row->resourceid]);
                $links .= html_writer::link(
                    $tryurl,
                    get_string('tryit', 'block_oerexchangequicklinks'),
                    [
                        'class' => 'btn btn-success btn-sm me-2',
                        'target' => '_blank',
                        'rel' => 'noopener',
                        // Distinct accessible names: five bare "Try it"
                        // links are indistinguishable in a screen-reader
                        // links list (WCAG 2.4.4).
                        'aria-label' => get_string('tryitfor', 'block_oerexchangequicklinks', $plaintitle),
                    ]
                );
            }
            $links .= html_writer::link(
                $dlurl,
                get_string('download', 'block_oerexchangequicklinks'),
                [
                    'class' => 'btn btn-outline-primary btn-sm',
                    'aria-label' => get_string('downloadfor', 'block_oerexchangequicklinks', $plaintitle),
                ]
            );

            // The title here has never been a link — the Try it / Download
            // buttons are the point of this block. The thumbnail therefore
            // gets its own link to the resource page, which is the one thing
            // the block was missing a route to, and is announced by the title
            // it sits beside rather than being hidden like the other blocks'.
            $thumb = html_writer::link(
                $resourceurl,
                \local_oerexchange\local\cover_image::listitem($coverurls[$row->resourceid] ?? null),
                [
                    'class' => 'flex-shrink-0',
                    'aria-label' => get_string('viewresourcefor', 'block_oerexchangequicklinks', $plaintitle),
                ]
            );

            $items .= html_writer::tag(
                'li',
                $thumb . html_writer::div(
                    html_writer::tag(
                        'div',
                        $formattedtitle,
                        ['class' => 'oerexchangequicklinks-title mb-1']
                    ) .
                    html_writer::tag('div', $links, ['class' => 'oerexchangequicklinks-actions']),
                    'oerexchangequicklinks-text flex-grow-1',
                    ['style' => 'min-width:0;']
                ),
                ['class' => 'oerexchangequicklinks-item d-flex gap-2 align-items-start mb-3']
            );
        }

        $this->content->text = html_writer::tag('ul', $items, ['class' => 'list-unstyled oerexchangequicklinks-list mb-0']);

        return $this->content;
    }
}
