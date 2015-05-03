<?php
global $CFG;
require_once('abstract_page.php');
require_once($CFG->libdir . '/tablelib.php');
require_once(dirname(dirname(__FILE__)) . '/model.php');
require_once($CFG->libdir.'/formslib.php');
class criterions_page extends abstract_page {
    private $mform;

    function __construct($cm, $flexible) {
        $this->cm = $cm;
        $this->flexible = $flexible;
    }

    public function pre_view() {
        if (optional_param('mode', '', PARAM_ACTION) == 'updateconfirmed') {
            $this->update_confirmed();
        }
    }
    function view() {
        global $DB, $OUTPUT;
        $poasmodel = flexible_model::get_instance();
        $id = $poasmodel->get_cm()->id;
        $context = $poasmodel->get_context();

        if (has_capability('mod/flexible:managecriterions', $context)) {
            $this->mform = new criterionsedit_form(null, array('id' => $id, 'flexibleid' => $poasmodel->get_flexible()->id));
            if($this->mform->get_data()) {
                $data = $this->mform->get_data();
                $this->confirm_update($data);
                /*
                     $result = $poasmodel->save_criterion($data);
                    if ($result == flexible_CRITERION_OK) {
                    redirect(new moodle_url('view.php', array('id' => $id, 'page' => 'criterions')), null, 0);
                    }*/
            }
            else {
                $this->mform->set_data($poasmodel->get_criterions_data());
                $this->mform->display();
            }
        }
        else {
            $this->show_read_only_criterions();
        }

    }

    /**
     * Extract criterion objects from POST
     *
     * @access private
     * @return array criterions
     */
    private function get_criterions_from_post() {
        $count = required_param('option_repeats', PARAM_INT);

        $names = required_param_array('name', PARAM_CLEANHTML);
        $descriptions = required_param_array('description', PARAM_RAW);
        $weights = required_param_array('weight', PARAM_RAW);
        $sources = required_param_array('source', PARAM_INT);
        $criterionids = required_param_array('criterionid', PARAM_INT);
        if (isset($_REQUEST['delete']))
            $delete = required_param_array('delete', PARAM_CLEANHTML);

        $criterions = array();
        for ($i = 0; $i < $count; $i++) {
            if ($names[$i] != '') {
                $criterion = new stdClass();
                $criterion->id = $criterionids[$i];
                $criterion->name = $names[$i];
                $criterion->description = $descriptions[$i];
                $criterion->weight = $weights[$i];
                $criterion->graderid = $sources[$i];
                $criterion->delete = isset($delete[$i]) && $delete[$i] == 1;
                $criterion->flexibleid = $this->flexible->id;

                $criterions[] = $criterion;
            }
        }
        return $criterions;
    }

    /**
     * Returns true, if $data param contains info about new criterion(s) data
     *
     * @access private
     * @param object $data criterions data
     * @return boolean true if there is at least 1 new criterion
     */
    private function has_new_criterions($data) {
        foreach ($data->name as $index => $name) {
            if ($name != '' && isset($data->criterionid[$index]) && $data->criterionid[$index] == -1) {
                return true;
            }
        }
        return false;
    }

    private function update_confirmed() {
        if (required_param('confirm', PARAM_TEXT) == get_string('no')) {
            redirect(new moodle_url('view.php?', array('id' => $this->cm->id, 'page' => 'criterions')));
        }
        if (required_param('confirm', PARAM_TEXT) == get_string('yes')) {
            $model = flexible_model::get_instance();
            $gradedcount = required_param('gradedcount', PARAM_INT);
            $insertedcriterions = $model->update_criterions($this->get_criterions_from_post());
            if ($gradedcount > 0) {
                $assigneeids = required_param('assigneeids', PARAM_RAW);

                foreach($assigneeids as $assigneeid) {
                    if (count($insertedcriterions) > 0) {
                        $action = required_param('action_'.$assigneeid, PARAM_ALPHANUMEXT);
                        switch($action) {
                            case 'put0':
                                $model->new_criterion_rating($assigneeid, $insertedcriterions, 0, get_string('newcriterionwithgrade', 'flexible').' 0');
                                break;
                            case 'put100':
                                $model->new_criterion_rating($assigneeid, $insertedcriterions, 100, get_string('newcriterionwithgrade', 'flexible').' 100');
                                break;
                            case 'puttotal':
                                $model->new_criterion_rating($assigneeid, $insertedcriterions, 'total', get_string('newcriterionwithgradetotal', 'flexible'));
                                break;
                            case 'putspecified':
                                $grade = required_param('specified_'.$assigneeid, PARAM_INT);
                                $model->new_criterion_rating(
                                    $assigneeid,
                                    $insertedcriterions,
                                    $grade,
                                    get_string('newcriterionwithgradespecified', 'flexible').' '.$grade);
                                break;
                            case 'putnull':
                                $model->new_criterion_rating($assigneeid, $insertedcriterions, null, get_string('newcriterionwithgrade', 'flexible').' null');
                                break;
                        }
                    }
                    $model->recalculate_rating($assigneeid);
                }

            }
            redirect(new moodle_url('view.php?', array('id' => $this->cm->id, 'page' => 'criterions'), 'redirecting...'));
        }
    }
    /**
     * Show confirm update criterions page
     *
     * @access private
     * @param object $data data from criterions moodleform
     */
    private function confirm_update($data) {
        global $OUTPUT;
        $model = flexible_model::get_instance();
        // Open form
        echo '<form action="view.php?page=criterions&id='.$this->cm->id.'" method="post">';

        $has_rated_attempts = $model->instance_has_rated_attempts();
        if ($has_rated_attempts) {
            $graded = $model->get_graded_assignees();
            echo '<input type="hidden" name="gradedcount" value="' . count($graded) . '"/>';
            // Show table of graded students
            $usersinfo = $model->get_users_info($graded);
            print_string('gradedassignees', 'flexible');
            require_once ('flexible_view.php');

            $extcolumns = array(
                'task',
                'put0',
                'put100',
                'puttotal',
                'putspecified',
                'putnull'
            );
            $extheaders = array(
                get_string('task', 'flexible'),

                get_string('put0', 'flexible').' '.
                    $OUTPUT->help_icon('put0', 'flexible'),

                get_string('put100', 'flexible').' '.
                    $OUTPUT->help_icon('put100', 'flexible'),

                get_string('puttotal', 'flexible').' '.
                    $OUTPUT->help_icon('puttotal', 'flexible'),

                get_string('putspecified', 'flexible').' '.
                    $OUTPUT->help_icon('putspecified', 'flexible'),

                get_string('putnull', 'flexible').' '.
                    $OUTPUT->help_icon('putnull', 'flexible')
            );

            $table = flexible_view::get_instance()->prepare_flexible_table_owners($extcolumns, $extheaders);
            foreach ($usersinfo as $userinfo) {
                $table->add_data($this->get_graded($userinfo));
                echo '<input type="hidden" name="assigneeids[]" value="'.$userinfo->id.'"/>';
            }
            $table->print_html();
        }
        else {
            echo '<input type="hidden" name="gradedcount" value="0"/>';
            print_string('nobodyhasgrade', 'flexible');
        }

        // Ask user to confirm update
        echo '<br/>';
        print_string('updatecriterionsconfirmation', 'flexible');
        if ($has_rated_attempts) {
            echo ' <span class="flexible-critical">(';
            print_string('changingcriterionswillchangestudentsdata', 'flexible');
            echo ')</span>';
        }
        // Add updated criterions in hidden elements
        echo '<input type="hidden" name="option_repeats" value="'.$data->option_repeats.'"/>';
        foreach($data->name as $name) {
            echo '<input type="hidden" name="name[]" value="'.$name.'"/>';
        }
        foreach($data->description as $description) {
            echo '<input type="hidden" name="description[]" value="'.$description.'"/>';
        }
        foreach($data->weight as $weight) {
            echo '<input type="hidden" name="weight[]" value="'.$weight.'"/>';
        }
        foreach($data->source as $source) {
            echo '<input type="hidden" name="source[]" value="'.$source.'"/>';
        }
        foreach($data->criterionid as $criterionid) {
            echo '<input type="hidden" name="criterionid[]" value="'.$criterionid.'"/>';
        }
        if (isset($data->delete)) {
            foreach($data->delete as $key => $delete) {
                echo '<input type="hidden" name="delete['.$key.']" value="'.$delete.'"/>';
            }
        }

        $nobutton = '<input type="submit" name="confirm" value="'.get_string('no').'"/>';
        $yesbutton = '<input type="submit" name="confirm" value="'.get_string('yes').'"/>';
        echo '<input type="hidden" name="mode" value="updateconfirmed"/>';
        echo '<div class="flexible-confirmation-buttons">'.$yesbutton.$nobutton.'</div>';
        echo '</form>';
    }

    /**
     * Get row for graded assignees flexible table
     *
     * @access public
     * @param object $userinfo graded assignee
     * @return array row
     */
    private function get_graded($userinfo) {
        $model = flexible_model::get_instance();
        $row = $model->get_flexible_table_assignees_row($userinfo);
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
        $row[] = html_writer::link($taskurl, $task->name . $model->help_icon($task->description));

        $row[] = '<input type="radio" name="action_'.$userinfo->id.'" value="put0"></input>';
        $row[] = '<input type="radio" name="action_'.$userinfo->id.'" value="put100"></input>';
        $row[] = '<input type="radio" name="action_'.$userinfo->id.'" value="puttotal" checked="checked"></input>';
        $row[] = '<input type="radio" name="action_'.$userinfo->id.'" value="putspecified"></input>'
            .'<input type="text" name="specified_'.$userinfo->id.'" value="77" style="width:30px"/>';
        $row[] = '<input type="radio" name="action_'.$userinfo->id.'" value="putnull"></input>';
        return $row;
    }
    private function show_read_only_criterions() {
        global $OUTPUT, $DB;
        $poasmodel = flexible_model::get_instance();
        $criterions = $DB->get_records('flexible_criterions', array('flexibleid' => $poasmodel->flexible->id));
        if (count($criterions) == 0) {
            echo '<p class=no-info-message>'.get_string('nocriterions','flexible').'</p>';
            return;
        }
        $weightsum = 0;
        foreach($criterions as $criterion) {
            $weightsum += $criterion->weight;
        }
        $canseedescription = has_capability('mod/flexible:seecriteriondescription',
            $poasmodel->get_context());
        echo '<table class="flexible-table">';
        echo '<tr>';
        echo '<td class = "header">' . get_string('criterionname', 'flexible') . '</td>';
        if ($canseedescription) {
            echo '<td class = "header">' . get_string('criteriondescription', 'flexible') . '</td>';
        }
        echo '<td class = "header">' . get_string('criterionweight', 'flexible') . '</td>';
        echo '</tr>';
        foreach ($criterions as $criterion) {
            echo '<tr>';

            echo '<td>' . $criterion->name . '</td>';

            echo '<td>';
            if ($canseedescription) {
                echo $criterion->description;
            }
            else {
                echo '-';
            }
            echo '</td>';

            echo '<td>' . round($criterion->weight / $weightsum, 2) . '</td>';

            echo '</tr>';
        }
        echo '</table>';
    }
}
class criterionsedit_form extends moodleform {
    function definition(){
        global $DB;
        $mform = $this->_form;
        $instance = $this->_customdata;

        $repeatarray = array();
        $repeatarray[] = $mform->createElement('header', 'criterionheader');
        $repeatarray[] = $mform->createElement('text', 'name', get_string('criterionname','flexible'),array('size'=>45));
        $repeatarray[] = $mform->createElement('textarea', 'description', get_string('criteriondescription','flexible'));
        $repeatarray[] = $mform->createElement('text', 'weight', get_string('criterionweight','flexible'));
        $sources[0] = 'manually';
        //TODO cash used graders in model class
        $usedgraders = $DB->get_records('flexible_used_graders',array('flexibleid' => $instance['flexibleid']));
        foreach($usedgraders as $usedgraderrecord) {
            $grader = $DB->get_record('flexible_graders',array('id' => $usedgraderrecord->graderid));
            $gradername = $grader->name;
            require_once($grader->path);
            $sources[$usedgraderrecord->graderid] = $gradername::name();

            // adding graders identificators - hidden elements to form

            $mform->addElement('hidden', 'grader' . (count($sources) - 1), $usedgraderrecord->graderid);
            $mform->setType('grader' . (count($sources) - 1), PARAM_INT);
        }
        $repeatarray[] = $mform->createElement('select', 'source', get_string('criterionsource','flexible'),$sources);
        $repeatarray[] = $mform->createElement('checkbox', 'delete', get_string('deletecriterion', 'flexible'));
        $repeatarray[] = $mform->createElement('hidden', 'criterionid', -1);

        if ($instance){
            $repeatno = $DB->count_records('flexible_criterions', array('flexibleid'=>$instance['flexibleid']));
            $repeatno += 1;
        } else {
            $repeatno = 2;
        }

        $repeateloptions = array();

        $repeateloptions['name']['helpbutton'] = array('criterionname', 'flexible');
        $repeateloptions['description']['helpbutton'] = array('criteriondescription', 'flexible');
        $repeateloptions['weight']['helpbutton'] = array('criterionweight', 'flexible');
        $repeateloptions['source']['helpbutton'] = array('criterionsource', 'flexible');

        $repeateloptions['delete']['default'] = 0;
        $repeateloptions['delete']['disabledif'] = array('criterionid', 'eq', -1);

        $mform->setType('criterionid', PARAM_INT);

        $this->repeat_elements($repeatarray, $repeatno,
            $repeateloptions, 'option_repeats', 'option_add_fields', 2);

        $mform->addElement('hidden', 'id', $instance['id']);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'page', 'criterions');
        $mform->setType('page', PARAM_TEXT);

        $this->add_action_buttons(false, get_string('savechanges', 'admin'));
    }
    function validation($data, $files) {
        $errors = parent::validation($data, $files);
        for ($i = 0; $i < $data['option_repeats']; $i++) {
            $nameisempty = $data['name'][$i] == '';
            $descriptionisempty = $data['description'][$i] == '';
            $weightisempty = $data['weight'][$i] == '';
            if ($nameisempty && $descriptionisempty && $weightisempty)
                continue;

            if ($nameisempty) {
                $errors["name[$i]"] = get_string('errornoname', 'flexible');
            }
            if ($weightisempty) {
                $errors["weight[$i]"] = get_string('errornoweight', 'flexible');
            }
            else {
                if (!is_numeric($data['weight'][$i])) {
                    $errors["weight[$i]"] = get_string('errornoweightnumeric', 'flexible');
                }
                else {
                    if ($data['weight'][$i] <= 0) {
                        $errors["weight[$i]"] = get_string('errornotpositiveweight', 'flexible');
                    }
                }
            }
        }

//        while (!empty($data['name'][$i] )) {
//            if(!isset($data['name'][$i])) {
//                $errors["name[$i]"] = get_string('errornoname', 'flexible');
//            }
//            if(!isset($data['weight'][$i])) {
//                $errors["weight[$i]"] = get_string('errornoweight', 'flexible');
//            }
//            if($data['weight'][$i] <= 0) {
//                $errors["weight[$i]"] = get_string('errornotpositiveweight', 'flexible');
//            }
//            $i++;
//        }
        if(count($errors) > 0) {
            return $errors;
        }
        else {
            return true;
        }
    }
}
