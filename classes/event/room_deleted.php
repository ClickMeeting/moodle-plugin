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

namespace mod_clickmeeting\event;

/**
 * Fired when a room is permanently removed from ClickMeeting.
 *
 * Deletion happens long after the Moodle activity disappears, so this event is what
 * ties a missing meeting back to the moment and the reason it was destroyed.
 *
 * @package    mod_clickmeeting
 * @copyright  2026 Clickmeeting
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class room_deleted extends \core\event\base {
    /**
     * Sets the event metadata.
     */
    protected function init() {
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Returns the localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event:roomdeleted', 'clickmeeting');
    }

    /**
     * Returns a description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The ClickMeeting room with id '{$this->other['conferenceid']}' was deleted " .
            "because its Moodle activity had been removed and the grace period expired.";
    }
}
