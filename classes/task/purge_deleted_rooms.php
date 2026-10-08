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

namespace mod_clickmeeting\task;

use mod_clickmeeting\event\room_deleted;

/**
 * Removes rooms whose Moodle activity is gone and whose grace period has expired.
 *
 * @package    mod_clickmeeting
 * @copyright  2026 Clickmeeting
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purge_deleted_rooms extends \core\task\scheduled_task {
    /**
     * Returns the localised task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task:purgedeletedrooms', 'clickmeeting');
    }

    /**
     * Deletes every room that is past its grace period and no longer referenced.
     */
    public function execute() {
        global $DB, $CFG, $clickmeetingowner;

        require_once($CFG->dirroot . '/mod/clickmeeting/lib.php');

        $cutoff = time() - clickmeeting_get_grace_period();
        $pending = $DB->get_records_select(
            'clickmeeting_pending_deletions',
            'timescheduled <= :cutoff',
            ['cutoff' => $cutoff]
        );

        foreach ($pending as $record) {
            // Another activity - a course copy, an import, an activity restored from the recycle
            // bin - points at this room again, so deleting it would take the meeting away from them.
            if ($DB->record_exists('clickmeeting_conferences', ['conference_id' => $record->conference_id])) {
                mtrace("Room {$record->conference_id} is in use again, cancelling its deletion.");
                $DB->delete_records('clickmeeting_pending_deletions', ['id' => $record->id]);
                continue;
            }

            // The room belongs to whoever created it, so it has to be deleted with their API key.
            $clickmeetingowner = $record->user_id;
            $apiresult = clickmeeting_delete_conference($record->conference_id);

            if ('"200 OK"' !== $apiresult && '"404 Not Found"' !== $apiresult) {
                // Leave the record alone so the next run retries it.
                mtrace("Could not delete room {$record->conference_id}, will retry: {$apiresult}");
                continue;
            }

            $DB->delete_records('clickmeeting_pending_deletions', ['id' => $record->id]);

            if ('"200 OK"' === $apiresult) {
                room_deleted::create([
                    'context' => \context_system::instance(),
                    'other' => ['conferenceid' => $record->conference_id],
                ])->trigger();
                mtrace("Deleted room {$record->conference_id}.");
            } else {
                mtrace("Room {$record->conference_id} was already gone from ClickMeeting.");
            }
        }
    }
}
