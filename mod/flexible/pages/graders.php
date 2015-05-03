<?php
global $CFG;
require_once('abstract_page.php');
require_once(dirname(dirname(__FILE__)) . '/model.php');
require_once($CFG->libdir.'/formslib.php');
class graders_page extends abstract_page {
    //var $flexible;
    
    function __construct(/* $cm, $flexible */) {
        //$this->flexible = $flexible;
        //$this->cm = $cm;
    }
    
    function get_cap() {
        return 'mod/flexible:grade';
    }
    
    function has_satisfying_parameters() {
        global $DB;
        if(!$DB->record_exists('flexible_used_graders',
                               array('flexibleid' => flexible_model::get_instance()->get_flexible()->id))) {
            $this->lasterror = 'errornograderused';
            return false;
        }
        return true;
    }
    function view() {
        global $DB,$OUTPUT;
        $model = flexible_model::get_instance();
        $id = $model->get_cm()->id;
        //$id = $this->cm->id;
        $flexibleid = $model->get_flexible()->id;
        //$flexibleid = $this->flexible->id;
        $mform = new graderssettings_form(null, array('id' => $id, 'flexibleid' => $flexibleid));
        $graders = $DB->get_records('flexible_used_graders', array('flexibleid' => $flexibleid));
            
        if($mform->get_data()) {
            foreach($graders as $graderrecord) {
                $usedgraderrecord = $DB->get_record('flexible_graders', array('id' => $graderrecord->graderid));
                require_once($usedgraderrecord->path);
                $gradername = $usedgraderrecord->name;
                $gradername::save_settings($mform->get_data(), $flexibleid);
            }
        }
        foreach($graders as $graderrecord) {
            $usedgraderrecord = $DB->get_record('flexible_graders', array('id' => $graderrecord->graderid));
            require_once($usedgraderrecord->path);
            $gradername = $usedgraderrecord->name;
            $mform->set_data($gradername::get_settings($flexibleid));
        }
        $mform->display();
    }
}

class graderssettings_form extends moodleform {
    function definition(){
        global $DB;
        $mform = $this->_form;
        $instance = $this->_customdata;
        
        // Show settings for all used graders
        $graders = $DB->get_records('flexible_used_graders', array('flexibleid' => $instance['flexibleid']));
        foreach($graders as $graderrecord) {
            $usedgraderrecord = $DB->get_record('flexible_graders', array('id' => $graderrecord->graderid));
            require_once($usedgraderrecord->path);
            $gradername = $usedgraderrecord->name;
            $grader = $gradername::show_settings($mform, $graderrecord->id, $instance['flexibleid']);
        }
        
        $mform->addElement('hidden', 'id', $instance['id']);
        $mform->setType('id', PARAM_INT);
        
        $mform->addElement('hidden', 'page', 'graders');
        $mform->setType('page', PARAM_TEXT);
        
        $this->add_action_buttons(false, get_string('savechanges', 'admin'));
    }
}