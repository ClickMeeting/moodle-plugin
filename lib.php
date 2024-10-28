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
 * Library of interface functions and constants for module clickmeeting
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 * All the clickmeeting specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 *
 * @package    mod
 * @subpackage clickmeeting
 * @copyright  2013 Łukasz Wojciechowski
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

////////////////////////////////////////////////////////////////////////////////
// Moodle core API                                                            //
////////////////////////////////////////////////////////////////////////////////

$clickmeeting_authTypeMap = [
    '1' => 'open',
    '2' => 'password',
    '3' => 'token',
];

/**
 * Returns the information on whether the module supports a feature
 *
 * @see plugin_supports() in lib/moodlelib.php
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function clickmeeting_supports($feature)
{
    switch ($feature) {
        case FEATURE_MOD_INTRO:         return false;
        case FEATURE_BACKUP_MOODLE2:    return false;
        default:                        return null;
    }
}


/**
 * Returns random string
 *
 * @param int $length Strings length
 * @return string Generated string
 */
function clickmeeting_get_random_string($length = 6)
{
    return substr(md5(rand()), 0, $length);
}

/**
 * @param string $startTime
 * @param int duration
 * @return boolean
 */
function clickmeeting_check_conference_availability($startTime, $duration, $id = 0)
{
    $apiurl = get_config('clickmeeting', 'apiurl');

    $datetime = new DateTime($startTime);
    $utcTimezone = new DateTimeZone('UTC');
    $datetime->setTimezone($utcTimezone);
    $startTime = $datetime->format('Y-m-d H:i:s');

    $params = [
        'start_time' => $startTime,
        'duration' => $duration,
        'id' => $id,
    ];

    $curl = clickmeeting_init_curl();
    $curl->post($apiurl.'conference/availability', $params);

    return 200 === $curl->get_info()['http_code'];
}

/**
 *
 * @return string
 */
function clickmeeting_get_api_key()
{
    global $CLICKMEETING_OWNER_ID, $DB, $CFG;
    require_once($CFG->dirroot.'/user/profile/lib.php');

    $user = $DB->get_record('user', array('id' => $CLICKMEETING_OWNER_ID));
    profile_load_data($user);

    if (isset($user->profile_field_clickmeetingapikey) && !empty($user->profile_field_clickmeetingapikey)) {
        return $user->profile_field_clickmeetingapikey;
    }

    return get_config('clickmeeting', 'apikey');
}

/**3
 * Returns api results
 *
 * @param array $params
 * @return string
 */
function clickmeeting_add_conference($params)
{
    $apiurl = get_config('clickmeeting', 'apiurl');

    $curlhandle = clickmeeting_init_curl();
    $result = $curlhandle->post($apiurl.'conferences', $params);

    return $result;
}

/**
 * Returns api results
 *
 * @param array $params
 * @param int $conferenceid
 * @return string
 */
function clickmeeting_edit_conference($conferenceid, $params)
{
    $apiurl = get_config('clickmeeting', 'apiurl');

    $curlhandle = clickmeeting_init_curl();
    $result = $curlhandle->put($apiurl.'conferences/'.$conferenceid, [], ['CURLOPT_POSTFIELDS' => http_build_query($params, '', '&')]);

    return $result;
}

/**
 * Returns api results
 *
 * @param array $params
 * @param int $conferenceid
 * @return string
 */
function clickmeeting_edit_conference_title($conferenceid, $title)
{
    $apiurl = get_config('clickmeeting', 'apiurl');

    $params = array(
        'name' => $title
    );

    $curlhandle = clickmeeting_init_curl();
    $result = $curlhandle->put($apiurl.'conferences/'.$conferenceid, [], ['CURLOPT_POSTFIELDS' => http_build_query($params, '', '&')]);

    return $result;
}

/**
 * Returns api results
 *
 * @param int $conferenceid
 * @return string
 */
function clickmeeting_delete_conference($conferenceid)
{
    $apiurl = get_config('clickmeeting', 'apiurl');

    $curlhandle = clickmeeting_init_curl();
    $result = $curlhandle->delete($apiurl.'conferences/'.$conferenceid);

    return $result;
}


/**
 * Returns api results
 *
 * @param int $roomId
 * @return string
 */

function clickmeeting_generate_token($roomId)
{
    $apiurl = get_config('clickmeeting', 'apiurl');

    $params = array();
    $params['how_many'] = 1;

    $curlhandle = clickmeeting_init_curl();
    $result = $curlhandle->post($apiurl.'conferences/'.$roomId.'/tokens', $params);
    $decoder = json_decode($result, true);

    return $decoder['access_tokens'][0]['token'];
}

function clickmeeting_is_token_protected(stdClass $clickmeeting)
{
    return in_array($clickmeeting->access_type, [3, 'token']);
}

function clickmeeting_is_password_protected(stdClass $clickmeeting)
{
    return in_array($clickmeeting->access_type, [2, 'password']);
}

/**
 * Returns api results
 *
 * @param int $conferenceid
 * @param string $email
 * @param string $nickname
 * @param string $role
 * @param string $password
 * @return string
 */
function clickmeeting_get_login_url($roomId, $email, $nickname, $role, $auth, $authType)
{
    global $clickmeeting_authTypeMap;
    $apiurl = get_config('clickmeeting', 'apiurl');

    $params = array();
    $params['email'] = $email;
    $params['nickname'] = $nickname;
    $params['role'] = $role;

    if ('1' !== $authType) {
        $params[$clickmeeting_authTypeMap[$authType]] = $auth;
    }

    $curlhandle = clickmeeting_init_curl();
    $result = $curlhandle->post($apiurl.'conferences/'.$roomId.'/room/autologin_hash', $params);
    $decoded = json_decode($result, true);

    return !empty($decoded['autologin_hash'])
        ? $decoded['autologin_hash']
        : '';
}

function clickmeeting_is_room_historical($roomId)
{
    $apiurl = get_config('clickmeeting', 'apiurl');

    $curlhandle = clickmeeting_init_curl();
    $result = $curlhandle->get($apiurl . 'conferences/' . $roomId);
    $response = json_decode($result, true);

    if (!empty($response['code']) && '404' === $response['code']) {
        return true;
    }

    if (empty($response['conference'])) {
        return true;
    }

    return 'inactive' === $response['conference']['status'];
}

/**
 * Saves a new instance of the clickmeeting into the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $clickmeeting An object from the form in mod_form.php
 * @param mod_clickmeeting_mod_form $mform
 * @return int The id of the newly inserted clickmeeting record
 */
function clickmeeting_add_instance(stdClass $clickmeeting, mod_clickmeeting_mod_form $mform = null)
{
    global $DB, $COURSE, $USER;
    global $CLICKMEETING_OWNER_ID;
    $CLICKMEETING_OWNER_ID = $USER->id;
    $section = required_param('section', PARAM_INT);
    $cw = get_fast_modinfo($COURSE)->get_section_info($section);

    $clickmeeting->timecreated = time();
    $clickmeeting->timemodified = time();
    $clickmeeting->start_time = date('Y-m-d H:i:s', $clickmeeting->timestart);
    $clickmeeting->user_id = $USER->id;

    if (!clickmeeting_check_conference_availability($clickmeeting->start_time, $clickmeeting->duration)) {
        print_error('This start date is already booked', '', course_get_url($COURSE, $cw->section));
        return false;
    }

    $transaction = $DB->start_delegated_transaction();
    $result = $DB->insert_record('clickmeeting', $clickmeeting);

    if (false != $result) {
        $password = clickmeeting_generate_password(8);
        $params = array();

        $timezone = date_default_timezone_get();

        $params['name'] = $clickmeeting->name;
        $params['room_type'] = $clickmeeting->room_type;
        $params['permanent_room'] = '0';
        $params['access_type'] = $clickmeeting->access_type;
        $params['lobby_description'] = $clickmeeting->lobby_msg;
        $params['starts_at'] = $clickmeeting->start_time;
        $params['duration'] = $clickmeeting->duration;
        $params['timezone'] = $timezone;
        $params['password'] = $password;

        $r = json_decode(clickmeeting_add_conference($params), true);

        $error = '';
        if (!empty($r['code'])) {
            foreach ($r['errors'] as $err) {
                $error .= $err['message'].'<br />';
            }
            print_error($error, '', course_get_url($COURSE, $cw->section));
        }

        if (isset($r['room'])) {
            $r = $r['room'];
            // insert info about new conference into database
            $conference = new stdClass();
            $conference->clickmeeting_id = $result;
            $conference->conference_id = $r['id'];
            $conference->room_url = $r['room_url'];
            $conference->embed_room = $r['embed_room_url'];
            $conference->room_pin = $r['room_pin'];
            $conference->password = $password;
            $DB->insert_record('clickmeeting_conferences', $conference);
        }
        $transaction->allow_commit();
    }
    return $result;
}


/**
 * Updates an instance of the clickmeeting in the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param object $clickmeeting An object from the form in mod_form.php
 * @param mod_clickmeeting_mod_form $mform
 * @return boolean Success/Fail
 */
function clickmeeting_update_instance(stdClass $clickmeeting, mod_clickmeeting_mod_form $mform = null)
{
    global $DB, $COURSE, $USER, $CLICKMEETING_OWNER_ID;
    $section = required_param('section', PARAM_INT);
    $cw = get_fast_modinfo($COURSE)->get_section_info($section);
    $timezone = date_default_timezone_get();
    $transaction = $DB->start_delegated_transaction();
    $clickmeeting->timemodified = time();
    $clickmeeting->id = $clickmeeting->instance;
    $clickmeeting->start_time = date('Y-m-d H:i:s', $clickmeeting->timestart);
    $CLICKMEETING_OWNER_ID = $clickmeeting->user_id;
    $conference = $DB->get_record('clickmeeting_conferences', array('clickmeeting_id' => $clickmeeting->id));

    if (!clickmeeting_check_conference_availability($clickmeeting->start_time, $clickmeeting->duration, $conference->conference_id)) {
        print_error('This start date is already booked', '', course_get_url($COURSE, $cw->section));
        return false;
    }

    if (!$DB->update_record('clickmeeting', $clickmeeting)) {
        print_error('Error updating record', '', course_get_url($COURSE, $cw->section));
        return false;
    }

    $params['name'] = $clickmeeting->name;
    $params['room_type'] = $clickmeeting->room_type;
    $params['lobby_description'] = $clickmeeting->lobby_msg;
    $params['duration'] = $clickmeeting->duration;
    $params['permanent_room'] = '0';
    $params['access_type'] = $clickmeeting->access_type;
    $params['lobby_description'] = $clickmeeting->lobby_msg;
    $params['starts_at'] = $clickmeeting->start_time;
    $params['timezone'] = $timezone;
    $params['password'] = $conference->password;

    if (clickmeeting_is_room_historical($conference->conference_id)) {
        $r = json_decode(clickmeeting_add_conference($params), true);
        if (isset($r['room'])) {
            $r = $r['room'];
            $conference->conference_id = $r['id'];
            $conference->room_url = $r['room_url'];
            $conference->embed_room = $r['embed_room_url'];
            $conference->room_pin = $r['room_pin'];
            $DB->update_record('clickmeeting_conferences', $conference);
        }
    } else {
        $r = json_decode(clickmeeting_edit_conference($conference->conference_id, $params), true);
    }

    if (!empty($r['code'])) {
        $error = '';
        foreach ($r['errors'] as $err) {
            $error .= $err['message'].'<br />';
        }
        print_error($error, '', course_get_url($COURSE, $cw->section));
        return false;
    }

    $transaction->allow_commit();

    return true;
}

/**
 * Removes an instance of the clickmeeting from the database
 *
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function clickmeeting_delete_instance($id)
{
    global $DB, $COURSE, $CLICKMEETING_OWNER_ID;

    if (! $clickmeeting = $DB->get_record('clickmeeting', array('id' => $id))) {
        return false;
    }

    $CLICKMEETING_OWNER_ID = $clickmeeting->user_id;

    if (0 < $DB->count_records('clickmeeting_conferences', array('clickmeeting_id' => $clickmeeting->id))) {
        $conference_id = $DB->get_field('clickmeeting_conferences', 'conference_id', array('clickmeeting_id' => $clickmeeting->id));
    } else {
        return false;
    }

    $apiresult = clickmeeting_delete_conference($conference_id);

    if ('"200 OK"' != $apiresult) {
        // jezeli nie znajdujemy conferencji w clickmeetingu to nie trzeba jej tam usuwac
        if ('"404 Not Found"' == $apiresult) {
            print_error($apiresult);
            return false;
        }
    }

    if (0 < $DB->count_records('clickmeeting_conferences', array('clickmeeting_id' => $clickmeeting->id))) {
        $DB->delete_records('clickmeeting_conferences', array('clickmeeting_id' => $clickmeeting->id));
    }
    $DB->delete_records('clickmeeting', array('id' => $clickmeeting->id));

    return true;
}

/**
 * Returns a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @return stdClass|null
 */
function clickmeeting_user_outline($course, $user, $mod, $clickmeeting)
{
    $return = new stdClass();
    $return->time = 0;
    $return->info = '';
    return $return;
}

/**
 * Prints a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @param stdClass $course the current course record
 * @param stdClass $user the record of the user we are generating report for
 * @param cm_info $mod course module info
 * @param stdClass $clickmeeting the module instance record
 * @return void, is supposed to echp directly
 */
function clickmeeting_user_complete($course, $user, $mod, $clickmeeting)
{
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in clickmeeting activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @return boolean
 */
function clickmeeting_print_recent_activity($course, $viewfullnames, $timestart)
{
    return false;  //  True if anything was printed, otherwise false
}

/**
 * Prepares the recent activity data
 *
 * This callback function is supposed to populate the passed array with
 * custom activity records. These records are then rendered into HTML via
 * {@link clickmeeting_print_recent_mod_activity()}.
 *
 * @param array $activities sequentially indexed array of objects with the 'cmid' property
 * @param int $index the index in the $activities to use for the next record
 * @param int $timestart append activity since this time
 * @param int $courseid the id of the course we produce the report for
 * @param int $cmid course module id
 * @param int $userid check for a particular user's activity only, defaults to 0 (all users)
 * @param int $groupid check for a particular group's activity only, defaults to 0 (all groups)
 * @return void adds items into $activities and increases $index
 */
function clickmeeting_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0)
{
}

/**
 * Prints single activity item prepared by {@see clickmeeting_get_recent_mod_activity()}

 * @return void
 */
function clickmeeting_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames)
{
}

/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * @return boolean
 * @todo Finish documenting this function
 **/
function clickmeeting_cron()
{
    return true;
}

/**
 * Returns all other caps used in the module
 *
 * @example return array('moodle/site:accessallgroups');
 * @return array
 */
function clickmeeting_get_extra_capabilities()
{
    return array();
}

////////////////////////////////////////////////////////////////////////////////
// Gradebook API                                                              //
////////////////////////////////////////////////////////////////////////////////

/**
 * Is a given scale used by the instance of clickmeeting?
 *
 * This function returns if a scale is being used by one clickmeeting
 * if it has support for grading and scales. Commented code should be
 * modified if necessary. See forum, glossary or journal modules
 * as reference.
 *
 * @param int $clickmeetingid ID of an instance of this module
 * @return bool true if the scale is used by the given clickmeeting instance
 */
function clickmeeting_scale_used($clickmeetingid, $scaleid)
{
    global $DB;

    /** @example */
    if ($scaleid and $DB->record_exists('clickmeeting', array('id' => $clickmeetingid, 'grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Checks if scale is being used by any instance of clickmeeting.
 *
 * This is used to find out if scale used anywhere.
 *
 * @param $scaleid int
 * @return boolean true if the scale is used by any clickmeeting instance
 */
function clickmeeting_scale_used_anywhere($scaleid)
{
    global $DB;

    try {
        if ($scaleid and $DB->record_exists('clickmeeting', array('grade' => -$scaleid))) {
            return true;
        }
    } catch (dml_exception $e) {}

    return false;
}

/**
 * Creates or updates grade item for the give clickmeeting instance
 *
 * Needed by grade_update_mod_grades() in lib/gradelib.php
 *
 * @param stdClass $clickmeeting instance object with extra cmidnumber and modname property
 * @return void
 */
function clickmeeting_grade_item_update(stdClass $clickmeeting)
{
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    /** @example */
    $item = array();
    $item['itemname'] = clean_param($clickmeeting->name, PARAM_NOTAGS);
    $item['gradetype'] = GRADE_TYPE_VALUE;
    $item['grademax']  = 0;
    $item['grademin']  = 0;

    $conference_id = $DB->get_field('clickmeeting_conferences', 'conference_id', array('clickmeeting_id' => $clickmeeting->id));
    clickmeeting_edit_conference_title($conference_id, $clickmeeting->name);

    grade_update('mod/clickmeeting', $clickmeeting->course, 'mod', 'clickmeeting', $clickmeeting->id, 0, null, $item);
}

function clickmeeting_generate_password($length)
{
    $availableCharacters = '34679ACFGHJKMNPRSTUWXY';
    $password = '';

    for ($i = 0; $i < $length; $i++) {
        $index = mt_rand(0, (strlen($availableCharacters) - 1));
        $password .= $availableCharacters[$index];
        $availableCharacters = str_replace($availableCharacters[$index], '', $availableCharacters);
    }

    return $password;
}

/**
 * Update clickmeeting grades in the gradebook
 *
 * Needed by grade_update_mod_grades() in lib/gradelib.php
 *
 * @param stdClass $clickmeeting instance object with extra cmidnumber and modname property
 * @param int $userid update grade of specific user only, 0 means all participants
 * @return void
 */
function clickmeeting_update_grades(stdClass $clickmeeting, $userid = 0)
{
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    /** @example */
    $grades = array(); // populate array of grade objects indexed by userid

    grade_update('mod/clickmeeting', $clickmeeting->course, 'mod', 'clickmeeting', $clickmeeting->id, 0, $grades);
}

////////////////////////////////////////////////////////////////////////////////
// File API                                                                   //
////////////////////////////////////////////////////////////////////////////////

/**
 * Returns the lists of all browsable file areas within the given module context
 *
 * The file area 'intro' for the activity introduction field is added automatically
 * by {@link file_browser::get_file_info_context_module()}
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return array of [(string)filearea] => (string)description
 */
function clickmeeting_get_file_areas($course, $cm, $context)
{
    return array();
}

/**
 * File browsing support for clickmeeting file areas
 *
 * @package mod_clickmeeting
 * @category files
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info instance or null if not found
 */
function clickmeeting_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename)
{
    return null;
}

/**
 * Serves the files from the clickmeeting file areas
 *
 * @package mod_clickmeeting
 * @category files
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the clickmeeting's context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 */
function clickmeeting_pluginfile($course, $cm, $context, $filearea, array $args, $forcedownload, array $options=array())
{
    global $DB, $CFG;

    if ($context->contextlevel != CONTEXT_MODULE) {
        send_file_not_found();
    }

    require_login($course, true, $cm);

    send_file_not_found();
}

////////////////////////////////////////////////////////////////////////////////
// Navigation API                                                             //
////////////////////////////////////////////////////////////////////////////////

/**
 * Extends the global navigation tree by adding clickmeeting nodes if there is a relevant content
 *
 * This can be called by an AJAX request so do not rely on $PAGE as it might not be set up properly.
 *
 * @param navigation_node $navref An object representing the navigation tree node of the clickmeeting module instance
 * @param stdClass $course
 * @param stdClass $module
 * @param cm_info $cm
 */
function clickmeeting_extend_navigation(navigation_node $navref, stdclass $course, stdclass $module, cm_info $cm)
{
}

/**
 * Extends the settings navigation with the clickmeeting settings
 *
 * This function is called when the context for the page is a clickmeeting module. This is not called by AJAX
 * so it is safe to rely on the $PAGE.
 *
 * @param settings_navigation $settingsnav {@link settings_navigation}
 * @param navigation_node $clickmeetingnode {@link navigation_node}
 */
function clickmeeting_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $clickmeetingnode=null)
{
}

/**
 * Initialize curl hande for clickmeeting api
 *
 * @return \curl
 */
function clickmeeting_init_curl()
{
    $curlhandle = new \curl();

    $curlhandle->setHeader(['X-Api-Key: ' . clickmeeting_get_api_key()]);
    $curlhandle->setopt([
        'CURLOPT_TIMEOUT' => 100,
        'CURLOPT_RETURNTRANSFER' => true
    ]);

    if ('develop' === '{{env}}') { //hack for local development
        $curlhandle->setopt([
            'CURLOPT_SSL_VERIFYPEER' => false,
            'CURLOPT_SSL_VERIFYHOST' => false
        ]);
    }

    return $curlhandle;
}
