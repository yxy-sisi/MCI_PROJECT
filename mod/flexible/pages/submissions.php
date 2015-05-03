<?php
global $CFG;
require_once('abstract_page.php');
require_once(dirname(dirname(__FILE__)) . '/model.php');
require_once($CFG->libdir . '/tablelib.php');
class submissions_page extends abstract_page {
    var $flexible;
    function submissions_page($cm, $flexible) {
        $this->flexible = $flexible;
        $this->cm = $cm;
    }
    function get_cap() {
        return 'mod/flexible:grade';
    }
    
    private function prepare_flexible_table() {
        global $PAGE;
        $table = new flexible_table('mod-flexible-submissions');
        $table->baseurl = $PAGE->url;
        $columns = array('picture');
        $columns[] = 'fullname';
        $headers = array(' ',get_string('fullname', 'flexible'));
        if($this->flexible->flags & ACTIVATE_INDIVIDUAL_TASKS) {
            $columns[]='task';
            $headers[]=get_string('task','flexible');
        }
        $columns[]='submission';
        $columns[]='status';
        $columns[]='submissiondate';
        $columns[]='gradedate';
        $columns[]='grade';
        $headers[]=get_string('submission','flexible');
        $headers[]=get_string('status','flexible');
        $headers[]=get_string('submissiondate','flexible');
        $headers[]=get_string('gradedate','flexible');
        $headers[]=get_string('grade','flexible');

        $table->define_columns($columns);
        $table->define_headers($headers);
        $table->collapsible(true);
        $table->initialbars(false);
        $table->set_attribute('class', 'flexible-table');
        $table->set_attribute('width', '100%');
        return $table;
    }
    function view() {
        global $DB, $CFG, $OUTPUT;
        $table = $this->prepare_flexible_table();
        
        $table->setup();
        $poasmodel=flexible_model::get_instance($this->flexible);
        //$assignees = $DB->get_records('flexible_assignee',array('flexibleid'=>$this->flexible->id));
        $plugins=$poasmodel->get_plugins();
        $groupmode = groups_get_activity_groupmode($this->cm);
        $currentgroup = groups_get_activity_group($this->cm, true);
        groups_print_activity_menu($this->cm, $CFG->wwwroot . '/mod/flexible/view.php?id='.$this->cm->id.'&page=submissions');
        $context=get_context_instance(CONTEXT_MODULE,$this->cm->id);

        $usersid = $poasmodel->get_users_with_active_tasks();
        $indtasks=$this->flexible->flags&ACTIVATE_INDIVIDUAL_TASKS;
        foreach($usersid as $userid) {
            $row = $this->get_row($userid, $this->flexible->id, $indtasks, $plugins);
            $table->add_data($row);
        }
        $table->print_html();
    }
    private function get_row($userid, $flexibleid, $indtasks, $plugins) {
        global $DB, $OUTPUT;
        $poasmodel = flexible_model::get_instance($flexibleid);
        
        // Row that will be returned
        $row = array();
        
        // Add user photo to the row
        $user = $DB->get_record('user', array('id' => $userid));
        $row[] = $OUTPUT->user_picture($user);
        
        // Add user's name to the row
        $userurl = new moodle_url('/user/profile.php', array('id' => $user->id));
        $row[]=html_writer::link($userurl,fullname($user, true));
        
        // Add task info to the row
        $assignee = $poasmodel->get_assignee($userid, $this->flexible->id);
        if($indtasks) {
            if($assignee && $assignee->taskid != 0) {
                $task = $DB->get_record('flexible_tasks',array('id'=>$assignee->taskid));
                $taskurl = new moodle_url('view.php',array('page' => 'taskview', 'taskid' => $assignee->taskid,'id' => $this->cm->id, 'assigneeid' => $assignee->id));
                $deleteurl = new moodle_url('warning.php',array('action'=>'canceltask','assigneeid'=>$assignee->id,'id'=>$this->cm->id),'d','post');
                $deleteicon = '<a href="'.$deleteurl.'">'.'<img src="'.$OUTPUT->pix_url('t/delete').
                            '" class="iconsmall" alt="'.get_string('canceltask', 'flexible').'" title="'.get_string('canceltask', 'flexible').'" /></a>';

                $row[]=html_writer::link($taskurl,$task->name).' '.$deleteicon;
            }
            else {
                $providetask = new moodle_url('view.php',array('page' => 'tasks', 'userid' => $assignee->userid, 'id' => $this->cm->id));
                $row[] = html_writer::link($providetask, get_string('notask','flexible'), array('title' => get_string('providetask','flexible')));
                //$row[]=get_string('notask','flexible');
            }
        }
        // Add last submission to the row
        $submis = '';
        if($assignee) {
            foreach($plugins as $plugin) {
                require_once($plugin->path);
                $flexibleplugin = new $plugin->name();
                $submis .= $flexibleplugin->show_assignee_answer($assignee->id,$this->flexible->id,0);
            }
        }
        if (strlen($submis) == 0)
            $submis = get_string('nosubmission', 'flexible');
        //$submis = shorten_text($submis);
        $row[]=$submis;
        
        // Add task status to the row
        if($assignee) {
            $attempts = $DB->get_records('flexible_attempts',array('assigneeid'=>$assignee->id));
            if(($indtasks && isset($assignee->taskid) && $assignee->taskid>0 && $attempts)||(!$indtasks && $attempts))
                $row[]=get_string('taskcompleted','flexible');
            if(($indtasks && isset($assignee->taskid) && $assignee->taskid>0 && !$attempts)||(!$indtasks && !$attempts))
                $row[]=get_string('taskinwork','flexible');
            if($indtasks && (!isset($assignee->taskid) || $assignee->taskid == 0))
                $row[]=get_string('notask','flexible');
        }
        else {
            if(!$indtasks)
                $row[]=get_string('taskinwork','flexible');
            if($indtasks)
                $row[]=get_string('notask','flexible');
        }
        
        // Add attempt date to the row
        if($assignee) {
            $attemptscount=$DB->count_records('flexible_attempts',array('assigneeid'=>$assignee->id));
            if($attemptscount>0) {
                $attempt=$DB->get_record('flexible_attempts',array('assigneeid'=>$assignee->id,'attemptnumber'=>$attemptscount));
                $row[]=userdate($attempt->attemptdate);
            }
            else    
                $row[] = get_string('nosubmission', 'flexible');
        }
        else    
            $row[] = get_string('nosubmission', 'flexible');
        
        // Add rating date to the row
        // Add rating to the row
        if($assignee) {

            $attemptscount=$DB->count_records('flexible_attempts',array('assigneeid'=>$assignee->id));
            $attempt=$DB->get_record('flexible_attempts',array('assigneeid'=>$assignee->id,'attemptnumber'=>$attemptscount));
            if($attempt) {
                $gradeurl = new moodle_url('view.php',array('page' => 'grade', 'assigneeid'=>$assignee->id,'id'=>$this->cm->id));
                if (isset($attempt->ratingdate)) {
                    $row[] = userdate($attempt->ratingdate);
                    if (isset($attempt->rating)) {
                        $ratingwithpenalty = $attempt->rating - $poasmodel->get_penalty($attempt->id);
                        if($attempt->ratingdate < $attempt->attemptdate)
                            $row[] = $ratingwithpenalty 
                                     . ' ('
                                     . get_string('outdated','flexible')
                                     . ') '
                                     . html_writer::link($gradeurl, get_string('editgrade', 'flexible'));
                        else {
                            $row[] = $ratingwithpenalty
                                     . ' '
                                     . html_writer::link($gradeurl, get_string('editgrade', 'flexible'));
                        }
                    }
                    else {
                        // Если нет оценки но есть дата - это был черновик
                        $row[] = get_string('draft','flexible')
                                 . ' '
                                 . html_writer::link($gradeurl, get_string('leavecomment', 'flexible'));
                    }
                }
                else {
                    $lastgraded = $poasmodel->get_last_graded_attempt($assignee->id);
                    if ($lastgraded == null) {
                        $row[] = '-';
                    }
                    else {
                        $row[] = userdate($lastgraded->ratingdate);
                    }

                    if ($attempt->draft == 1) {
                        $row[] = get_string('draft','flexible')
                                 . ' '
                                 . html_writer::link($gradeurl, get_string('leavecomment', 'flexible'));
                    }
                    else {
                        if ($lastgraded == null) {
                            $row[] = $OUTPUT->action_link($gradeurl, get_string('addgrade','flexible'));
                        }
                        else {
                            $ratingwithpenalty = $lastgraded->rating - $poasmodel->get_penalty($lastgraded->id);
                            $row[] = $ratingwithpenalty
                                    .' ('
                                    .get_string('outdated','flexible')
                                    .') '
                                    .html_writer::link($gradeurl,get_string('editgrade','flexible'));
                        }
                    }

                }
                /*
                if(isset($attempt->rating)) {
                    if($attempt->draft == 0) {
                        $ratingwithpenalty = $attempt->rating-$poasmodel->get_penalty($attempt->id);
                        if($attempt->ratingdate < $attempt->attemptdate)
                            $row[]=$ratingwithpenalty.' ('.get_string('outdated','flexible').') '.html_writer::link($gradeurl,get_string('editgrade','flexible'));
                        else
                            $row[]=$ratingwithpenalty.' '.html_writer::link($gradeurl,get_string('editgrade','flexible'));
                    }
                    else {
                        //$row[]='-';
                        $row[] = $OUTPUT->action_link($gradeurl, get_string('addgrade','flexible'));
                        //$row[]=html_writer::link($gradeurl,get_string('addgrade','flexible'));
                    }
                }
                if(!isset($attempt->rating)) {
                    $row[] = $OUTPUT->action_link($gradeurl, get_string('addgrade','flexible'));
                }*/
            }
            else {
                $row[]=get_string('noattemptsshort', 'flexible');
                $row[]=get_string('noattemptsshort', 'flexible');
            }
        }
        else {
                $row[]=get_string('noattemptsshort', 'flexible');
                $row[]=get_string('noattemptsshort', 'flexible');
        }
        return $row;
    }
}