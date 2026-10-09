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
 * Tests for the clickmeeting activity library.
 *
 * @package    mod_clickmeeting
 * @copyright  2026 Clickmeeting
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lib_test extends \advanced_testcase {
    /** @var string Response the ClickMeeting API returns when a room was deleted. */
    private const API_OK = '"200 OK"';

    /**
     * Loads the plugin library under test.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/clickmeeting/lib.php');
        parent::setUpBeforeClass();
    }

    /**
     * Points the plugin at a dead address, so a test that unexpectedly reaches for the
     * network fails fast instead of talking to the real ClickMeeting API.
     */
    protected function setUp(): void {
        global $clickmeetingowner;

        parent::setUp();
        $this->resetAfterTest();
        set_config('apiurl', 'http://127.0.0.1:9/', 'clickmeeting');
        $clickmeetingowner = null;
    }

    /**
     * Creates an activity row together with its conference row.
     *
     * @param int $courseid Course the activity belongs to.
     * @param int $conferenceid ClickMeeting room id the activity points at.
     * @param int $ownerid User owning the room.
     * @return int Id of the new clickmeeting record.
     */
    private function create_activity(int $courseid, int $conferenceid, int $ownerid): int {
        global $DB;

        $instanceid = $DB->insert_record('clickmeeting', (object) [
            'course' => $courseid,
            'name' => 'Webinar',
            'user_id' => $ownerid,
            'introformat' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $DB->insert_record('clickmeeting_conferences', (object) [
            'clickmeeting_id' => $instanceid,
            'conference_id' => $conferenceid,
        ]);

        return $instanceid;
    }

    /**
     * Runs the purge task, capturing the progress output it writes.
     *
     * @return string Whatever the task reported.
     */
    private function run_purge_task(): string {
        ob_start();
        (new \mod_clickmeeting\task\purge_deleted_rooms())->execute();

        return ob_get_clean();
    }

    /**
     * Asserts that nothing so far spoke to the API, by showing that a queued mock response
     * is still waiting to be handed out. Consuming it here also keeps the queue, which Moodle
     * does not reset between tests, from leaking into the next one.
     */
    private function assert_api_was_not_called(): void {
        $leftover = (new \curl())->get('http://127.0.0.1:9/');

        $this->assertEquals(self::API_OK, $leftover, 'The ClickMeeting API should not have been called.');
    }

    /**
     * Deleting an activity must not destroy the room straight away, because Moodle
     * deletions are reversible from the recycle bin.
     */
    public function test_delete_instance_schedules_room_deletion_instead_of_calling_api(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $owner = $this->getDataGenerator()->create_user();
        $instanceid = $this->create_activity($course->id, 777001, $owner->id);

        $this->assertTrue(clickmeeting_delete_instance($instanceid));

        $this->assertFalse($DB->record_exists('clickmeeting', ['id' => $instanceid]));
        $pending = $DB->get_record('clickmeeting_room_deletions', ['conference_id' => 777001]);
        $this->assertNotFalse($pending, 'Room deletion should have been scheduled.');
        $this->assertEquals($owner->id, $pending->user_id);
    }

    /**
     * Once the grace period has passed the room is really gone from ClickMeeting.
     */
    public function test_purge_task_deletes_room_once_grace_period_expired(): void {
        global $DB;

        $this->resetAfterTest();
        set_config('graceperiod', WEEKSECS, 'clickmeeting');
        $owner = $this->getDataGenerator()->create_user();
        $DB->insert_record('clickmeeting_room_deletions', (object) [
            'conference_id' => 777002,
            'user_id' => $owner->id,
            'timescheduled' => time() - WEEKSECS - 1,
        ]);
        \curl::mock_response(self::API_OK);

        $sink = $this->redirectEvents();
        $this->run_purge_task();
        $events = $sink->get_events();
        $sink->close();

        $this->assertFalse($DB->record_exists('clickmeeting_room_deletions', ['conference_id' => 777002]));
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\mod_clickmeeting\event\room_deleted::class, $events[0]);
        $this->assertEquals(777002, $events[0]->other['conferenceid']);
    }

    /**
     * Within the grace period the room must be left alone, so that restoring the activity
     * from the recycle bin gives the meeting and its recordings back.
     */
    public function test_purge_task_keeps_room_within_grace_period(): void {
        global $DB;

        $this->resetAfterTest();
        set_config('graceperiod', WEEKSECS, 'clickmeeting');
        $owner = $this->getDataGenerator()->create_user();
        $DB->insert_record('clickmeeting_room_deletions', (object) [
            'conference_id' => 777003,
            'user_id' => $owner->id,
            'timescheduled' => time() - DAYSECS,
        ]);
        \curl::mock_response(self::API_OK);

        $sink = $this->redirectEvents();
        $this->run_purge_task();
        $events = $sink->get_events();
        $sink->close();

        $this->assertTrue($DB->record_exists('clickmeeting_room_deletions', ['conference_id' => 777003]));
        $this->assertCount(0, $events);
        $this->assert_api_was_not_called();
    }

    /**
     * A room shared with another activity - a course copy, an import, a restore - must survive
     * the deletion of any single activity pointing at it.
     */
    public function test_purge_task_spares_room_still_used_by_another_activity(): void {
        global $DB;

        $this->resetAfterTest();
        set_config('graceperiod', WEEKSECS, 'clickmeeting');
        $course = $this->getDataGenerator()->create_course();
        $owner = $this->getDataGenerator()->create_user();
        $this->create_activity($course->id, 777004, $owner->id);
        $DB->insert_record('clickmeeting_room_deletions', (object) [
            'conference_id' => 777004,
            'user_id' => $owner->id,
            'timescheduled' => time() - WEEKSECS - 1,
        ]);
        \curl::mock_response(self::API_OK);

        $sink = $this->redirectEvents();
        $this->run_purge_task();
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(0, $events, 'A room another activity still points at must not be deleted.');
        $this->assert_api_was_not_called();
        $this->assertFalse($DB->record_exists('clickmeeting_room_deletions', ['conference_id' => 777004]));
    }

    /**
     * An activity whose room row is missing must still be removable, otherwise Moodle keeps
     * throwing on every attempt and the activity can never be deleted.
     */
    public function test_delete_instance_removes_activity_that_has_no_room(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $owner = $this->getDataGenerator()->create_user();
        $instanceid = $DB->insert_record('clickmeeting', (object) [
            'course' => $course->id,
            'name' => 'Webinar without a room',
            'user_id' => $owner->id,
            'introformat' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $this->assertTrue(clickmeeting_delete_instance($instanceid));

        $this->assertFalse($DB->record_exists('clickmeeting', ['id' => $instanceid]));
        $this->assertEquals(0, $DB->count_records('clickmeeting_room_deletions'));
    }

    /**
     * A room already gone from ClickMeeting is simply forgotten, without pretending we deleted it.
     */
    public function test_purge_task_forgets_room_that_no_longer_exists(): void {
        global $DB;

        set_config('graceperiod', WEEKSECS, 'clickmeeting');
        $owner = $this->getDataGenerator()->create_user();
        $DB->insert_record('clickmeeting_room_deletions', (object) [
            'conference_id' => 777005,
            'user_id' => $owner->id,
            'timescheduled' => time() - WEEKSECS - 1,
        ]);
        \curl::mock_response('"404 Not Found"');

        $sink = $this->redirectEvents();
        $this->run_purge_task();
        $events = $sink->get_events();
        $sink->close();

        $this->assertFalse($DB->record_exists('clickmeeting_room_deletions', ['conference_id' => 777005]));
        $this->assertCount(0, $events, 'Nothing was deleted, so no deletion should be reported.');
    }

    /**
     * A failing API call must not lose the room: the record stays so the next run retries it.
     */
    public function test_purge_task_retries_room_after_a_failed_api_call(): void {
        global $DB;

        set_config('graceperiod', WEEKSECS, 'clickmeeting');
        $owner = $this->getDataGenerator()->create_user();
        $DB->insert_record('clickmeeting_room_deletions', (object) [
            'conference_id' => 777006,
            'user_id' => $owner->id,
            'timescheduled' => time() - WEEKSECS - 1,
        ]);
        \curl::mock_response('{"errors":[{"message":"Service unavailable"}]}');

        $output = $this->run_purge_task();

        $this->assertTrue($DB->record_exists('clickmeeting_room_deletions', ['conference_id' => 777006]));
        $this->assertStringContainsString('will retry', $output);
    }

    /**
     * Rooms live on the subaccount that created them, so calls must use the owner's key even
     * when somebody else - or cron, acting as nobody - triggers the deletion.
     */
    public function test_api_key_belongs_to_the_room_owner_not_the_acting_user(): void {
        global $clickmeetingowner;

        set_config('apikey', 'main-account-key', 'clickmeeting');
        $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text',
            'shortname' => 'clickmeetingapikey',
            'name' => 'ClickMeeting API key',
        ]);
        $owner = $this->getDataGenerator()->create_user(['profile_field_clickmeetingapikey' => 'subaccount-key']);
        $this->setUser($this->getDataGenerator()->create_user());

        $clickmeetingowner = $owner->id;

        $this->assertEquals('subaccount-key', clickmeeting_get_api_key());
    }

    /**
     * With no owner on record the global key is the only sensible choice, and a missing user
     * must not bring the whole deletion down.
     */
    public function test_api_key_falls_back_to_the_global_key_for_an_unknown_owner(): void {
        global $clickmeetingowner;

        set_config('apikey', 'main-account-key', 'clickmeeting');
        $this->setUser($this->getDataGenerator()->create_user());

        $clickmeetingowner = 999999;

        $this->assertEquals('main-account-key', clickmeeting_get_api_key());
    }

    /**
     * Access tokens carry user data, so they must not survive the activity they belong to.
     */
    public function test_delete_instance_removes_access_tokens(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $owner = $this->getDataGenerator()->create_user();
        $participant = $this->getDataGenerator()->create_user();
        $instanceid = $this->create_activity($course->id, 777007, $owner->id);
        $DB->insert_record('clickmeeting_tokens', (object) [
            'clickmeeting_id' => $instanceid,
            'conference_id' => 777007,
            'user_id' => $participant->id,
            'token' => 'abc123',
        ]);

        clickmeeting_delete_instance($instanceid);

        $this->assertEquals(0, $DB->count_records('clickmeeting_tokens', ['clickmeeting_id' => $instanceid]));
    }

    /**
     * Two activities sharing a room both schedule it, which must not collide on the unique
     * conference id, and must leave a single pending record behind.
     */
    public function test_scheduling_the_same_room_twice_leaves_one_record(): void {
        global $DB;

        $owner = $this->getDataGenerator()->create_user();

        clickmeeting_schedule_room_deletion(777008, $owner->id);
        clickmeeting_schedule_room_deletion(777008, $owner->id);

        $this->assertEquals(1, $DB->count_records('clickmeeting_room_deletions', ['conference_id' => 777008]));
    }

    /**
     * The pending deletions table stores who owned a room, which is personal data and has to
     * be declared - Moodle's own privacy checks do not catch a "user_id" column on their own.
     */
    public function test_room_deletions_table_is_declared_to_the_privacy_api(): void {
        $collection = \mod_clickmeeting\privacy\provider::get_metadata(
            new \core_privacy\local\metadata\collection('mod_clickmeeting')
        );

        $tables = [];
        foreach ($collection->get_collection() as $type) {
            if ($type instanceof \core_privacy\local\metadata\types\database_table) {
                $tables[] = $type->get_name();
            }
        }

        $this->assertContains('clickmeeting_room_deletions', $tables);
    }

    /**
     * Moodle rejects table names longer than 28 characters on 4.1 and earlier, which this
     * plugin still supports. The limit was raised later, so a name that installs fine on 4.5
     * can still break every older site.
     */
    public function test_table_names_fit_the_moodle_name_limit(): void {
        global $CFG;

        $xml = simplexml_load_file($CFG->dirroot . '/mod/clickmeeting/db/install.xml');

        $this->assertNotFalse($xml, 'install.xml should be readable.');
        foreach ($xml->TABLES->TABLE as $table) {
            $name = (string) $table['NAME'];
            $this->assertLessThanOrEqual(28, strlen($name), "Table name {$name} is too long for Moodle 4.1.");
        }
    }
}
