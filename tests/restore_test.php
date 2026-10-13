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

namespace mod_clickmeeting;

/**
 * Tests for what happens to the ClickMeeting room when an activity is copied.
 *
 * Copying, importing and restoring a course all go through the restore machinery. A copy must
 * get a room of its own: two activities sharing one room means two courses sharing one meeting,
 * one set of recordings and one schedule.
 *
 * @package    mod_clickmeeting
 * @copyright  2026 Clickmeeting
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class restore_test extends \advanced_testcase {
    /** @var string Room the API hands out when asked to create one. */
    private const API_NEW_ROOM = '{"room":{"id":777911,"room_url":"https://example.clickmeeting.com/new",'
        . '"embed_room_url":"https://example.clickmeeting.com/new/embed","room_pin":222222}}';

    /** @var int Room the original activity points at. */
    private const ORIGINAL_ROOM = 777900;

    /**
     * Loads the plugin library and the course functions the tests drive.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/clickmeeting/lib.php');
        require_once($CFG->dirroot . '/course/lib.php');
        parent::setUpBeforeClass();
    }

    /**
     * Points the plugin at a dead address, so a test that unexpectedly reaches for the
     * network fails fast instead of talking to the real ClickMeeting API.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('apiurl', 'http://127.0.0.1:9/', 'clickmeeting');
    }

    /**
     * Creates an activity, its conference row and the course module holding them together.
     *
     * @param \stdClass $course Course the activity lives in.
     * @param int $conferenceid ClickMeeting room the activity points at.
     * @return \cm_info The new course module.
     */
    private function create_activity(\stdClass $course, int $conferenceid): \cm_info {
        global $DB, $USER;

        $timestart = time() + DAYSECS;
        $instanceid = $DB->insert_record('clickmeeting', (object) [
            'course' => $course->id,
            'name' => 'Webinar',
            'description' => 'A meeting',
            'user_id' => $USER->id,
            'lobby_msg' => 'Please wait',
            'start_time' => date('Y-m-d H:i:s', $timestart),
            'timestart' => $timestart,
            'duration' => 2,
            'room_type' => 'meeting',
            'access_type' => '1',
            'introformat' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $DB->insert_record('clickmeeting_conferences', (object) [
            'clickmeeting_id' => $instanceid,
            'conference_id' => $conferenceid,
            'room_url' => 'https://example.clickmeeting.com/original',
            'embed_room' => 'https://example.clickmeeting.com/original/embed',
            'room_pin' => 111111,
            'password' => 'originalpassword',
        ]);

        $module = $DB->get_record('modules', ['name' => 'clickmeeting'], '*', MUST_EXIST);
        $cmid = add_course_module((object) [
            'course' => $course->id,
            'module' => $module->id,
            'instance' => $instanceid,
            'section' => 0,
            'visible' => 1,
            'visibleold' => 1,
            'groupmode' => 0,
            'groupingid' => 0,
            'completion' => 0,
            'showdescription' => 0,
        ]);
        course_add_cm_to_section($course, $cmid, 0);

        return get_fast_modinfo($course)->get_cm($cmid);
    }

    /**
     * Returns the conference row belonging to an activity.
     *
     * @param int $instanceid Activity the room belongs to.
     * @return \stdClass
     */
    private function get_conference(int $instanceid): \stdClass {
        global $DB;

        return $DB->get_record('clickmeeting_conferences', ['clickmeeting_id' => $instanceid], '*', MUST_EXIST);
    }

    /**
     * A duplicated activity must book its own room rather than reuse the original one.
     */
    public function test_duplicating_an_activity_creates_a_new_room(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->create_activity($course, self::ORIGINAL_ROOM);
        \curl::mock_response(self::API_NEW_ROOM);

        $copy = duplicate_module($course, $cm);

        $this->assertEquals(777911, $this->get_conference($copy->instance)->conference_id);
    }

    /**
     * The original activity keeps the room it had, so copying a course does not disturb it.
     */
    public function test_duplicating_leaves_the_original_room_alone(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->create_activity($course, self::ORIGINAL_ROOM);
        \curl::mock_response(self::API_NEW_ROOM);

        duplicate_module($course, $cm);

        $this->assertEquals(self::ORIGINAL_ROOM, $this->get_conference($cm->instance)->conference_id);
    }

    /**
     * Everything identifying the room has to come from the API answer. A copied room_url or
     * password would send participants to the original meeting however new the room id looks.
     */
    public function test_duplicating_does_not_copy_the_room_credentials(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->create_activity($course, self::ORIGINAL_ROOM);
        \curl::mock_response(self::API_NEW_ROOM);

        $copy = duplicate_module($course, $cm);

        $conference = $this->get_conference($copy->instance);
        $this->assertEquals('https://example.clickmeeting.com/new', $conference->room_url);
        $this->assertEquals('https://example.clickmeeting.com/new/embed', $conference->embed_room);
        $this->assertEquals(222222, $conference->room_pin);
        $this->assertNotEquals('originalpassword', $conference->password);
    }

    /**
     * The room type decides what the booked room actually is, so the backup has to carry it.
     */
    public function test_duplicating_keeps_the_room_type_of_the_original(): void {
        global $DB;

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->create_activity($course, self::ORIGINAL_ROOM);
        \curl::mock_response(self::API_NEW_ROOM);

        $copy = duplicate_module($course, $cm);

        $this->assertEquals('meeting', $DB->get_record('clickmeeting', ['id' => $copy->instance])->room_type);
    }

    /**
     * If the room cannot be booked the copy would be an activity pointing at nothing, so the
     * restore has to fail loudly instead of leaving that behind.
     */
    public function test_duplicating_fails_when_the_api_cannot_create_a_room(): void {
        global $DB;

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->create_activity($course, self::ORIGINAL_ROOM);
        \curl::mock_response('{"code":400,"errors":[{"message":"Room limit reached"}]}');

        try {
            duplicate_module($course, $cm);
            $this->fail('Expected the restore to abort with a moodle_exception.');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString('Room limit reached', $e->getMessage());
        } finally {
            // An aborted restore never gets to drop its temporary table. A real request tears the
            // database connection down with it, but here the table outlives the test and the reset
            // afterwards trips over it, so drop it while it is still there.
            foreach (['backup_ids_temp', 'backup_files_temp'] as $name) {
                $temptable = new \xmldb_table($name);
                if ($DB->get_manager()->table_exists($temptable)) {
                    $DB->get_manager()->drop_table($temptable);
                }
            }
        }
    }
}
