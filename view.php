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
 * @package    mod
 * @subpackage clickmeeting
 * @copyright  2024 Clickmeeting
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$n  = optional_param('n', 0, PARAM_INT);  // clickmeeting instance ID - it should be named as the first character of the module

global $COURSE, $USER, $THEME, $CLICKMEETING_OWNER_ID;

// Add the required JavaScript to the page
$THEME = new stdClass();
$THEME->javascripts = array(
    'jquery-1.7.1.min'
);

if ($id) {
    $cm         = get_coursemodule_from_id('clickmeeting', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $clickmeeting  = $DB->get_record('clickmeeting', array('id' => $cm->instance), '*', MUST_EXIST);
    $clickmeeting_conference  = $DB->get_record('clickmeeting_conferences', array('clickmeeting_id' => $clickmeeting->id), '*', MUST_EXIST);
} elseif ($n) {
    $clickmeeting  = $DB->get_record('clickmeeting', array('id' => $n), '*', MUST_EXIST);
    $clickmeeting_conference  = $DB->get_record('clickmeeting_conferences', array('clickmeeting_id' => $clickmeeting->id), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $clickmeeting->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('clickmeeting', $clickmeeting->id, $course->id, false, MUST_EXIST);
} else {
    error('You must specify a course_module ID or an instance ID');
}
$CLICKMEETING_OWNER_ID = $clickmeeting->user_id;

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
if (is_siteadmin()) {
    $role = 'host'; // host
} elseif (has_capability('mod/clickmeeting:host', $context)) {
    //if(has_capability('mod/clickmeeting:host', $context)) {
    $role = 'host';
} elseif (has_capability('mod/clickmeeting:presenter', $context)) {
    $role = 'presenter';
} elseif (has_capability('mod/clickmeeting:listener', $context)) {
    $role = 'listener';
}

/// Print the page header

$PAGE->set_url('/mod/clickmeeting/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($clickmeeting->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);


//// GENERUJEMY TOKEN DLA USER-a
$token = $DB->get_field('clickmeeting_tokens', 'token', array('user_id' => $USER->id, 'conference_id' => $clickmeeting_conference->conference_id));

if (empty($token) && clickmeeting_is_token_protected($clickmeeting)) {
    $token = clickmeeting_generate_token($clickmeeting_conference->conference_id);

    $token_info = new stdClass();
    $token_info->user_id = $USER->id;
    $token_info->clickmeeting_id = $clickmeeting->id;
    $token_info->role = 0;
    $token_info->conference_id = $clickmeeting_conference->conference_id;
    $token_info->token = $token;

    $DB->insert_record('clickmeeting_tokens', $token_info);
}
?>

<?php
    $PAGE->requires->css('/mod/clickmeeting/styles/clickmeeting.css');
    $PAGE->requires->css('/mod/clickmeeting/styles/jquery.popupLight.css');
?>
<script type="text/javascript" src="<?php echo $CFG->wwwroot ?>/mod/clickmeeting/scripts/jquery.min.js"></script>
<script>
    window.cQuery = window.jQuery;
</script>
<script type="text/javascript" src="<?php echo $CFG->wwwroot ?>/mod/clickmeeting/scripts/swfobject.js"></script>
<script type="text/javascript" src="<?php echo $CFG->wwwroot ?>/mod/clickmeeting/scripts/popup.js"></script>
<script type="text/javascript" src="<?php echo $CFG->wwwroot ?>/mod/clickmeeting/scripts/jquery.popupLight.js"></script>
<script type="text/javascript">
    (function ($) {
        $(document).ready(function(){

            $('.enter_meeting').bind('click', function(e){
                e.preventDefault();

                var url = this.href;
                var popup = $.ajaxToPopup({ });

                popup.doRedirect({
                    url: url
                });

                e.stopPropagation();
            });
        });
    })(window.cQuery);
</script>


<?php

$context = context_module::instance($cm->id);
if (is_siteadmin()) {
    $role = 'host'; // host
} elseif (has_capability('mod/clickmeeting:host', $context)) {
    //if(has_capability('mod/clickmeeting:host', $context)) {
    $role = 'host';
} elseif (has_capability('mod/clickmeeting:presenter', $context)) {
    $role = 'presenter';
} elseif (has_capability('mod/clickmeeting:listener', $context)) {
    $role = 'listener';
}

$auth = '';
if (clickmeeting_is_password_protected($clickmeeting)) {
    $auth = $clickmeeting_conference->password;
} elseif (clickmeeting_is_token_protected($clickmeeting)) {
    $auth = $token;
}

$login_hash = clickmeeting_get_login_url($clickmeeting_conference->conference_id, $USER->email, trim($USER->firstname . ' ' . $USER->lastname), $role, $auth, $clickmeeting->access_type);

// Output starts here
echo $OUTPUT->header();


echo '<b style="font-size: 110%;">'.get_string('view:room_name', 'clickmeeting').': </b>'.$clickmeeting->name;
echo '<br /><br />';

?>

<?php
if (!clickmeeting_is_room_historical($clickmeeting_conference->conference_id)) {
    echo '<b style="font-size: 110%;">'.get_string('view:room_date', 'clickmeeting').': </b>'.$clickmeeting->start_time.' - '.date('Y-m-d H:i:s', strtotime('+'.$clickmeeting->duration.' hours', $clickmeeting->timestart));
    echo '<br /><br />';
    echo '<b style="font-size: 110%;">'.get_string('view:room_description', 'clickmeeting').': </b>'.$clickmeeting->description;
    echo '<br /><br />';
    if (clickmeeting_is_password_protected($clickmeeting)) {
        echo '<b style="font-size: 110%;">'.get_string('view:password', 'clickmeeting').': </b>'.$clickmeeting_conference->password;
        echo '<br /><br />';
    }
    echo '<div style="text-align: center;" class="bt-green"><a class="enter_meeting" href="'.$clickmeeting_conference->room_url.'?l='.$login_hash.'" target="_blank">'.get_string('view:joinmeeting', 'clickmeeting').'</a></div>';
} else {
    echo '<b style="font-size: 110%;">' . get_string('view:oldmeeting', 'clickmeeting') . '</b>';
}
?>

<br /><br /><br /><br />

<?php

// Finish the page
echo $OUTPUT->footer();
