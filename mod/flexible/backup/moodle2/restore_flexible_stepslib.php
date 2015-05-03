<?php

class restore_flexible_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('flexible', '/activity/flexible');
        $paths[] = new restore_path_element('flexible_criterion', '/activity/flexible/criterions/criterion');
        $paths[] = new restore_path_element('flexible_answersetting', '/activity/flexible/answersettings/answersetting');
        $paths[] = new restore_path_element('flexible_field', '/activity/flexible/fields/field');
        $paths[] = new restore_path_element('flexible_variant', '/activity/flexible/fields/field/variants/variant');
        $paths[] = new restore_path_element('flexible_usedgrader', '/activity/flexible/usedgraders/usedgrader');
        $paths[] = new restore_path_element('flexible_task', '/activity/flexible/tasks/task');
        $paths[] = new restore_path_element('flexible_nonrandomtaskvalue',
                '/activity/flexible/tasks/task/nonrandomtaskvalues/nonrandomtaskvalue');
        
        // userinfo 
        
        $paths[] = new restore_path_element('flexible_assignee', '/activity/flexible/assignees/assignee');
        $paths[] = new restore_path_element('flexible_randomtaskvalue',
                '/activity/flexible/assignees/assignee/randomtaskvalues/randomtaskvalue');
                
        $paths[] = new restore_path_element('flexible_attempt',
                '/activity/flexible/assignees/assignee/attempts/attempt');
        
        // now it's time to come back to assignees and define lastattemptid
        $paths[] = new restore_path_element('flexible_assignee_add_lastattemptid', '/activity/flexible/extraassignees/extraassignee');
                
        $paths[] = new restore_path_element('flexible_submission',
                '/activity/flexible/assignees/assignee/attempts/attempt/submissions/submission');
                
        $paths[] = new restore_path_element('flexible_ratings',
                '/activity/flexible/assignees/assignee/attempts/attempt/ratings/rating');
                
        // Apply for 'assignment' subplugins optional paths at assignment level
        $this->add_subplugin_structure('flexibletaskgivers', $flexible);

        //if ($userinfo) {
        //    $submission = new restore_path_element('assignment_submission', '/activity/assignment/submissions/submission');
        //    $paths[] = $submission;
            // Apply for 'assignment' subplugins optional stuff at submission level
        //    $this->add_subplugin_structure('assignment', $submission);
        //}

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_flexible($data) {
        echo '<br>'.__FUNCTION__;
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        if(isset($data->timedue)) {
            $data->timedue = $this->apply_date_offset($data->timedue);
        }
        if(isset($data->timeavailable)) {
            $data->timeavailable = $this->apply_date_offset($data->timeavailable);
        }
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        //if ($data->grade < 0) { // scale found, get mapping
        //    $data->grade = -($this->get_mappingid('scale', abs($data->grade)));
        //}

        // insert the assignment record
        $newitemid = $DB->insert_record('flexible', $data);
        // immediately after inserting "activity" record, call this
        $this->apply_activity_instance($newitemid);
    }
    protected function process_flexible_criterion($data) {
        echo '<br>'.__FUNCTION__;
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        
        $data->flexibleid = $this->get_new_parentid('flexible');
        
        $newitemid = $DB->insert_record('flexible_criterions', $data);
        $this->set_mapping('flexible_criterions', $oldid, $newitemid);
    }
    protected function process_flexible_answersetting($data) {
        echo '<br>'.__FUNCTION__;
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        
        $data->flexibleid = $this->get_new_parentid('flexible');
        
        $newitemid = $DB->insert_record('flexible_ans_stngs', $data);
        $this->set_mapping('flexible_ans_stngs', $oldid, $newitemid);
    }
    protected function process_flexible_field($data) {
        echo '<br>'.__FUNCTION__;
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        
        $data->flexibleid = $this->get_new_parentid('flexible');
        
        $newitemid = $DB->insert_record('flexible_fields', $data);
        $this->set_mapping('flexible_fields', $oldid, $newitemid);
    }
    protected function process_flexible_variant($data) {
        echo '<br>'.__FUNCTION__;
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        
        $data->fieldid = $this->get_mappingid('flexible_fields', $data->fieldid);
        
        $newitemid = $DB->insert_record('flexible_variants', $data);
        $this->set_mapping('flexible_variants', $oldid, $newitemid);
    }
    protected function process_flexible_usedgrader($data) {
        echo '<br>'.__FUNCTION__;
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        
        $data->flexibleid = $this->get_new_parentid('flexible');
        
        $newitemid = $DB->insert_record('flexible_used_graders', $data);
        $this->set_mapping('flexible_used_graders', $oldid, $newitemid);
    }
    
    protected function process_flexible_task($data) {
        echo '<br>'.__FUNCTION__;
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        
        $data->flexibleid = $this->get_new_parentid('flexible');
        
        $newitemid = $DB->insert_record('flexible_tasks', $data);
        $this->set_mapping('flexible_tasks', $oldid, $newitemid);
    }
    protected function process_flexible_nonrandomtaskvalue($data) {
        echo '<br>'.__FUNCTION__;
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        
        $data->taskid = $this->get_mappingid('flexible_tasks', $data->taskid);
        $data->fieldid = $this->get_mappingid('flexible_fields', $data->fieldid);
        // $data->assigneeid here we process nonrandom task values so assigneeid was 0
        
        $newitemid = $DB->insert_record('flexible_task_values', $data);
        $this->set_mapping('flexible_task_values', $oldid, $newitemid);
    }
    
    protected function process_flexible_assignee($data) {
        echo '<br>'.__FUNCTION__;
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        
        $data->flexibleid = $this->get_new_parentid('flexible');
        $data->taskid = $this->get_mappingid('flexible_tasks', $data->taskid);
        // $data->lastattemptid - we will be back soon to update this value. 
        // At the moment we don't have attempts
        
        $newitemid = $DB->insert_record('flexible_assignee', $data);
        $this->set_mapping('flexible_assignee', $oldid, $newitemid);
    }
    protected function process_flexible_randomtaskvalue($data) {
        echo '<br>'.__FUNCTION__;
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        
        $data->taskid = $this->get_mappingid('flexible_tasks', $data->taskid);
        $data->fieldid = $this->get_mappingid('flexible_fields', $data->fieldid);
        $data->assigneeid = $this->get_mappingid('flexible_assignee', $data->assigneeid);
        
        $newitemid = $DB->insert_record('flexible_task_values', $data);
        $this->set_mapping('flexible_task_values', $oldid, $newitemid);
    }
    protected function process_flexible_attempt($data) {
        echo '<br>'.__FUNCTION__;
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        
        $data->assigneeid = $this->get_mappingid('flexible_assignee', $data->assigneeid);
        
        $newitemid = $DB->insert_record('flexible_attempts', $data);
        $this->set_mapping('flexible_attempts', $oldid, $newitemid);
    }
    protected function process_flexible_assignee_add_lastattemptid($data) {
        echo '<br>'.__FUNCTION__;
        global $DB;

        $data = (object)$data;
        $newitemid = $this->get_mappingid('flexible_assignee', $data->id);
        $assignee = $DB->get_record('flexible_assignee', array('id' => $newitemid));
        $assignee->lastattemptid = $this->get_mappingid('flexible_attempts', $assignee->lastattemptid);
        
        // $data->lastattemptid - we will be back soon to update this value. 
        // At the moment we don't have attempts
        
        $DB->update_record('flexible_assignee', $assignee);
    }
    protected function process_flexible_submission($data) {
        echo '<br>'.__FUNCTION__;
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        
        $data->attemptid = $this->get_mappingid('flexible_attempts', $data->attemptid);
        
        $newitemid = $DB->insert_record('flexible_submissions', $data);
        $this->set_mapping('flexible_submissions', $oldid, $newitemid);
    }
    protected function process_flexible_ratings($data) {
        echo '<br>'.__FUNCTION__;
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        
        $data->attemptid = $this->get_mappingid('flexible_attempts', $data->attemptid);
        $data->criterionid = $this->get_mappingid('flexible_criterions', $data->criterionid);
        
        $newitemid = $DB->insert_record('flexible_rating_values', $data);
        $this->set_mapping('flexible_rating_values', $oldid, $newitemid);
    }
    /* protected function process_assignment_submission($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->assignment = $this->get_new_parentid('assignment');
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->timemarked = $this->apply_date_offset($data->timemarked);

        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->teacher = $this->get_mappingid('user', $data->teacher);

        $newitemid = $DB->insert_record('assignment_submissions', $data);
        $this->set_mapping('assignment_submission', $oldid, $newitemid, true); // Going to have files
    } */

    protected function after_execute() {
        //print_r($this);
        // Add assignment related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_flexible', 'flexiblefiles', null);
        $this->add_related_files('mod_flexible', 'flexibletaskfiles', 'flexible_task_values');
        $this->add_related_files('mod_flexible', 'submissionfiles', 'flexible_submission');
        $this->add_related_files('mod_flexible', 'commentfiles', null);
        // Add assignment submission files, matching by assignment_submission itemname
        //$this->add_related_files('mod_assignment', 'submission', 'assignment_submission');
        //$this->add_related_files('mod_assignment', 'response', 'assignment_submission');
    }
}
