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
 * @package moodlecore
 * @subpackage backup-moodle2
 * @copyright 2024 Clickmeeting
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define the complete choice structure for backup, with file and id annotations
 */
class backup_clickmeeting_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated
        $clickmeeting = new backup_nested_element('clickmeeting', array('id'), array(
            'course', 'name', 'description', 'lobby_msg',
            'start_time', 'timestart', 'duration'));

        $conferences = new backup_nested_element('conferences');

        $conference = new backup_nested_element('conference', array('id'), array(
            'conference_id', 'room_url', 'embed_room', 'room_pin', 'password'));

        // Build the tree
        $clickmeeting->add_child($conferences);
        $conferences->add_child($conference);

        // Define sources
        $clickmeeting->set_source_table('clickmeeting', array('id' => backup::VAR_ACTIVITYID));
        $conference->set_source_table('clickmeeting_conferences', array('clickmeeting_id' => backup::VAR_ACTIVITYID));

        // Define id annotations

        // Define file annotations

        // Return the root element (choice), wrapped into standard activity structure
        return $this->prepare_activity_structure($clickmeeting);

    }
}
