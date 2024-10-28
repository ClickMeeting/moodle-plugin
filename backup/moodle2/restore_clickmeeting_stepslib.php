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
 * Structure step to restore one choice activity
 *
 * @package mod_clickmeeting
 * @copyright 2024 Clickmeeting
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_clickmeeting_activity_structure_step extends restore_activity_structure_step {

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
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $newitemid = $DB->insert_record('clickmeeting', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Inserts conference data into new table
     *
     * @param object $data
     */
    protected function process_clickmeeting_conferences($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->clickmeeting_id = $this->get_new_parentid('clickmeeting');

        $newitemid = $DB->insert_record('clickmeeting_conferences', $data);
    }

    /**
     * Defines relates after exec
     */
    protected function after_execute() {
        $this->add_related_files('mod_clickmeeting', 'intro', null);
    }
}
