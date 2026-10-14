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

namespace mod_clickmeeting;

defined('MOODLE_INTERNAL') || die();

use restore_path_element;

/**
 * Structure step to restore one choice activity
 *
 * @package mod_clickmeeting
 * @copyright 2024 Clickmeeting
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_activity_structure_step extends \restore_activity_structure_step {
    /**
     * Defines table structure
     */
    protected function define_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('clickmeeting', '/activity/clickmeeting');
        $paths[] = new restore_path_element('clickmeeting_conferences', '/activity/clickmeeting/conferences/conference');

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Inserts data into new table
     *
     * @param object $data
     */
    protected function process_clickmeeting($data) {
        global $DB, $USER;

        $data = (object)$data;
        $oldid = $data->id;
        $data->user_id = $USER->id;
        $data->course = $this->get_courseid();

        $newitemid = $DB->insert_record('clickmeeting', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Creates a brand new ClickMeeting room for the restored activity.
     *
     * The backed up room belongs to the original activity and must not be shared: a copy would
     * let two activities (and two courses) drive the same conference, and deleting one would
     * take the other down with it. Restoring therefore books a fresh room through the API,
     * owned by whoever is running the restore.
     *
     * @param object $data
     */
    protected function process_clickmeeting_conferences($data) {
        global $CFG, $DB, $USER;
        global $clickmeetingowner;
        require_once($CFG->dirroot . '/mod/clickmeeting/lib.php');

        $data = (object)$data;
        $data->user_id = $USER->id;
        $data->clickmeeting_id = $this->get_new_parentid('clickmeeting');

        $clickmeeting = $DB->get_record('clickmeeting', ['id' => $data->clickmeeting_id], '*', MUST_EXIST);

        // The room is created with the restoring user's API key, so it has to be billed to them too.
        $clickmeetingowner = $USER->id;

        $password = clickmeeting_generate_password(8);

        $params = [
            'name' => $clickmeeting->name,
            // Backups taken before room_type was exported carry no type; webinar is the plugin default.
            'room_type' => !empty($clickmeeting->room_type) ? $clickmeeting->room_type : 'webinar',
            'permanent_room' => '0',
            'access_type' => $clickmeeting->access_type,
            'lobby_description' => $clickmeeting->lobby_msg,
            'starts_at' => $clickmeeting->start_time,
            'duration' => $clickmeeting->duration,
            'timezone' => date_default_timezone_get(),
            'password' => $password,
        ];

        $r = json_decode(clickmeeting_add_conference($params), true);

        if (!empty($r['code'])) {
            $error = '';
            foreach ($r['errors'] as $err) {
                $error .= $err['message'] . '<br />';
            }
            throw new \moodle_exception($error, 'error');
        }

        if (!isset($r['room'])) {
            throw new \moodle_exception('cannotcreateroom', 'clickmeeting');
        }

        $room = $r['room'];
        $data->conference_id = $room['id'];
        $data->room_url = $room['room_url'];
        $data->embed_room = $room['embed_room_url'];
        $data->room_pin = $room['room_pin'];
        $data->password = $password;

        $DB->insert_record('clickmeeting_conferences', $data);
    }

    /**
     * Defines relates after exec
     */
    protected function after_execute() {
        $this->add_related_files('mod_clickmeeting', 'intro', null);
    }
}
