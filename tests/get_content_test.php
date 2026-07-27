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

    public function test_titles_from_federated_sites_are_escaped(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->seed_trial((int) $user->id, '<script>alert(1)</script>Evil');

        $html = $this->render();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
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
