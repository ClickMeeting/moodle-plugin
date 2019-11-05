<?php

require_once($CFG->dirroot . '/mod/clickmeeting/backup/moodle2/backup_clickmeeting_stepslib.php');
require_once($CFG->dirroot . '/mod/clickmeeting/backup/moodle2/backup_clickmeeting_settingslib.php');

/**
 * clickmeeting backup task that provides all the settings and steps to perform one
 * complete backup of the activity
 */
class backup_clickmeeting_activity_task extends backup_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        // clickmeeting only has one structure step
        $this->add_step(new backup_clickmeeting_activity_structure_step('clickmeeting_structure', 'clickmeeting.xml'));
    }

    /**
     * Code the transformations to perform in the activity in
     * order to get transportable (encoded) links
     */
    static public function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot,"/");

        // Link to the list of clickmeeting
        $search="/(".$base."\/mod\/clickmeeting\/index.php\?id\=)([0-9]+)/";
        $content= preg_replace($search, '$@CLICKMEETINGINDEX*$2@$', $content);

        // Link to clickmeeting view by moduleid
        $search="/(".$base."\/mod\/clickmeeting\/view.php\?id\=)([0-9]+)/";
        $content= preg_replace($search, '$@CLICKMEETINGVIEWBYID*$2@$', $content);

        return $content;
    }
}