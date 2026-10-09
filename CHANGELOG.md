# Changelog

## v1.2.0 (2026-10-08)

Fixes meetings and recordings disappearing from ClickMeeting without anyone deleting them
on purpose.

### Fixed

- **Deleting an activity no longer destroys the room immediately.** Moodle activity deletion
  is reversible — it goes through the recycle bin and runs from cron — but the room in
  ClickMeeting was being deleted right away. Restoring the activity brought back a link to a
  room that no longer existed. Rooms now outlive their activity by a configurable grace
  period, five months by default.
- **A room shared by several activities survives.** Copying, importing or restoring a course
  duplicates the activity while both copies keep pointing at the same ClickMeeting room.
  Deleting either copy destroyed the room for the other one, in a course nobody had touched.
  A room is now only deleted once no activity refers to it.
- **Deletions use the room owner's API key.** The key was taken from whoever happened to be
  acting, which under cron is nobody in particular, so the request fell back to the main
  account key and could reach a room on a different subaccount.
- **Activities whose room record is missing can be deleted again.** Previously the deletion
  failed indefinitely and the activity could never be removed from the course.
- **Access tokens are removed with their activity.** They identify participants and were
  being left behind in the database.

### Added

- Admin setting "Keep deleted rooms for" (`clickmeeting/graceperiod`), default five months.
- Scheduled task `mod_clickmeeting\task\purge_deleted_rooms`, which deletes rooms once their
  grace period has passed. Runs daily at 03:20.
- Event `mod_clickmeeting\event\room_deleted`, logged whenever a room is really deleted, so
  a missing meeting can be traced back to when and why it went away.
- PHPUnit coverage for the deletion lifecycle.
- Privacy API support for the new table: the room owner is discoverable and is cleared on an
  erasure request, while the room itself stays scheduled for deletion.

### Compatibility

Declares `$plugin->supported = [39, 503]`: Moodle 3.9 LTS through 5.3, each verified with a
clean install and a v1.1.5 upgrade. This is advisory - it does not block installation - and
makes the plugin show as compatible for Moodle 5.x in the plugins directory.

### Upgrade notes

Adds the table `clickmeeting_room_deletions`. Existing data is untouched. Rooms already
deleted before this release cannot be recovered.
