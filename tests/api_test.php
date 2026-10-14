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
 * Tests for the requests the plugin puts on the wire.
 *
 * Mocked responses say nothing about what was actually sent, and the bug these tests cover lives
 * entirely in the request: an edit that reached the API carrying no fields at all. So these tests
 * run a throwaway HTTP server, point the plugin at it and read back the requests it received.
 *
 * @package    mod_clickmeeting
 * @copyright  2026 Clickmeeting
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class api_test extends \advanced_testcase {
    /** @var resource|null Handle of the stub API server, closed after each test. */
    private $server = null;

    /** @var string Directory the stub server records received requests into. */
    private string $serverdir = '';

    /**
     * Loads the plugin library under test.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/clickmeeting/lib.php');
        require_once($CFG->dirroot . '/course/lib.php');
        parent::setUpBeforeClass();
    }

    /**
     * Sets up a user, since every API call looks up its caller's API key.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Stops the stub server so no process outlives the test.
     */
    protected function tearDown(): void {
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
            $this->server = null;
        }
        parent::tearDown();
    }

    /**
     * Starts a stub ClickMeeting API on a free port and points the plugin at it.
     *
     * @param array $routes Responses keyed by "METHOD uri-fragment", matched in order.
     * @param string $default Response for any request none of the routes matches.
     */
    private function start_stub_api(array $routes, string $default = '{}'): void {
        $this->serverdir = make_request_directory();
        file_put_contents($this->serverdir . '/routes.json', json_encode([
            'routes' => $routes,
            'default' => $default,
        ]));
        file_put_contents($this->serverdir . '/router.php', <<<'PHP'
        <?php
        $request = [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'body' => file_get_contents('php://input'),
        ];
        file_put_contents(__DIR__ . '/requests.jsonl', json_encode($request) . "\n", FILE_APPEND);

        $routes = json_decode(file_get_contents(__DIR__ . '/routes.json'), true);
        $body = $routes['default'];
        foreach ($routes['routes'] as $route => $response) {
            [$method, $fragment] = explode(' ', $route, 2);
            if ($method === $request['method'] && str_contains($request['uri'], $fragment)) {
                $body = $response;
                break;
            }
        }

        header('Content-Type: application/json');
        echo $body;
        PHP);

        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false);
        $port = (int)substr($address, strrpos($address, ':') + 1);
        fclose($socket);

        $devnull = ['file', '/dev/null', 'w'];
        $this->server = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $this->serverdir, $this->serverdir . '/router.php'],
            [1 => $devnull, 2 => $devnull],
            $pipes
        );

        for ($attempt = 0; $attempt < 100; $attempt++) {
            $probe = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
            if ($probe !== false) {
                fclose($probe);
                set_config('apiurl', 'http://127.0.0.1:' . $port . '/', 'clickmeeting');
                return;
            }
            usleep(50000);
        }

        $this->fail('The stub ClickMeeting API did not come up on port ' . $port . '.');
    }

    /**
     * Returns the requests the stub API received, oldest first.
     *
     * @return array List of requests, each with method, uri and raw body.
     */
    private function get_received_requests(): array {
        $recorded = $this->serverdir . '/requests.jsonl';
        $this->assertFileExists($recorded, 'The plugin never reached the API.');

        $requests = [];
        foreach (file($recorded, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $requests[] = json_decode($line, true);
        }

        return $requests;
    }

    /**
     * Returns the first recorded request made with the given method.
     *
     * @param string $method HTTP method to look for.
     * @return array The request, with method, uri and raw body.
     */
    private function get_received_request(string $method): array {
        foreach ($this->get_received_requests() as $request) {
            if ($method === $request['method']) {
                return $request;
            }
        }

        $this->fail('No ' . $method . ' request reached the API.');
    }

    /**
     * Editing a room has to carry the new values; a PUT with an empty body leaves the room
     * exactly as it was, which is how renaming or rescheduling an activity silently did nothing.
     */
    public function test_editing_a_room_sends_the_changed_fields(): void {
        $this->start_stub_api([], '{"room":{"id":777911}}');

        clickmeeting_edit_conference(777911, [
            'name' => 'Renamed meeting',
            'starts_at' => '2026-11-20 10:00:00',
            'duration' => 3,
        ]);

        $request = $this->get_received_request('PUT');
        $this->assertStringContainsString('conferences/777911', $request['uri']);
        parse_str($request['body'], $sent);
        $this->assertSame('Renamed meeting', $sent['name']);
        $this->assertSame('2026-11-20 10:00:00', $sent['starts_at']);
        $this->assertSame('3', $sent['duration']);
    }

    /**
     * Renaming an activity inline on the course page goes through its own, narrower call, which
     * has to put the new title on the wire just the same.
     */
    public function test_editing_a_room_title_sends_the_new_title(): void {
        $this->start_stub_api([], '{"room":{"id":777911}}');

        clickmeeting_edit_conference_title(777911, 'Renamed inline');

        $request = $this->get_received_request('PUT');
        parse_str($request['body'], $sent);
        $this->assertSame('Renamed inline', $sent['name']);
    }

    /**
     * The whole path a teacher takes when they edit an activity: the new name and start date have
     * to reach the room, not just the Moodle database.
     */
    public function test_updating_an_activity_sends_the_new_name_and_date_to_the_api(): void {
        global $COURSE, $DB;

        $course = $this->getDataGenerator()->create_course();
        $COURSE = $course;
        // The edit form posts the section back and the update refuses to run without it.
        $_POST['section'] = 0;

        $clickmeeting = (object)[
            'course' => $course->id,
            'name' => 'Original name',
            'description' => '',
            'room_type' => 'meeting',
            'lobby_msg' => 'Please wait',
            'access_type' => '1',
            'timestart' => time() + DAYSECS,
            'start_time' => date('Y-m-d H:i:s', time() + DAYSECS),
            'duration' => 2,
            'user_id' => get_admin()->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $clickmeeting->id = $DB->insert_record('clickmeeting', $clickmeeting);
        $DB->insert_record('clickmeeting_conferences', (object)[
            'clickmeeting_id' => $clickmeeting->id,
            'conference_id' => 777911,
            'room_url' => 'https://example.clickmeeting.com/room',
            'embed_room' => 'https://example.clickmeeting.com/room/embed',
            'room_pin' => 111111,
            'password' => 'roompassword',
        ]);

        $this->start_stub_api([
            'GET conferences/777911' => '{"conference":{"status":"active"}}',
            'PUT conferences/777911' => '{"room":{"id":777911}}',
        ], '{}');

        $newstart = time() + (5 * DAYSECS);
        $update = clone $clickmeeting;
        $update->instance = $clickmeeting->id;
        $update->name = 'Renamed by the teacher';
        $update->timestart = $newstart;

        clickmeeting_update_instance($update);

        $request = $this->get_received_request('PUT');
        parse_str($request['body'], $sent);
        $this->assertSame('Renamed by the teacher', $sent['name']);
        $this->assertSame(date('Y-m-d H:i:s', $newstart), $sent['starts_at']);
    }
}
