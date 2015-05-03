<?php
global $CFG;
require_once dirname(dirname(__FILE__)).'/taskgiver.php';
require_once($CFG->libdir.'/formslib.php');
class randomchoice extends taskgiver {

    public static function has_settings() {
        return false;
    }
    public static function show_tasks() {
        return false;
    }

    function process_before_output($cmid, $flexible) {
        global $USER;
        $model = flexible_model::get_instance();
        if (has_capability('mod/flexible:havetask', $model->get_context()) && !$model->check_dates()) {
            if (!flexible_model::user_have_active_task($USER->id, $flexible->id)) {
                $tasks = $model->get_available_tasks($USER->id);
                $taskid = flexible_model::get_random_task_id($tasks);

                if($taskid > -1) {
                    $poasmodel = flexible_model::get_instance($flexible);
                    $poasmodel->bind_task_to_assignee($USER->id, $taskid);
                    redirect(new moodle_url('view.php',array('id'=>$cmid,'page'=>'view')),null,0);
                }
                else {
                    print_error('noavailabletask', 'flexibletaskgivers_randomchoice', new moodle_url('/mod/flexible/view.php',
                            array('id'=>$model->get_cm()->id, 'page' => 'view')));
                }
            }
        }
    }
}
?>
