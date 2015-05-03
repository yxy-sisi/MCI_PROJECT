<?php
global $CFG;
require_once('abstract_page.php');
require_once(dirname(dirname(__FILE__)) . '/model.php');

class submission_page extends abstract_page {
    private $mform;
    function __construct() {
    }
    function get_cap() {
        return 'mod/flexible:havetask';
    }
    function has_satisfying_parameters() {
        // TODO
        return true;
    }
    public function pre_view() {
        global $DB, $OUTPUT, $USER;
        $model = flexible_model::get_instance();
        $flexibleid = $model->get_flexible()->id;
        $this->mform = new answer_form(null, array('flexibleid' => $flexibleid,
                                           'userid' => $USER->id,
                                           'id' => $model->get_cm()->id));
        $plugins = $model->get_plugins();
        if (has_capability('mod/flexible:viewownsubmission', $model->get_context())) {
            foreach($plugins as $plugin) {
                if (flexible_answer::used_in_flexible($plugin->id, $flexibleid)) {
                    require_once($plugin->path);
                    $flexibleplugin = new $plugin->name();
                    $preloadeddata = $flexibleplugin->get_answer_values($flexibleid);
                    $this->mform->set_data($preloadeddata);
                }
            }
        }
        if ($this->mform->is_cancelled()) {
            redirect(new moodle_url('view.php', array('id' => $model->get_cm()->id,'page' => 'view')), null, 0);
        }
        else {
            if ($this->mform->get_data()) {
                $data = $this->mform->get_data();
                //save data
                $assignee = $model->get_assignee($USER->id);
                $model->cash_assignee_by_user_id($USER->id);
                $attemptid = $model->save_attempt($data);
                foreach($plugins as $plugin) {
                    if(flexible_answer::used_in_flexible($plugin->id, $flexibleid)) {
                        require_once($plugin->path);
                        $answerplugin = new $plugin->name();
                        $answerplugin->save_submission($attemptid, $data);
                    }
                }
                // save attempt as last attempt of this assignee
                $model->assignee->lastattemptid = $attemptid;
                //echo '...lastattemptid='.$attemptid;
                $DB->update_record('flexible_assignee', $model->assignee);
                
                // trigger flexibleevent
                $model->trigger_flexible_event(ATTEMPT_DONE, $model->assignee->id);
                
                //noitify teacher if needed
                $model->email_teachers($model->assignee);
                
                $model->evaluate_attempt($attemptid);
                
                redirect(new moodle_url('view.php', 
                                        array('id'=>$model->get_cm()->id, 'page'=>'view')), 
                                        null, 
                                        0);
            }
        }
    }
    function view() {
        global $OUTPUT;
        echo $OUTPUT->box_start('generalbox boxaligncenter', 'intro');
        $this->mform->display();
        echo $OUTPUT->box_end();
    }
    public static function display_in_navbar() {
        return false;
    }
}