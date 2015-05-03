<?php
require_once(dirname(dirname(__FILE__)).'/answer.php');
//require_once('answer.php');

class answer_file extends flexible_answer {
    var $checked;
    var $fieldnames = array ( 'fileamount','maxfilesize','fileextensions');
    function __construct() {
        global $DB;
        $plugin = $DB->get_record('flexible_answers',
                                  array('name' => 'answer_file'));
        if ($plugin) {
            $this->answerid = $plugin->id;
        }
    }
    
    /** Display plugin settings 
     *
     *  Display separate fieldset with plugin settings
     */
    function show_settings($mform,$flexibleid) {
        global $CFG, $COURSE, $DB;
        
        // Adding header
        //----------------------------------------------------------------------
        $mform->addElement('header',
                           'answerfileheader',
                           get_string('pluginname', 'flexibleanswertypes_answer_file'));
                           
        // Adding selection checkbox
        //----------------------------------------------------------------------
        $mform->addElement('checkbox',
                           'answerfile',
                           get_string('answerfile', 'flexibleanswertypes_answer_file'));
        
        $conditions = array('flexibleid' => $flexibleid,
                            'answerid' => $this->answerid);
        if ($DB->record_exists('flexible_ans_stngs', $conditions))
            $mform->setDefault('answerfile', 'true');
        $mform->addHelpButton('answerfile', 
                              'answerfile', 
                              'flexibleanswertypes_answer_file');
        
        // Adding file amount counter
        //----------------------------------------------------------------------
        $mform->addElement('select', 
                           'fileamount', 
                           get_string('submissionfilesamount', 
                                      'flexibleanswertypes_answer_file'),
                           array(
                                   -1=>get_string('any', 'flexibleanswertypes_answer_file'),
                                   1=>1,
                                   2=>2,
                                   3=>3,
                                   4=>4,
                                   5=>5,
                                   6=>6,
                                   7=>7,
                                   8=>8,
                                   9=>9,
                                   10=>10,
                                   15=>15,
                                   20=>20,
                                   30=>30,
                                   50=>50));
        $conditions = array('flexibleid' => $flexibleid,
                            'answerid' => $this->answerid,
                            'name' => 'fileamount');
        if ($DB->record_exists('flexible_ans_stngs', $conditions)) {
            $rec = $DB->get_record('flexible_ans_stngs', $conditions);
            $mform->setDefault('fileamount', $rec->value);
        }
        $mform->disabledIf('fileamount', 'answerfile');
        $mform->addHelpButton('fileamount', 
                              'submissionfilesamount', 
                              'flexibleanswertypes_answer_file');
        
        // Adding maximum upload size selectbox
        //----------------------------------------------------------------------
        
        $choices = get_max_upload_sizes($CFG->maxbytes, $COURSE->maxbytes);
        $choices[0] = get_string('courseuploadlimit') . ' (' . display_size($COURSE->maxbytes) . ')';
        $mform->addElement('select', 
                           'maxfilesize', 
                           get_string('submissionfilemaxsize', 
                                      'flexibleanswertypes_answer_file'),
                           $choices);
        $conditions = array('flexibleid' => $flexibleid,
                            'answerid' => $this->answerid,
                            'name' => 'maxfilesize');
        if ($DB->record_exists('flexible_ans_stngs', $conditions)) {
            $rec = $DB->get_record('flexible_ans_stngs', $conditions);
            $mform->setDefault('maxfilesize', $rec->value);
        }
        $mform->disabledIf('maxfilesize', 'answerfile');
        $mform->addHelpButton('maxfilesize', 
                              'submissionfilemaxsize', 
                              'flexibleanswertypes_answer_file');
        
        // Adding file extensions string
        //----------------------------------------------------------------------
        $mform->addElement('text', 
                           'fileextensions', 
                           get_string('fileextensions', 'flexibleanswertypes_answer_file'),
                           array('size' => '64'));
        $conditions = array('flexibleid' => $flexibleid,
                            'answerid' => $this->answerid,
                            'name' => 'fileextensions');
        if ($DB->record_exists('flexible_ans_stngs', $conditions)) {
            $rec = $DB->get_record('flexible_ans_stngs', $conditions);
            $mform->setDefault('fileextensions', $rec->value);
            }
        $mform->addHelpButton('fileextensions', 
                              'fileextensions', 
                              'flexibleanswertypes_answer_file');
        $mform->disabledIf('fileextensions', 'answerfile');
    }
    
    static function validation($data, &$errors) {
        if ( isset($data['answerfile']) && $data['fileextensions'] !== '') {
            // Must look like *.txt, *.ogg, *.doc
            if (preg_match('/^([a-zA-Z]+|(\*\.[a-zA-Z0-9]+))(,(\s)?([a-zA-Z]+|(\*\.[a-zA-Z0-9]+)))*$/', $data['fileextensions']) == 0)
                $errors['fileextensions'] = get_string('incorrectextensions','flexibleanswertypes_answer_file');
            return $errors;
        }
    }
    function save_settings($flexible, $id) {
        global $DB;
        if ($this->checked) {
            $settingsrecord = new stdClass();

            $settingsrecord->flexibleid = $id;
            $settingsrecord->answerid = $this->answerid;
            $settingsrecord->name = 'fileamount';
            $settingsrecord->value = $flexible->fileamount;
            $DB->insert_record('flexible_ans_stngs', $settingsrecord);
            
            $settingsrecord->name = 'maxfilesize';
            $settingsrecord->value = $flexible->maxfilesize;
            $DB->insert_record('flexible_ans_stngs', $settingsrecord);
            
            $settingsrecord->name = 'fileextensions';
            $settingsrecord->value = $flexible->fileextensions;
            $DB->insert_record('flexible_ans_stngs', $settingsrecord);
        }
    }
    function update_settings($flexible) {
        global $DB;
        $conditions = array('flexibleid' => $flexible->id, 'answerid' => $this->answerid);
        $recordexists = $DB->record_exists('flexible_ans_stngs', $conditions);
        if (!$recordexists)
            $this->save_settings($flexible, $flexible->id);
        if ($recordexists && !$this->checked)
            $this->delete_settings($flexible->id);
        if ($recordexists && $this->checked) {
            $settingsrecord = new stdClass();
            $settingsrecord->flexibleid = $flexible->id;
            $settingsrecord->answerid = $this->answerid;            
            $conditions = array('flexibleid' => $flexible->id,
                                'name' => 'fileamount');
            $currentsetting = $DB->get_record('flexible_ans_stngs',$conditions);
            $settingsrecord->id = $currentsetting->id;
            $settingsrecord->name ='fileamount';
            $settingsrecord->value = $flexible->fileamount;
            $DB->update_record('flexible_ans_stngs',$settingsrecord);
            
            $conditions = array('flexibleid'=>$flexible->id,
                                'name'=>'maxfilesize');
            $currentsetting = $DB->get_record('flexible_ans_stngs',$conditions);
            $settingsrecord->id = $currentsetting->id;
            $settingsrecord->name = 'maxfilesize';
            $settingsrecord->value = $flexible->maxfilesize;
            $DB->update_record('flexible_ans_stngs', $settingsrecord);
            
            $conditions = array('flexibleid' => $flexible->id,
                                'name'=>'fileextensions');
            $currentsetting = $DB->get_record('flexible_ans_stngs', $conditions);
            $settingsrecord->id = $currentsetting->id;
            $settingsrecord->name = 'fileextensions';
            $settingsrecord->value = $flexible->fileextensions;
            $DB->update_record('flexible_ans_stngs',$settingsrecord);
        }
    }
    function delete_settings($flexibleid) {
        global $DB;
        //$plugin=$DB->get_record('flexible_answers',array('name'=>'answer_file'));
        $conditions = array('flexibleid'=>$flexibleid,
                //'answerid'=>$plugin->id);
                'answerid'=>$this->answerid);
        return $DB->delete_records('flexible_ans_stngs',$conditions);
    }
    function show_answer_form($mform, $flexibleid) {
        global $DB;
        $mform->addElement('header', 
                           'answerfileheader', 
                           get_string('pluginname','flexibleanswertypes_answer_file'));
                
        $options = array();
        $options['subdirs'] = 1;
        $plugin_settings_size = $DB->get_record('flexible_ans_stngs',
                                                array('flexibleid' => $flexibleid,
                                                       'answerid' => $this->answerid,
                                                       'name' => 'maxfilesize'));
        $plugin_settings_amount = $DB->get_record('flexible_ans_stngs',
                                                  array('flexibleid' => $flexibleid,
                                                        'answerid' => $this->answerid,
                                                        'name'=>'fileamount'));  
        $plugin_settings_types = $DB->get_record('flexible_ans_stngs',
                                                array('flexibleid' => $flexibleid,
                                                        'answerid' => $this->answerid,
                                                        'name' => 'fileextensions'));
        if ($plugin_settings_size) {
            $options['maxbytes'] = $plugin_settings_size->value;
        }
        if ($plugin_settings_amount) {
            $options['maxfiles'] = $plugin_settings_amount->value;
        }
        if ($plugin_settings_types) {
            $options['accepted_types'] = explode(',', $plugin_settings_types->value);
        }
        if ($plugin_settings_amount->value == -1) {
            $mform->addElement(    'static',
                                'filescount',
                                get_string('filescount', 'flexibleanswertypes_answer_file'),
                                get_string('any', 'flexibleanswertypes_answer_file'));
        }
        else {
            $mform->addElement('static', 'filetypes', get_string('filestypes', 'flexibleanswertypes_answer_file'), $plugin_settings_types->value);
        }
        $mform->addElement( 'filemanager', 
                            'answerfiles_filemanager', 
                            get_string('loadfiles','flexibleanswertypes_answer_file'),
                            null,
                            $options);

        $mform->closeHeaderBefore('answerfileheader');
    }
    function configure_flag($flexible) {
        if (isset($flexible->answerfile)) {
            $this->checked=true;
            unset($flexible->answerfile);
            }
        else
            $this->checked=false;
    }
    
    public function save_submission($attemptid, $data) {
        global $DB;
        $poasmodel = flexible_model::get_instance();
        $submission = new stdClass();
        
        $submission->attemptid = $attemptid;
        $submission->answerid = $this->answerid;
        $submission->value = $data->answerfiles_filemanager;
        $submission->id =  $DB->insert_record('flexible_submissions', $submission);
        $poasmodel->save_files($data->answerfiles_filemanager,'submissionfiles',$submission->id);
        return $submission->id;
    }
    
    function show_assignee_answer($assigneeid,$flexibleid,$needbox=1) {
        global $DB,$OUTPUT;
        $poasmodel = flexible_model::get_instance();
        $html='';
        if(!$assigneeid)
            return $html;
        $attemptscount=$DB->count_records('flexible_attempts',array('assigneeid'=>$assigneeid));
        $attempt=$DB->get_record('flexible_attempts',array('assigneeid'=>$assigneeid,'attemptnumber'=>$attemptscount));
        if($attempt) {
            $submission=$DB->get_record('flexible_submissions',array('answerid'=>$this->answerid,'attemptid'=>$attempt->id));
            if($submission) {
                if($needbox)
                    $html.=$OUTPUT->box_start();
                $cm = get_coursemodule_from_instance('flexible',$flexibleid);
                $context = get_context_instance(CONTEXT_MODULE, $cm->id);
                $html.= $poasmodel->view_files($context->id,'submissionfiles',$submission->id);
                if($needbox) 
                    $html.= $OUTPUT->box_end();                
            }
            return $html;
                //echo $submission->value;
        }
    }
    
    function get_answer_values($flexibleid) {
        global $DB;
        $cm = get_coursemodule_from_instance('flexible',$flexibleid);
        $context=get_context_instance(CONTEXT_MODULE, $cm->id);
        $data = new stdclass();
        $poasmodel=flexible_model::get_instance();
        $filemanager_options = array('subdirs'=>0);
        if($poasmodel->assignee) {
            $attemptscount=$DB->count_records('flexible_attempts',array('assigneeid'=>$poasmodel->assignee->id));
            $attempt=$DB->get_record('flexible_attempts',array('assigneeid'=>$poasmodel->assignee->id,'attemptnumber'=>$attemptscount));
            if($attempt) {
                $submission=$DB->get_record('flexible_submissions',array('answerid'=>$this->answerid,'attemptid'=>$attempt->id));
                if($submission) {
                    $data = file_prepare_standard_filemanager(
                        $data,
                        'answerfiles',
                        $filemanager_options,
                        $context,
                        'mod_flexible',
                        'submissionfiles',
                        $submission->id,
                        array('subdirs' => true));
                }
            }
            }
        return $data;
    // set file manager itemid, so it will find the files in draft area
    }

    public static function validate_submission($data, $files) {
        $errors = array();
        $fileinfo = file_get_draft_area_info($data['answerfiles_filemanager']);
        if ($fileinfo['filecount'] == 0) {
            $errors['answerfiles_filemanager'] = get_string('nofilesadded', 'flexibleanswertypes_answer_file');
        }
        return $errors;
    }
}