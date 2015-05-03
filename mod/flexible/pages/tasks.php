<?php
global $CFG;
require_once('abstract_page.php');
require_once(dirname(dirname(__FILE__)) . '/model.php');
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot.'/lib/tablelib.php');
class tasks_page extends abstract_page {
    var $flexible;
    private $userid = -1;

    private $taskgiver;                     // cached current taskgiver
    private $taskgiverinstance;             // stored taskgiver instance

    private $hascapmanage;                  // has capability to manage tasks
    private $hascaphavetask;                // has capability to have task

    function __construct($cm,$flexible) {
        global $DB;

        $this->flexible = $flexible;
        $this->cm = $cm;


        $this->userid = optional_param('userid', -1, PARAM_INT);

        $this->hascapmanage = has_capability('mod/flexible:managetasks', get_context_instance(CONTEXT_MODULE, $this->cm->id));
        $this->hascaphavetask = has_capability('mod/flexible:havetask', get_context_instance(CONTEXT_MODULE, $this->cm->id));

        $this->taskgiver = $DB->get_record('flexible_taskgivers', array('id' => $this->flexible->taskgiverid));
    }

    /**
     * Checks, if the user is providing a task to student (and has capability for doing this)
     */
    function is_providing_task() {
        $correctuserid = $this->userid > 0;
        $userhavetask = flexible_model::user_have_active_task($this->userid, $this->flexible->id);
        return ($correctuserid && $this->hascapmanage && !$userhavetask);
    }

    function has_satisfying_parameters() {
        global $DB,$USER;
        $flag = $this->flexible->flags & ACTIVATE_INDIVIDUAL_TASKS;
        if (!$flag) {
            $this->lasterror='errorindtaskmodeisdisabled';
            return false;
        }
        $model = flexible_model::get_instance();
        if ($assignee = $model->get_assignee($USER->id,$this->flexible->id)){
            if (isset($assignee->taskid) && $assignee->taskid > 0) {
                if (!$this->hascapmanage) {
                    $this->lasterror='alreadyhavetask';
                    return false;
                }
            }
        }
        return true;
    }

    function pre_view() {
        if ($this->taskgiver) {
            require_once ($this->taskgiver->path);
            $taskgivername = $this->taskgiver->name;
            $this->taskgiverinstance = new $taskgivername();
        }
        if (!$this->is_providing_task() && $this->hascaphavetask) {
            $this->taskgiverinstance->process_before_output($this->cm->id, $this->flexible);
        }
    }
    function view() {
        global $DB,$OUTPUT,$USER,$PAGE;
        if ($this->is_providing_task()) {
            $user = $DB->get_record('user', array('id' => $this->userid));
            $userurl = new moodle_url('/user/profile.php', array('id' => $user->id));
            $student = html_writer::link($userurl, fullname($user, true));
            echo $OUTPUT->heading(get_string('providetaskto', 'flexible'). ' ' . $student);

            $this->view_table($this->hascapmanage, false);
        }
        if (!$this->is_providing_task()) {

            $this->taskgiverinstance->process_before_tasks($this->cm->id, $this->flexible);

            if ($error = flexible_model::get_instance()->check_dates())
                echo '<div class="flexible-critical center">'.get_string($error, 'flexible').'</div>';

            $taskgivername = $this->taskgiver->name;
            if ($this->hascapmanage || $taskgivername::show_tasks()) {
                $this->view_table();
                $this->taskgiverinstance->process_after_tasks($this->cm->id, $this->flexible);
            }

            if ($this->hascapmanage) {
                $id = $this->cm->id;
                echo '<div align="center">';
                echo $OUTPUT->single_button(new moodle_url('view.php', array('id' => $id, 'page' => 'taskedit')),get_string('addtask','flexible'));
                echo '</div>';
            }
        }

    }
    private function view_table() {
        global $DB, $OUTPUT, $PAGE, $USER;
        $poasmodel = flexible_model::get_instance($this->flexible);
        $table = new flexible_table('mod-flexible-tasks');
        $table->baseurl = $PAGE->url;
        $fields = $DB->get_records('flexible_fields', array('flexibleid' => $this->flexible->id));


        $columns[]=get_string('taskname', 'flexible');
        $columns[]=get_string('taskdescription', 'flexible');
        $headers[]=get_string('taskname', 'flexible');
        $headers[]=get_string('taskdescription', 'flexible');

        if (count($fields)) {
            foreach ($fields as $field) {
                if ($field->showintable>0) {
                    if ($this->hascapmanage ||(!$this->hascapmanage && !$field->secretfield)) {
                        $columnname = $field->name;
                        $header = $field->name;
                        $columns[] = $columnname;
                        if (has_capability('mod/flexible:seefielddescription', get_context_instance(CONTEXT_MODULE, $this->cm->id))) {
                            $header .= ' ' . $poasmodel->help_icon($field->description);
                        }

                        if (has_capability('mod/flexible:managetasksfields', get_context_instance(CONTEXT_MODULE, $this->cm->id))) {
                            $updateurl = new moodle_url('view.php',
                                array('id' => $this->cm->id,
                                    'fieldid' => $field->id,
                                    'page' => 'taskfieldedit')
                            );
                            $updateicon = '<a href="' . $updateurl . '">' . '<img src="' .
                                $OUTPUT->pix_url('t/edit') . '" class="iconsmall" alt="' .
                                get_string('edit') . '" title="' . get_string('edit') .'" /></a>';

                            $header .= ' ' . $updateicon;
                        }

                        $headers[] = $header;
                    }
                }
            }
        }
        $table->define_columns($columns);
        $table->define_headers($headers);
        $table->collapsible(true);
        $table->initialbars(true);
        $table->set_attribute('class', 'flexible-table tasks-table');
        $table->set_attribute('width', '100%');
        $table->setup();
        // Show all tasks if we can manage tasks
        if(has_capability('mod/flexible:managetasks',
                          get_context_instance(CONTEXT_MODULE, $this->cm->id))) {
            $tasks = $DB->get_records('flexible_tasks', array('flexibleid' => $this->flexible->id));

            $availabletasks = $poasmodel->get_available_tasks($USER->id, 1);
        }
        // Else show available for user tasks
        else {
            $tasks = $poasmodel->get_available_tasks($USER->id);
        }
        foreach ($tasks as $task) {
            // Hide hidden tasks from students
            if (!$this->hascapmanage && $task->hidden)
                continue;

            $row = array();

            if ($this->is_providing_task()) {
                // If task is unavailable, note teacher
                $viewurl = new moodle_url('view.php',array('page' => 'taskview', 'taskid'=>$task->id,'id'=>$this->cm->id));
                $attributes = array('title' => get_string('view'));
                if ($task->hidden) {
                    $attributes['class'] = 'hiddentask';
                }
                $namecolumn = html_writer::link($viewurl, $task->name, $attributes);

                if (!array_key_exists($task->id, $availabletasks)) {
                    $namecolumn = '<span class="critical">'.get_string('taskistaken', 'flexible').' - </span>' . $namecolumn;
                }

                $takeurl = new moodle_url('warning.php?id=' . $this->cm->id . '&action=taketask&taskid=' . $task->id . '&userid=' . $this->userid);
                $namecolumn .= ' ' . html_writer::link(
                    $takeurl,
                    '(' . get_string('providetask', 'flexible') . ')',
                    array('title' => get_string('providetask', 'flexible')));
            }
            else {
                $viewurl = new moodle_url('view.php',array('page' => 'taskview', 'taskid'=>$task->id,'id'=>$this->cm->id),'v','get');
                if ($task->hidden) {
                    $namecolumn = html_writer::link(
                        $viewurl,
                        $task->name,
                        array('title' => get_string('view'), 'class' => 'hiddentask'));
                }
                else {
                    $namecolumn = html_writer::link(
                        $viewurl,
                        $task->name,
                        array('title' => get_string('view')));
                }

                if ($this->taskgiverinstance) {
                    $namecolumn .= $this->taskgiverinstance->get_task_extra_string($task->id,$this->cm->id);
                }

                if ($this->hascapmanage) {

                    $updateurl = new moodle_url('view.php',
                                                array('taskid'=>$task->id,'id'=>$this->cm->id,'page' => 'taskedit'),'u','get');
                    $deleteurl = new moodle_url('warning.php',
                                                array('taskid'=>$task->id,
                                                        'action'=>'deletetask',
                                                        'id'=>$this->cm->id
                                                        ),
                                                'd',
                                                'get');

                    $showicon = '<a href="'.$updateurl.'">'.'<img src="'.$OUTPUT->pix_url('t/show').
                                '" class="iconsmall" alt="'.get_string('edit').'" title="'.get_string('edit').'" /></a>';
                    $hideicon = '<a href="'.$updateurl.'">'.'<img src="'.$OUTPUT->pix_url('t/hide').
                                '" class="iconsmall" alt="'.get_string('edit').'" title="'.get_string('edit').'" /></a>';
                    $updateicon = '<a href="'.$updateurl.'">'.'<img src="'.$OUTPUT->pix_url('t/edit').
                                '" class="iconsmall" alt="'.get_string('edit').'" title="'.get_string('edit').'" /></a>';
                    $deleteicon = '<a href="'.$deleteurl.'">'.'<img src="'.$OUTPUT->pix_url('t/delete').
                                '" class="iconsmall" alt="'.get_string('delete').'" title="'.get_string('delete').'" /></a>';
                    if ($task->hidden) {
                        $showurl = new moodle_url('view.php',
                                                  array('taskid' => $task->id,
                                                        'mode' => SHOW_MODE,
                                                        'id' => $this->cm->id,
                                                        'page' => 'taskedit'),
                                                  'u',
                                                  'get');
                        $showicon = '<a href="'.$showurl.'">'.'<img src="'.$OUTPUT->pix_url('t/show').
                                '" class="iconsmall" alt="'.get_string('show').'" title="'.get_string('show').'" /></a>';
                        $namecolumn .= '&nbsp;' . $showicon;
                    }
                    else {
                        $hideurl = new moodle_url('view.php',
                                                  array('taskid' => $task->id,
                                                        'mode' => HIDE_MODE,
                                                        'id' => $this->cm->id,
                                                        'page' => 'taskedit'),
                                                  'u',
                                                  'get');
                        $hideicon = '<a href="'.$hideurl.'">'.'<img src="'.$OUTPUT->pix_url('t/hide').
                                '" class="iconsmall" alt="'.get_string('hide').'" title="'.get_string('hide').'" /></a>';
                        $namecolumn .= '&nbsp;' . $hideicon;
                    }
                    $namecolumn.='&nbsp;'.$updateicon.'&nbsp;'.$deleteicon;

                }
            }
            $row[]=$namecolumn;
            $row[]=shorten_text(strip_tags($task->description));
            foreach ($fields as $field) {
                $value = '<span class="flexible-critical">'.get_string('notdefined', 'flexible').'</span>';
                if ($field->showintable>0) {
                    if ($this->hascapmanage ||(!$this->hascapmanage && !$field->secretfield)) {
                        $taskvalue=$DB->get_record('flexible_task_values',
                                                    array('taskid'=>$task->id, 'fieldid'=>$field->id, 'assigneeid'=>0));
                        if ($field->random == 1) {
                            $value= get_string('randomfield', 'flexible');
                        }
                        else {                        
                            if ($taskvalue) {
                                if (isset($taskvalue->value)) {
                                    switch ($field->ftype) {
                                        case TEXT:
                                            $value = shorten_text($taskvalue->value);
                                            break;
                                        case LISTOFELEMENTS:
                                            $variants = $poasmodel->get_variants($field->id);
                                            $variant = $variants[$taskvalue->value];
                                            $value = $variant;
                                            break;
                                        case MULTILIST:
                                            $indexes = explode(',', $taskvalue->value);
                                            $variants = $poasmodel->get_variants($field->id);
                                            $value = '';
                                            foreach ($indexes as $index) {
                                                if (is_number($index)) {
                                                    $value .= $variants[$index].'<br/>';
                                                }
                                            }
                                            break;
                                        case DATE:
                                            $value = userdate($taskvalue->value);
                                            break;
                                        case FILE:
                                            $context = get_context_instance(CONTEXT_MODULE, $this->cm->id);
                                            $value = $poasmodel->view_files($context->id,'flexibletaskfiles',$taskvalue->id);
                                            break;
                                        default:
                                            $value = $taskvalue->value; 
                                            break;
                                    }
                                }
                            }
                        }
                        $row[] = $value;
                    }
                }
            }
            $table->add_data($row);
        }
            $table->print_html();
    }
}
