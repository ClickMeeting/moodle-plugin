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
 * Main javascript file
 *
 * @package    mod_clickmeeting
 * @copyright  2024 Clickmeeting
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$(document).ready(function(){
    const getClickmeetingUrl = function() {
        const coursemoduleId = $("input[name='coursemodule']").val();
        const courseId = $("input[name='clickmeeting_course_id']").val();
        const url = $("#id_duration").parents('form').attr('action');
        const params = {
            course: courseId,
            coursemodule: coursemoduleId,
            add: 'clickmeeting',
            type: '',
            section: 0,
            return: 0,
            sr: 0,
            check_availability: 1,
        };

        return url + '?' + $.param(params);
    }

    const buildDatetime = function() {
        var day = $('#id_timestart_day').val();
        var month = $('#id_timestart_month').val();
        var year = $('#id_timestart_year').val();
        var hour = $('#id_timestart_hour').val();
        var minute = $('#id_timestart_minute').val();

        return  year + '-' + month + '-' + day + ' ' + hour + ':' + minute + ':00';
    };
    const getDuration = function() {
        return $("#id_duration").val();
    };
    const showNoSessionsInfo = function() {
        if (!$("#no-sessions").length) {
            $("#id_clickmeetingfieldset").append('<div id="no-sessions" class="no-sessions alert alert-danger fade in alert-dismissible">' + noSessionsText + '</div>');
        }
    };
    const hideNoSessionsInfo = function() {
        $('#no-sessions').remove();
    };
    const noSessionsText = $("input[name='clickmeeting_no_free_sessions']").val();
    const clickmeetingUrl = getClickmeetingUrl()


    $("div[data-groupname='timestart'] select, #id_duration").change(function() {
        var data = {
            'start_time': buildDatetime(),
            'duration': getDuration()
        };
        $.ajax({
            type: "POST",
            url: clickmeetingUrl,
            data: data,
            crossDomain: true,
            success: function(){
                hideNoSessionsInfo();
            },
            error: function() {
                showNoSessionsInfo();
            }
        });
    });
});
