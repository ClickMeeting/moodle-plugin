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
 * Book plugin settings
 *
 * @package    mod_book
 * @copyright  2004-2011 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree)
{
    require_once(dirname(__FILE__).'/lib.php');

    $settings->add(new admin_setting_configtext('clickmeeting/apiurl',
                                                get_string('settings:apiurl', 'clickmeeting'),
                                                get_string('settings:apiurldesc', 'clickmeeting'),
                                                'https://api.clickmeeting.com/v1/',
                                                PARAM_URL));

    $settings->add(new admin_setting_configtext('clickmeeting/apikey',
                                                get_string('settings:apikey', 'clickmeeting'),
                                                get_string('settings:apikeydesc', 'clickmeeting'),
                                                ''));
}
