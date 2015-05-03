<?php
global $CFG;
require_once('abstract_page.php');
require_once(dirname(dirname(__FILE__)) . '/model.php');
require_once($CFG->libdir . '/tablelib.php');

class taskedit_page extends abstract_page {
    private $taskid;
    private $owners;
    
    function __construct($cm,$flexible) {
        $model = flexible_model::get_instance();

        $this->taskid = optional_param('taskid', 0, PARAM_INT);
        $this->mode   = optional_param('mode', null, PARAM_INT);
        $this->cm = $cm;
        $this->flexible = $flexible;
        $this->owners = $model->get_task_owners($this->taskid);
    }
    function get_cap() {
        return 'mod/flexible:managetasks';
    }
    
    function has_satisfying_parameters() {
        global $DB;
        if($this->taskid != 0 && !$this->task = $DB->get_record('flexible_tasks', array('id' => $this->taskid))) {
            $this->lasterror = 'errornonexistenttask';
            return false;
        }
        return true;
    }
    function pre_view() {
        global $PAGE;
        $id = flexible_model::get_instance()->get_cm()->id;
        // add navigation nodes
        $tasks = new moodle_url('view.php', array('id' => $id,
                                                        'page' => 'tasks'));
        $PAGE->navbar->add(get_string('tasks','flexible'), $tasks);

        $taskedit = new moodle_url('view.php', array('id' => $id,
                                                          'page' => 'taskedit',
                                                          'taskid' => $this->taskid));
        $PAGE->navbar->add(get_string('taskedit','flexible'), $taskedit);

        $model = flexible_model::get_instance();
        if ($this->mode == SHOW_MODE || $this->mode == HIDE_MODE) {
            if (isset($this->taskid) && $this->taskid > 0) {
                $model->set_task_visibility($this->taskid, $this->mode == SHOW_MODE);
                redirect(new moodle_url('view.php',array('id'=>$model->get_cm()->id, 'page'=>'tasks')), null, 0);
            }
            else {
                print_error(
                    'invalidtaskid',
                    'flexible',
                    new moodle_url('/mod/flexible/view.php',
                        array(
                            'id'=>flexible_model::get_instance()->get_cm()->id,
                            'page' => 'tasks')));
            }
        }
        if ($this->mode == 'changeconfirmed') {
            $this->update_confirmed();
        }
        if (isset($_REQUEST["submitbutton"])) {
            $this->check_quite_update();
        }
        
        $flexibleid = $model->get_flexible()->id;
        $this->mform = new taskedit_form(null, array('id' => $model->get_cm()->id, 
                                       'taskid' => $this->taskid,
                                       'flexibleid' => $flexibleid));
        // Cancel editing
        if ($this->mform->is_cancelled()) {
            redirect(new moodle_url('view.php', array('id' => $model->get_cm()->id, 
                                                      'page' => 'tasks')), 
                                                      null, 
                                                      0);
        }
        // Add task if needed
        if ($this->mform->get_data()) {
            $data = $this->mform->get_data();
            if ($this->taskid <= 0) {
                $model->add_task($data);
                redirect(new moodle_url('view.php', array('id' => $model->get_cm()->id, 'page' => 'tasks')), null, 0);
            }
        }
        
        // Get additional fields to the form
        if ($this->taskid > 0) {
            $data = $model->get_task_values($this->taskid);
            $data->id = $model->get_cm()->id;
            $this->mform->set_data($data);
        }
    }
    
    function view() {
        $model = flexible_model::get_instance();
        if ($this->mform->get_data()) {
            $data = $this->mform->get_data();
            if ($this->taskid > 0) {
                $this->confirm_update($data);
            }
        }
        else {
               $this->mform->display();
        }
    }
    
    /**
     * Updates task using settings, sent by POST
     * 
     * @access private
     */
    private function update_confirmed() {
        $confirm = required_param('confirm', PARAM_TEXT);

        if ($confirm == get_string('no')) {
            redirect(new moodle_url('view.php', array('page' => 'tasks', 'id' => $this->cm->id)));
        }
        else {
            $ownerscount = required_param('ownerscount', PARAM_INT);
            // If there is at least one student, who owns the task,
            // apply changes to him according to settings
            $model = flexible_model::get_instance();
            if ($ownerscount > 0) {
                // $_POST['assigneids'] contains array of owners ids
                $assigneeids = $_POST['assigneids'];

                // If teacher prefered to create new task for at least one student,
                // create new task and make old hidden
                $createnew = false;
                foreach ($assigneeids as $assigneeid) {
                    $action = required_param('action_'.$assigneeid, PARAM_TEXT);
                    if ($action == 'leavehiddentask') {
                        $createnew = true;
                    }
                }

                $newtaskid = required_param('taskid', PARAM_INT);

                if ($createnew) {
                    // Create new task
                    $newtaskid = $model->add_task((object)$_POST);
                    // Make old task hidden
                    $model->set_task_visibility(required_param('taskid', PARAM_INT), false);
                    // Make new task visible
                    $model->set_task_visibility($newtaskid, true);
                }
                foreach ($assigneeids as $assigneeid) {
                    $action = required_param('action_'.$assigneeid, PARAM_TEXT);
                    switch ($action) {
                        case 'changetaskwithprogress':
                            // Update taskid if new task is created
                            if ($createnew) {
                                $model->replace_assignee_taskid($assigneeid, $newtaskid);
                            }
                            else {
                                // Update task
                                $model->update_task(required_param('taskid', PARAM_INT), (object)$_POST);
                            }
                            break;
                        case 'changetaskwithoutprogress':

                            // Update taskid if new task is created
                            if ($createnew) {
                                $model->replace_assignee_taskid($assigneeid, $newtaskid);
                            }
                            else {
                                // Update task
                                $model->update_task(required_param('taskid', PARAM_INT), (object)$_POST);
                            }
                            // Drop progress - attempts and grades
                            $model->drop_assignee_progress($assigneeid);
                            break;
                        case 'leavehiddentask':
                            // Everything is done already for this case
                            break;
                    }
                }
            }
            else {
                // Update task
                $model->update_task(required_param('taskid', PARAM_INT), (object)$_POST);
            }
            redirect(new moodle_url('view.php', array('page' => 'tasks', 'id' => $this->cm->id)));
        }

    }
    
    /**
     * Get information about task owner and his task's status
     * 
     * @access private
     * @param object $userinfo assignee object
     * @return array information
     */
    private function get_owner($userinfo) {
        $model = flexible_model::get_instance();
        $owner = array();

        // Get student username and profile link
        $userurl = new moodle_url('/user/profile.php', array('id' => $userinfo->userid));
        $owner[] = html_writer::link($userurl, fullname($userinfo->userinfo, true));

        // TODO Get student's groups
        $owner[] = '?';


        // Get information about assignee's attempts and grades
        if ($attempt = $model->get_last_attempt($userinfo->id)) {
            $owner[] = get_string('hasattempts', 'flexible');

            // If assignee has an attempt(s), show information about his grade
            if ($attempt->rating != null) {
                // Show actual grade with penalty
                $owner[] =
                    get_string('hasgrade', 'flexible').
                    ' ('.
                    $model->show_rating_methematics($attempt->rating, $model->get_penalty($attempt->id)).
                    ')';
            }
            else {
                // Looks like assignee has no grade or outdated grade
                if ($lastgraded = $model->get_last_graded_attempt($userinfo->id)) {
                    $owner[] =
                        get_string('hasoutdatedgrade', 'flexible').
                        ' ('.
                        $model->show_rating_methematics($lastgraded->rating, $model->get_penalty($lastgraded->id)).
                        ')';
                }
                else {
                    // There is no graded attempts, so show 'No grade'
                    $owner[] = get_string('nograde', 'flexible');
                }
            }
        }
        else {
            // No attepts => no grade
            $owner[] = get_string('hasnoattempts', 'flexible');
            $owner[] = get_string('nograde', 'flexible');
        }
        $owner[] = '<input type="radio" name="action_'.$userinfo->id.'" value="changetaskwithprogress" checked="checked"></input>';
        $owner[] = '<input type="radio" name="action_'.$userinfo->id.'" value="changetaskwithoutprogress"></input>';
        $owner[] = '<input type="radio" name="action_'.$userinfo->id.'" value="leavehiddentask"></input>';

        return $owner;
    }
    /**
     * Show confirm update screen.
     * If no one took the task, it seems like ordinary confirm screen
     * - are you sure? - yes/no.
     * If someone took the task, page shows table 
     * of taskowners and offer what to do with each student
     * 
     * @access public
     * @param object $data - updated task data
     */
    public function confirm_update($data) {
        global $OUTPUT, $CFG;
        $model = flexible_model::get_instance();

        // Open form
        echo '<form action="view.php?page=taskedit&id='.$this->cm->id.'" method="post">';

        echo '<input type="hidden" name="ownerscount" value="'.count($this->owners).'"/>';
        // If there are students, that own this task, show them
        if (count($this->owners) > 0) {
            // Show owners table
            $usersinfo = $model->get_users_info($this->owners);
            print_string('ownersofthetask', 'flexible');
            require_once ('flexible_view.php');
            $extcolumns = array(
                    'changetaskwithprogress',
                    'changetaskwithoutprogress',
                    'leavehiddentask'
                    );
            $extheaders = array(
                    get_string('changetaskwithprogress', 'flexible').' '.
                        $OUTPUT->help_icon('changetaskwithprogress', 'flexible'),

                    get_string('changetaskwithoutprogress', 'flexible').' '.
                        $OUTPUT->help_icon('changetaskwithoutprogress', 'flexible'),

                    get_string('leavehiddentask', 'flexible').' '.
                        $OUTPUT->help_icon('leavehiddentask', 'flexible')
                    );

            $table = flexible_view::get_instance()->prepare_flexible_table_owners($extcolumns, $extheaders);
            foreach ($usersinfo as $userinfo) {
                $table->add_data($this->get_owner($userinfo));
                echo '<input type="hidden" name="assigneids[]" value="'.$userinfo->id.'"/>';
            }
            $table->print_html();
        }
        else {
            print_string('nooneownsthetask', 'flexible');
        }
        // Ask user to confirm delete
        echo '<br/>';
        print_string('changetaskconfirmation', 'flexible');
        if (count($this->owners) > 0) {
            echo ' <span class="flexible-critical">(';
            print_string('changingtaskwillchangestudentsdata', 'flexible');
            echo ')</span>';
        }

        // Add updated task in hidden elements
        foreach ((array)$data as $name => $field) {
            if (!is_array($field)) {
                echo '<input type="hidden" name="'.$name.'" value="'.htmlspecialchars($field).'"/>';
            }
            else {
                foreach ($field as $key => $value) {
                    echo '<input type="hidden" name="'.$name.'['.$key.']" value="'.$value.'"/>';
                }
            }
        }
        $nobutton = '<input type="submit" name="confirm" value="'.get_string('no').'"/>';
        $yesbutton = '<input type="submit" name="confirm" value="'.get_string('yes').'"/>';
        echo '<input type="hidden" name="mode" value="changeconfirmed"/>';
        echo '<div class="flexible-confirmation-buttons">'.$yesbutton.$nobutton.'</div>';
        echo '</form>';
    }

    /**
     * Checks, if there is no owners of task being updated and skips confirm screen
     */
    private function check_quite_update() {
        $model = flexible_model::get_instance();

        // If there is no owners, do quite update
        if (count($this->owners) == 0) {
            $model->update_task(required_param('taskid', PARAM_INT), (object)$_POST);
            redirect(new moodle_url('view.php', array('page' => 'tasks', 'id' => $this->cm->id)));
        }
    }
    
    public static function display_in_navbar() {
        return false;
    }
    
}
class taskedit_form extends moodleform {

    function definition(){
        global $DB;
        $mform = $this->_form;
        $instance = $this->_customdata;
        if($instance['taskid']>0)
            $mform->addElement('header','taskeditheader',get_string('taskeditheader','flexible'));
        else
            $mform->addElement('header','taskaddheader',get_string('taskaddheader','flexible'));
        
        $mform->addElement('text','name',get_string('taskname','flexible'),array('size'=>45));
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('htmleditor','description',get_string('taskintro', 'flexible'));

        $mform->addElement('checkbox','hidden',get_string('taskhidden', 'flexible'));
        $mform->addHelpButton('hidden','taskhidden', 'flexible');

        $fields=$DB->get_records('flexible_fields',array('flexibleid'=>$instance['flexibleid']));
        $poasmodel= flexible_model::get_instance();
        foreach($fields as $field) {
            $name = $field->name.' '.$poasmodel->help_icon($field->description);
            if($field->ftype==STR)
                $mform->addElement('text','field'.$field->id,$name,array('size'=>45));
                
            if($field->ftype==TEXT)
                $mform->addElement('htmleditor','field'.$field->id,$name);
                
            if( ($field->ftype==FLOATING || $field->ftype==NUMBER) && $field->random) {
                $mform->addElement('static','field'.$field->id,$name, get_string('randomfield', 'flexible'));
            }
            
            if( ($field->ftype==FLOATING || $field->ftype==NUMBER) && !$field->random) {
                $mform->addElement('text','field'.$field->id,$name,array('size'=>10));
            }

            if($field->ftype==DATE) {
                $mform->addElement('date_selector','field'.$field->id,$name);
            }
            
            if($field->ftype==FILE) {
                $mform->addElement('filemanager','field'.$field->id,$name);
            }
            if($field->ftype==LISTOFELEMENTS || $field->ftype==MULTILIST) {
                if($field->random==0) {
                    /* $tok = strtok($field->variants,"\n");
                    while($tok) {
                        $opt[]=$tok;
                        $tok=strtok("\n");
                    } */
                    $opt=$poasmodel->get_field_variants($field->id);
                    $select=&$mform->addElement('select','field'.$field->id,$name,$opt);
                    if($field->ftype==MULTILIST)
                        $select->setMultiple(true);
                }
                else
                    $mform->addElement('static','field'.$field->id,$name,get_string('randomfield', 'flexible'));
            }
        }
        
        // hidden params
        
        $mform->addElement('hidden', 'id', $instance['id']);
        $mform->setType('id', PARAM_INT);
        
        $mform->addElement('hidden', 'flexibleid', $instance['flexibleid']);
        $mform->setType('flexibleid', PARAM_INT);
        
        $mform->addElement('hidden', 'taskid', $instance['taskid']);
        $mform->setType('taskid', PARAM_INT);
        $mform->addElement('hidden', 'page', 'taskedit');
        $mform->setType('taskid', PARAM_TEXT);
        
        $this->add_action_buttons(true, get_string('savechanges', 'admin'));
    }
    
    function validation($data, $files) {
        $errors = parent::validation($data, $files);
        global $DB;
        $fields=$DB->get_records('flexible_fields',array('flexibleid'=>$data['flexibleid']));
        foreach($fields as $field) {
            if(!$field->random &&($field->ftype==FLOATING || $field->ftype==NUMBER)) {
                if(!($field->valuemin==0 && $field->valuemax==0 )) {
                    if($data['field'.$field->id]>$field->valuemax || $data['field'.$field->id]<$field->valuemin) {
                    $errors['field'.$field->id]=get_string('valuemustbe','flexible').' '.
                                                get_string('morethen','flexible').' '.
                                                $field->valuemin.' '.
                                                get_string('and','flexible').' '.
                                                get_string('lessthen','flexible').' '.
                                                $field->valuemax;
                    return $errors;
                    }
                }
            }
            if($field->ftype==MULTILIST && !isset($data['field'.$field->id])) {
                $errors['field'.$field->id]=get_string('errornovariants','flexible');
                return $errors;
            }
            
        }
       
        return true;
    }
}
