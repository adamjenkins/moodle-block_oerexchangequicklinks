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

use block_oerexchangequicklinks\local\content_builder;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for content_builder: scoping by user, dedup by resource, status
 * filtering, and picking the current latest ready version rather than the
 * trial's original versionid.
 *
 * @package    block_oerexchangequicklinks
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(content_builder::class)]
final class content_builder_test extends \advanced_testcase {
    /**
     * Insert a resource row via the same field set local_oerexchange's own
     * tests use.
     *
     * @param int $creatorid
     * @param int $siteid
     * @param string $status
     * @param string $title
     * @return int the new resourceid
     */
    protected function create_resource(int $creatorid, int $siteid, string $status, string $title): int {
        global $DB;

        return $DB->insert_record('local_oerexchange_resources', (object) [
            'type' => 'course', 'title' => $title, 'summary' => '', 'language' => '', 'tags' => '',
            'licenseshortname' => 'cc-4.0', 'activitytype' => null, 'courseformat' => null,
            'creatorid' => $creatorid, 'siteid' => $siteid, 'status' => $status,
            'downloadcount' => 0, 'importcount' => 0, 'forkedfromid' => null,
            'timeshared' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * Insert a version row.
     *
     * @param int $resourceid
     * @param int $versionnumber
     * @param string $status
     * @return int the new versionid
     */
    protected function create_version(int $resourceid, int $versionnumber, string $status = 'ready'): int {
        global $DB;

        return $DB->insert_record('local_oerexchange_versions', (object) [
            'resourceid' => $resourceid, 'versionnumber' => $versionnumber, 'itemid' => 0,
            'filename' => "v{$versionnumber}.mbz", 'filesize' => 1, 'status' => $status, 'timecreated' => time(),
        ]);
    }

    /**
     * Insert a trial row.
     *
     * @param int $resourceid
     * @param int $versionid
     * @param int|null $userid
     * @param int $timecreated
     * @return int the new trial id
     */
    protected function create_trial(int $resourceid, int $versionid, ?int $userid, int $timecreated): int {
        global $DB;

        return $DB->insert_record('local_oerexchange_trials', (object) [
            'resourceid' => $resourceid, 'versionid' => $versionid, 'userid' => $userid,
            'moodlebranch' => 'MOODLE_502_STABLE', 'timecreated' => $timecreated,
        ]);
    }

    /**
     * Create a minimal registered site row (matches
     * local_oerexchange's own test fixtures — resources have a NOTNULL
     * siteid).
     *
     * @return int the new siteid
     */
    protected function create_site(): int {
        global $DB;

        $siteuser = $this->getDataGenerator()->create_user(['auth' => 'manual']);
        return $DB->insert_record('local_oerexchange_sites', (object) [
            'name' => 'Test site', 'url' => 'https://example.com', 'contact' => 'x@example.com',
            'serviceuserid' => $siteuser->id, 'status' => 'active',
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    public function test_returns_empty_when_user_has_no_trials(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();

        $this->assertSame([], content_builder::get_recent_trials_for_user((int) $user->id));
    }

    public function test_scopes_results_to_the_given_user(): void {
        $this->resetAfterTest();

        $creator = $this->getDataGenerator()->create_user();
        $siteid = $this->create_site();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $resourceid = $this->create_resource($creator->id, $siteid, 'published', 'Only user1 tried this');
        $versionid = $this->create_version($resourceid, 1);
        $this->create_trial($resourceid, $versionid, $user1->id, time());

        $this->assertCount(1, content_builder::get_recent_trials_for_user((int) $user1->id));
        $this->assertSame([], content_builder::get_recent_trials_for_user((int) $user2->id));
    }

    public function test_dedups_by_resource_keeping_most_recent_trial(): void {
        $this->resetAfterTest();

        $creator = $this->getDataGenerator()->create_user();
        $siteid = $this->create_site();
        $user = $this->getDataGenerator()->create_user();

        $resourceid = $this->create_resource($creator->id, $siteid, 'published', 'Tried three times');
        $versionid = $this->create_version($resourceid, 1);

        $this->create_trial($resourceid, $versionid, $user->id, time() - 300);
        $this->create_trial($resourceid, $versionid, $user->id, time() - 200);
        $this->create_trial($resourceid, $versionid, $user->id, time() - 100);

        $rows = content_builder::get_recent_trials_for_user((int) $user->id);

        $this->assertCount(1, $rows);
        $this->assertSame($resourceid, $rows[0]->resourceid);
    }

    public function test_excludes_resources_that_are_no_longer_published(): void {
        $this->resetAfterTest();

        $creator = $this->getDataGenerator()->create_user();
        $siteid = $this->create_site();
        $user = $this->getDataGenerator()->create_user();

        $publishedid = $this->create_resource($creator->id, $siteid, 'published', 'Still live');
        $publishedversionid = $this->create_version($publishedid, 1);
        $this->create_trial($publishedid, $publishedversionid, $user->id, time() - 50);

        foreach (['hidden', 'removed'] as $status) {
            $resourceid = $this->create_resource($creator->id, $siteid, $status, "Status {$status}");
            $versionid = $this->create_version($resourceid, 1);
            $this->create_trial($resourceid, $versionid, $user->id, time());
        }

        $rows = content_builder::get_recent_trials_for_user((int) $user->id);

        $this->assertCount(1, $rows);
        $this->assertSame($publishedid, $rows[0]->resourceid);
    }

    public function test_picks_current_latest_ready_version_not_the_trials_versionid(): void {
        $this->resetAfterTest();

        $creator = $this->getDataGenerator()->create_user();
        $siteid = $this->create_site();
        $user = $this->getDataGenerator()->create_user();

        $resourceid = $this->create_resource($creator->id, $siteid, 'published', 'Newer version since trial');
        $oldversionid = $this->create_version($resourceid, 1);
        $this->create_trial($resourceid, $oldversionid, $user->id, time());

        // A newer, ready version has been uploaded since the trial was launched.
        $newversionid = $this->create_version($resourceid, 2);

        $rows = content_builder::get_recent_trials_for_user((int) $user->id);

        $this->assertCount(1, $rows);
        $this->assertSame($newversionid, $rows[0]->versionid);
        $this->assertNotSame($oldversionid, $rows[0]->versionid);
    }

    public function test_ignores_resources_with_no_ready_version(): void {
        $this->resetAfterTest();

        $creator = $this->getDataGenerator()->create_user();
        $siteid = $this->create_site();
        $user = $this->getDataGenerator()->create_user();

        $resourceid = $this->create_resource($creator->id, $siteid, 'published', 'Version still parsing');
        $versionid = $this->create_version($resourceid, 1, 'parsing');
        $this->create_trial($resourceid, $versionid, $user->id, time());

        $this->assertSame([], content_builder::get_recent_trials_for_user((int) $user->id));
    }

    public function test_respects_limit_and_orders_most_recent_first(): void {
        $this->resetAfterTest();

        $creator = $this->getDataGenerator()->create_user();
        $siteid = $this->create_site();
        $user = $this->getDataGenerator()->create_user();

        $expectedorder = [];
        for ($i = 0; $i < 7; $i++) {
            $resourceid = $this->create_resource($creator->id, $siteid, 'published', "Resource {$i}");
            $versionid = $this->create_version($resourceid, 1);
            $this->create_trial($resourceid, $versionid, $user->id, time() - (10 - $i));
            $expectedorder[] = $resourceid;
        }
        // Most recently tried resource was created last, so expected order is reversed.
        $expectedorder = array_reverse($expectedorder);

        $rows = content_builder::get_recent_trials_for_user((int) $user->id, 5);

        $this->assertCount(5, $rows);
        $actualorder = array_map(fn($row) => $row->resourceid, $rows);
        $this->assertSame(array_slice($expectedorder, 0, 5), $actualorder);
    }
}
