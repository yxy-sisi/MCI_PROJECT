<?php
global $CFG;
require_once('abstract_page.php');
require_once(dirname(dirname(__FILE__)) . '/model.php');

class taskfieldedit_page extends abstract_page {
    private $fieldid;
    private $field;
    private $mform;
    private $mode;
    function __construct($cm, $flexible) {
        $this->fieldid = optional_param('fieldid', 0, PARAM_INT);
        $this->mode = optional_param('mode', '', PARAM_TEXT);        
        $this->cm = $cm;
        $this->flexible = $flexible;
    }
    function get_cap() {
        return 'mod/flexible:managetasksfields';
    }
    function has_satisfying_parameters() {
        // page is available if individual tasks mode is avtive
        $flag = flexible_model::get_instance()->has_flag(ACTIVATE_INDIVIDUAL_TASKS);
        if (!$flag) {
            $this->lasterror = 'errorindtaskmodeisdisabled';
            return false;
        }
        // field is available for edidting if exists
        global $DB;
        $flexibleid = flexible_model::get_instance()->get_flexible()->id;
        $options = array('id' => $this->fieldid, 'flexibleid' => $flexibleid);
        $fieldexistsininstance = $this->field = $DB->get_record('flexible_fields', $options);
        if($this->fieldid != 0 && !$fieldexistsininstance ) {
            $this->lasterror = 'errornonexistentfield';
            return false;
        }
        return true;
    }
    public function pre_view() {

        global $PAGE;
        $id = flexible_model::get_instance()->get_cm()->id;
        // add navigation nodes
        $tasksfields = new moodle_url('view.php', array('id' => $id,
                                                        'page' => 'tasksfields'));
        $PAGE->navbar->add(get_string('tasksfields','flexible'), $tasksfields);

        $taskfieldedit = new moodle_url('view.php', array('id' => $id,
                                                          'page' => 'taskfieldedit',
                                                          'fieldid' => $this->fieldid));
        $PAGE->navbar->add(get_string('taskfieldedit','flexible'), $taskfieldedit);

        $model = flexible_model::get_instance();
        $flexibleid = $model->get_flexible()->id;
        $this->mform = new taskfieldedit_form(null, array('id' => $model->get_cm()->id,
                                                      'fieldid' => $this->fieldid,
                                                      'flexibleid' => $flexibleid));
        if ($this->mform->is_cancelled()) {
            // return to taskfields page
            redirect(new moodle_url('view.php',
                                    array('id' => $model->get_cm()->id,
                                          'page' => 'tasksfields')), 
                     null, 
                     0);
        }
        if ($this->mode == 'changeconfirmed') {
            $this->update_confirmed();
        }
        if ($this->mode == 'addconfirmed') {
            $this->add_confirmed();
        }
    }
    function view() {
        global $DB, $OUTPUT, $USER;

        $model = flexible_model::get_instance();
        
        if ($data = $this->mform->get_data()) {
            if ($this->mode == 'confirmedit') {
                $this->confirm_update($data);
            }
            if ($this->mode == 'confirmadd') {
                $this->confirm_add($data);
            }
        }
        else {        
            if ($this->fieldid > 0) {
                $this->mform->set_data($DB->get_record('flexible_fields', array('id' => $this->fieldid)));
                $data = new stdClass();
                $data->variants = $model->get_field_variants($this->fieldid, 0);
                $data->id = $model->get_cm()->id;
                $this->mform->set_data($data);
            }
            $this->mform->display();
        }
    }
    public static function display_in_navbar() {
        return false;
    }
    
    /**
     * Show table of task's owners
     * 
     * @access public
     * @param unknown_type $owners
     */
    public function show_owners($owners) {
        global $CFG, $OUTPUT;
        $model = flexible_model::get_instance();
        echo '<input type="hidden" name="ownerscount" value="'.count($owners).'"/>';
        if (count($owners) > 0) {
            // Show owners table
            $usersinfo = $model->get_users_info($owners);
            print_string('instanceowners', 'flexible');
            require_once ('flexible_view.php');
            $extcolumns = array(
                    'task',
                    'saveprogress',
                    'dropprogress'
            );
            $extheaders = array(
                    get_string('task', 'flexible'),

                    get_string('saveprogress', 'flexible').' '.
                    $OUTPUT->help_icon('saveprogress', 'flexible'),

                    get_string('dropprogress', 'flexible').' '.
                    $OUTPUT->help_icon('dropprogress', 'flexible')
            );

            $table = flexible_view::get_instance()->prepare_flexible_table_owners($extcolumns, $extheaders);
            foreach ($usersinfo as $userinfo) {
                $table->add_data($this->get_owner($userinfo));
                echo '<input type="hidden" name="assigneids[]" value="'.$userinfo->id.'"/>';
            }
            // Add field's parameters

            echo '<input type="hidden" name="name" value="'.required_param('name', PARAM_TEXT).'"/>';
            echo '<input type="hidden" name="description" value="'.required_param('description', PARAM_TEXT).'"/>';
            echo '<input type="hidden" name="ftype" value="'.required_param('ftype', PARAM_INT).'"/>';

            // Checkboxes
            if (optional_param('showintable', false, PARAM_INT)) {
                echo '<input type="hidden" name="showintable" value="1"/>';
            }
            if (optional_param('secretfield', false, PARAM_INT)) {
                echo '<input type="hidden" name="secretfield" value="1"/>';
            }
            if (optional_param('random', false, PARAM_INT)) {
                echo '<input type="hidden" name="random" value="1"/>';
            }

            echo '<input type="hidden" name="valuemin" value="' . optional_param('valuemin', 0, PARAM_FLOAT) . '"/>';
            echo '<input type="hidden" name="valuemax" value="' . optional_param('valuemax', 0, PARAM_FLOAT) . '"/>';

            if ($variants = optional_param('variants', false, PARAM_RAW)) {
                echo '<input type="hidden" name="variants" value="' . $variants . '"/>';
            }

            $table->print_html();
        }
        else {
            print_string('nobodytooktask', 'flexible');
        }
    }
    
    /**
     * Generate HTML-string that contains all params as hidden
     * elements
     *  
     * @access private
     * @param object $data field
     * @return string html-code
     */
    private function data_to_hidden($data) {
        $html = '';
        $html .= '<input type="hidden" name="name" value="'.$data->name.'"/>';
        $html .= '<input type="hidden" name="description" value="'.$data->description.'"/>';
        $html .= '<input type="hidden" name="ftype" value="'.$data->ftype.'"/>';
        if (isset($data->showintable)) {
            $html .= '<input type="hidden" name="showintable" value="1"/>';
        }
        if (isset($data->secretfield)) {
            $html .= '<input type="hidden" name="secretfield" value="1"/>';
        }
        if (isset($data->random)) {
            $html .= '<input type="hidden" name="random" value="1"/>';
        }
        if (isset($data->variants)) {
            $html .= '<input type="hidden" name="variants" value="'.$data->variants.'"/>';
        }
        if (isset($data->valuemax)) {
            $html .= '<input type="hidden" name="valuemax" value="'.$data->valuemax.'"/>';
        }
        if (isset($data->valuemin)) {
            $html .= '<input type="hidden" name="valuemin" value="'.$data->valuemin.'"/>';
        }
        return $html;
    }
    /**
     * Show confirm edit screen with task owners list
     * 
     * @access private
     * @param object $data data from moodleform
     */
    private function confirm_update($data) {
        $model = flexible_model::get_instance();
        $owners = $model->get_instance_task_owners();
        // Open form
        echo '<form action="view.php?page=taskfieldedit&id='.$this->cm->id.'" method="post">';

        // Show owners table
        $this->show_owners($owners);

        // Ask user to confirm delete
        echo '<br/>';
        print_string('changefieldconfirmation', 'flexible');
        if (count($owners) > 0) {
            echo ' <span class="flexible-critical">(';
            print_string('changingfieldwillchangestudentsdata', 'flexible');
            echo ')</span>';
        }

        // Add field params as hidden elements
        echo $this->data_to_hidden($data);

        $nobutton = '<input type="submit" name="confirm" value="'.get_string('no').'"/>';
        $yesbutton = '<input type="submit" name="confirm" value="'.get_string('yes').'"/>';
        echo '<input type="hidden" name="mode" value="changeconfirmed"/>';
        echo '<input type="hidden" name="fieldid" value="'.$this->fieldid.'"/>';
        echo '<div class="flexible-confirmation-buttons">'.$yesbutton.$nobutton.'</div>';
        echo '</form>';
    }
    
    /**
     * Show confirm add screen with task owners list
     *
     * @access private
     * @param object $data data from moodleform
     */
    private function confirm_add($data) {
        $model = flexible_model::get_instance();
        $owners = $model->get_instance_task_owners();
        // Open form
        echo '<form action="view.php?page=taskfieldedit&id='.$this->cm->id.'" method="post">';

        // Show owners table
        $this->show_owners($owners);

        // Ask user to confirm delete
        echo '<br/>';
        print_string('addfieldconfirmation', 'flexible');
        if (count($owners) > 0) {
            echo ' <span class="flexible-critical">(';
            print_string('addingfieldwillchangestudentsdata', 'flexible');
            echo ')</span>';
        }

        // Add field params as hidden elements
        echo $this->data_to_hidden($data);

        $nobutton = '<input type="submit" name="confirm" value="'.get_string('no').'"/>';
        $yesbutton = '<input type="submit" name="confirm" value="'.get_string('yes').'"/>';
        echo '<input type="hidden" name="mode" value="addconfirmed"/>';
        echo '<div class="flexible-confirmation-buttons">'.$yesbutton.$nobutton.'</div>';
        echo '</form>';
    }
    
    /**
     * Updates task field using settings, sent by POST
     * 
     * @access private
     */
    private function update_confirmed() {
        $confirm = required_param('confirm', PARAM_TEXT);

        if ($confirm == get_string('no')) {
            redirect(new moodle_url('view.php', array('page' => 'tasksfields', 'id' => $this->cm->id)));
        }
        else {
            $model = flexible_model::get_instance();


            $save_fieldvalues = $model->changing_field_without_deleting_task_values($this->fieldid, (object)$_POST);
            // Delete old task values
            if (!$save_fieldvalues)
                $model->delete_fieldvalues($this->fieldid);

            // Update task field, insert new task field variants
            $model->update_task_field($this->fieldid, (object)$_POST);

            // Generate random values for field
            $model->generate_randoms($model->get_task_field($this->fieldid));

            if (required_param('ownerscount', PARAM_INT) > 0) {
                // $_POST['assigneids'] contains array of owners ids
                $assigneeids = $_POST['assigneids'];
                foreach ($assigneeids as $assigneeid) {
                    if (required_param('action_'.$assigneeid, PARAM_ALPHANUMEXT) == 'dropprogress') {
                        // Drop progress - attempts and grades
                        $model->drop_assignee_progress($assigneeid);
                    }
                }
            }

            // Redirect to fields page
            redirect(new moodle_url('view.php', array('page' => 'tasksfields', 'id' => $this->cm->id)));
        }
    } 
    
    /**
     * Add task field using settings, sent by POST
     * 
     * @access private
     */
    private function add_confirmed() {
        $confirm = required_param('confirm', PARAM_TEXT);

        if ($confirm == get_string('no')) {
            redirect(new moodle_url('view.php', array('page' => 'tasksfields', 'id' => $this->cm->id)));
        }
        else {
            $model = flexible_model::get_instance();

            // Insert field
            $data = $model->add_task_field((object)$_POST);
            
            // Generate random values for students, who already took the task
            $model->generate_randoms($data);
            

            if (required_param('ownerscount', PARAM_INT) > 0) {
                // $_POST['assigneids'] contains array of owners ids
                $assigneeids = $_POST['assigneids'];
                foreach ($assigneeids as $assigneeid) {
                    if (required_param('action_'.$assigneeid, PARAM_ALPHANUMEXT) == 'dropprogress') {
                        // Drop progress - attempts and grades
                        $model->drop_assignee_progress($assigneeid);
                    }
                }
            }

            // Redirect to fields page
            redirect(new moodle_url('view.php', array('page' => 'tasksfields', 'id' => $this->cm->id)));
        }
    } 
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

        // Get link to student's task
        $taskurl = new moodle_url(
                    'view.php',
                    array(
                            'page' => 'taskview',
                            'taskid' => $userinfo->taskid,
                            'id' => $model->get_cm()->id
                            )
                    );
        $task = $model->get_task_info($userinfo->taskid);
        $owner[] = html_writer::link($taskurl, $task->name.$model->help_icon($task->description));

        $owner[] = '<input type="radio" name="action_'.$userinfo->id.'" value="saveprogress" checked="checked"></input>';
        $owner[] = '<input type="radio" name="action_'.$userinfo->id.'" value="dropprogress"></input>';

        return $owner;
    }
}
class taskfieldedit_form extends moodleform {
    function definition() {
        $mform = $this->_form;
        $instance = $this->_customdata;
        if ($instance['fieldid'] > 0) {
            $mform->addElement('header','taskfieldeditheader',get_string('taskfieldeditheader','flexible'));
            $mform->addElement('hidden', 'mode', 'confirmedit');
        }
        else {
            $mform->addElement('header','taskfieldaddheader',get_string('taskfieldaddheader','flexible'));
            $mform->addElement('hidden', 'mode', 'confirmadd');
        }
        $mform->setType('mode', PARAM_TEXT);
        
        $mform->addElement('text','name',get_string('taskfieldname','flexible'),array('maxlength' => 45, 'size' => 50));
        $mform->addHelpButton('name', 'taskfieldname', 'flexible');

        $mform->addElement('textarea','description',get_string('taskfielddescription','flexible'),'rows="5" cols="50"');
        $mform->addHelpButton('description', 'taskfielddescription', 'flexible');
        $mform->addRule('name', null, 'required', null, 'client');
        $ftypes = array(get_string('char','flexible'),
                        get_string('text','flexible'),
                        get_string('float','flexible'),
                        get_string('int','flexible'),
                        get_string('date','flexible'),
                        get_string('file','flexible'),
                        get_string('list','flexible'),
                        get_string('multilist','flexible'));
                        //get_string('category', 'flexible'));
        $mform->addElement('select','ftype',get_string('ftype','flexible'),$ftypes);
        $mform->addHelpButton('ftype', 'ftype', 'flexible');

        $mform->addElement('checkbox','showintable',get_string('showintable','flexible'));
        $mform->addHelpButton('showintable', 'showintable', 'flexible');

        $mform->addElement('checkbox','secretfield',get_string('secretfield','flexible'));
        $mform->addHelpButton('secretfield', 'secretfield', 'flexible');
        
        $mform->addElement('checkbox','random',get_string('random','flexible'));
        $mform->addHelpButton('random', 'random', 'flexible');

        $types = array(STR, TEXT, DATE, FILE, MULTILIST, CATEGORY);
        foreach ($types as $type) {
            $mform->disabledIf('random', 'ftype', 'eq', $type);
        }
        //$mform->disabledIf('random','valuemin','eq','valuemax');
       
        
        $mform->addElement('text','valuemin',get_string('valuemin','flexible'),10);
        $mform->setDefault('valuemin', 0);
        
        $mform->addElement('text','valuemax',get_string('valuemax','flexible'),10);
        $mform->setDefault('valuemax', 100);
        
        $types = array(STR, TEXT, DATE, FILE, LISTOFELEMENTS, MULTILIST);
        foreach ($types as $type) {
            $mform->disabledIf('valuemin', 'ftype', 'eq', $type);
            $mform->disabledIf('valuemax', 'ftype', 'eq', $type);
        }
        
        $mform->addElement('textarea','variants',get_string('variants','flexible'),'rows="10" cols="50"');
        $mform->addHelpButton('variants', 'variants', 'flexible');
        $types = array(STR, TEXT, FLOATING, NUMBER, DATE, FILE);
        foreach ($types as $type) {
                $mform->disabledIf('variants', 'ftype', 'eq', $type);
        }
        
        // hidden params
        $mform->addElement('hidden', 'fieldid', $instance['fieldid']);
        $mform->setType('fieldid', PARAM_INT);
        $mform->addElement('hidden', 'id', $instance['id']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'page', 'taskfieldedit');
        $mform->setType('id', PARAM_TEXT);
        $mform->addElement('hidden', 'flexibleid', $instance['flexibleid']);
        $mform->setType('flexibleid', PARAM_INT);
        
        $this->add_action_buttons(true, get_string('savechanges', 'admin'));
    }
    
    function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ($data['ftype'] == LISTOFELEMENTS || $data['ftype'] == MULTILIST) {
            $tok = strtok($data['variants'], "\n");
            $count = 0;
            while ($tok) {
                $count++;
                $tok = strtok("\n");
            }
            if ($count < 2) {
                $errors['variants'] = get_string('errorvariants', 'flexible');
                return $errors;
            }
        }
        if (isset($data['valuemax']) && isset($data['valuemin'])) {
            if ($data['valuemax'] < $data['valuemin']) {
                $errors['valuemax'] = get_string('errormaxislessthenmin', 'flexible');
                return $errors;
            }
        }
        return true;
    }
}
