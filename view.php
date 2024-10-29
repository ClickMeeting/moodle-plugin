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
 * Prints a particular instance of clickmeeting
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_clickmeeting
 * @copyright  2024 Clickmeeting
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$n  = optional_param('n', 0, PARAM_INT);  // clickmeeting instance ID - it should be named as the first character of the module

global $COURSE, $USER, $THEME, $clickmeetingowner;

if ($id) {
    $cm         = get_coursemodule_from_id('clickmeeting', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $clickmeeting  = $DB->get_record('clickmeeting', ['id' => $cm->instance], '*', MUST_EXIST);
    $conference  = $DB->get_record('clickmeeting_conferences', ['clickmeeting_id' => $clickmeeting->id], '*', MUST_EXIST);
} else if ($n) {
    $clickmeeting  = $DB->get_record('clickmeeting', ['id' => $n], '*', MUST_EXIST);
    $conference  = $DB->get_record('clickmeeting_conferences', ['clickmeeting_id' => $clickmeeting->id], '*', MUST_EXIST);
    $course     = $DB->get_record('course', ['id' => $clickmeeting->course], '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('clickmeeting', $clickmeeting->id, $course->id, false, MUST_EXIST);
} else {
    throw new \moodle_exception('needcoursecategroyid', 'error');
}
$clickmeetingowner = $clickmeeting->user_id;

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
if (is_siteadmin()) {
    $role = 'host'; // host
} else if (has_capability('mod/clickmeeting:host', $context)) {
    $role = 'host';
} else if (has_capability('mod/clickmeeting:presenter', $context)) {
    $role = 'presenter';
} else if (has_capability('mod/clickmeeting:listener', $context)) {
    $role = 'listener';
}

// Print the page header

$PAGE->set_url('/mod/clickmeeting/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($clickmeeting->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);


// GENERUJEMY TOKEN DLA USER-a
$token = $DB->get_field('clickmeeting_tokens', 'token', ['user_id' => $USER->id, 'conference_id' => $conference->conference_id]);

if (empty($token) && clickmeeting_is_token_protected($clickmeeting)) {
    $token = clickmeeting_generate_token($conference->conference_id);

    $tokeninfo = new stdClass();
    $tokeninfo->user_id = $USER->id;
    $tokeninfo->clickmeeting_id = $clickmeeting->id;
    $tokeninfo->role = 0;
    $tokeninfo->conference_id = $conference->conference_id;
    $tokeninfo->token = $token;

    $DB->insert_record('clickmeeting_tokens', $tokeninfo);
}

$PAGE->requires->css('/mod/clickmeeting/styles/clickmeeting.css');
$PAGE->requires->css('/mod/clickmeeting/styles/jquery.popupLight.css');
$PAGE->requires->jquery();

$auth = '';
if (clickmeeting_is_password_protected($clickmeeting)) {
    $auth = $conference->password;
} else if (clickmeeting_is_token_protected($clickmeeting)) {
    $auth = $token;
}

$loginhash = clickmeeting_get_login_url($conference->conference_id, $USER->email, trim($USER->firstname . ' ' . $USER->lastname), $role, $auth, $clickmeeting->access_type);

// Output starts here
echo $OUTPUT->header();

echo '<b style="font-size: 110%;">'.get_string('view:room_name', 'clickmeeting').': </b>'.$clickmeeting->name;
echo '<br /><br />';

if (!clickmeeting_is_room_historical($conference->conference_id)) {
    $timestamp = strtotime('+'.$clickmeeting->duration.' hours', $clickmeeting->timestart);
    echo '<b style="font-size: 110%;">'.get_string('view:room_date', 'clickmeeting').': </b>'.$clickmeeting->start_time.' - '.date('Y-m-d H:i:s', $timestamp);
    echo '<br /><br />';
    echo '<b style="font-size: 110%;">'.get_string('view:room_description', 'clickmeeting').': </b>'.$clickmeeting->description;
    echo '<br /><br />';
    if (clickmeeting_is_password_protected($clickmeeting)) {
        echo '<b style="font-size: 110%;">'.get_string('view:password', 'clickmeeting').': </b>'.$conference->password;
        echo '<br /><br />';
    }
    $loginurl = sprintf('%s?l=%s', $conference->room_url, $loginhash);
    echo '<div style="text-align: center;" class="bt-green">';
    echo '<a class="enter_meeting" href="'.$loginurl.'" target="_blank">'.get_string('view:joinmeeting', 'clickmeeting').'</a>';
    echo '</div>';
} else {
    echo '<b style="font-size: 110%;">' . get_string('view:oldmeeting', 'clickmeeting') . '</b>';
}

echo '<br /><br /><br /><br />';

// Finish the page
echo $OUTPUT->footer();
