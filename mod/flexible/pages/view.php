<?php
require_once('abstract_page.php');
require_once(dirname(dirname(__FILE__)) . '/model.php');
class view_page extends abstract_page {
    var $flexible;
    var $context;

    /** Constructor, initializes variables $flexible, $cm, $context
     */
    function view_page($cm,$flexible) {
        $this->flexible = $flexible;
        $this->cm = $cm;
        $this->context=get_context_instance(CONTEXT_MODULE, $cm->id);
    }

    function view() {
        global $OUTPUT,$USER;
        //$poasmodel = flexible_model::get_instance($this->flexible);
        $poasmodel = flexible_model::get_instance();
        $poasmodel->cash_assignee_by_user_id($USER->id);
        // Show submission statistics if user has capability
        if (has_capability('mod/flexible:grade', $this->context))
            echo '<div align="right">'.$poasmodel->get_statistics().'</div>';

        // Show flexible intro
        $this->view_intro();

        // Show task files
        echo $poasmodel->view_files($this->context->id, 'flexiblefiles',0);
        $poasmodel->get_files('flexiblefiles', 0);
        echo $OUTPUT->box_end();
        $this->view_status();
        $this->view_dates();
        $this->view_feedback();
        $this->view_testresult();
        $this->view_answer_block();
    }

    /** Show task status
     *
     *  Draws box with information about student task only if individual tasks
     *  mode is activate.
     */
    function view_status() {
        if (!has_capability('mod/flexible:havetask', $this->context)) {
            return;
        }
        global $DB,$USER,$OUTPUT;
        $poasmodel = flexible_model::get_instance($this->flexible);
        // If individual tasks mode is active
        if ($this->flexible->flags & ACTIVATE_INDIVIDUAL_TASKS) {
            echo $OUTPUT->box_start('generalbox boxaligncenter', 'intro');
            echo $OUTPUT->heading(get_string('status','flexible'));
            if ($error = $poasmodel->check_dates()) {
                echo '<div class="flexible-critical">' . get_string($error, 'flexible') . '</div>';
            }
            else {
                $assignee = $poasmodel->get_assignee($USER->id, $this->flexible->id);
                if ($assignee && $assignee->taskid > 0) {
                    echo get_string('youhavetask', 'flexible') . ' ';
                    // Show link to the task
                    $taskurl = new moodle_url('view.php',
                        array('page' => 'taskview',
                            'taskid' => $assignee->taskid,
                            'id' => $this->cm->id,
                            'from' => 'view'),
                        'v',
                        'get');
                    $task = $DB->get_record('flexible_tasks', array('id' => $assignee->taskid));
                    echo html_writer::link($taskurl, $task->name);

                    // If user can cancel task - show cancel button
                    if($poasmodel->can_cancel_task($assignee->id, $this->context)) {
                        //if (has_capability('mod/flexible:managetasks', $this->context)) {
                        $deleteurl = new moodle_url('warning.php',
                            array(
                                'action'=>'canceltask',
                                'assigneeid'=>$assignee->id,
                                'id'=>$this->cm->id),
                            'd',
                            'post');
                        $deleteicon = '<a href="' . $deleteurl . '">'.'<img src="' . $OUTPUT->pix_url('t/delete').
                            '" class="iconsmall" alt="'.get_string('delete').'" title="'.get_string('delete').'" /></a>';
                        echo ' '.$deleteicon;
                    }

                    echo     '<br/>'.
                        get_string('taskwastakenat', 'flexible').
                        ' - '.
                        userdate($assignee->timetaken).
                        ' ('.
                        flexible_model::time_difference($assignee->timetaken).
                        ')';

                    if(!empty($this->flexible->deadline)) {
                        echo '<br><br>';
                        echo '<b>' .
                            get_string('timetocompletetask', 'flexible') .
                            ': ' .
                            flexible_model::time_difference($this->flexible->deadline) .
                            '</b>';
                    }
                }
                else {
                    // If user have no task - show link to task page
                    echo get_string('youhavenotask', 'flexible');
                    $taskspageurl = new moodle_url('view.php', array('id'=>$this->cm->id, 'page'=>'tasks'));
                    echo ' '.html_writer::link($taskspageurl, get_string('gototasskpage', 'flexible'));
                    if(!empty($this->flexible->choicedate) && time() < $this->flexible->choicedate) {
                        echo '<br><br>';
                        echo '<b>' .
                            get_string('timetochoosetask', 'flexible') .
                            ': ' .
                            flexible_model::time_difference($this->flexible->choicedate) .
                            '</b>';
                    }
                }
            }
            echo $OUTPUT->box_end();
        }
    }

    /** Show module intro
     */
    function view_intro() {
        global $OUTPUT;
        echo $OUTPUT->box_start('generalbox boxaligncenter', 'intro');
        echo $OUTPUT->heading(get_string('taskdescription','flexible'));
        echo format_module_intro('poassignment', $this->flexible, $this->cm->id);
    }

    /** Show dates (available date, choice date, deadline) if they exist
     */
    function view_dates() {
        global $OUTPUT;
        if (!empty($this->flexible->availabledate) && !empty($this->flexible->choicedate) && !empty($this->flexible->deadline)) {
            echo $OUTPUT->box_start('generalbox boxaligncenter', 'dates');
            echo '<table>';
            if (!empty($this->flexible->availabledate)) {
                echo '<tr><td class="c0">'.get_string('availablefrom','flexible').'</td>';
                echo '    <td class="c1">'.userdate($this->flexible->availabledate).'</td></tr>';
            }
            if (!empty($this->flexible->choicedate)) {
                echo '<tr><td class="c0">'.get_string('selectbefore','flexible').'</td>';
                echo '<td class="c1">'
                        . userdate($this->flexible->choicedate)
                        . ' ('
                        . flexible_model::time_difference($this->flexible->choicedate)
                        .')</td></tr>';
            }
            if (!empty($this->flexible->deadline)) {
                echo '<tr><td class="c0">'.get_string('deadline','flexible').'</td>';
                echo '<td class="c1">'
                        . userdate($this->flexible->deadline)
                        . ' ('
                        . flexible_model::time_difference($this->flexible->deadline)
                        . ')</td></tr>';
            }
            echo '</table>';
            echo $OUTPUT->box_end();
        }
    }
    function view_feedback_optimized() {
        global $OUTPUT,$DB,$USER;
        if($assignee=$DB->get_record('flexible_assignee', array('flexibleid'=>$this->flexible->id,
                                                                        'userid'=>$USER->id))) {
            if($lastattempt = $DB->get_record('flexible_attempts',array('id'=>$assignee->lastattemptid))) {

            }
        }
    }
    /** Show teacher comments and submissions for last graded attempt
     */
    function view_feedback() {
        //$this->view_feedback_optimized();
        //return;

        global $OUTPUT,$DB,$USER;

        $poasmodel = flexible_model::get_instance($this->flexible);
        if (!$assignee = $poasmodel->get_assignee($USER->id, $this->flexible->id)) {
            return;
        }

        $attempts=array_reverse($DB->get_records('flexible_attempts',array('assigneeid'=>$assignee->id),'attemptnumber'));
        $plugins=$poasmodel->get_plugins();
        $criterions=$DB->get_records('flexible_criterions',array('flexibleid'=>$this->flexible->id));
        $latestattempt = $poasmodel->get_last_attempt($assignee->id);
        $attemptscount=count($attempts);
        // show latest graded feedback
        foreach($attempts as $attempt) {
            if(!$attempt->ratingdate) {
                continue;
            }
            echo $OUTPUT->box_start();
            echo $OUTPUT->heading(get_string('lastgraded','flexible'));
            $hascap = has_capability('mod/flexible:viewownsubmission', $poasmodel->get_context());
            echo attempts_page::show_attempt($attempt, $hascap);
            $canseecriteriondescr = has_capability('mod/flexible:seecriteriondescription', $poasmodel->get_context());
            attempts_page::show_feedback($attempt, $latestattempt, $canseecriteriondescr);
            echo $OUTPUT->box_end();
            break;
        }
    }

    function view_testresult() {
        global $DB, $OUTPUT, $USER;
        $poasmodel = flexible_model::get_instance();
        if(!$DB->record_exists('flexible_used_graders', array('flexibleid' => $this->flexible->id)))
            return;
        if(!$assignee=$DB->get_record('flexible_assignee', array('flexibleid'=>$this->flexible->id,
                                                                            'userid'=>$USER->id)))
            return;

        $attempts=array_reverse($DB->get_records('flexible_attempts',array('assigneeid'=>$assignee->id),'attemptnumber'));

        foreach ($attempts as $attempt) {
            // ask grader if student have test results
            if(!$poasmodel->have_test_results($attempt)) {
                continue;
            }
            else {
                echo $OUTPUT->box_start();
                echo $OUTPUT->heading(get_string('lasttestresults','flexible'));
                echo $OUTPUT->heading(get_string('attemptnumber','flexible').':'.$attempt->attemptnumber.' ('.userdate($attempt->attemptdate).')');
                echo $poasmodel->show_test_results($attempt);
                echo $OUTPUT->box_end();
                break;
            }
        }

    }
    function view_answer_block() {
        $poasmodel=flexible_model::get_instance();
        if (!has_capability('mod/flexible:havetask', $poasmodel->get_context())) {
            return;
        }
        require_once('attempts.php');
        global $OUTPUT,$DB,$USER;
        $poasmodel=flexible_model::get_instance();
        $plugins=$poasmodel->get_plugins();
        $attemptscount=$DB->count_records('flexible_attempts',array('assigneeid'=>$poasmodel->assignee->id));
        // if individual tasks mode is active
        if($this->flexible->flags & ACTIVATE_INDIVIDUAL_TASKS) {
            if($DB->record_exists('flexible_assignee',
                            array('flexibleid'=>$this->flexible->id,'userid'=>$USER->id))) {
                if($attempt=$DB->get_record('flexible_attempts',
                            array('assigneeid'=>$poasmodel->assignee->id,'attemptnumber'=>$attemptscount))) {
                    $hascap = has_capability('mod/flexible:viewownsubmission', $poasmodel->get_context());
                    echo $OUTPUT->heading(get_string('lastattempt','flexible'));
                    echo attempts_page::show_attempt($attempt, $hascap);

                    /* If student has several attempts and hasn't final grade */
                    if($this->flexible->flags&SEVERAL_ATTEMPTS && $poasmodel->assignee->finalized!=1) {
                        if(!$attempt->final || ($attempt->final  && $attempt->rating!=null ))
                            if($submission=$DB->get_records('flexible_attempts',array('assigneeid'=>$poasmodel->assignee->id))) {
                                echo $OUTPUT->single_button(new moodle_url('view.php',
                                                                           array('id' => $this->cm->id,
                                                                                 'assigneeid' => $poasmodel->assignee->id,
                                                                                 'page' => 'submission')),
                                                            get_string('editsubmission','flexible'));
                            }
                    }
                }

                if($poasmodel->assignee->taskid > 0 && !$DB->get_records('flexible_attempts',array('assigneeid'=>$poasmodel->assignee->id))) {
                            echo $OUTPUT->single_button(new moodle_url('view.php',array('id'=>$this->cm->id,'page' => 'submission')),get_string('addsubmission','flexible'));
                }
            }
        }
        else {
            if($DB->record_exists('flexible_assignee',
                            array('flexibleid'=>$this->flexible->id,'userid'=>$USER->id))) {
                $attempt=$DB->get_record('flexible_attempts',
                            array('assigneeid'=>$poasmodel->assignee->id,'attemptnumber'=>$attemptscount));
                echo $OUTPUT->heading(get_string('yoursubmissions','flexible'));
                foreach($plugins as $plugin) {
                    require_once($plugin->path);
                    $flexibleplugin = new $plugin->name();
                    echo $flexibleplugin->show_assignee_answer($poasmodel->assignee->id,$this->flexible->id);
                }
                if($this->flexible->flags&SEVERAL_ATTEMPTS) {
                    if($attempt && (!$attempt->final || ($attempt->final  && $attempt->rating>0 )))
                        if($submission=$DB->get_records('flexible_attempts',array('assigneeid'=>$poasmodel->assignee->id))) {
                            echo $OUTPUT->single_button(new moodle_url('view.php',
                                            array('id'=>$this->cm->id,'assigneeid'=>$poasmodel->assignee->id,'page'=>'submission')),get_string('editsubmission','flexible'));
                }
            }
            }
            else {
                echo '<div align="center">';
                echo $OUTPUT->single_button(new moodle_url('view.php',array('id'=>$this->cm->id, 'page' => 'submission')),get_string('addsubmission','flexible'));
                echo '</div>';
            }

        }
    }
}