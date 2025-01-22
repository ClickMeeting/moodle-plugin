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
 * Contains class mod_clickmeeting\privacy\provider
 *
 * @package    mod_clickmeeting
 * @copyright  2024 Clickmeeting
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_clickmeeting\privacy;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\helper;
use core_privacy\local\request\plugin\provider as request_plugin_provider;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Ad hoc task that performs the actions for approved data privacy requests.
 */
class provider implements metadata_provider, request_plugin_provider, core_userlist_provider {

    /**
     * Returns metadata about this system.
     *
     * @param   collection $collection The collection to add metadata to.
     * @return  collection  The array of metadata
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('clickmeeting_tokens', [
            'user_id' => 'privacy:metadata:clickmeetingtokens:userid',
        ], 'privacy:metadata:clickmeetingtokens');

        $collection->add_external_location_link('clickmeeting.com', [
            'email' => 'privacy:metadata:clickmeeting_api:email',
            'nickname' => 'privacy:metadata:clickmeeting_api:nickname',
        ], 'privacy:metadata:clickmeeting_api');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param   int $userid The user to search.
     * @return  contextlist $contextlist The list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist
    {
        $contextlist = new contextlist();

        $sql = 'SELECT c.id
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {clickmeeting} cmt ON cmt.id = cm.instance
            LEFT JOIN {clickmeeting_tokens} cmtt ON cmtt.clickmeeting_id = cmt.id
                 WHERE cmtt.user_id = :userid
        ';

        $params = [
            'modname' => 'clickmeeting',
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts, using the supplied exporter instance.
     *
     * @param   approved_contextlist    $contextlist    The approved contexts to export information for.
     * @link http://tandl.churchward.ca/2018/06/implementing-moodles-privacy-api-in.html
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        [$contextsql, $contextparams] = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $sql = "SELECT cmtt.id,
                       cmt.name AS conferencename,
                       cmt.start_time AS conferencestartsat,
                       cmt.duration AS conferenceduration,
                       cmtt.token,
                       cm.id AS cmid
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {clickmeeting} cmt ON cmt.id = cm.instance
            INNER JOIN {clickmeeting_tokens} cmtt ON cmtt.clickmeeting_id = cmt.id
                 WHERE c.id $contextsql
                       AND cmtt.user_id = :userid
              ORDER BY cm.id ASC
        ";

        $params = [
                'modname' => 'clickmeeting',
                'contextlevel' => CONTEXT_MODULE,
                'userid' => $user->id,
            ] + $contextparams;

        $tokens = $DB->get_recordset_sql($sql, $params);
        foreach ($tokens as $token) {
            $context = context_module::instance($token->cmid);
            $contextdata = helper::get_context_data($context, $user);

            $instancedata = [
                'name' => $token->conferencename,
                'start_time' => transform::datetime($token->conferencestartsat),
                'duration' => $token->conferenceduration,
                'token' => $token->token,
            ];

            $contextdata = (object) array_merge((array) $contextdata, $instancedata);
            writer::with_context($context)->export_data([], $contextdata);
        }

        $tokens->close();
    }

    /**
     * Delete all personal data for all users in the specified context.
     *
     * @param \context $context Context to delete data from.
     */
    public static function delete_data_for_all_users_in_context(\context $context)
    {
        global $DB;

        if (!($context instanceof context_module)) {
            return;
        }

        if ($cm = get_coursemodule_from_id('clickmeeting', $context->instanceid)) {
            $DB->delete_records('clickmeeting_tokens', ['clickmeeting_id' => $cm->instance]);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist    $contextlist    The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if (!($context instanceof context_module)) {
                continue;
            }

            if ($cm = get_coursemodule_from_id('clickmeeting', $context->instanceid)) {
                $accesstokens = $DB->get_records('clickmeeting_tokens', ['clickmeeting_id' => $cm->instance]);
                foreach ($accesstokens as $accesstoken) {
                    $DB->delete_records('clickmeeting_tokens', ['id' => $accesstoken->id, 'user_id' => $user->id]);
                }
            }
        }
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param   userlist    $userlist   The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if (!($context instanceof context_module)) {
            return;
        }

        $params = [
            'instanceid' => $context->instanceid,
            'modulename' => 'clickmeeting',
        ];

        $sql = "SELECT cmtt.userid
                  FROM {clickmeeting_tokens} cmtt
                  JOIN {clickmeeting} cmt ON cmtt.clickmeeting_id = cmt.id
                  JOIN {modules} m ON m.name = :modulename
                  JOIN {course_modules} cm ON z.id = cm.instance AND m.id = cm.module
                 WHERE cm.id = :instanceid";

        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist       $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();

        if (!($context instanceof context_module)) {
            return;
        }

        $userids = $userlist->get_userids();
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params = array_merge($inparams, ['contextid' => $context->id, 'modlevel' => CONTEXT_MODULE]);

        $sql = "SELECT cmtt.id
                  FROM {clickmeeting_tokens} cmtt
                  JOIN {clickmeeting} cmt ON cmtt.clickmeeting_id = cmt.id
                  JOIN {modules} m ON m.name = 'clickmeeting'
                  JOIN {course_modules} cm ON z.id = cm.instance AND m.id = cm.module
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :modlevel
                 WHERE ctx.id = :contextid";

        $DB->delete_records_select('clickmeeting_tokens', "user_id $insql AND id IN ($sql)", $params);
    }
}
