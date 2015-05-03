<?php
require_once('abstract_page.php');
require_once(dirname(dirname(__FILE__)) . '/model.php');
class taskgiversettings_page extends abstract_page {
    //var $flexible;
    
    function __construct($cm, $flexible) {
        //$this->flexible = $flexible;
        //$this->cm = $cm;
    }
    
    function get_cap() {
        return 'mod/flexible:grade';
    }
     function has_satisfying_parameters() {
        if(!flexible_model::get_instance()->has_flag(ACTIVATE_INDIVIDUAL_TASKS)) {
            $this->lasterror = 'errorindtaskmodeisdisabled';
            return false;
        }
        global $DB;
        $tgid = flexible_model::get_instance()->get_flexible()->taskgiverid;
        if(!$DB->record_exists('flexible_taskgivers', array('id' => $tgid))) {
            $this->lasterror = 'errorindtaskmodeisdisabled';
            return false;
        }
        else {
            $tg = $DB->get_record('flexible_taskgivers', array('id' => $tgid));
            $tgname = $tg->name;
            require_once(dirname(dirname(__FILE__)) . '/' . $tg->path);
            if (!$tgname::has_settings()) {
                $this->lasterror = 'errorthistghasntsettings';
                return false;
            }
        }
        return true;
    }
    
    function view() {
        global $DB;
        $model = flexible_model::get_instance();
        $id = $model->get_cm()->id;
        $flexibleid = $model->get_flexible()->id;
        $taskgiverrec = $DB->get_record('flexible_taskgivers', array('id' => $model->get_flexible()->taskgiverid));
        require_once($taskgiverrec->path);
        $taskgivername = $taskgiverrec->name;
        $taskgiver = new $taskgivername();
        echo '<div align="center"><b><big>' .
             get_string('currenttaskgiver', 'flexible') .
             ' : ' .
             get_string('pluginname', "flexibletaskgivers_$taskgivername") .
             '</big></b></div><br>';
        $mform = $taskgiver->get_settings_form($id, $flexibleid);
        $data = $taskgiver->get_settings($flexibleid);
        $mform->set_data($data);
        if($mform->get_data()) {
            $taskgiver->save_settings($mform->get_data());
        }
        $mform->display(); 
    }
    
}