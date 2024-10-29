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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/clickmeeting/backup/moodle2/backup_clickmeeting_stepslib.php');

use mod_clickmeeting\backup_activity_structure_step;

/**
 * Activity task backup
 *
 * @package    mod_clickmeeting
 * @copyright  2024 Clickmeeting
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_clickmeeting_activity_task extends backup_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        $this->add_step(new backup_activity_structure_step('clickmeeting_structure', 'clickmeeting.xml'));
    }

    /**
     * Code the transformations to perform in the activity in
     * order to get transportable (encoded) links
     *
     * @param string $content
     * @return string
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, "/");

        // Link to the list of clickmeeting
        $search = "/(" . $base . "\/mod\/clickmeeting\/index.php\?id=)([0-9]+)/";
        $content = preg_replace($search, '$@CLICKMEETINGINDEX*$2@$', $content);

        // Link to clickmeeting view by moduleid
        $search = "/(" . $base . "\/mod\/clickmeeting\/view.php\?id=)([0-9]+)/";
        $content = preg_replace($search, '$@CLICKMEETINGVIEWBYID*$2@$', $content);

        return $content;
    }
}
