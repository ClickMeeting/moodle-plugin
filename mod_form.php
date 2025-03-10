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
 * The main clickmeeting configuration form
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package    mod_clickmeeting
 * @copyright  2024 Clickmeeting
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

/**
 * Module instance settings form
 */
class mod_clickmeeting_mod_form extends moodleform_mod {

    /**
     * Defines webinar open to all
     */
    const ACCESS_TYPE_OPEN = '1';

    /**
     * Defines webinar password protected
     */
    const ACCESS_TYPE_PASSWORD = '2';

    /**
     * Defines webinar token protected
     */
    const ACCESS_TYPE_TOKEN = '3';

    /**
     * Defines forms elements
     */
    public function definition() {
        global $CFG, $DB, $PAGE, $COURSE, $USER, $clickmeetingowner;

        if (1 === optional_param('check_availability', null, PARAM_INT)) {
            $conferenceid = 0;
            $coursemoduleid = optional_param('coursemodule', null, PARAM_INT);
            if (0 !== $coursemoduleid) {
                $cm = get_coursemodule_from_id('clickmeeting', $coursemoduleid, 0, false, MUST_EXIST);
                $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
                $clickmeeting = $DB->get_record('clickmeeting', ['id' => $cm->instance], '*', MUST_EXIST);
                $conference = $DB->get_record('clickmeeting_conferences', ['clickmeeting_id' => $clickmeeting->id]);
                $clickmeetingowner = $clickmeeting->user_id;
                 $conferenceid = $conference->conference_id;
            } else {
                $clickmeetingowner = $USER->id;
            }

            $conferencestarttime = required_param('start_time', PARAM_TEXT);
            $conferenceduration = required_param('duration', PARAM_INT);
            if (clickmeeting_check_conference_availability($conferencestarttime, $conferenceduration,  $conferenceid)) {
                http_response_code(200);
                echo 'SUCCESS';
            } else {
                http_response_code(422);
                echo 'ERROR';
            }
            die;
        }

        $mform = $this->_form;

        $PAGE->requires->jquery();
        $PAGE->requires->js('/mod/clickmeeting/scripts/main.js');
        $PAGE->requires->css('/mod/clickmeeting/styles/additional.css');

        // -------------------------------------------------------------------------------
        // Adding the "general" fieldset, where all the common settings are showed
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field
        $mform->addElement('text', 'name', get_string('clickmeetingname', 'clickmeeting'), ['size' => '64']);
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('textarea', 'description', get_string('description'), ['rows' => 10, 'cols' => 62]);

        $mform->addElement(
            'select',
            'room_type',
            get_string('form:select_room_type', 'clickmeeting'),
            [
                'webinar' => get_string('form:webinar', 'clickmeeting'),
                'meeting' => get_string('form:meeting', 'clickmeeting'),
            ]
        );

        $mform->addElement(
            'select',
            'access_type',
            get_string('form:access_type', 'clickmeeting'),
            [
                self::ACCESS_TYPE_OPEN => get_string('form:open', 'clickmeeting'),
                self::ACCESS_TYPE_PASSWORD => get_string('form:password', 'clickmeeting'),
                self::ACCESS_TYPE_TOKEN => get_string('form:token', 'clickmeeting'),
            ]
        );

        $mform->addElement('hidden', 'clickmeeting_course_id', $COURSE->id);
        $mform->setType('clickmeeting_course_id', PARAM_INT);

        $mform->addElement('hidden', 'user_id', $COURSE->id);
        $mform->setType('user_id', PARAM_INT);

        $mform->addElement('textarea', 'lobby_msg', get_string("form:waitingmsg", "clickmeeting"), 'rows="10" cols="62"');
        $mform->addRule('lobby_msg', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('lobby_msg', 'form:waitingmsg', 'clickmeeting');

        $mform->addElement('header', 'clickmeetingfieldset', get_string('form:data_header', 'clickmeeting'));

        $mform->addElement('date_time_selector', 'timestart', get_string('fromdate'));
        $mform->addElement('select', 'duration', get_string('timelimit', 'quiz'), ['1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6]);
        $mform->addElement('hidden', 'clickmeeting_no_free_sessions', get_string('form:no_sessions', 'clickmeeting'));
        $mform->setType('clickmeeting_no_free_sessions', PARAM_TEXT);

        $mform->addHelpButton('duration', 'form:maxduration', 'clickmeeting');

        // -------------------------------------------------------------------------------
        // add standard elements, common to all modules
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }
}
