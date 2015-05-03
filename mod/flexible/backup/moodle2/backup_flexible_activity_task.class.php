<?php

require_once($CFG->dirroot . '/mod/flexible/backup/moodle2/backup_flexible_stepslib.php'); // Because it exists (must)

class backup_flexible_activity_task extends backup_activity_task {

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
        // Choice only has one structure step
        $this->add_step(new backup_flexible_activity_structure_step('flexible_structure', 'flexible.xml'));
    }

    /**
     * Code the transformations to perform in the activity in
     * order to get transportable (encoded) links
     */
    static public function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot,"/");

        // Link to the list of assignments
        $search="/(".$base."/mod/flexible/index.php/?id/=)([0-9]+)/";
        $content= preg_replace($search, '$@flexibleINDEX*$2@$', $content);

        // Link to assignment view by moduleid
        $search="/(".$base."/mod/flexible/view.php/?id/=)([0-9]+)/";
        $content= preg_replace($search, '$@flexibleVIEWBYID*$2@$', $content);

        return $content;
    }
}
