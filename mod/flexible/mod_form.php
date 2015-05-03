<?php

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once('model.php');
class mod_flexible_mod_form extends moodleform_mod {

    var $plugins=array();
    /** Displays main options of flexible
     */
    function definition() {
        global $COURSE, $CFG, $DB;
        $mform =& $this->_form;
        
        // Adding the "general" fieldset
        //----------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field
        $mform->addElement('text', 'name', get_string('flexiblename', 'flexible'), array('size'=>'64'));
        $mform->addHelpButton('name', 'instancename', 'flexible');
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Adding the standard "intro" and "introformat" fields
        $this->add_intro_editor(true, get_string('flexibleintro', 'flexible'));

        // Adding filemanager field where teracher can attach file to the assignment
        $mform->addElement('filemanager', 'flexiblefiles', get_string('flexiblefiles', 'flexible'));
        $mform->addHelpButton('flexiblefiles', 'flexiblefiles', 'flexible');

        //$mform->addElement('filemanager', 'flexiblefiles', get_string('flexiblefiles', 'flexible'));

        $mform->addElement('date_time_selector', 'availabledate', get_string('availabledate', 'flexible'), array('optional'=>true));
        $mform->addHelpButton('availabledate', 'availabledate', 'flexible');
        $mform->setDefault('availabledate', time());

        $mform->addElement('date_time_selector', 'choicedate', get_string('choicedate', 'flexible'), array('optional'=>true));
        $mform->addHelpButton('choicedate', 'choicedate', 'flexible');
        $mform->setDefault('choicedate', time()+2*24*3600); // By default student have 2 days to choose task
        //$mform->disabledIf('choicedate', 'activateindividualtasks');

        
        $mform->addElement('checkbox', 'preventlatechoice', get_string('preventlatechoice', 'flexible'));
        $mform->addHelpButton('preventlatechoice', 'preventlatechoice', 'flexible');

        $mform->addElement('checkbox', 'randomtasksafterchoicedate', get_string('randomtasksafterchoicedate', 'flexible'));
        $mform->addHelpButton('randomtasksafterchoicedate', 'randomtasksafterchoicedate', 'flexible');

        $mform->addElement('date_time_selector', 'deadline', get_string('deadline', 'flexible'), array('optional'=>true));
        $mform->addHelpButton('deadline', 'deadline', 'flexible');
        $mform->setDefault('deadline', time()+7*24*3600); // By default student have 7 days to complete task

        $mform->addElement('checkbox', 'preventlate', get_string('preventlate', 'flexible'));
        $mform->addHelpButton('preventlate', 'preventlate', 'flexible');
        // Adding answers fieldset
        //----------------------------------------------------------------------
        global $COURSE, $CFG,$DB;
        $mform->addElement('header', 'answers', get_string('answers', 'flexible'));
        
        $mform->addElement('checkbox', 'severalattempts', get_string('severalattempts', 'flexible'));
        $mform->addHelpButton('severalattempts', 'severalattempts', 'flexible');
        
        $mform->addElement('checkbox', 'newattemptbeforegrade', get_string('newattemptbeforegrade', 'flexible'));
        $mform->addHelpButton('newattemptbeforegrade', 'newattemptbeforegrade', 'flexible');
        $mform->setAdvanced('newattemptbeforegrade');
        
        $mform->addElement('text', 'penalty', get_string('penalty', 'flexible'));
        $mform->addHelpButton('penalty', 'penalty', 'flexible');
        $mform->setDefault('penalty', 0);
        $mform->disabledIf('penalty', 'severalattempts', 'notchecked');
        
        $mform->addElement('checkbox','finalattempts',get_string('finalattempts','flexible'));
        $mform->addHelpButton('finalattempts','finalattempts','flexible');
        $mform->setAdvanced('finalattempts');
        $mform->disabledIf('finalattempts', 'severalattempts', 'notchecked');
        
        $mform->addElement('checkbox', 'notifyteachers', get_string('notifyteachers', 'flexible'));
        $mform->addHelpButton('notifyteachers', 'notifyteachers', 'flexible');
        $mform->setAdvanced('notifyteachers');
        
        $mform->addElement('checkbox', 'notifystudents', get_string('notifystudents', 'flexible'));
        $mform->addHelpButton('notifystudents', 'notifystudents', 'flexible');
        $mform->setAdvanced('notifystudents');
        
        // Adding answers fieldsets
        //----------------------------------------------------------------------
        $this->plugins = $DB->get_records('flexible_answers');
        foreach ($this->plugins as $plugin) { 
            require_once($plugin->path);
            $flexibleplugin = new $plugin->name();
            $flexibleplugin->show_settings($mform, $this->_instance);
        }

        // Adding individual tasks fieldset
        //----------------------------------------------------------------------
        $mform->addElement('header', 'flexiblefieldset', get_string('flexiblefieldset', 'flexible'));

        $mform->addElement('checkbox', 'activateindividualtasks', get_string('activateindividualtasks', 'flexible'));
        $mform->addHelpButton('activateindividualtasks', 'activateindividualtasks', 'flexible');

        // Adding taskgivers selectbox
        //----------------------------------------------------------------------
        $taskgivers=$DB->get_records('flexible_taskgivers');
        $names = array();
        foreach ($taskgivers as $taskgiver) {
            $names[$taskgiver->id] = get_string('pluginname', 'flexibletaskgivers_' . $taskgiver->name);
            //array_push($names, get_string('pluginname', 
            //                              'flexibletaskgivers_' . $taskgiver->name));
        }
        
        $mform->addElement('select', 
                           'taskgiverid', 
                           get_string('taskgiverid', 'flexible'),
                           $names);
                                
        $mform->disabledIf('taskgiverid', 'activateindividualtasks');
        $mform->addHelpButton('taskgiverid', 'taskgiverid', 'flexible');

        $mform->addElement('checkbox', 'secondchoice', get_string('secondchoice', 'flexible'));
        $mform->disabledIf('secondchoice', 'activateindividualtasks');
        $mform->addHelpButton('secondchoice', 'secondchoice', 'flexible');

        $mform->addElement('select', 'uniqueness', get_string('uniqueness', 'flexible'),
                            array(
                                get_string('nouniqueness', 'flexible'),
                                get_string('uniquewithingroup', 'flexible'),
                                get_string('uniquewithingrouping', 'flexible'),
                                get_string('uniquewithincourse', 'flexible')));
        $mform->disabledIf('uniqueness', 'activateindividualtasks');
        $mform->addHelpButton('uniqueness', 'uniqueness', 'flexible');

        $mform->addElement('checkbox', 'cyclicrandom', get_string('cyclicrandom', 'flexible'));
        $mform->disabledIf('cyclicrandom', 'activateindividualtasks');
        $mform->disabledIf('cyclicrandom', 'uniqueness', 'eq', 0);
        $mform->addHelpButton('cyclicrandom', 'cyclicrandom', 'flexible');
        
        $mform->addElement('checkbox', 'teacherapproval', get_string('teacherapproval', 'flexible'));
        $mform->disabledIf('teacherapproval', 'activateindividualtasks');
        $mform->addHelpButton('teacherapproval', 'teacherapproval', 'flexible');

        // Adding graders list
        //----------------------------------------------------------------------
        
        $mform->addElement('header', 'flexiblegraderslist', get_string('flexiblegraderslist', 'flexible'));
        
        $this->graders=$DB->get_records('flexible_graders');
        foreach ($this->graders as $graderrecord) {
            require_once($graderrecord->path);
            $mform->addElement('checkbox',$graderrecord->name,get_string($graderrecord->name,'flexible_'.$graderrecord->name));
            $conditions = array('flexibleid' => $this->_instance, 'graderid' => $graderrecord->id);
            if($DB->record_exists('flexible_used_graders',$conditions))
                $mform->setDefault($graderrecord->name,'true');
        }

        // add standard elements, common to all modules
        //----------------------------------------------------------------------
        $this->standard_coursemodule_elements();

        // add standard buttons, common to all modules
        //----------------------------------------------------------------------
        $this->add_action_buttons();
    }

    /** Load files and flags from existing module
     */
    function data_preprocessing(&$default_values){
    
        if(!isset($default_values['intro'])) {
            $default_values['introeditor'] = array('text' => '<p>' . get_string('defaultintro', 'flexible') . '</p>');
        }
        if (isset($default_values['flags'])) {
            $flags = (int)$default_values['flags'];
            unset($default_values['flags']);
            $default_values['preventlatechoice'] = $flags & PREVENT_LATE_CHOICE;
            $default_values['randomtasksafterchoicedate'] = $flags & RANDOM_TASKS_AFTER_CHOICEDATE;
            $default_values['preventlate'] = $flags & PREVENT_LATE;
            $default_values['severalattempts'] = $flags & SEVERAL_ATTEMPTS;
            $default_values['notifyteachers'] = $flags & NOTIFY_TEACHERS;
            $default_values['notifystudents'] = $flags & NOTIFY_STUDENTS;
            $default_values['activateindividualtasks'] = $flags & ACTIVATE_INDIVIDUAL_TASKS;
            $default_values['secondchoice'] = $flags & SECOND_CHOICE;
            $default_values['teacherapproval'] = $flags & TEACHER_APPROVAL;
            $default_values['newattemptbeforegrade'] = $flags & ALL_ATTEMPTS_AS_ONE;
            $default_values['finalattempts'] = $flags & MATCH_ATTEMPT_AS_FINAL;
            $default_values['cyclicrandom'] = $flags & flexible_CYCLIC_RANDOM;
        }
        if ($this->current->instance) {
            $draftitemid = file_get_submitted_draft_itemid('flexiblefiles');
            file_prepare_draft_area($draftitemid, $this->context->id, 'mod_flexible', 'flexiblefiles', 0, array('subdirs'=>true));
            $default_values['flexiblefiles'] = $draftitemid;
        }
        if(isset($default_values['taskgiverid'])) {
            //echo ' ��������� � '.$default_values['taskgiverid'];
            $default_values['taskgiverid'] = $default_values['taskgiverid']/*-1*/;
        }
        
    }

    /** Check dates
     */
    function validation($data, $files) {
        $errors = parent::validation($data, $files);
        
        // Check open and close times are consistent.
        if ($data['availabledate'] != 0 && $data['choicedate'] != 0 && $data['choicedate'] < $data['availabledate']) {
            $errors['choicedate'] = get_string('choicebeforeopen', 'flexible');
        }
        if ($data['availabledate'] != 0 && $data['deadline'] != 0 && $data['deadline'] < $data['availabledate']) {
            $errors['deadline'] = get_string('deadlinebeforeopen', 'flexible');
        }
        if ($data['choicedate'] != 0 && $data['deadline'] != 0 && $data['deadline'] < $data['choicedate']) {
            $errors['deadline'] = get_string('deadlinebeforechoice', 'flexible');
        }
        
        foreach ($this->plugins as $plugin) { 
            $pluginname=$plugin->name;
            $pluginname::validation($data,$errors);
        }
        
        foreach ($this->graders as $grader) { 
            $gradername = $grader->name;
            $gradername::validation($data,$errors);
        }
        
        if (count($errors) == 0) {
            return true;
        } else {
            return $errors;
        }        
        
        // TODO validate graders
    }
}