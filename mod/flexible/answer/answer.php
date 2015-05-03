<?php 
require_once($CFG->dirroot.'/course/moodleform_mod.php');
class flexible_answer {

    var $answerid;
    
    function __construct() {
    }

    /**
     * Displays subplugin's settings in mod_form.php
     *
     * @param $mform - POAS assignment mod_form.php moodle form
     * @param $flexibleid - POAS assignment instance id
     */
    function show_settings($mform, $flexibleid) {
    }
    
    // Vaildates subplugin's settigns in mod_form.php
    static function validation($data, &$errors) {
    }

    /**
     * Displays form to input an answer
     *
     * @param $mform - POAS assignment mod_form.php moodle form
     * @param $flexibleid - POAS assignment instance id
     */
    function show_answer_form($mform, $flexibleid) {
    }
    
    // Saves subplugin settings in DB
    function save_settings($flexible, $id) {
    }
    // Delete all subplugin settings from DB
    function delete_settings($flexibleid) {
        global $DB;
        return $DB->delete_records('flexible_ans_stngs',array('flexibleid'=>$flexibleid));
    }
    
    function return_settings_type($flexibleid,$type) {
    }
    
    function delete_settings_type($flexibleid, $type) {
    }
    
    // Returns true, if plugin with $answerid subplugin is used in flexible with $flexibleid
    static function used_in_flexible($answerid,$flexibleid) {
        global $DB;
        return $DB->record_exists('flexible_ans_stngs',array('flexibleid'=>$flexibleid,
                                                                'answerid'=>$answerid));    
    }
    
    public function save_submission($attemptid, $data) {
    }
    
    function bind_submission_to_attempt($assigneeid,$draft,$final=0) {
        global $DB;
        $attemptscount=$DB->count_records('flexible_attempts',array('assigneeid'=>$assigneeid));
        
        //echo $draft;
        $newattempt=new stdClass();
        $newattempt->assigneeid=$assigneeid;
        $newattempt->attemptdate=time();
        $newattempt->disablepenalty=0;
        $newattempt->draft=$draft;
        $newattempt->final=$final;
        if($draft)
            $newattempt->disablepenalty=1;
        
        if($attemptscount==0) {
            $newattempt->attemptnumber=1;
            $attemptid=$DB->insert_record('flexible_attempts',$newattempt);
        }
        if($attemptscount>0) {
            $attempt=$DB->get_record('flexible_attempts',array('assigneeid'=>$assigneeid,'attemptnumber'=>$attemptscount));
            if(!$DB->record_exists('flexible_submissions',array('answerid'=>$this->answerid,'attemptid'=>$attempt->id)))
                $attemptid=$attempt->id;
            else {
                $newattempt->attemptnumber=$attemptscount+1;
                $newattempt->ratingdate=$attempt->ratingdate;
                $newattempt->rating=$attempt->rating;
                $attemptid=$DB->insert_record('flexible_attempts',$newattempt);
            }
        }
        return $attemptid;
    }
    public function show_attempt_submission($attemptid) {
    }

    /**
     * Validate user's submission.
     *
     * @param array $data data
     * @param array $files files
     * @return true | array of errors
     */
    public static function validate_submission($data, $files) {
        return array();
    }
    
    
}

class answer_form extends moodleform {
    function definition() {        
        global $DB;
        $mform = $this->_form;
        $instance = $this->_customdata;
        $model = flexible_model::get_instance();
        $model->cash_instance($instance['flexibleid']);
        $plugins=$DB->get_records('flexible_answers');
        foreach($plugins as $plugin) {
            if(flexible_answer::used_in_flexible($plugin->id, $instance['flexibleid'])) {
                require_once($plugin->path);
                $flexibleplugin = new $plugin->name();
                $flexibleplugin->show_answer_form($mform, $instance['flexibleid']);
            }
        }
        
        $mform->addElement('header', 'submissionoptions', get_string('submissionoptions', 'flexible'));
        
        $mform->addElement('checkbox', 'draft', get_string('draft', 'flexible'));
        
        //$flexible  = $DB->get_record('flexible', array('id' => $instance['flexibleid']), '*', MUST_EXIST);


        //$model = flexible_model::get_instance($flexible);
        
        if($model->get_flexible()->flags & MATCH_ATTEMPT_AS_FINAL) {
            $mform->addElement('checkbox','final',get_string('final','flexible'));
        }        
        
        $mform->addElement('hidden', 'flexibleid', $instance['flexibleid']);
        $mform->setType('flexibleid', PARAM_INT);
        
        
        $mform->addElement('hidden', 'id', $instance['id']);
        $mform->setType('id', PARAM_INT);
        
        $mform->addElement('hidden', 'userid', $instance['userid']);
        $mform->setType('userid', PARAM_INT);
        
        $mform->addElement('hidden', 'page', 'submission');
        $mform->setType('userid', PARAM_TEXT);
        
        $this->add_action_buttons(true,get_string('sendsubmission', 'flexible'));
    }

    function validation($data, $files) {
        $errors = array();
        $plugins = flexible_model::get_instance()->get_plugins();
        foreach($plugins as $plugin) {
            if(flexible_answer::used_in_flexible($plugin->id, $data['flexibleid'])) {
                require_once($plugin->path);
                $pluginname = $plugin->name;
                $errors = array_merge($errors, $pluginname::validate_submission($data, $files));
            }
        }
        return $errors;
    }
}