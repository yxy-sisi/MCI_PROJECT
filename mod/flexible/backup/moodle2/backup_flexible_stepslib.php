<?php

class backup_flexible_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated
        $flexible = new backup_nested_element('flexible', array('id'), array(
                'name', 'intro', 'introformat', 'timemodified', 
                'availabledate', 'choicedate', 'deadline', 'flags',
                'taskgiverid', 'uniqueness', 'penalty'));
                
        $criterions = new backup_nested_element('criterions');
        $criterion = new backup_nested_element('criterion', array('id'), array(
                'name', 'weight', 'description', 'graderid'));
                
        $answersettings = new backup_nested_element('answersettings');
        $answersetting = new backup_nested_element('answersetting', array('id'), array(
                'name', 'value', 'answerid'));
                
        $fields = new backup_nested_element('fields');
        $field = new backup_nested_element('field', array('id'), array(
                'ftype', 'name', 'showintable', 'valuemax', 'valuemin',
                'secretfield', 'random', 'description'));
                
        $variants = new backup_nested_element('variants');
        $variant = new backup_nested_element('variant', array('id'), array(
                'fieldid', 'sortorder', 'value'));
                
        $usedgraders = new backup_nested_element('usedgraders');
        $usedgrader = new backup_nested_element('usedgrader', array('id'), array(
                'graderid'));
                
        $tasks = new backup_nested_element('tasks');
        $task = new backup_nested_element('task', array('id'), array(
                'name', 'description', 'deadline', 'hidden'));
                
        $nonrandomtaskvalues = new backup_nested_element('nonrandomtaskvalues');
        $nonrandomtaskvalue = new backup_nested_element('nonrandomtaskvalue', array('id'), array(
                'taskid', 'fieldid', 'value', 'assigneeid'));
        
        //userinfo here
        $assignees = new backup_nested_element('assignees');
        $assignee = new backup_nested_element('assignee', array('id'), array(
                'userid', 'teacher', 'timemarked', 'taskid', 'finalized'));
        
        $randomtaskvalues = new backup_nested_element('randomtaskvalues');
        $randomtaskvalue = new backup_nested_element('randomtaskvalue', array('id'), array(
                'taskid', 'fieldid', 'value', 'assigneeid'));
                
        $attempts = new backup_nested_element('attempts');
        $attempt = new backup_nested_element('attempt', array('id'), array(
                'assigneeid', 'attemptnumber', 'rating', 'attemptdate', 
                'ratingdate', 'disablepenalty', 'draft', 'final'));
                
        $extraassignees = new backup_nested_element('extraassignees');
        $extraassignee = new backup_nested_element('extraassignee', array('id'), array('lastattemptid'));
        
        $submissions = new backup_nested_element('submissions');
        $submission = new backup_nested_element('submission', array('id'), array(
                'attemptid', 'answerid', 'value'));
                
        $ratings = new backup_nested_element('ratings');
        $rating = new backup_nested_element('rating', array('id'), array(
                'criterionid', 'value', 'attemptid'));
        
        // Build the tree

        // Apply for 'assignment' subplugins optional stuff at assignment level (not multiple)
        // Remember that order is important, try moving this line to the end and compare XML
        $this->add_subplugin_structure('flexibletaskgivers', $flexible, false);
//        $this->add_subplugin_structure('assignment', $assignment, false);

        $flexible->add_child($criterions);
        $criterions->add_child($criterion);
        
        $flexible->add_child($answersettings);
        $answersettings->add_child($answersetting);
        
        $flexible->add_child($fields);
        $fields->add_child($field);
        
        $field->add_child($variants);
        $variants->add_child($variant);
        
        $flexible->add_child($usedgraders);
        $usedgraders->add_child($usedgrader);
        
        $flexible->add_child($tasks);
        $tasks->add_child($task);
        
        $task->add_child($nonrandomtaskvalues);
        $nonrandomtaskvalues->add_child($nonrandomtaskvalue);
       /* 
        if($userinfo)
            echo 'userinfoyes';
        else
            echo 'userinfono';
            */
        //userinfo
        
        $flexible->add_child($assignees);
        $assignees->add_child($assignee);
        
        $assignee->add_child($randomtaskvalues);
        $randomtaskvalues->add_child($randomtaskvalue);
        
        $assignee->add_child($attempts);
        $attempts->add_child($attempt);
        
        $flexible->add_child($extraassignees);
        $extraassignees->add_child($extraassignee);
        
        $attempt->add_child($submissions);
        $submissions->add_child($submission);
        
        $attempt->add_child($ratings);
        $ratings->add_child($rating);

        // Apply for 'assignment' subplugins optional stuff at submission level (not multiple)
//        $this->add_subplugin_structure('assignment', $submission, false);

        // Define sources
        $flexible->set_source_table('flexible', array('id' => backup::VAR_ACTIVITYID));
        $criterion->set_source_table('flexible_criterions', array('flexibleid' => backup::VAR_ACTIVITYID));
        $answersetting->set_source_table('flexible_ans_stngs', array('flexibleid' => backup::VAR_ACTIVITYID));
        $field->set_source_table('flexible_fields', array('flexibleid' => backup::VAR_ACTIVITYID));
        $variant->set_source_table('flexible_variants', array('fieldid' => backup::VAR_PARENTID));
        $usedgrader->set_source_table('flexible_used_graders', array('flexibleid' => backup::VAR_ACTIVITYID));
        $task->set_source_table('flexible_tasks', array('flexibleid' => backup::VAR_ACTIVITYID));
                
        $nonrandomtaskvalue->set_source_sql("
                SELECT *
                  FROM {flexible_task_values}
                  WHERE assigneeid = 0 AND taskid = ?",
                  array(backup::VAR_PARENTID));
                  
        // userinfo 
        
        $assignee->set_source_table('flexible_assignee', array('flexibleid' => backup::VAR_ACTIVITYID));
        $randomtaskvalue->set_source_table('flexible_task_values', array('assigneeid' => backup::VAR_PARENTID));
        $attempt->set_source_table('flexible_attempts', array('assigneeid' => backup::VAR_PARENTID));
        $extraassignee->set_source_table('flexible_assignee', array('flexibleid' => backup::VAR_ACTIVITYID));
        $submission->set_source_table('flexible_submissions', array('attemptid' => backup::VAR_PARENTID));
        $rating->set_source_table('flexible_rating_values', array('attemptid' => backup::VAR_PARENTID));
        // All the rest of elements only happen if we are including user info
        //if ($userinfo) {
        //    $submission->set_source_table('assignment_submissions', array('assignment' => backup::VAR_PARENTID));
        //}

        // Define id annotations
        //$flexible->annotate_ids('scale', 'grade');
        //$submission->annotate_ids('user', 'userid');
        //$submission->annotate_ids('user', 'teacher');

        // Define file annotations
        $flexible->annotate_files('mod_flexible', 'flexiblefiles', null); // This file area hasn't itemid
        $nonrandomtaskvalue->annotate_files('mod_flexible', 'flexibletaskfiles', 'id');
        $submission->annotate_files('mod_flexible', 'submissionfiles', 'id');
        $flexible->annotate_files('mod_flexible', 'commentfiles', null);
        //$submission->annotate_files('mod_assignment', 'submission', 'id');
        //$submission->annotate_files('mod_assignment', 'response', 'id');

        // Return the root element (assignment), wrapped into standard activity structure
        return $this->prepare_activity_structure($flexible);
    }
}
