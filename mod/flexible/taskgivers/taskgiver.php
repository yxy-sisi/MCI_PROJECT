<?php

/**
 * Description of taskgiver
 *
 * @author Arkanif
 */
abstract class taskgiver {
    public function process_before_output($cmid, $flexible) {}
    public function process_before_tasks($cmid, $flexible) {}
    public function get_task_extra_string($taskid, $cmid){}
    public function process_after_tasks() {}
    public function get_settings_form($id, $flexibleid) {}
    public function get_settings($flexibleid) {}
    public function save_settings($data) {}
    public function delete_settings($flexibleid) {}
    public static function has_settings() {
        return false;
    }
    public static function show_tasks() {
        return false;
    }
}
?>