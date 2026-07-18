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

namespace block_oerexchangequicklinks\local;

/**
 * Data access for the "quick links" block: no HTML, plain data only, so it
 * can be unit-tested independently of get_content().
 *
 * @package    block_oerexchangequicklinks
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content_builder {
    /**
     * Fetch the resources this user has most recently launched a sandbox
     * trial for, deduplicated by resource (most recent trial time wins),
     * filtered to still-published resources, each paired with its current
     * latest ready version id (not necessarily the version id the trial was
     * originally launched against, if a newer version has since been
     * uploaded).
     *
     * @param int $userid Moodle userid the trials are scoped to.
     * @param int $limit Maximum number of resources to return.
     * @return array list of stdClass rows: resourceid, title, type, versionid
     *     (the current latest ready version id for that resource).
     */
    public static function get_recent_trials_for_user(int $userid, int $limit = 5): array {
        global $DB;

        // One row per resource: the most recent trial timestamp this user
        // launched for it (a user may have tried the same resource several
        // times).
        $trialtimes = $DB->get_records_sql(
            "SELECT resourceid, MAX(timecreated) AS lasttrialtime
               FROM {local_oerexchange_trials}
              WHERE userid = :userid
           GROUP BY resourceid",
            ['userid' => $userid]
        );

        if (!$trialtimes) {
            return [];
        }

        // Drop resources that are no longer published (hidden/removed) —
        // don't show stale links as if they still work.
        [$insql, $inparams] = $DB->get_in_or_equal(array_keys($trialtimes), SQL_PARAMS_NAMED);
        $resources = $DB->get_records_select(
            'local_oerexchange_resources',
            "id $insql AND status = :status",
            $inparams + ['status' => 'published'],
            '',
            'id, title, type'
        );

        if (!$resources) {
            return [];
        }

        // Most-recently-tried resource first, capped to $limit.
        $order = [];
        foreach (array_keys($resources) as $resourceid) {
            $order[$resourceid] = $trialtimes[$resourceid]->lasttrialtime;
        }
        arsort($order);
        $topids = array_slice(array_keys($order), 0, $limit);

        // The current latest *ready* version per resource — mirrors the
        // lookup resource.php uses for its own Try it / Download buttons,
        // not the versionid stored on the (possibly stale) trial row.
        [$versql, $verparams] = $DB->get_in_or_equal($topids, SQL_PARAMS_NAMED);
        $versions = $DB->get_records_select(
            'local_oerexchange_versions',
            "resourceid $versql AND status = :vstatus",
            $verparams + ['vstatus' => 'ready'],
            'resourceid ASC, versionnumber DESC',
            'id, resourceid'
        );
        $latestversionid = [];
        foreach ($versions as $version) {
            if (!isset($latestversionid[$version->resourceid])) {
                $latestversionid[$version->resourceid] = $version->id;
            }
        }

        $rows = [];
        foreach ($topids as $resourceid) {
            if (!isset($latestversionid[$resourceid])) {
                // No ready version currently available for this resource —
                // nothing usable to link to, skip it.
                continue;
            }
            $resource = $resources[$resourceid];
            $rows[] = (object) [
                'resourceid' => (int) $resourceid,
                'title' => $resource->title,
                'type' => $resource->type,
                'versionid' => (int) $latestversionid[$resourceid],
            ];
        }

        return $rows;
    }
}
