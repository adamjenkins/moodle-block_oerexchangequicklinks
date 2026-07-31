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

namespace block_oerexchangequicklinks;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the block's rendering: escaping of federated titles, and the
 * Try it link honouring the sandbox configuration and the author's opt-out.
 *
 * @package    block_oerexchangequicklinks
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\block_oerexchangequicklinks::class)]
final class get_content_test extends \advanced_testcase {
    /**
     * Seed one tried resource with a ready version for the given user.
     *
     * @param int $userid
     * @param string $title
     * @param int $trydisabled
     * @param string $type
     * @return int resourceid
     */
    protected function seed_trial(int $userid, string $title, int $trydisabled = 0, string $type = 'course'): int {
        global $DB;

        $siteuser = $this->getDataGenerator()->create_user(['auth' => 'manual']);
        $siteid = $DB->insert_record('local_oerexchange_sites', (object) [
            'name' => 'Test site', 'url' => 'https://example.com', 'contact' => 'x@example.com',
            'serviceuserid' => $siteuser->id, 'status' => 'active',
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $resourceid = $DB->insert_record('local_oerexchange_resources', (object) [
            'type' => $type, 'title' => $title, 'summary' => '', 'language' => '', 'tags' => '',
            'licenseshortname' => 'cc-4.0', 'creatorid' => $userid, 'siteid' => $siteid,
            'status' => 'published', 'downloadcount' => 0, 'importcount' => 0,
            'trydisabled' => $trydisabled, 'timeshared' => time(), 'timemodified' => time(),
        ]);
        $versionid = $DB->insert_record('local_oerexchange_versions', (object) [
            'resourceid' => $resourceid, 'versionnumber' => 1, 'itemid' => 0,
            'filename' => 'v1.mbz', 'filesize' => 1, 'status' => 'ready', 'timecreated' => time(),
        ]);
        $DB->insert_record('local_oerexchange_trials', (object) [
            'resourceid' => $resourceid, 'versionid' => $versionid, 'userid' => $userid,
            'moodlebranch' => 'MOODLE_502_STABLE', 'timecreated' => time(),
        ]);

        return $resourceid;
    }

    /**
     * Render the block for the current user.
     *
     * @return string the rendered block text
     */
    protected function render(): string {
        $block = \block_instance('oerexchangequicklinks');
        return (string) $block->get_content()->text;
    }

    /**
     * Switch the parent plugin's sandbox on for the Try it gate tests.
     */
    protected function enable_sandbox(): void {
        set_config('sandboxenabled', 1, 'local_oerexchange');
        set_config('sandboxbaseurl', 'https://example.com/try/', 'local_oerexchange');
    }

    /**
     * format_string() (used for the visible title, and — via $plaintitle —
     * now for the three aria-labels too) strips unfiltered markup outright
     * rather than escaping it, so a raw `<script>` title never survives in
     * the output at all, executable or otherwise. (Before the aria-label
     * fix, this test's old assertion of literal "&lt;script&gt;" in $html
     * only ever passed because the aria-labels still interpolated the RAW
     * title, and html_writer's own attribute-escaping produced that exact
     * substring — an accidental pass riding on the very bug fixed above.)
     */
    public function test_titles_from_federated_sites_are_escaped(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->seed_trial((int) $user->id, '<script>alert(1)</script>Evil');

        $html = $this->render();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('alert(1)Evil', $html);
    }

    public function test_try_it_appears_only_when_the_sandbox_is_configured_on(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->seed_trial((int) $user->id, 'A resource');

        // Sandbox off (default): a Try it link would be a guaranteed error.
        $html = $this->render();
        $this->assertStringNotContainsString('sandbox_launch.php', $html);
        $this->assertStringContainsString('download.php', $html);
    }

    public function test_try_it_honours_the_author_opt_out(): void {
        $this->resetAfterTest();
        $this->enable_sandbox();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->seed_trial((int) $user->id, 'Opted out', 1);
        $this->seed_trial((int) $user->id, 'Available');

        $html = $this->render();

        // Exactly one Try it link: the opted-out resource gets none, the
        // other does. Download stays available for both.
        $this->assertSame(1, substr_count($html, 'sandbox_launch.php'));
        $this->assertSame(2, substr_count($html, 'download.php'));
    }

    /**
     * Regression test: the visible title (:152, $formattedtitle) used to
     * render as raw `<span lang="en" class="multilang">...` markup instead
     * of collapsing to one language. Enables the filter trio locally rather
     * than relying on site config, and pins the double-escape guard (a
     * title containing `&` must be escaped exactly once).
     *
     * Scoped to the title <div> specifically, not the whole block markup:
     * the aria-label assertions live in
     * test_aria_labels_render_through_multilang_and_escape_ampersand_once_each()
     * below.
     */
    public function test_titles_render_through_multilang_and_escape_ampersand_once(): void {
        $this->resetAfterTest();
        filter_set_global_state('multilang', TEXTFILTER_ON);
        set_config('filterall', 1);
        set_config('stringfilters', 'multilang');

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->seed_trial(
            (int) $user->id,
            '<span lang="en" class="multilang">Reading &amp; Writing</span>'
                . '<span lang="ja" class="multilang">読み書き</span>'
        );

        $html = $this->render();

        $this->assertMatchesRegularExpression(
            '#<div class="oerexchangequicklinks-title mb-1">([^<]*)</div>#',
            $html
        );
        preg_match('#<div class="oerexchangequicklinks-title mb-1">([^<]*)</div>#', $html, $matches);
        $titledivcontent = $matches[1];

        $this->assertSame('Reading &amp; Writing', $titledivcontent);
        $this->assertStringNotContainsString('読み書き', $titledivcontent);
        $this->assertStringNotContainsString('multilang', $titledivcontent);
        $this->assertSame(1, substr_count($titledivcontent, '&amp;'));
    }

    /**
     * Regression test: the three aria-label attributes (tryitfor/:120,
     * downloadfor/:129, viewresourcefor/:143) used to interpolate the raw
     * $row->title, so a screen reader read out the literal
     * `<span lang="en" class="multilang">...` markup. They must instead
     * carry the multilang-filtered, plain-text title — decoded back out of
     * format_string()'s escaped HTML output before being handed to
     * html_writer, which does its own (single) attribute escaping.
     */
    public function test_aria_labels_render_through_multilang_and_escape_ampersand_once_each(): void {
        $this->resetAfterTest();
        $this->enable_sandbox();
        filter_set_global_state('multilang', TEXTFILTER_ON);
        set_config('filterall', 1);
        set_config('stringfilters', 'multilang');

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->seed_trial(
            (int) $user->id,
            '<span lang="en" class="multilang">Reading &amp; Writing</span>'
                . '<span lang="ja" class="multilang">読み書き</span>'
        );

        $html = $this->render();

        preg_match_all('/aria-label="([^"]*)"/', $html, $matches);
        $arialabels = $matches[1];

        // One aria-label each for Try it, Download, and the thumbnail link.
        $this->assertCount(3, $arialabels);
        foreach ($arialabels as $label) {
            $this->assertStringContainsString('Reading &amp; Writing', $label);
            $this->assertStringNotContainsString('読み書き', $label);
            $this->assertStringNotContainsString('<span', $label);
            $this->assertSame(1, substr_count($label, '&amp;'));
        }
    }

    public function test_data_resources_never_offer_try_it(): void {
        $this->resetAfterTest();
        $this->enable_sandbox();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->seed_trial((int) $user->id, 'A glossary export', 0, 'data');

        $html = $this->render();

        $this->assertStringNotContainsString('sandbox_launch.php', $html);
        $this->assertStringContainsString('download.php', $html);
    }
}
