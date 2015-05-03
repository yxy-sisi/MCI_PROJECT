<?php
require_once('lib.php');
require_once('answer/answer.php');
require_once($CFG->dirroot . '/comment/lib.php');
comment::init();
/**
 * Main DB-work class. Singletone
 */
class flexible_model {

    /**
     * flexible instance
     */
    var $flexible;

    /**
     * Types of fields of tasks
     * @var array
     */
    var $ftypes;

    /**
     * Context of flexible instance
     */
    var $context;

    private $cm;
    private $course;
     /**
     * Context of flexible instance
     */
    var $assignee;

    /**
     * Answer plugins array
     * @var array
     */
    private $plugins=array();

    /**
     * Grader plugins array
     * @var array
     */
    private $graders=array();

    private $usedgraders;

    /**
     * Saves object of flexible_model class
     * @var flexible_model
     */
    protected static $model;

    public static $extpages = array('tasksfields' => 'pages/tasksfields.php',
                                    'tasks' => 'pages/tasks.php',
                                    'taskgiversettings' => 'pages/taskgiversettings.php',
                                    'view' => 'pages/view.php',
                                    'attempts' => 'pages/attempts.php',
                                    'graderresults' => 'pages/graderresults.php',
                                    'criterions' => 'pages/criterions.php',
                                    'graders' => 'pages/graders.php',
                                    'submissions' => 'pages/submissions.php',
                                    'grade' => 'pages/grade.php',
                                    'taskview' => 'pages/taskview.php',
                                    'submission' => 'pages/submission.php',
                                    'taskfieldedit' => 'pages/taskfieldedit.php',
                                    'categoryedit' => 'pages/categoryedit.php',
                                    'taskedit' => 'pages/taskedit.php',
                                    'tasksimport' => 'pages/tasksimport.php',
                                    'auditortasks' => 'pages/auditortasks.php',
                                    );
    private static $flags = array(
                            'preventlatechoice' => PREVENT_LATE_CHOICE,
                            'randomtasksafterchoicedate' => RANDOM_TASKS_AFTER_CHOICEDATE,
                            'preventlate' => PREVENT_LATE,
                            'severalattempts' => SEVERAL_ATTEMPTS,
                            'notifyteachers' => NOTIFY_TEACHERS,
                            'notifystudents' => NOTIFY_STUDENTS,
                            'activateindividualtasks' => ACTIVATE_INDIVIDUAL_TASKS,
                            'secondchoice' => SECOND_CHOICE,
                            'teacherapproval' => TEACHER_APPROVAL,
                            'newattemptbeforegrade' => ALL_ATTEMPTS_AS_ONE,
                            'finalattempts' => MATCH_ATTEMPT_AS_FINAL,
                            'cyclicrandom' => flexible_CYCLIC_RANDOM);

    /**
     * Cached result of check_dates result
     */
    private $checkdateerror = null;

    /**
     * Constructor. Cannot be called outside of the class
     * @param $flexible module instance
     */
    private function __construct($flexible = null) {
        //echo 'creating';
        global $DB,$USER;
        $this->flexible = $flexible;
        if (isset($this->flexible->id)) {
            $this->assignee=$DB->get_record('flexible_assignee',array('userid'=>$USER->id,'flexibleid'=>$this->flexible->id));
        }
        else {
            //echo 'Constructing model without id';
        }
        if (!$this->assignee) {
            $this->assignee = new stdClass();
            $this->assignee->id = 0;
        }

        $this->initArrays();
    }
    /**
     * Method is used instead of constructor. If flexible_model
     * object exists, returns it, otherwise creates object and returns it.
     * @param $flexible module instance
     * @return flexible_model
     */
    static function &get_instance($flexible=null) {
        if (self::$model==null) {
            self::$model = new self($flexible);
        }
        return self::$model;
    }

    public function cash_instance($id) {
        global $DB;
        if (!$DB->record_exists('flexible', array('id' => $id))) {
            print_error('nonexistentmoduleinstance', 'flexible');
        }
        else {
            if(!isset($this->flexible)
               || $this->flexible->id !== $id) {
                $this->flexible = $DB->get_record('flexible', array('id' => $id));
                $this->course = $DB->get_record('course',
                                                array('id' => $this->flexible->course),
                                                '*',
                                                MUST_EXIST);
                $this->cm = get_coursemodule_from_instance('flexible',
                                                           $this->flexible->id,
                                                           $this->course->id,
                                                           false,
                                                           MUST_EXIST);
                $this->context = get_context_instance(CONTEXT_MODULE,$this->cm->id);
                //echo 'change';
            }
        }
        //echo "now i store instance $id";
    }
    public function cash_assignee_by_user_id($userid) {
        $this->assignee = $this->get_assignee($userid, $this->flexible->id);
        if(!$this->assignee) {
            $this->assignee->id = 0;
        }
    }
    private function initArrays() {
        global $DB;
        $this->ftypes = array(get_string('char','flexible'),
                              get_string('text','flexible'),
                              get_string('float','flexible'),
                              get_string('int','flexible'),
                              get_string('date','flexible'),
                              get_string('file','flexible'),
                              get_string('list','flexible'),
                              get_string('multilist','flexible'));

        $this->plugins=$DB->get_records('flexible_answers');
        $this->graders = $DB->get_records('flexible_graders');
        $this->taskgivers = $DB->get_records('flexible_taskgivers');
    }
    /**
     * Returns flexible answer plugins
     * @return array
     */
    public function get_plugins() {
        global $DB;
        if (!$this->plugins)
            $this->plugins = $DB->get_records('flexible_answers');
        return $this->plugins;
    }

    public function get_flexible() {
        return $this->flexible;
    }
    public function get_cm() {
        return $this->cm;
    }
    public function get_course() {
        return $this->course;
    }
    public function get_context() {
        return $this->context;
    }

    public function get_graders() {
        return $this->graders;
    }

    public function has_flag($flag) {
        return (isset($this->flexible) && $this->flexible->flags & $flag);
    }

    public function get_assigneeid() {
        return $this->assignee->id;
    }
    /**
     * Inserts flexible data into DB
     * @return int flexible id
     */
    function add_instance() {
        global $DB;
        $this->flexible->flags = self::configure_flags($this->flexible);
        $this->flexible->timemodified = time();
        if(!isset($this->flexible->taskgiverid)) {
            $this->flexible->taskgiverid = 0;
        }

        $this->flexible->id = $DB->insert_record('flexible', $this->flexible);
        foreach ($this->plugins as $plugin) {
            require_once($plugin->path);
            $flexibleplugin = new $plugin->name();
            $flexibleplugin->configure_flag($this->flexible);
            $flexibleplugin->save_settings($this->flexible,$this->flexible->id);
        }
        foreach ($this->graders as $graderrecord) {
            require_once($graderrecord->path);
            $gradername = $graderrecord->name;
            if (isset($this->flexible->$gradername)) {
                $rec = new stdClass();
                $rec->flexibleid = $this->flexible->id;
                $rec->graderid = $graderrecord->id;
                $DB->insert_record('flexible_used_graders',$rec);
            }
            unset($this->flexible->$gradername);
        }
        $this->context = get_context_instance(CONTEXT_MODULE, $this->flexible->coursemodule);
        $this->save_files($this->flexible->flexiblefiles, 'flexiblefiles', 0);

        // Create 1 criterion
        $criterion = new stdClass();
        $criterion->name = get_string('standardcriterionname', 'flexible');
        $criterion->description = get_string('standardcriteriondesc', 'flexible');
        $criterion->weight = 1;
        $criterion->graderid = 0;
        $criterion->flexibleid = $this->flexible->id;
        $DB->insert_record('flexible_criterions', $criterion);
        //$this->grade_item_update();
        return $this->flexible->id;
    }

    /**
     * Updates flexible data in DB
     * @return int flexible id
     */
    function update_instance() {
        global $DB;
        $this->flexible->flags = self::configure_flags($this->flexible);
        $this->flexible->timemodified = time();
        if(!isset($this->flexible->taskgiverid)) {
            $this->flexible->taskgiverid = 0;
        }


        foreach ($this->plugins as $plugin) {
            require_once($plugin->path);
            $flexibleplugin = new $plugin->name();
            $flexibleplugin->configure_flag($this->flexible);
            $flexibleplugin->update_settings($this->flexible);
        }
        foreach ($this->graders as $graderrecord) {
            require_once($graderrecord->path);
            $gradername = $graderrecord->name;

            $rec = new stdClass();
            $rec->flexibleid = $this->flexible->id;
            $rec->graderid = $graderrecord->id;

            $isgraderused = $DB->record_exists('flexible_used_graders',
                                               array('flexibleid' => $rec->flexibleid,
                                                     'graderid' => $rec->graderid));
            if (isset($this->flexible->$gradername)) {
                if (!$isgraderused)
                    $DB->insert_record('flexible_used_graders',$rec);
            }
            else {
                if ($isgraderused)
                    $DB->delete_records('flexible_used_graders',
                                               array('flexibleid' => $rec->flexibleid,
                                                     'graderid' => $rec->graderid));
            }
            unset($this->flexible->$gradername);
        }
        //$this->flexible->taskgiverid++;
        $oldflexible = $DB->get_record('flexible', array('id' => $this->flexible->id));
        if($oldflexible->taskgiverid != $this->flexible->taskgiverid && $oldflexible->taskgiverid > 0) {
            $this->delete_taskgiver_settings($oldflexible->id, $oldflexible->taskgiverid);
        }
        $flexibleid = $DB->update_record('flexible', $this->flexible);

        $cm = get_coursemodule_from_instance('flexible', $this->flexible->id);
        $this->delete_files($cm->id, 'flexible', 0);
        $this->context = get_context_instance(CONTEXT_MODULE, $this->flexible->coursemodule);
        $this->save_files($this->flexible->flexiblefiles, 'flexiblefiles', 0);
        return $this->flexible->id;
    }

    /**
     * Deletes flexible data from DB
     * @param int $id id of flexible to be deleted
     * @return bool
     */
    function delete_instance($id) {
        global $DB;
        if (! $DB->record_exists('flexible', array('id' => $id))) {
            return false;
        }
        $cm = get_coursemodule_from_instance('flexible', $id);
        $this->flexible=$DB->get_record('flexible',array('id'=>$id));

        $flexible_answer= new flexible_answer();
        $flexible_answer->delete_settings($id);

        $this->delete_files($cm->id);
        $DB->delete_records('flexible', array('id' => $id));
        $tasks = $DB->get_records('flexible_tasks', array('flexibleid' => $id), 'id');
        foreach ($tasks as $task) {
            $this->delete_task($task->id);
        }
        $types=$DB->get_records('flexible_ans_stngs', array('flexibleid' => $id));
        foreach ( $types as $type) {
            $DB->delete_records('flexible_answers', array('id' => $type->answerid));
        }
        $DB->delete_records('flexible_used_graders',array('flexibleid' => $id));
        $DB->delete_records('flexible_ans_stngs', array('flexibleid' => $id));
        $DB->delete_records('flexible_criterions', array('flexibleid' => $id));
        $fields=$DB->get_records('flexible_fields', array('flexibleid' => $id));
        foreach ( $fields as $field) {
            $DB->delete_records('flexible_task_values', array('fieldid' => $field->id));
            $DB->delete_records('flexible_variants', array('fieldid' => $field->id));
        }
        $DB->delete_records('flexible_fields', array('flexibleid' => $id));
        $DB->delete_records('flexible_assignee',array('flexibleid' => $id));
        $this->delete_taskgiver_settings($id, $this->flexible->taskgiverid);
        //delete_course_module($cm->id);
        return true;
    }

    /**
     * Converts some flexibles settings into one variable
     * @return int
     */
    private static function configure_flags($instance) {
        $flags = 0;
        foreach(self::$flags as $field => $flag) {
            if (isset($instance->$field)) {
                $flags += $flag;
            }
        }
        return $flags;
    }

    function save_files($draftitemid,$filearea,$itemid) {
        global $DB;
        $fs = get_file_storage();
        if (!isset($this->context)) {
            $cm = get_coursemodule_from_instance('flexible',$this->flexible->id);
            //echo $this->flexible->id;
            $this->context = get_context_instance(CONTEXT_MODULE, $cm->id);
        }
        //$this->context = get_context_instance(CONTEXT_MODULE, $this->flexible->coursemodule);
        if ($draftitemid) {
            file_save_draft_area_files(
                $draftitemid,
                $this->context->id,
                'mod_flexible',
                $filearea,
                $itemid,
                array('subdirs'=>true));
        }
    }

    /**
     * Delete certain files from area
     *
     * @param $cmid context id
     * @param $filearea file area name
     * @param $itemid
     * @return bool success
     */
    function delete_files($cmid,$filearea=false,$itemid=false) {
        global $DB;
        $fs = get_file_storage();
        $this->context = get_context_instance(CONTEXT_MODULE, $cmid);
        return $fs->delete_area_files($this->context->id,$filearea,$itemid);
    }

    function get_flexibles_files_urls($cm) {
        $fs = get_file_storage();
        $context = get_context_instance(CONTEXT_MODULE, $cm->id);
        $dir =$fs->get_area_tree($context->id, 'mod_flexible', 'flexiblefiles', 0);
        $files = $fs->get_area_files($context->id, 'mod_flexible', 'flexiblefiles', 0, 'sortorder');
        if (count($files) >= 1) {
            $file = array_pop($files);
        }
        $urls = array();
        $urls[]=$this->view_flexible_file($dir,$urls);
    }
    function view_flexible_file($dir,$urls) {
        global $CFG;
        foreach ($dir['subdirs'] as $subdir) {
            $urls[]=$this->view_flexible_file($subdir,$urls);
            return $urls;
        }
        foreach ($dir['files'] as $file) {

            $path = '/'.$this->context->id.'/mod_flexible/flexiblefiles/0'.$file->get_filepath().$file->get_filename();
            $url = file_encode_url($CFG->wwwroot.'/pluginfile.php', $path, false);
            $filename = $file->get_filename();
            $file->fileurl = html_writer::link($url, $filename);
            return $file->fileurl.'<br>';
        }
    }

    /**
     * Add task into DB
     * 
     * @access public
     * @param object $data task's parameters
     * @return int inserted task id 
     */
    public function add_task($data) {
        global $DB;
        $data->flexibleid=$this->flexible->id;
        //$flexible = $DB->get_record('flexible',array('id'=>$this->flexible->id));
        $data->deadline = $this->flexible->deadline;
        $data->hidden = isset($data->hidden);
        $taskid=$DB->insert_record('flexible_tasks',$data);
        $fields=$DB->get_records('flexible_fields',array('flexibleid'=>$this->flexible->id));
        foreach ($fields as $field) {
            $fieldvalue = new stdClass();
            $fieldvalue->taskid=$taskid;
            $fieldvalue->fieldid=$field->id;
            $value = 'field'.$field->id;
            if (!$field->random)
                $fieldvalue->value=$data->$value;
            else
                $fieldvalue->value=null;
            $multilistvalue='';
            if ($field->ftype==MULTILIST) {
                for($i=0; $i<count($fieldvalue->value); $i++)
                    $multilistvalue .= $fieldvalue->value[$i].',';
                $fieldvalue->value = $multilistvalue;

            }
            $taskvalueid=$DB->insert_record('flexible_task_values',$fieldvalue);
            if ($field->ftype==FILE) {
                $this->save_files($data->$value,'flexibletaskfiles',$taskvalueid);
            }
        }
        return $taskid;
    }

    /** 
     * Update task
     * 
     * @access public
     * @param int $taskid task id
     * @param object $task updated task
     */
    public function update_task($taskid, $task) {
        global $DB;
        $task->id=$taskid;
        $task->flexibleid=$this->flexible->id;
        $task->deadline = $this->flexible->deadline;
        $task->hidden = isset($task->hidden);
        $DB->update_record('flexible_tasks',$task);
        $fields=$DB->get_records('flexible_fields',array('flexibleid'=>$this->flexible->id));
        foreach ($fields as $field) {
            $fieldvalue = new stdClass();
            $fieldvalue->taskid=$taskid;
            $fieldvalue->fieldid=$field->id;
            $value = 'field'.$field->id;
            if (!$field->random)
                $fieldvalue->value=$task->$value;
            else
                $fieldvalue->value=null;

            if ($field->ftype==MULTILIST) {
                $multilistvalue='';
                for($i=0;$i<count($fieldvalue->value);$i++) $multilistvalue.=$fieldvalue->value[$i].',';
                $fieldvalue->value=$multilistvalue;
            }

            if ($getrec = $DB->get_record(
                    'flexible_task_values',
                    array(
                        'taskid' => $taskid,
                        'fieldid' => $field->id,
                        'assigneeid' => 0
                    ))) {
                $fieldvalue->id=$getrec->id;
                $DB->update_record('flexible_task_values',$fieldvalue);
                $taskvalueid = $getrec->id;
            }
            else
                $taskvalueid = $DB->insert_record('flexible_task_values',$fieldvalue);

            if ($field->ftype==FILE) {
                $cm = get_coursemodule_from_instance('flexible',$this->flexible->id);
                $this->delete_files($cm->id,'flexibletaskfiles', $taskvalueid);
                $this->save_files($task->$value,'flexibletaskfiles', $taskvalueid);
            }

        }
    }

    /**
     * Delete task from DB, it's taskfield values, connected data from students
     * @param int $taskid task id
     */
    function delete_task($taskid) {
        global $DB;

        // Delete task record
        $DB->delete_records('flexible_tasks',array('id'=>$taskid));
        
        // Delete task values
        $taskvalues = $DB->get_records('flexible_task_values',array('taskid'=>$taskid));
        $cm = get_coursemodule_from_instance('flexible',$this->flexible->id);
        foreach ($taskvalues as $taskvalue) {
            $field=$DB->get_record('flexible_fields',array('id'=>$taskvalue->fieldid));
            if ($field->ftype==FILE);
                $this->delete_files($cm->id,'flexibletaskfiles',$taskvalue->id);
        }
        $DB->delete_records('flexible_task_values',array('taskid'=>$taskid));
        
        // Delete task from students
        $assignees = $DB->get_records('flexible_assignee', array('taskid' => $taskid), '', 'id, taskid');
        $DB->delete_records('flexible_assignee', array('taskid' => $taskid));

        $DB->delete_records('auditor_sync_tasks', array('flexibletaskid' => $taskid));
        //TODO удалять попытки и оценки студента по этому заданию
    }
    
    function get_task_values($taskid) {
        global $DB;
        $task = $DB->get_record('flexible_tasks',array('id'=>$taskid));
        $fields=$DB->get_records('flexible_fields',array('flexibleid'=>$this->flexible->id));
        foreach ($fields as $field) {
            $name='field'.$field->id;
            if (($field->ftype==STR
                || $field->ftype==TEXT
                || $field->ftype==FLOATING
                || $field->ftype==NUMBER
                || $field->ftype==DATE
                || $field->ftype == LISTOFELEMENTS) && $field->random == 0) {
                $value = $DB->get_record('flexible_task_values',array('fieldid'=>$field->id,'taskid'=>$taskid));
                if ($value)
                    $task->$name=$value->value;
            }
            if ($field->ftype == FILE) {
                $draftitemid = file_get_submitted_draft_itemid('flexibletaskfiles');
                if ($value = $DB->get_record('flexible_task_values',array('fieldid'=>$field->id,'taskid'=>$taskid))) {
                    file_prepare_draft_area(
                        $draftitemid,
                        $this->get_context()->id,
                        'mod_flexible',
                        'flexibletaskfiles',
                        $value->id,
                        array('subdirs'=>true));
                    $task->$name = $draftitemid;
                }
            }
            if ($field->ftype==MULTILIST) {
                $value = $DB->get_record('flexible_task_values',array('fieldid'=>$field->id,'taskid'=>$taskid));
                if ($value) {
                    $tok = strtok($value->value,',');
                    $opts=array();
                    while (strlen($tok)>0) {
                        $opts[]=$tok;
                        $tok=strtok(',');
                    }
                    $task->$name=$opts;
                }
            }
        }
        return $task;
    }
    function get_criterions_data() {
        global $DB;
        $criterions = $DB->get_records('flexible_criterions',array('flexibleid'=>$this->flexible->id));
        if ($criterions) {
            $i = 0;
            $data = new stdClass();
            foreach ($criterions as $criterion) {
                $data->name[$i] = $criterion->name;
                $data->description[$i] = $criterion->description;
                $data->weight[$i] = $criterion->weight;
                $data->source[$i] = $criterion->graderid;
                $data->criterionid[$i] = $criterion->id;
                $i++;
            }
            return $data;
        }
    }

    /**
     * Update instance criterions. Includes:
     *  - delete criterions, that marked as 'to be deleted';
     *  - update existing criterions;
     *  - create new criterions.
     *  
     * @access public
     * @param array $criterions criterions objects
     * @return array inserted criterions
     */
    public function update_criterions($criterions) {
        global $DB;
        foreach ($criterions as $key => $criterion) {
            if ($criterion->delete) {
                // Delete criterion and all grades
                $DB->delete_records('flexible_criterions', array('id' => $criterion->id));
                $DB->delete_records('flexible_rating_values', array('criterionid' => $criterion->id));
                // Only new criterions must be returned
                unset($criterions[$key]);
            }
            else {
                unset($criterion->delete);
                if (!isset($criterion->id) || $criterion->id == -1) {
                    // Insert new criterions
                    unset($criterion->id);
                    $criterion->id = $DB->insert_record('flexible_criterions', $criterion);
                }
                else {
                    // Update existing criterions
                    $DB->update_record('flexible_criterions', $criterion);
                    // Only new criterions must be returned
                    unset($criterions[$key]);
                }
            }
        }
        return $criterions;
    }
    
    /**
     * Updates rating for each assignee's attempt . Update values in table
     * {flexible_attempts} and Moodle Gradebook.
     * 
     * @access public
     * @param int $assigneeid assignee id
     */
    public function recalculate_rating($assigneeid) {
        global $DB;
        // Get all attempts to recalculate
        $attempts = $DB->get_records('flexible_attempts', array('assigneeid' => $assigneeid), 'id', 'id, rating, ratingdate, attemptdate');
        if (count($attempts) > 0) {
            $assignee = $DB->get_record('flexible_assignee', array('id' => $assigneeid));
            // Calculate total weight of criterions
            $criterions = $DB->get_records('flexible_criterions', array('flexibleid' => $assignee->flexibleid), 'id', 'id, weight');
            $totalweight = 0;
            foreach ($criterions as $criterion) {
                $totalweight += $criterion->weight;
            }
            // Calculate relative weight for each criterion
            foreach ($criterions as $criterion) {
                $criterion->relativeweight = round($criterion->weight / $totalweight, 2);
            }
            foreach ($attempts as $attempt) {
                $ratingvalues = $DB->get_records('flexible_rating_values', array('attemptid' => $attempt->id), 'id', 'id, value, criterionid, attemptid');
                // Calculate total rating for each attempt
                $rating = 0;
                foreach ($ratingvalues as $ratingvalue) {
                    $rating += $ratingvalue->value * $criterions[$ratingvalue->criterionid]->relativeweight;
                }

                $attempt->rating = $rating;
                $attempt->ratingdate = time();
                $DB->update_record('flexible_attempts', $attempt);
            }
            $this->update_assignee_gradebook_grade($assignee);
        }
    }
    
    /**
     * Put grade on every criterion in each assignee's attempt
     * 
     * @access public
     * @param int $assigneeid assignee id
     * @param array $criterions criterion objects
     * @param mixed $value rating
     * @param string $comment comment string
     */
    public function new_criterion_rating($assigneeid, $criterions, $value, $comment = '') {
        global $DB;
        $attempts = $DB->get_records('flexible_attempts', array('assigneeid' => $assigneeid));
        foreach ($attempts as $attempt) {
            if ($value === 'total') {
                $value = $attempt->rating;
            }
            foreach ($criterions as $criterion) {
                $this->put_rating($criterion->id, $attempt->id, $value, $comment);
            }
        }
    }
    /**
     * Puts rating on criterion in database
     * 
     * @access public
     * @param int $criterionid criterion id
     * @param int $attemptid attempt id
     * @param int $value rating value
     * @param string $commentmessage comment to rating (optional)
     */
    public function put_rating($criterionid, $attemptid, $value, $commentmessage) {
        global $DB;
        $rating = new stdClass();
        $rating->criterionid = $criterionid;
        $rating->attemptid = $attemptid;
        $rating->value = $value;

        $id = $DB->insert_record('flexible_rating_values', $rating);
        // Insert comment
        $cm = get_coursemodule_from_instance('flexible', $this->flexible->id);
        $context = get_context_instance(CONTEXT_MODULE, $cm->id);

        $options = new stdClass();
        $options->area    = 'flexible_comment';
        $options->pluginname = 'flexible';
        $options->context = $context;
        $options->cm = $cm;
        $options->showcount = true;
        $options->component = 'mod_flexible';
        $options->itemid  = $id;

        $comment = new comment($options);
        $comment->add($commentmessage);
    }

    function get_rating_data($assigneeid) {
        global $DB;
        $attemptscount = $DB->count_records('flexible_attempts',array('assigneeid'=>$assigneeid));
        $attempt = $DB->get_record('flexible_attempts',array('assigneeid'=>$assigneeid,'attemptnumber'=>$attemptscount));
        $assignee = $DB->get_record('flexible_assignee',array('id'=>$attempt->assigneeid));
        $data->final = $assignee->finalized;
        if ($ratingvalues = $DB->get_records('flexible_rating_values',array('attemptid'=>$attempt->id))) {
            foreach ($ratingvalues as $ratingvalue) {
                $field = 'criterion' . $ratingvalue->criterionid;
                $data->$field = $ratingvalue->value;
            }
            return $data;
        }
    }

    /**
     * Saves student's grade in DB
     *
     * @param int $assigneeid
     * @param object $data
     */
    function save_grade($assigneeid, $data) {
        global $DB;
        $dfs = get_object_vars($data);
        foreach ($dfs as $dfk => $dfv) {
            //echo "$dfk=>$dfv<br>";
            //echo $data->criterion1.'<br>';
        }
        $criterions = $DB->get_records('flexible_criterions',
                                       array('flexibleid' => $this->flexible->id));
        $rating = 0;
        $cm = get_coursemodule_from_instance('flexible', $this->flexible->id);
        $context = get_context_instance(CONTEXT_MODULE, $cm->id);

        $options->area    = 'flexible_comment';
        $options->pluginname = 'flexible';
        $options->context = $context;
        $options->cm = $cm;
        $options->showcount = true;
        $options->component = 'mod_flexible';

        $attemptscount = $DB->count_records('flexible_attempts', array('assigneeid' => $assigneeid));
        $attempt = $DB->get_record('flexible_attempts',
                                   array('assigneeid' => $assigneeid, 'attemptnumber' => $attemptscount));
        foreach ($criterions as $criterion) {
            $elementname = 'criterion'.$criterion->id;
            $elementcommentname = 'criterion'.$criterion->id.'comment';
            if (!$DB->record_exists('flexible_rating_values', array('attemptid' => $attempt->id, 'criterionid' => $criterion->id))) {
                $rec = new stdClass();
                $rec->attemptid = $attempt->id;
                $rec->criterionid = $criterion->id;
                $rec->assigneeid = $assigneeid;
                if ($attempt->draft == 0)
                    $rec->value = $data->$elementname;
                $ratingvalueid = $DB->insert_record('flexible_rating_values', $rec);

                $options->itemid  = $ratingvalueid;
                $comment = new comment($options);
                $comment->add($data->$elementcommentname);
            }
            else {
                $ratingvalue = $DB->get_record('flexible_rating_values', array('attemptid' => $attempt->id, 'criterionid' => $criterion->id));
                if ($attempt->draft == 0)
                    $ratingvalue->value = $data->$elementname;
                $DB->update_record('flexible_rating_values', $ratingvalue);

                //$options->itemid  = $ratingvalue->id;
                //$comment = new comment($options);
                //$comment->add($data->$elementcommentname);
            }
            if ($attempt->draft == 0) {
                $rating += $data->$elementname * round($criterion->weight / $data->weightsum, 2);
            }
        }
        if ($attempt->draft == 0) {
            $attempt->rating = $rating;
        }
        $attempt->ratingdate = time();
        $DB->update_record('flexible_attempts', $attempt);
        $assignee = $DB->get_record('flexible_assignee', array('id'=>$assigneeid));
//        $assignee->rating=$rating;
        $assignee->finalized=isset($data->final);
        $DB->update_record('flexible_assignee', $assignee);
        if ($this->flexible->flags & ALL_ATTEMPTS_AS_ONE) {
            $this->disable_previous_attempts($assignee->id);
        }
        $this->save_files($data->commentfiles_filemanager, 'commentfiles', $attempt->id);

        // Update grade in gradebook
        $this->update_assignee_gradebook_grade($assignee);

    }

    function disable_previous_attempts($attemptid) {
        global $DB;
        $attempts=$DB->get_records('flexible_attempts',array('id'=>$attemptid),'attemptnumber');
        $attempts=array_reverse($attempts);
        $i=0;
        foreach ($attempts as $attempt) {
            if ($i==0)
                continue;
            if ($DB->record_exists('flexible_task_values',array('attemptid'=>$attempt->id)))
                break;
            $attempt->disablepenalty=1;

            $DB->update_record('flexible_attempts',$attempt);
            $i++;
        }


    }
    function set_default_values_taskfields($default_values,$fieldid) {
        global $DB;
        $field = $DB->get_record('flexible_fields',array('id'=>$fieldid));
        $default_values['name']=$field->name;
        $default_values['ftype']=$field->ftype;
        $default_values['valuemin']=$field->valuemin;
        $default_values['valuemax']=$field->valuemax;
        $default_values['showintable']=$field->showintable;
        return $default_values;
    }

    /**
     * Get variants for field
     * 
     * @access public
     * @param int $fieldid
     * @return array variants
     */
    public function get_variants($fieldid) {
        global $DB;
        $records = $DB->get_records('flexible_variants', array('fieldid' => $fieldid), 'sortorder, id', 'id, value');
        $variants = array();
        foreach ($records as $record) {
            $variants[] = trim($record->value);
        }
        return $variants;
    }
    
    /**
     * Get list item by it's index
     * 
     * @access public
     * @param int $index item index
     * @param string $variants variants, separated by "\n"
     * @return string variant or error message
     */
    function get_variant($index, $variants) {
        $tok = strtok($variants,"\n");
        while (strlen($tok)>0) {
            $opt[]=$tok;
            $tok=strtok("\n");
        }
        if ($index>=0 && $index <=count($opt) &&isset($index))
            return $opt[$index];
        else
            return get_string('erroroutofrange','flexible');
    }

    /**
     * Returns variants of the field by field id
     * 
     * @param int $fieldid field id
     * @param int $asarray
     * @param string $separator symbols to separate variants
     * @return mixed array with variants, if $asarray==1 or string
     * separated by $separator if $asarray != 1
     */
    function get_field_variants($fieldid, $asarray = 1, $separator = "\n") {
        global $DB;
        $variants = $DB->get_records('flexible_variants',
                                     array('fieldid' => $fieldid),
                                     'sortorder');
        if ($variants) {
            $variantvalues=array();
            foreach ($variants as $variant) {
                $variantvalues[] = $variant->value;
            }
            if ($asarray)
                return $variantvalues;
            else
                return implode($separator,$variantvalues);
        }
        return '';
    }

    /** 
     * Insert variants of list or multilist field type
     * 
     * @access private
     * @param int $fieldid field id
     * @param string $variants varitants, separated by \n sybmol
     * @return array variants
     */
    private function insert_field_variants($fieldid, $variants) {
        global $DB;
        $variants = explode("\n", $variants);
        $i = 0;
        foreach ($variants as $variant) {
            $rec = new stdClass();
            $rec->fieldid = $fieldid;
            $rec->sortorder = $i;
            $rec->value = $variant;
            $DB->insert_record('flexible_variants', $rec);
            $i++;
        }
        return $variants;
    } 
    /**
     * Add task field to instance
     * 
     * @access public
     * @param object $data field to insert in DB
     * @return object record with id 
     */
    function add_task_field($data) {
        global $DB;
        $data->flexibleid = $this->flexible->id;
        $data->showintable = isset($data->showintable);
        $data->secretfield = isset($data->secretfield);
        $data->random = isset($data->random);
        $data->assigneeid = 0;
        $data->name = trim(clean_param($data->name, PARAM_TEXT));
        $data->description = trim(clean_param(s($data->description), PARAM_TEXT));

        $fieldid = $DB->insert_record('flexible_fields',$data);
        $data->id = $fieldid;
        if ($data->ftype==LISTOFELEMENTS || $data->ftype==MULTILIST) {
            $data->variants = $this->insert_field_variants($fieldid, $data->variants);
        }
        if ($data->ftype == FLOATING || $data->ftype == NUMBER) {
            if ($data->valuemax == $data->valuemin)
                $data->random = 0;
        }
        return $data;
    }

    /**
     * Update task's field. If field's type was list of elements or
     * multiple list, list variants will be deleted. 
     * 
     * @access public
     * @param int $fieldid field id
     * @param object $field record
     * @return boolean true
     */
    function update_task_field($fieldid, $field) {
        global $DB;
        
        // Create record object
        $field->id = $fieldid;
        $field->showintable = isset($field->showintable);
        $field->secretfield = isset($field->secretfield);
        $field->random = isset($field->random);
        $field->name = clean_param($field->name, PARAM_TEXT);
        $field->description = clean_param($field->description, PARAM_TEXT);
        
        // Drop old variants
        $DB->delete_records('flexible_variants', array('fieldid' => $field->id));
        
        // Add new variants if needed 
        if ($field->ftype == LISTOFELEMENTS || $field->ftype == MULTILIST) {
            $this->insert_field_variants($field->id, $field->variants);
        }
        if ($field->ftype == FLOATING || $field->ftype == NUMBER) {
            if ($field->valuemax == $field->valuemin)
                $field->random = 0;
        }
        return $DB->update_record('flexible_fields',$field);
    }

    /**
     * Delete task field and all task values for the field
     * 
     * @access public
     * @param int $id field id
     */
    public function delete_field($id) {
        global $DB;
        

        
        $field = $DB->get_record('flexible_fields', array('id' => $id));
        // Delete variants if type is list or multilist
        if ($field->ftype == LISTOFELEMENTS || $field->ftype == MULTILIST) {
            $DB->delete_records('flexible_variants', array('fieldid' => $id));
        }
        
        // Delete files
        $cm = get_coursemodule_from_instance('flexible', $this->flexible->id);
        $taskvalues = $DB->get_records('flexible_task_values', array('fieldid' => $id));
        foreach ($taskvalues as $taskvalue) {
            if ($field->ftype == FILE)
                $this->delete_files($cm->id, 'flexibletaskfiles', $taskvalue->id);
        }
        // Delete field
        $DB->delete_records('flexible_fields', array('id' => $id));
        //Delete task values
        $DB->delete_records('flexible_task_values', array('fieldid' => $id));
    }

    function prepare_files($dir,$contextid,$filearea,$itemid) {
        global $CFG;
        foreach ($dir['subdirs'] as $subdir) {
            $this->prepare_files($subdir,$contextid,$filearea,$itemid);
        }
        foreach ($dir['files'] as $file) {
            $path = '/'.$contextid.'/mod_flexible/'.$filearea.'/'.$itemid.$file->get_filepath().$file->get_filename();
            $url = file_encode_url($CFG->wwwroot.'/pluginfile.php', $path, false);
            $filename = $file->get_filename();
            $file->fileurl = html_writer::link($url, $filename);
        }
    }

    function htmllize_tree($dir) {
        require_once(dirname(dirname(dirname(__FILE__))).'/lib/plagiarismlib.php');
        global $CFG,$OUTPUT;
        $yuiconfig = array();
        $yuiconfig['type'] = 'html';

        if (empty($dir['subdirs']) and empty($dir['files'])) {
            return '';
        }
        $result = '<ul>';
        foreach ($dir['subdirs'] as $subdir) {
            $image = $OUTPUT->pix_icon("/f/folder", $subdir['dirname'], 'moodle', array('class'=>'icon'));
            $result .= '<li yuiConfig=\''.json_encode($yuiconfig).'\'><div>'.$image.' '.s($subdir['dirname']).'</div> '.$this->htmllize_tree($subdir).'</li>';
        }

        foreach ($dir['files'] as $file) {
            $filename = $file->get_filename();
            $icon = mimeinfo("icon", $filename);
            $image = $OUTPUT->pix_icon("f/$icon", $filename, 'moodle', array('class'=>'icon'));
            $result .= '<li yuiConfig=\''.json_encode($yuiconfig).'\'><div>'.$image.' '.$file->fileurl.' </div></li>';
            
        }
        $result .= '</ul>';
        return $result;
    }

    function view_files($contextid,$filearea,$itemid) {
        global $PAGE;
        $PAGE->requires->js('/mod/flexible/flexible.js');
        $fs = get_file_storage();
        $dir =$fs->get_area_tree($contextid, 'mod_flexible', $filearea, $itemid);
        $files = $fs->get_area_files($contextid, 'mod_flexible', $filearea, $itemid, 'sortorder');
        if (count($files) <1)
            return;
        $this->prepare_files($dir,$contextid,$filearea,$itemid);
        $htmlid = 'flexible_files_tree_'.uniqid();
        $PAGE->requires->js_init_call('M.mod_flexible.init_tree', array(true, $htmlid));
        $html = '<div id="'.$htmlid.'">';
        $html.=$this->htmllize_tree($dir);
        $html .= '</div>';
        return $html;
    }
    public function get_files($filearea, $itemid) {
        $contextid = $this->get_context()->id;
        $fs = get_file_storage();
        $dir =$fs->get_area_tree($contextid, 'mod_flexible', $filearea, $itemid);
        $arr = $this->get_files_content($dir, $filearea, $itemid);
        return $arr;
        //print_r($arr);
    }

    public function get_files_content($dir, $filearea, $itemid) {
        global $CFG;
        $contextid = $this->get_context()->id;
        $arr = array();
        foreach ($dir['subdirs'] as $subdir) {
            $arr[$subdir['dirname']] = $this->get_files_content($subdir, $filearea, $itemid);
        }
        foreach ($dir['files'] as $file) {
            $filename = $file->get_filename();
            $contents = $file->get_content();
            $arr[$filename] = mb_convert_encoding($contents, 'utf8', 'windows-1251');;
        }
        return $arr;
    }
    public function save_attempt($data) {
        global $DB;
        $attempt = new stdClass();
        $attempt->draft = isset($data->draft);
        $attempt->final = isset($data->final);
        $attempt->assigneeid = $this->assignee->id;
        $attempt->attemptdate = time();
        $attempt->disablepenalty = 0;

        $attemptscount = $DB->count_records('flexible_attempts',array('assigneeid'=>$this->assignee->id));
        $attempt->attemptnumber = $attemptscount + 1;
        return $DB->insert_record('flexible_attempts', $attempt);
    }

    /**
     * Get assignee's record. If there is not record - create it.
     * Method always returns last user's assignee record
     *
     * @access public
     * @param int $userid user id
     * @param int $flexibleid instance id
     * @return object assignee record
     */
    public function get_assignee($userid, $flexibleid = null) {
        global $DB;
        if ($flexibleid == null) {
            $flexibleid = $this->flexible->id;
        }
        if(!$DB->record_exists('flexible_assignee',
                array('userid' => $userid, 'flexibleid' => $flexibleid))) {
            $rec = $this->create_assignee($userid);
        }
        else {
            $sql = 'SELECT * from {flexible_assignee}
                    WHERE userid = ? AND flexibleid = ?
                    ORDER BY id DESC LIMIT 1';
            $recs = $DB->get_records_sql($sql, array($userid, $flexibleid));
            if (count($recs) == 1) {
                $rec = array_pop($recs);
            }
            else {
                $rec = null;
            }
        }
        $this->assignee->id = $rec->id;

        return $rec;
    }
    
    /**
     * Create assignee record for user
     * 
     * @access private
     * @param int $userid user id
     * @return object record
     */
    private function create_assignee($userid) {
        global $DB;
        $rec = new stdClass();
        $rec->userid = $userid;
        $rec->flexibleid = $this->flexible->id;
        $rec->taskid = 0;
        $rec->timetaken = 0;
        $rec->id = $DB->insert_record('flexible_assignee', $rec);
        return $rec;
    }
    public function evaluate_attempt($attemptid) {
        global $DB;
        // Get graders list
        $usedgraders = $DB->get_records('flexible_used_graders',
                                        array('flexibleid' => $this->flexible->id));
        if(count($usedgraders) == 0) {
            return;
        }
        $graderids = array();
        foreach ($usedgraders as $usedgrader) {
            $graderids[] = $usedgrader->graderid;
        }
        $inorequal = $DB->get_in_or_equal($graderids);
        $sql = "SELECT * FROM {flexible_graders} WHERE id" . $inorequal[0]. "";
        $graderrecords = $DB->get_records_sql($sql, $inorequal[1]);

        // Call graders to evaluate attempt $attemptid
        foreach ($graderrecords as $graderrecord) {
            require_once($graderrecord->path);
            $gradername = $graderrecord->name;
            $grader = new $gradername;
            $grader->evaluate_attempt($attemptid);
        }

    }
    // Runs after adding submission. Calls all graders, used in module.
    public function test_attempt($attemptid) {
        //echo 'testing';
        global $DB;
        $usedgraders = $DB->get_records('flexible_used_graders',
                                        array('flexibleid' => $this->flexible->id));
        if(count($usedgraders) == 0) {
            return;
        }
        $attempt = $DB->get_record('flexible_attempts', array('id' => $attemptid));
        foreach ($usedgraders as $usedgrader) {
            //echo $usedgrader->id;
            $graderrecord = $DB->get_record('flexible_graders', array('id' => $usedgrader->graderid));

            require_once($graderrecord->path);
            $gradername = $graderrecord->name;
            $grader = new $gradername;
            $rating = $grader->test_attempt($attemptid);
            //echo $rating ;

            $criterions = $DB->get_records('flexible_criterions',
                                           array('flexibleid' => $this->flexible->id,
                                                 'graderid' => $usedgrader->graderid));
            foreach ($criterions as $criterion) {
                $ratingvalue = new stdClass();
                $ratingvalue->attemptid = $attemptid;
                $ratingvalue->criterionid = $criterion->id;

                $ratingvalue->assigneeid = $attempt->assigneeid;

                $ratingvalue->value = $rating;
                //if ($attempt->draft == 0)
                //    $ratingvalue->value = $data->$elementname;
                //echo 'adding grade';
                $ratingvalueid = $DB->insert_record('flexible_rating_values', $ratingvalue);
            }

        }
        // if attempt has grades for all criterions, caluclulate total grade
        $criterions = $DB->get_records('flexible_criterions', array('flexibleid' => $this->flexible->id));
        $allcriterions = true;
        $totalweight = 0;
        $criteriongrades = array();
        foreach($criterions as $criterion) {
            if(!$DB->record_exists('flexible_rating_values', array('criterionid' => $criterion->id, 'attemptid' => $attemptid))) {
                $allcriterions = false;
                break;
            }
            else {
                $rating = $DB->get_record('flexible_rating_values', array('criterionid' => $criterion->id, 'attemptid' => $attemptid));
                $criteriongrades[$criterion->id] = $rating->value;
                $totalweight += $criterion->weight;
            }
        }
        if ($allcriterions) {
            $grade = 0;
            foreach($criterions as $criterion) {
                $grade += $criteriongrades[$criterion->id] * round($criterion->weight / $totalweight, 2);
            }
            $attempt->rating = $grade;
            $attempt->ratingdate = time();
            $DB->update_record('flexible_attempts', $attempt);
            $this->update_assignee_gradebook_grade($DB->get_record('flexible_assignee', array('id' => $attempt->assigneeid)));
            // TODO Просто вызвать функцию, которая выставляет оценку
        }
    }
    
    /**
     * Get random valuefrom field $field
     * 
     * @access public
     * @param object $field
     * @return mixed value
     */
    public function get_random_value($field) {
        if (!($field->valuemin == 0 && $field->valuemax == 0)) {
            if ($field->ftype == NUMBER)
                $randvalue = rand($field->valuemin, $field->valuemax);
            if ($field->ftype == FLOATING)
                $randvalue = (float)rand($field->valuemin * 100, $field->valuemax * 100) / 100;
        }
        else {
            if ($field->ftype == NUMBER)
                $randvalue = rand();
            if ($field->ftype == FLOATING)
                $randvalue = (float)rand() / 100;
        }
        if ($field->ftype == LISTOFELEMENTS) {
            $randvalue = rand(0, count($field->variants) - 1);
        }
        return $randvalue;
    }
    
    function bind_task_to_assignee($userid, $taskid) {
        global $DB;
        $rec = $this->get_assignee($userid);
        $rec->taskid = $taskid;
        $rec->timetaken = time();
        $DB->update_record('flexible_assignee', $rec);
        $this->assignee->id = $rec->id;

        $fields = $DB->get_records('flexible_fields',array('flexibleid'=>$this->flexible->id));
        foreach ($fields as $field) {

            if ($field->random == 1) {
                $field->variants = $this->get_variants($field->id);

                $randrec = new stdClass();
                $randrec->value = $this->get_random_value($field);
                $randrec->taskid = $taskid;
                $randrec->fieldid = $field->id;                
                $randrec->assigneeid = $this->assignee->id;
                
                $DB->insert_record('flexible_task_values',$randrec);
            }
        }
    }

    /**
     * Cancel assignee's task
     *
     * Creates new assignee record for user, old record becomes "cancelled"
     * @param int $assigneeid assignee's id
     */
    function cancel_task($assigneeid) {
        global $DB;

        // Set "cancelled" = 1 for old assignee record
        $assignee = $DB->get_record('flexible_assignee', array('id' => $assigneeid));
        $assignee->cancelled = 1;
        $DB->update_record('flexible_assignee', $assignee);

        // Create new assignee record
        $newassignee = new stdClass();
        $newassignee->userid = $assignee->userid;
        $newassignee->timetaken = 0;
        $newassignee->taskid = 0;
        $newassignee->flexibleid = $assignee->flexibleid;

        $DB->insert_record('flexible_assignee', $newassignee);

        // Delete grade from gradebook
        global $CFG;
        require_once($CFG->libdir.'/gradelib.php');
        $record = new stdClass();
        $record->userid = $assignee->userid;
        $record->rawgrade = null;
        grade_update(
            'mod/flexible',
            $this->flexible->course,
            'mod',
            'flexible',
            $this->flexible->id,
            0,
            $record,
            null
        );
    }

    /**
     * Checks ability to cancel assignee's task
     *
     * Assignee can cancel own task if SECOND_CHOICE option is enabled
     * and he has only one task in "history"
     *
     * @access public
     * @param int $assigneeid assignee's id
     * @param object $context module context
     * @return boolean true, if user can cancel task or false
     */
    public function can_cancel_task($assigneeid, $context) {
        global $DB;
        $assignee = $DB->get_record('flexible_assignee',
            array('id' => $assigneeid), 'userid, flexibleid');
        $has_cap = has_capability('mod/flexible:managetasks', $context);
        $model = flexible_model::get_instance();
        $has_ability = ($this->flexible->flags & SECOND_CHOICE)
            && ($model->count_assignees_tasks($assignee) < 2);
        return ($has_cap || $has_ability);
    }

    function help_icon($text) {
        global $CFG,$OUTPUT,$PAGE;
        if (empty($text)) {
            return;
        }
        $src = $OUTPUT->pix_url('help');
        $alt = $text;
        $attributes = array('src'=>$src, 'alt'=>$alt, 'class'=>'iconhelp');
        $output = html_writer::empty_tag('img', $attributes);
        $url = new moodle_url('/mod/flexible/showtext.php', array('text' => $text));
        $title = get_string('about','flexible');
        $attributes = array('href'=>$url, 'title'=>$title);
        $id = html_writer::random_id('helpicon');
        $attributes['id'] = $id;
        $output = html_writer::tag('a', $output, $attributes);

        $PAGE->requires->js_init_call('M.util.help_icon.add', array(array('id'=>$id, 'url'=>$url->out(false))));

        return html_writer::tag('span', $output, array('class' => 'helplink'));
    }

    /**
     * Get all users who are enrolled for the course
     *
     * @return array users ids
     */
    public function get_course_users() {
        global $COURSE, $DB;
        $userids = array();

        $context = get_context_instance( CONTEXT_COURSE, $COURSE->id );
        $query = '
            SELECT u.id AS id
            FROM mdl_role_assignments AS a, mdl_user AS u
            WHERE contextid=' . $context->id . ' AND roleid<>0 AND a.userid=u.id;';

        $rs = $DB->get_recordset_sql($query);

        foreach( $rs as $r ) {
            $userids[] = $r->id;
        }
        return $userids;
    }
    /**
     * Get list of users, who have active tasks
     *
     * @access public
     * @return array of ids
     */
    public function get_users_with_active_tasks() {

        global $DB;
        if ($usersid = get_enrolled_users(
            get_context_instance(CONTEXT_MODULE,$this->cm->id),
            'mod/flexible:havetask',
            groups_get_activity_group($this->cm, true),
            'u.id')) {

            $usersid = array_keys($usersid);
        }
        $enrolledusers = $this->get_course_users();
        // Add users that haven't capability but have task
        $assignees = $DB->get_records('flexible_assignee', array('flexibleid'=>$this->flexible->id, 'cancelled' => 0), 'id', 'id, userid, taskid');
        foreach ($assignees as $assignee) {
            if ($assignee->taskid > 0 && array_search($assignee->userid, $usersid) === false && in_array($assignee->userid, $enrolledusers)) {
                if (!has_capability('mod/flexible:havetask', $this->get_context(), $assignee->userid))
                    array_push($usersid, $assignee->userid);
            }
        }
        return $usersid;
    }
    function get_statistics() {
        global $DB,$OUTPUT,$CFG;
        $html = '';
        $cm = get_coursemodule_from_instance('flexible',$this->flexible->id);
        $groupmode = groups_get_activity_groupmode($cm);
        $currentgroup = groups_get_activity_group($cm, true);
        groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/flexible/view.php?id=' . $cm->id.'&page=view');
        $context=get_context_instance(CONTEXT_MODULE,$cm->id);
        $notchecked=0;
        $count=0;

        if ($usersid = $this->get_users_with_active_tasks()) {
            $count=count($usersid);
            foreach ($usersid as $userid) {
                if ($assignee = $this->get_assignee($userid, $this->flexible->id)) {
                    $attemptscount=$DB->count_records('flexible_attempts',array('assigneeid'=>$assignee->id));
                    if ($attempt=$DB->get_record('flexible_attempts',array('assigneeid'=>$assignee->id,'attemptnumber'=>$attemptscount))) {
                        if ($attempt->attemptdate>$attempt->ratingdate || !isset($attempt->rating))
                        $notchecked++;
                    }
                }
            }
        }

        /// If we know how much students are enrolled on this task show "$notchecked of $count need grade" message
        if ($count!=0) {
            $html = $notchecked.' '.get_string('of','flexible').' '.$count.' '.get_string('needgrade','flexible');
            $submissionsurl = new moodle_url('view.php',array('id'=>$cm->id,'page'=>'submissions'));
            return "<align='right'>".html_writer::link($submissionsurl,$html);
        }
        else {
            $notchecked=0;
            $assignees = $DB->get_records('flexible_assignee',array('flexibleid'=>$this->flexible->id));
            foreach ($assignees as $assignee) {
                $attemptscount=$DB->count_records('flexible_attempts',array('assigneeid'=>$assignee->id));
                if ($attempt=$DB->get_record('flexible_attempts',array('assigneeid'=>$assignee->id,'attemptnumber'=>$attemptscount))) {
                    if ($attempt->attemptdate>$attempt->ratingdate || !isset($attempt->rating))
                    $notchecked++;
                }
            }
            /// If there is no enrollment on this task but someone loaded anser show "$notchecked need grade" message
            if ($notchecked!=0) {
                $html = $notchecked.' '.get_string('needgrade','flexible');
                $submissionsurl = new moodle_url('view.php',array('id'=>$cm->id,'page'=>'submissions'));
                return "<align='right'>".html_writer::link($submissionsurl,$html);
            }
        }
        $html = get_string('noattempts','flexible');
        $submissionsurl = new moodle_url('view.php',array('id'=>$cm->id,'page'=>'submissions'));
        return "<align='right'>".html_writer::link($submissionsurl,$html);
    }

    function get_penalty($attemptid) {
        global $DB;
        $currentattempt = $DB->get_record('flexible_attempts',array('id'=>$attemptid));
        $attempts = $DB->get_records('flexible_attempts',array('assigneeid'=>$currentattempt->assigneeid), 'attemptnumber');
        $realnumber = $currentattempt->attemptnumber;
        foreach ($attempts as $attempt) {
            if ($attempt->disablepenalty == 1) {
                $realnumber--;
            }
        }
        if ($this->flexible->penalty * ($realnumber - 1) >= 0)
            return $this->flexible->penalty * ($realnumber - 1);
        else
            return 0;
        return ;
    }

    function grade_item_update($grades=NULL) {
        global $CFG;
        require_once($CFG->libdir.'/gradelib.php');

        if (!isset($this->flexible->courseid)) {
            $this->flexible->courseid = $this->flexible->course;
        }

        $params = array('itemname'=>$this->flexible->name, 'idnumber'=>$this->flexible->cmidnumber);

        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = 100;
        $params['grademin']  = 0;

        if ($grades  === 'reset') {
            $params['reset'] = true;
            $grades = NULL;
        }
        return grade_update('mod/flexible', $this->flexible->courseid, 'mod', 'flexible', $this->flexible->id, 0, $grades, $params);
    }
    function grade_item_delete() {
        global $CFG;
        require_once($CFG->libdir.'/gradelib.php');
        if (!isset($this->flexible->courseid)) {
            $this->flexible->courseid = $this->flexible->course;
        }

        return grade_update('mod/flexible', $this->flexible->courseid, 'mod', 'flexible', $this->flexible->id, 0, NULL, array('deleted'=>1));
    }

    function have_test_results($attempt) {
        global $DB;
        $usedgraders = $DB->get_records('flexible_used_graders', array('flexibleid' => $this->flexible->id));
        foreach($usedgraders as $usedgrader) {
            $graderrec = $this->graders[$usedgrader->graderid];
            require_once($graderrec->path);
            $gradername = $graderrec->name;
            //$grader = new $gradername;
            if($gradername::attempt_was_tested($attempt->id))
                return true;
        }
    }
    function show_test_results($attempt) {
        global $DB;
        $usedgraders = $DB->get_records('flexible_used_graders', array('flexibleid' => $this->flexible->id));
        $html = '';
        foreach($usedgraders as $usedgrader) {
            $graderrec = $this->graders[$usedgrader->graderid];
            require_once($graderrec->path);
            $gradername = $graderrec->name;
            $grader = new $gradername;
            if($gradername::attempt_was_tested($attempt->id))
                $html .= $grader->show_test_results($attempt->id, $this->context);
        }
        return $html;
    }
    function trigger_flexible_event($mode,$assigneeid) {
        $eventdata = new stdClass();
        $eventdata->student = $assigneeid;
        $eventdata->flexibleid = $this->flexible->id;
        if ($mode==TASK_RECIEVED) {
            events_trigger('flexible_task_recieved', $eventdata);
        }
        if ($mode==ATTEMPT_DONE) {
            events_trigger('flexible_attempt_done', $eventdata);
        }
        if ($mode==GRADE_DONE) {
            events_trigger('flexible_grade_done', $eventdata);
        }
    }
    function email_teachers($assignee) {
        global $DB;

        if (!($this->flexible->flags & NOTIFY_TEACHERS))
            return;

        $user = $DB->get_record('user', array('id'=>$assignee->userid));
        $eventdata= new stdClass();

        $teachers = $this->get_teachers($user);

        $eventdata->component = 'mod_flexible';
        $eventdata->name = 'flexible_updates';
        $eventdata->userfrom = $user;
        $eventdata->subject = 'Attempt done';
        $eventdata->fullmessage= 'Student '.fullname($user,true).' uploaded his answer' ;
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml   = '<b>'.$eventdata->fullmessage.'</b>';
        $eventdata->smallmessage = '';
        $eventdata->notification = 1;

        foreach ($teachers as $teacher) {
            $eventdata->userto = $teacher;
            message_send($eventdata);
        }

    }
    function get_teachers() {
        $cm = get_coursemodule_from_instance('flexible',$this->flexible->id);
        $context=get_context_instance(CONTEXT_MODULE,$cm->id);
        $potgraders = get_users_by_capability($context, 'mod/flexible:grade', '', '', '', '', '', '', false, false);
        return $potgraders;
    }

    /**
     * Saves assignee grade in gradebook
     *
     * @access public
     * @param object $assignee
     */
    public function update_assignee_gradebook_grade($assignee) {
        global $CFG, $DB;
        require_once($CFG->libdir.'/gradelib.php');

        $grade = new stdClass();
        $grade->userid = $assignee->userid;
        $attempt = $this->get_last_attempt($assignee->id);
        if ($attempt) {
            $grade->rawgrade = $attempt->rating - $this->get_penalty($attempt->id);
            $grade->dategraded = $attempt->ratingdate;
            $grade->datesubmitted = $attempt->attemptdate;
        }
        grade_update('mod/flexible', $this->flexible->course, 'mod', 'flexible', $this->flexible->id, 0, $grade, null);
    }
    static function user_have_active_task($userid, $flexibleid) {
        global $DB;
        if ($DB->record_exists('flexible_assignee',
                    array('userid'=>$userid,'flexibleid'=>$flexibleid, 'cancelled' => '0'))) {
            $assignee=$DB->get_record('flexible_assignee', array(
                'userid'=>$userid,
                'flexibleid'=>$flexibleid,
                'cancelled' => '0'));
            return ($assignee && $assignee->taskid>0);
        }
        return false;
    }
    public function delete_taskgiver_settings($flexibleid, $taskgiverid) {
        global $DB;
        if(!($this->flexible->flags & ACTIVATE_INDIVIDUAL_TASKS))
            return;
        if($taskgiverrec = $DB->get_record('flexible_taskgivers', array('id' => $taskgiverid))) {
            require_once($taskgiverrec->path);
            $taskgivername = $taskgiverrec->name;
            $tg = new $taskgivername();
            if($taskgivername::has_settings()) {
                $tg->delete_settings($flexibleid);
            }
        }
    }
    
    /**
     * Get user's groups
     * @param int $userid user id
     * @param int $courseid course id
     * @return array groups identoficators
     */
    public function get_user_groups($userid, $courseid) {
        global $DB;
        $groupmembers = $DB->get_records('groups_members', array('userid' => $userid));
        $ret = array();
        foreach($groupmembers as $groupmember) {
            // Get first user's groups within $courseid
            $groups = $DB->get_records('groups', array('id' => $groupmember->groupid,
                                                       'courseid' => $courseid));
            foreach($groups as $group) {
                $ret[] = $group->id;
            }
        }
        return $ret;
    }
    public function get_user_groupings($userid, $courseid) {
        global $DB;
        $groups = $this->get_user_groups($userid, $courseid);
        $ret = array();
        foreach($groups as $group) {
            $groupinggroups = $DB->get_records('groupings_groups', array('groupid' => $group));
            foreach($groupinggroups as $groupinggroup) {
                $groupings = $DB->get_records('groupings', array('id' => $groupinggroup->groupingid,'courseid' => $courseid));
                foreach($groupings as $grouping) {
                    $ret[] = $grouping->id;
                }
            }
        }
        return $ret;
    }
    /* Get all tasks that are available for current user.
     *
     * Method checks instance's uniqueness, visibility of all tasks.
     * @param int $flexibleid
     * @param int $userid
     * @param int $givehidden
     * @return array available tasks
     */
    public function get_available_tasks($userid, $givehidden = 0) {

        // Get all tasks in instance at first
        global $DB;
        $values = array();
        $values['flexibleid'] = $this->flexible->id;
        if(!$givehidden) {
            $values['hidden'] = 0;
        }
        $tasks = $DB->get_records('flexible_tasks', $values);

        // If there is no tasks at this stage - return empty array
        if(count($tasks) == 0) {
            return array();
        }

        // Filter tasks using 'uniqueness' field in flexible instance
        if($instance = $DB->get_record('flexible', array('id' => $this->flexible->id))) {
            // If no uniqueness required, return $tasks without changes
            if($instance->uniqueness == flexible_NO_UNIQUENESS) {
                //return $tasks;
            }
            // If uniqueness within groups or groupings required, filter tasks
            elseif($instance->uniqueness == flexible_UNIQUENESS_GROUPS ||
               $instance->uniqueness == flexible_UNIQUENESS_GROUPINGS) {
                foreach($tasks as $key => $task) {
                    // Get all assignees that have this task
                    $assignees = $DB->get_records('flexible_assignee', array('taskid' => $task->id, 'cancelled' => 0));
                    // If nobody have this task continue
                    if(count($assignees) == 0) {
                        continue;
                    }
                    else {
                        foreach($assignees as $assignee) {
                            if($instance->uniqueness == flexible_UNIQUENESS_GROUPS) {
                                // If current user and any owner of the task have common group within
                                // course remove this task from array

                                $commongroups = array_intersect($this->get_user_groups($userid, $instance->course),
                                                                $this->get_user_groups($assignee->userid, $instance->course));
                                if (count($commongroups) > 0) {
                                    unset($tasks[$key]);
                                }
                            }
                            if ($instance->uniqueness == flexible_UNIQUENESS_GROUPINGS) {
                                // If current user and any owner of the task have common grouping within
                                // course remove this task from array

                                $commongroupings = array_intersect($this->get_user_groupings($userid, $instance->course),
                                                                  $this->get_user_groupings($assignee->userid, $instance->course));
                                if (count($commongroupings) > 0) {
                                    unset($tasks[$key]);
                                }
                            }
                        }
                    }
                }
                //return $tasks;
            }
            elseif ($instance->uniqueness == flexible_UNIQUENESS_COURSE) {
                foreach ($tasks as $key => $task) {
                    if ($DB->record_exists('flexible_assignee', array('taskid' => $task->id, 'cancelled' => 0))) {
                        unset($tasks[$key]);
                    }
                }
                //return $tasks;
            }
            // If there is no available tasks, check cyclic random option
            if (count($tasks) == 0 && $this->has_flag(flexible_CYCLIC_RANDOM)) {
                $cyclictasks = $this->get_cyclic_available_tasks($userid, $givehidden);
                return $cyclictasks;
            }
            return $tasks;
        }
    }

    /**
     * Get students, who are in similiar group/grouping/course with current user.
     *
     * @return array user ids
     */
    public function get_unique_neighbors() {
        global $USER;
        if ($this->flexible->uniqueness == flexible_NO_UNIQUENESS) {
            return array();
        }
        elseif ($this->flexible->uniqueness == flexible_UNIQUENESS_GROUPS) {
            $groups = $this->get_user_groups($USER->id, $this->flexible->course);
            if (!$groups) {
                return false;
            }
            $neighbors = array();
            foreach ($groups as $groupid) {
               $groupusers = groups_get_members($groupid, 'u.id', 'RAND()');
                if ($groupusers) {
                    $neighbors = array_merge($neighbors, $groupusers);
                }
            }
            return $neighbors;
        }
        elseif ($this->flexible->uniqueness == flexible_UNIQUENESS_GROUPINGS) {
            $groupings = $this->get_user_groupings($USER->id, $this->flexible->course);
            if (!$groupings) {
                return false;
            }
            $neighbors = array();
            foreach ($groupings as $groupingid) {
                $groupingusers = groups_get_grouping_members($groupingid, 'u.id', 'RAND()');
                if ($groupingusers) {
                    $neighbors = array_merge($neighbors, $groupingusers);
                }
            }
            return $neighbors;
        }
        elseif ($this->flexible->uniqueness == flexible_UNIQUENESS_COURSE) {
            global $DB;
            $assignees = $DB->get_records('flexible_assignee', array('flexibleid' => $this->flexible->id, 'cancelled' => 0), 'RAND()', 'userid as id');
            return $assignees;
        }
        return array();
    }

    /**
     * Get cyclic available tasks
     * @param $userid
     * @param int $givehidden
     * @return array
     */
    public function get_cyclic_available_tasks($userid, $givehidden = 0) {
        global $DB;

        $neighbors = $this->get_unique_neighbors();
        $neighborsid = array();
        if ($neighbors) {
            foreach ($neighbors  as $neighbor) {
                $neighborsid[] = (int)$neighbor->id;
            }
            $neighborsid = implode(',', $neighborsid);

            $sql = "
                SELECT t.*, COUNT(a.taskid) as count
                FROM {flexible_assignee} AS a
                JOIN {flexible_tasks} AS t ON t.id = a.taskid
                WHERE
                    a.userid in ($neighborsid)
                    AND
                    a.flexibleid = ?
                    AND
                    taskid <> 0
                    AND
                    cancelled = 0
                GROUP BY a.taskid
                ORDER BY count, a.taskid
            ";
            //echo '<pre>',print_r($neighborsid, true),'</pre>';
            $tasks = $DB->get_records_sql($sql, array($this->flexible->id));
            $min = 2000;
            foreach ($tasks as $task) {
                if ($task->count < $min) {
                    $min = $task->count;
                }
            }
            foreach ($tasks as $key => $task) {
                if ($task->count !== $min) {
                    unset($tasks[$key]);
                }
            }
            return $tasks;
        }
        return array();
    }
    
    /**
     * Check available date
     *
     * @access public
     * @return boolean true, if module is opened or available date is not set
     */
    public function is_opened() {
        if ($this->get_flexible()->availabledate != 0) {
            if (time() < $this->get_flexible()->availabledate) {
                if (!has_capability('mod/flexible:managetasks', $this->get_context())) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Check choice date
     *
     * @access public
     * @return string error message
     */
    public function check_dates() {
        if ($this->checkdateerror === false || is_string($this->checkdateerror))
            return $this->checkdateerror;

        if (has_capability('mod/flexible:havetask', $this->get_context())
            && $this->get_flexible()->choicedate != 0) {
            if (time() > $this->get_flexible()->choicedate) {
                global $USER;
                $assignee = $this->get_assignee($USER->id);
                // If assignee hasn't task
                if ($assignee->taskid == 0) {
                    if ($this->has_flag(RANDOM_TASKS_AFTER_CHOICEDATE)) {
                        // Try to get random task
                        $taskid = flexible_model::get_random_task_id($this->get_available_tasks($USER->id));
                        if ($taskid == -1 ) {
                            $this->checkdateerror = 'errormodulehavenotasktogiveyou';
                            return 'errormodulehavenotasktogiveyou';
                        }
                        $this->bind_task_to_assignee($USER->id, $taskid);
                    }
                    else {
                        // Return error
                        $this->checkdateerror = 'erroryouhadtochoosetask';
                        return 'erroryouhadtochoosetask';
                    }
                }
            }
            else
                $this->checkdateerror = false;
        }
        return false;
    }

    /**
     * Get random task from array of tasks
     *
     * @access public
     * @static
     * @param array $tasks tasks records
     * @return int id of selected task or -1
     */
    static public function get_random_task_id($tasks) {
        $tasksarray = array();
        foreach($tasks as $task)
            $tasksarray[] = $task->id;
        if(count($tasksarray) > 0) {
            return $tasksarray[rand(0, count($tasksarray) - 1)];
        }
        else {
            return -1;
        }
    }

    public static function time_difference($time) {
        $result = format_time(time() - $time);
        if (time() > $time) {
            $result .= ' ' . get_string('ago','flexible');
        }
        return $result;
    }

     /**
     * Get last attempt record of the assignee
     * 
     * @access public
     * @param int $assigneeid assignee's id
     * @return object attempt record or null
     */
    public function get_last_attempt($assigneeid) {
        global $DB;
        $rec = $DB->get_record_sql("SELECT * FROM {flexible_attempts} WHERE assigneeid = ? ORDER BY id DESC LIMIT 1;", array($assigneeid));
        return $rec;
    }

    /**
     * Get last assignee's attempt id
     *
     * @access public
     * @param int $assigneeid assignee id
     * @return int last attempt's id or null
     */
    public function get_last_attempt_id($assigneeid) {
        global $DB;
        $rec = $DB->get_record_sql("SELECT id FROM {flexible_attempts} WHERE assigneeid = ? ORDER BY id DESC LIMIT 1;", array($assigneeid));
        if ($rec && isset($rec->id)) {
            return $rec->id;
        }
        else {
            return null;
        }
    }

    /**
     * Get last assignee's attempt with grade
     *
     * @access public
     * @param int $assigneeid assignee's id
     * @return object attempt record or null
     */
    public function get_last_graded_attempt($assigneeid) {
        global $DB;
        $rec = $DB->get_record_sql("SELECT * FROM {flexible_attempts} WHERE assigneeid = ? AND rating >= 0 AND ratingdate > 0 ORDER BY attemptdate DESC LIMIT 1;", array($assigneeid));
        return $rec;
    }

    public function get_last_commented_attempt($assigneeid) {
        global $DB;
        $rec = $DB->get_record_sql("SELECT * FROM {flexible_attempts} WHERE assigneeid = ? AND ratingdate > 0 ORDER BY attemptnumber DESC LIMIT 1;", array($assigneeid));
        return $rec;
    }

    /**
     * Looks for any rated attempt
     *
     * @access public
     * @return boolean true, if instance has no rated attempts, else false
     */
    public function instance_has_rated_attempts() {
        global $DB;
        $sql = "SELECT COUNT(*) as count FROM {flexible_assignee} st join {flexible_attempts} at on st.id = at.assigneeid";
        $sql .= " WHERE st.flexibleid = ? AND at.rating IS NOT NULL AND at.ratingdate IS NOT NULL";
        $rec = $DB->get_record_sql($sql, array($this->get_flexible()->id));
        return $rec->count > 0;
    }

    /**
     * Get assignees with rating
     *
     * @access public
     * @return array assignees
     */
    public function get_graded_assignees() {
        global $DB;
        $sql = "SELECT DISTINCT st.id, st.userid, st.taskid";
        $sql .= " FROM {flexible_assignee} st join {flexible_attempts} at on st.id = at.assigneeid";
        $sql .= " WHERE st.flexibleid = ? AND at.rating IS NOT NULL AND at.ratingdate IS NOT NULL";
        $assignees = $DB->get_records_sql($sql, array($this->get_flexible()->id));
        return $assignees;
    }
    
    /**
     *  Get owners of task
     * @param int $taskid task id
     * @return mixed array of students
     */
    public function get_task_owners($taskid) {
        global $DB;
        $assignees = $DB->get_records('flexible_assignee', array('taskid' => $taskid, 'cancelled' => 0), 'userid', 'id, userid');
        return $assignees;
    }
    
    public function get_users_info(array $assignees) {
        global $DB;
        foreach ($assignees as $assignee) {
            $assignee->userinfo = $DB->get_record('user', array('id' => $assignee->userid), 'firstname, lastname');
            $assignee->usergroups = $this->get_user_groups_extended($assignee->userid);
        }
        return $assignees;
    }
    
    /**
     *  Get id, name and description of all user's groups
     * @param int $userid user id
     * @return array groups
     */
    public function get_user_groups_extended($userid) {
        global $DB;
        $sql = "SELECT gr.name, gr.description, gr.id
                FROM {groups} gr
                JOIN {groups_members} grmem
                ON  grmem.groupid = gr.id
                WHERE   grmem.userid = $userid";
        $groups = $DB->get_records_sql($sql);
        return $groups;
    }
    
    /**
     * Hide or show task by it's id 
     * 
     * @access public
     * @param int $taskid task id
     * @param bool $visibility true to show task, false to hide
     * @return bool true
     */
    public function set_task_visibility($taskid, $visibility) {
        global $DB;
        $task = $DB->get_record('flexible_tasks', array('id'=>$taskid));
        if ($visibility) {
            $task->hidden = 0;
        }
        else {
            $task->hidden = 1;
        }
        return $DB->update_record('flexible_tasks', $task);
    }

    /**
     * Connects existing task with existing assignee.
     * Used when teacher updates task and asks module to save old task.
     * 
     * @access public
     * @param int $assigneeid assignee id
     * @param int $taskid new task id
     * @return bool true, if update is successfull or false
     */
    public function replace_assignee_taskid($assigneeid, $taskid) {
        global $DB;
        if ($DB->record_exists('flexible_assignee', array('id' => $assigneeid))) {
            $assignee = $DB->get_record(
                    'flexible_assignee',
                    array('id' => $assigneeid),
                    'id, taskid');

            // Update random generated values for assignee
            $taskrandomvalues = $DB->get_records(
                    'flexible_task_values',
                    array(
                            'taskid' => $assignee->taskid,
                            'assigneeid' => $assigneeid),
                    'id',
                    'id, taskid');
            foreach ($taskrandomvalues as $taskrandomvalue) {
                $taskrandomvalue->taskid = $taskid;
                $DB->update_record('flexible_task_values', $taskrandomvalue);
            }

            $assignee->taskid = $taskid;
            return $DB->update_record('flexible_assignee', $assignee);
        }
        return false;
    }
    
    /**
     * Drop student's attempts and grades, but save task id
     * 
     * @access public
     * @param int $assigneeid
     */
    public function drop_assignee_progress($assigneeid) {
        global $DB;

        // Clean assignee record
        $assignee = $DB->get_record(
                'flexible_assignee',
                array('id' => $assigneeid),
                'id, taskid, finalized, timetaken, userid'
        );
        $assignee->finalized = null;
        $DB->update_record('flexible_assignee', $assignee);

        // Delete random task values for the assignee
        $DB->delete_records('flexible_task_values', array('assigneeid' => $assigneeid));

        // Get all attempts
        $attempts = $DB->get_records('flexible_attempts', array('assigneeid' => $assigneeid), 'id');
        foreach ($attempts as $attempt) {
            // Delete all submissions for each attempt
            $DB->delete_records('flexible_rating_values', array('attemptid' => $attempt->id));
            // Delete all grades for each attempt
            $DB->delete_records('flexible_submissions', array('attemptid' => $attempt->id));
        }
        // Delete all attempts
        $DB->delete_records('flexible_attempts', array('assigneeid' => $assigneeid));

        // Delete grade from gradebook
        global $CFG;
        require_once($CFG->libdir.'/gradelib.php');
        $record = new stdClass();
        $record->userid = $assignee->userid;
        $record->rawgrade = null;
        grade_update(
                'mod/flexible',
                $this->flexible->course,
                'mod',
                'flexible',
                $this->flexible->id,
                0,
                $record,
                null
        );

    }
    
    /**
     * Generate random fields for students who don't have value in this field
     *
     * @access public
     * @param object $field
     */
    public function generate_randoms($field) {
        if ($field->random == 1) {
            global $DB;
            $sql = "SELECT a.id, a.taskid, a.userid FROM
            {flexible_assignee} a WHERE taskid <> 0 AND
            a.flexibleid = $field->flexibleid";

            $assignees = $DB->get_records_sql($sql);
            foreach ($assignees as $assignee) {
                $value = $this->get_random_value($field);
                $rec = new stdClass();
                $rec->taskid = $assignee->taskid;
                $rec->fieldid = $field->id;
                $rec->value = $value;
                $rec->assigneeid = $assignee->id;
                $DB->insert_record('flexible_task_values', $rec);
            }
        }
    }

    /**
     * Get all assignees who have task
     *
     * @access public
     * @return array
     */
    public function get_instance_task_owners() {
        global $DB;
        $poasid = $this->get_flexible()->id;
        $rec = $DB->get_records_sql(
            "SELECT id, userid, taskid
            FROM {flexible_assignee}
            WHERE flexibleid = $poasid AND taskid > 0 AND cancelled = 0
            ORDER BY id");

        return $rec;
    }
    
    /**
     * Get general information about task
     * 
     * @access public
     * @param int $taskid task id
     * @return object record or false
     */
    public function get_task_info($taskid) {
        global $DB;
        $task = $DB->get_record('flexible_tasks', array('id' => $taskid), 'name, description');
        return $task;
    }
    
    /**
     * Delete field values for all tasks and assignees
     * 
     * @param int $fieldid field id
     * @return boolean true
     */
    public function delete_fieldvalues($fieldid) {
        global $DB;
        return $DB->delete_records('flexible_task_values', array('fieldid' => $fieldid));
    }
    
    public function get_task_field($fieldid) {
        global $DB;
        $field = $DB->get_record('flexible_fields', array('id' => $fieldid));
        if ($field->ftype == LISTOFELEMENTS || $field->ftype == MULTILIST) {
            $field->variants = $this->get_variants($fieldid);
        }
        return $field;
    }
    
    /**
     * Get "rating - penaty = total" string
     *
     * @access public
     * @param int $rating
     * @param int $penalty
     */
    public function show_rating_methematics($rating, $penalty) {
        $string = '';

        $string .= $rating;
        $string .= ' - ';
        $string .= '<span style="color:red;">'.$penalty.'</span>';
        $string .= ' = ';
        $string .= $rating - $penalty;

        return $string;
    }

    /**
     * Get row for flexible assignees row (used while confirming updating tasks,
     * fields, criterions)
     *
     * @access public
     * @param object $assignee assignee record
     * @return array assignee
     */
    public function get_flexible_table_assignees_row($assignee) {
        $row = array();

        // Get student username and profile link
        $userurl = new moodle_url('/user/profile.php', array('id' => $assignee->userid));
        $row[] = html_writer::link($userurl, fullname($assignee->userinfo, true));

        // TODO Get student's groups
        $row[] = '?';

        // Get information about assignee's attempts and grades
        if ($attempt = $this->get_last_attempt($assignee->id)) {
            $row[] = get_string('hasattempts', 'flexible');

            // If assignee has an attempt(s), show information about his grade
            if ($attempt->rating != null) {
                // Show actual grade with penalty
                $row[] =
                    get_string('hasgrade', 'flexible').
                    ' ('.
                    $this->show_rating_methematics($attempt->rating, $this->get_penalty($attempt->id)).
                    ')';
            }
            else {
                // Looks like assignee has no grade or outdated grade
                if ($lastgraded = $this->get_last_graded_attempt($assignee->id)) {
                    $row[] =
                        get_string('hasoutdatedgrade', 'flexible').
                        ' ('.
                        $this->show_rating_methematics($lastgraded->rating, $this->get_penalty($lastgraded->id)).
                        ')';
                }
                else {
                    // There is no graded attempts, so show 'No grade'
                    $row[] = get_string('nograde', 'flexible');
                }
            }
        }
        else {
            // No attepts => no grade
            $row[] = get_string('hasnoattempts', 'flexible');
            $row[] = get_string('nograde', 'flexible');
        }

        return $row;
    }

    /**
     * Count assignee's attempts
     *
     * @access public
     * @param int $assigneeid assignee's id
     * @return int amount of records
     */
    public function count_attempts($assigneeid) {
        global $DB;
        return $DB->count_records('flexible_attempts', array('assigneeid' => $assigneeid));
    }

    /**
     * Count assignee's tasks
     *
     * @access public
     * @param object $assignee assignee record
     * @return int
     */
    public function count_assignees_tasks($assignee) {
        global $DB;
        return $DB->count_records_sql(
            'SELECT COUNT(*) as cnt
            FROM {flexible_assignee}
            WHERE userid = ?
            AND flexibleid = ?
            AND taskid <> 0',
            array($assignee->userid, $assignee->flexibleid));
    }

    /**
     * Get ids and names of instance's tasks
     *
     * @access publit
     * @param $flexibleid instance's id
     * @return array records
     */
    public function get_instance_tasks($flexibleid) {
        global $DB;
        return $DB->get_records('flexible_tasks', array('flexibleid' => $flexibleid), 'id', 'id, name');
    }

    /**
     * Get penalty for next attempt
     *
     * @param int $assigneeid - assignee id
     * @return int penalty for next attempt
     */
    public function next_attempt_penalty($assigneeid) {
        return 0;
    }

    /**
     * Get array of flexible instance's id in same course with mentioned instace.
     *
     * @static
     * @param int $flexibleid instance id
     * @return array
     */
    public static function get_sibling_instances($flexibleid) {
        global $DB;
        $instances = array();

        $sql = 'SELECT id FROM {modules} WHERE name="flexible"';
        $moudleid = $DB->get_record_sql($sql)->id;

        $sql = 'SELECT course FROM {course_modules} WHERE module=? AND instance=?';
        $courseid = $DB->get_record_sql($sql, array($moudleid, $flexibleid))->course;

        $sql = 'SELECT instance FROM {course_modules} WHERE module=? AND course=? AND instance<>?';
        $result = $DB->get_records_sql($sql, array($moudleid, $courseid, $flexibleid));
        foreach($result as $record) {
            $instances[] = $record->instance;
        }
        return $instances;
    }

    /**
     * Get flexible instance by its id.
     *
     * @static
     * @param int $flexibleid flexible id
     * @return mixed record
     */
    public static function get_flexible_by_id($flexibleid) {
        global $DB;
        return $DB->get_record('flexible', array('id' => $flexibleid));
    }

    /**
     * Compares field in database with it's new params. Returns true, if old task values
     * can be used with new field params. (e.g. same type and variants).
     *
     * @param $fieldid field id
     * @param $newfield new field params
     * @return bool true, if old task values can be saved
     */
    public function changing_field_without_deleting_task_values($fieldid, $newfield) {
        $oldfield = $this->get_task_field($fieldid);
        if (!isset($oldfield->variants))
            $oldfield->variants = array();
        $sametype = $newfield->ftype == $oldfield->ftype;
        $newfield->variants = explode("\r\n", $newfield->variants);
        $samevariants = $newfield->variants == $oldfield->variants;
        return $sametype && $samevariants;
    }

    /**
     * Get user info by assignee id
     *
     * @param $assigneeid assignee id
     * @return mixed user info or false
     */
    public function get_user_by_assigneeid($assigneeid) {
        global $DB;
        $sql = '
        SELECT firstname, lastname
        FROM {user}
        JOIN {flexible_assignee} on {flexible_assignee}.userid={user}.id
        WHERE {flexible_assignee}.id = ?';
        $result = $DB->get_record_sql($sql, array($assigneeid));
        if ($result)
            return $result;
        else
            return false;
    }

    /**
     * This function adds plugin pages to the navigation menu
     *
     * @static
     * @param string $subtype - The type of plugin (submission or feedback)
     * @param part_of_admin_tree $admin - The handle to the admin menu
     * @param admin_settingpage $settings - The handle to current node in the navigation tree
     * @param stdClass $module - The handle to the current module
     * @return None
     */
    public static function add_admin_plugin_settings($subtype, part_of_admin_tree $admin, admin_settingpage $settings, stdClass $module) {
        global $CFG;

        $plugins = get_plugin_list_with_file($subtype, 'settings.php', false);
        $pluginsbyname = array();
        foreach ($plugins as $plugin => $plugindir) {
            $pluginname = get_string('pluginname', $subtype . '_'.$plugin);
            $pluginsbyname[$pluginname] = $plugin;
        }
        ksort($pluginsbyname);
        foreach ($pluginsbyname as $pluginname => $plugin) {
            $settings = new admin_settingpage($subtype . '_'.$plugin,
                $pluginname, 'moodle/site:config', !$module->visible);
            if ($admin->fulltree) {
                include($plugins[$plugin]);
            }
            $admin->add($subtype . 'plugins', $settings);
        }

    }
}