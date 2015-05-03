<?php
require_once('abstract_page.php');
require_once(dirname(dirname(__FILE__)) . '/model.php');
class grade_page extends abstract_page{
    private $assigneeid;
    private $assignee;
    function __construct() {
        $this->assigneeid = optional_param('assigneeid', 0, PARAM_INT);
    }

    /** Getter of page capability
     * @return capability 
     */
    function get_cap() {
        return 'mod/flexible:grade';
    }

    /** Checks module settings that prohibit viewing this page, used in has_ability_to_view
     * @return true if neither setting prohibits
     */
    function has_satisfying_parameters() {
        global $DB;
        if (!$this->assignee = $DB->get_record('flexible_assignee', array('id' => $this->assigneeid))) {
            $this->lasterror = 'errornonexistentassignee';
            return false;
        }
        return true;
    }
    
    public function pre_view() {
        $poasmodel = flexible_model::get_instance();
        $cmid = $poasmodel->get_cm()->id;
        $flexibleid = $poasmodel->get_flexible()->id;
        $this->mform = new grade_form(null,array('id' => $cmid, 'assigneeid' => $this->assigneeid, 'flexibleid' => $flexibleid));
        if ($this->mform->is_cancelled()) {
            redirect(new moodle_url('view.php',array('id'=>$cmid,'page'=>'submissions')),null,0);
        }
        else {
            if($data = $this->mform->get_data()) {
                $poasmodel->save_grade($this->assigneeid, $data);
                redirect(new moodle_url('view.php',array('id'=>$cmid,'page'=>'submissions')),null,0);
            }
        }

    }
    function view() {
        $model = flexible_model::get_instance();
        $data = $model->get_rating_data($this->assigneeid);
        $this->mform->set_data($data);
        $this->mform->display();
    }
    
    public static function display_in_navbar() {
        return false;
    }
    
}
class grade_form extends moodleform {

    function definition(){
        global $DB,$OUTPUT;
        $mform =& $this->_form;
        $instance = $this->_customdata;
        $assignee = $DB->get_record('flexible_assignee',array('id'=>$instance['assigneeid']));
        $poasmodel = flexible_model::get_instance();
        $user = $DB->get_record('user',array('id'=>$assignee->userid));
        $attemptscount = $DB->count_records('flexible_attempts',array('assigneeid'=>$instance['assigneeid']));
        $attempt = $DB->get_record('flexible_attempts',
                                    array('assigneeid' => $instance['assigneeid'],'attemptnumber' => $attemptscount));
        $lateness = format_time(time() - $attempt->attemptdate);
        $flexible = $DB->get_record('flexible',array('id'=>$instance['flexibleid']));
        $attemptsurl = new moodle_url('view.php',array('page' => 'attempts',
                                                       'id' => $instance['id'],
                                                       'assigneeid' => $instance['assigneeid']));
        $userurl = new moodle_url('/user/profile.php',array('id'=>$user->id));
        if ($poasmodel->has_flag(ACTIVATE_INDIVIDUAL_TASKS)) {
            $taskviewurl = new moodle_url('view.php', array('page' => 'taskview', 
                                                            'id' => $instance['id'], 
                                                            'taskid' => $assignee->taskid));
        }
        else {
            $taskviewurl = '';
        }
        $mform->addElement('static', 'picture', $OUTPUT->user_picture($user),
                                                html_writer::link($userurl,fullname($user, true)) . '<br>'.
                                                userdate($attempt->attemptdate) . '<br/>' .
                                                $lateness.' '.get_string('ago','flexible').'<br>'.
                                                html_writer::link($attemptsurl,get_string('studentattempts','flexible') . '<br>'.
                                                html_writer::link($taskviewurl,get_string('stundetstask','flexible'))));
        
        $mform->addElement('header','studentsubmission',get_string('studentsubmission','flexible'));
        require_once('attempts.php');
        $mform->addElement('static',null,null,attempts_page::show_attempt($attempt));
        $mform->addElement('header','gradeeditheader',get_string('gradeeditheader','flexible'));
        $criterions=$DB->get_records('flexible_criterions',array('flexibleid'=>$instance['flexibleid']));
        for($i=0;$i<101;$i++) 
            $opt[]=$i.'/100';
        $weightsum = 0;
        foreach($criterions as $criterion) 
            $weightsum += $criterion->weight;
        
        $context = get_context_instance(CONTEXT_MODULE, $instance['id']);
        
        $options->area    = 'flexible_comment';
        $options->pluginname = 'flexible';
        $options->component = 'mod_flexible';
        $options->context = $context;
        $options->showcount = true;
        foreach($criterions as $criterion) {
            $mform->addElement('html', $OUTPUT->box_start());
            // show grading element
            if($attempt->draft == 0 || 
               has_capability('mod/flexible:manageanything', $context)) {
                $mform->addElement('select',
                                   'criterion' . $criterion->id,
                                   $criterion->name . ' ' . $poasmodel->help_icon($criterion->description),
                                   $opt);
            }
            // show normalized criterion weight
            $mform->addElement('static',
                               'criterion' . $criterion->id . 'weight',
                               get_string('normalizedcriterionweight', 'flexible'),
                               round($criterion->weight / $weightsum, 2));
            
            // show feedback
            $ratingvalue = $DB->get_record('flexible_rating_values', array('criterionid' => $criterion->id,
                                                                                 'attemptid' => $attempt->id));
            if($ratingvalue) {
                $options->itemid = $ratingvalue->id;
                $comment= new comment($options);
                $mform->addElement('static', 
                                   'criterion' . $criterion->id . 'comment',
                                   get_string('comment', 'flexible'),
                                   $comment->output(true));
            }
            else
                $mform->addElement('htmleditor','criterion'.$criterion->id.'comment',get_string('comment','flexible'));
            
            $mform->addElement('html',$OUTPUT->box_end());
        }
        if($attempt->draft == 0 || has_capability('mod/flexible:manageanything',$context)) {
            $mform->addElement('checkbox', 'final', get_string('finalgrade','flexible'));
        }
        
        $mform->addElement('static','penalty',get_string('penalty','flexible'),$poasmodel->get_penalty($attempt->id));
        $mform->addElement('filemanager', 'commentfiles_filemanager', get_string('commentfiles','flexible'));
        
        // hidden params
        $mform->addElement('hidden', 'weightsum', $weightsum);
        $mform->setType('weightsum', PARAM_FLOAT);
        
        $mform->addElement('hidden', 'id', $instance['id']);
        $mform->setType('id', PARAM_INT);
        
        $mform->addElement('hidden', 'flexibleid', $instance['flexibleid']);
        $mform->setType('flexibleid', PARAM_INT);
        
        $mform->addElement('hidden', 'assigneeid', $instance['assigneeid']);
        $mform->setType('assigneeid', PARAM_INT);
        
        $mform->addElement('hidden', 'page', 'grade');
        $mform->setType('assigneeid', PARAM_TEXT);
        
        $this->add_action_buttons(true, get_string('savechanges', 'admin'));
    }
}
