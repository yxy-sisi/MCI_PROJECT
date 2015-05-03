<?php
require_once(dirname(dirname(__FILE__)).'/grader/grader.php');
require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/comment/lib.php');
class autotester extends grader{
    
    public function get_test_mode() {
        return FLEXIBLE_GRADER_INDIVIDUAL_TESTS;
    }
    
    public static function name() {
        return get_string('autotester','flexible_autotester');
    }
    public static function prefix() {
        return __CLASS__;
    }
    public static function validation($data, &$errors) {
        if(isset($data[self::prefix()]) && !isset($data['answertext'])) {
            $errors['answertext'] = get_string('textanswermustbeenabled',
                                               'flexible_autotester');
        }
        if(isset($data[self::prefix()]) && !isset($data['answertext'])) {
            $errors['answerfile'] = get_string('fileanswermustbeenabled',
                                               'flexible_autotester');
        }
        //TODO ��������� ����� ���. �������
    }
    private $cpath = 'D:\Program Files\Microsoft Visual Studio 9.0\VC\\';
    
    private $lastout = '';
    
    private static $attemptcommentarea = 'flexible_attempt_autotester_comment';
    private static $testcommentarea = 'flexible_test_autotester_comment';
    
    private static $colorextra = 'YELLOW';
    private static $colornormal = 'WHITE';
    private static $colormissed = 'RED';
    
    public function test_attempt($attemptid) {
        global $DB;
        
        // step 1: compile student's program
        $compiled = $this->compile($attemptid);
        
        $out = implode("\n", $this->lastout);
        $out = mb_convert_encoding($out, 'utf8', 'cp866');
        $this->add_attempt_grader_comment($attemptid, $out);
        
        if(!$compiled) {
            $this->clean_files($attemptid, 'grader\autotester\attempts\tests\\');
            print_error('errorexewasntcreated', 'flexible_autotester', '', null, $out);
            return 0;
        }
        // step 2: create test files
        
        // step 2.1 get task id
        $attempt = $DB->get_record('flexible_attempts', array('id' => $attemptid));
        $assignee = $DB->get_record('flexible_assignee', array('id' => $attempt->assigneeid));
        
        // step 2.2 get grader tests
        
        if(! $rec = $DB->get_record('flexible_gr_autotester', array('taskid' => $assignee->taskid))) {
            return 100;
        }
        $gradertestrec = $DB->get_record('question_gradertest', array('questionid' => $rec->questionid));
        $gradertests = $DB->get_records('question_gradertest_tests', array('gradertestid' => $gradertestrec->id));
        
        $this->create_test_files($gradertests, 'grader\autotester\attempts\tests\\', $attemptid);
        
        // step 3: call each test and update testing result table
        $results = $this->run_tests($gradertests, 'grader\autotester\attempts\tests\\', $attemptid);
        
        // step 4: compare student's output and test's output and produce grade
        $totalweight = 0;
        foreach($gradertests as $test) {
            $totalweight += $test->weight;
        }
        $grade = 0;
        foreach ($results as $result) {
            $testout = str_replace("\r", '',$gradertests[$result->testid]->testout);
            if($result->studentout == $testout) {
                $grade += 100 * ($gradertests[$result->testid]->weight) / ($totalweight);
            }
            //else {
            //    echo $result->studentout;
            //    echo '!=';
            //    echo $gradertests[$result->testid]->testout;
            //}
        }
        $this->clean_files($attemptid, 'grader\autotester\attempts\tests\\');
        return $grade;
    }
    /**
     * Compiles source code using VC compiler
     *
     * @param int $attemptid
     * @return true if "exe" file was created
     */
    private function compile($attemptid) {
        global $DB;
        $textanswerrec = $DB->get_record('flexible_answers', array('name' => 'answer_text'));
        //$textanswerrec = $DB->get_record('flexible_answers', array('name' => 'answer_file'));
        mkdir("grader/autotester/attempts/attempt$attemptid");
        if($textanswerrec) {
            $submission = $DB->get_record('flexible_submissions', array('attemptid' => $attemptid, 'answerid' => $textanswerrec->id));
            if(strlen($submission->value) > 0) {
                $f = fopen("grader/autotester/attempts/attempt$attemptid/attempt$attemptid.cpp", 'w+');
                fwrite($f, $submission->value);
                fclose($f);
            }
            $str = $this->add_submission_files($attemptid);
            
            $runf = fopen('grader\autotester\runattempt' . $attemptid . '.bat', 'w+');
            $text = 'cd grader\autotester';  
            $text .= "\n";
            $text .= 'call "' . $this->cpath . 'vcvarsall.bat"';
            $text .= "\n";
            $text .= '"' . $this->cpath . 'bin\cl.exe" ';
            $text .= "/Feattempts/attempt$attemptid/attempt$attemptid.exe ";
            //$text .= "/Foattempts\attempt$attemptid\attempt$attemptid.obj ";
            $text .= '/Od /D "WIN32" /D "_DEBUG" /D "_CONSOLE" /D "_UNICODE" /D "UNICODE" /Gm /EHsc /RTC1 /MDd /W3 /nologo /ZI /TP /errorReport:prompt ';
            if(strlen($submission->value) > 0) {
                $text .= " attempts/attempt$attemptid/attempt$attemptid.cpp";
            }
            $text .= $this->get_files($attemptid);
            $text .= "\n";
            fwrite($runf, $text);
            fclose($runf);
            exec('Call grader\autotester\runattempt' . $attemptid . '.bat', $out);
            $this->lastout = $out;
        }
        return file_exists("grader/autotester/attempts/attempt$attemptid/attempt$attemptid.exe");
    }
    private function add_submission_files($attemptid) {
        global $DB;
        $answerrec = $DB->get_record('flexible_answers', array('name' => 'answer_file'));
        if($submission = $DB->get_record('flexible_submissions', array('attemptid' => $attemptid, 'answerid' => $answerrec->id))) {
            $model = flexible_model::get_instance();
            $files = $model->get_files('submissionfiles', $submission->id);
            
            //print_r($files);
            $this->create_files("grader/autotester/attempts/attempt$attemptid",$files);
        }
    }
    private function get_files($attemptid) {
        global $DB;
        $answerrec = $DB->get_record('flexible_answers', array('name' => 'answer_file'));
        if($submission = $DB->get_record('flexible_submissions', array('attemptid' => $attemptid, 'answerid' => $answerrec->id))) {
            $model = flexible_model::get_instance();
            $files = $model->get_files('submissionfiles', $submission->id);
            
            return $this->get_filenames("attempts/attempt$attemptid",$files);
        }
        return '';
    }
    private function get_filenames($path, $files) {
        $names = '';
        foreach($files as $name => $element) {
            if(is_array($element)) {
                $names .= $this->get_filenames("$path\\$name", $element);
            }
            else {
                $names .= " $path\\$name";
            }
        }
        return $names;
    }
    private function create_files($path, $files) {
        foreach($files as $name => $element) {
            if(is_array($element)) {
                mkdir("$path\\$name");
                $this->create_files("$path\\$name", $element);
            }
            else {
                $f = fopen("$path\\$name", 'w+');
                fwrite($f, $element);
                fclose($f);
            }
        }
    }
    private function run_tests($tests, $path, $attemptid) {
        global $DB;
        $testresults = array();
        //$tests = array_reverse($tests);
        foreach($tests as $test) {
            $out = array();
            $command = "grader/autotester/attempts/attempt$attemptid/attempt$attemptid.exe";
            //$command = 'grader\autotester\attempts\attempt'. 
            //            $attemptid . 
            //            '.exe';
            $command .= '<';
            $command .= "grader/autotester/attempts/attempt$attemptid\\tests\\test_$test->id.txt";
            //$command .= $path . 
            //            'test_' . 
            //            $test->id . 
            //            '_' . 
            //            $attemptid . 
            //            '.txt';
            
            exec($command, $out);
            
            $testresult = new stdClass();
            $testresult->testid = $test->id;
            $testresult->attemptid = $attemptid;
            if(count($out) > 1) {
                $testresult->studentout = '';
                foreach ($out as $outline) {
                    $testresult->studentout .= $outline;
                    $testresult->studentout .= "\n";
                }
            }
            else {  
                $testresult->studentout = $out[0];
            }
            $td = str_replace("\r", '',$test->testout);
            if($testresult->studentout == $td) {
                $testresult->testpassed = 1;
            }
            else {
                $testresult->testpassed = 0;
            }
            $testresult->id = $DB->insert_record('flexible_gr_at_res', $testresult);
            $testresults[] = $testresult;
        }
        return $testresults;
    }
    private function create_test_files($tests, $path, $attemptid) {
        mkdir("grader/autotester/attempts/attempt$attemptid\\tests");
        foreach($tests as $test) {
            $f = fopen("grader/autotester/attempts/attempt$attemptid\\tests\\test_$test->id.txt", 'w+');
            fwrite($f, $test->testin);
            fclose($f);
        }
    }
    private function clean_files($attemptid, $path) {
        $this->delete_file_or_dir("grader/autotester/attempts/attempt$attemptid");
        if(file_exists("grader/autotester/attempts/attempt$attemptid")) {
            rmdir("grader/autotester/attempts/attempt$attemptid");
        }
        $this->safe_delete_file('grader\autotester\vc90.idb');
        $this->safe_delete_file('grader\autotester\vc90.pdb');
        $this->safe_delete_file('grader\autotester\runattempt' . $attemptid . '.bat');
    }
    private function delete_file_or_dir($path) {
        $content = scandir($path);
        foreach ($content as $item) {
            if(is_file("$path\\$item")) {
                unlink("$path\\$item");
            }
            else {
                if($item != '.' && $item != '..' && is_dir("$path\\$item") ) {
                    $this->delete_file_or_dir("$path\\$item");
                    rmdir("$path\\$item");
                }
            }
        }
    }
    private function safe_delete_file($path) {
        if(file_exists($path)) {
            unlink($path);
        }
    }
    private function add_attempt_grader_comment($attemptid, $text) {
        $commentoptions = $this->default_comment_options();
        $commentoptions->itemid  = $attemptid;
        $comment = new comment($commentoptions);
        $comment->add($text);
    }
    private function default_comment_options() {
        $commentoptions = new stdClass();
        $commentoptions->area    = self::$attemptcommentarea;
        $commentoptions->pluginname = 'flexible';
        $commentoptions->context = flexible_model::get_instance()->get_context();
        $commentoptions->cm = flexible_model::get_instance()->get_cm();
        $commentoptions->showcount = true;
        $commentoptions->component = 'mod_flexible';
        return $commentoptions;
    }
    // ����������� ����� ���������� ���������� (������ simple_test_result'��)
    private $testresults;
    private $successfultestscount;
    
    public function show_result($options) {
    }
    
    // TODO: ������ � ������� ����� ��� ������� �����
        
    // �������������� ������( �������� �� ���������� ����� ������ � �������������� ������������)
    function edit_tests($tests) {
        return null;
    }
    // ��������� ����
    function turn_off_test($testid) {
        return null;
    }
    // ��������� ������� ����
    function delete_test($testid) {
        return null;
    }
    
    // ������� ������
    function tests_export($exportParams) {
        return null;
    }
    
    // ������ ������
    function tests_import($importParams) {
        return null;
    }
        
    // ���������� ������ ������ $submission �� ������� $taskid ����������� flexible'a
    function evaluate($submission, $flexibleid, $taskid = -1) {
        return array();
    }
        
    // ���������� ������ ������
    function show_tests($flexibleid, $taskid=-1){
    
    }
    
    public static function show_settings($mform, $usedgraderid, $flexibleid) {
        global $DB;
        $tasksrecs = $DB->get_records('flexible_tasks', array('flexibleid' => $flexibleid));
        $testrecs = $DB->get_records('question_gradertest');
        $tests = array();
        foreach($testrecs as $testrec) {
            $question = $DB->get_record('question', array('id' => $testrec->questionid));
            $tests[$question->id] = $question->name;
        }
        foreach($tasksrecs as $taskrec) {
            $mform->addElement('select', 'autotester_task' . $taskrec->id, $taskrec->name, $tests);
        }
    }
    public static function save_settings($data, $flexibleid) {
        global $DB;
        $tasksrecs = $DB->get_records('flexible_tasks', array('flexibleid' => $flexibleid));
        foreach ($tasksrecs as $taskrec) {
            $DB->delete_records('flexible_gr_autotester', array('taskid' => $taskrec->id));
            $rec = new stdClass();
            $rec->taskid = $taskrec->id;
            $name = 'autotester_task' . $taskrec->id;
            $rec->questionid = $data->$name;
            $DB->insert_record('flexible_gr_autotester', $rec);
        }
    }
    public static function get_settings($flexibleid) {
        global $DB;
        $recs = $DB->get_records('flexible_gr_autotester', array());
        $data = array();
        foreach($recs as $rec) {
            $data['autotester_task' . $rec->taskid] = $rec->questionid;
        }
        return $data;
    }
    
    public static function attempt_was_tested($attemptid) {
        global $DB;
        $testsuccessfull = $DB->record_exists('flexible_gr_at_res', array('attemptid' => $attemptid));
        $hasdebuginfo = $DB->record_exists('comments', array('commentarea' => self::$attemptcommentarea, 'itemid' => $attemptid));
        return ($testsuccessfull || $hasdebuginfo);
    }
    function diff($old, $new){
        $maxlen = 0;
        foreach($old as $oindex => $ovalue){
                $nkeys = array_keys($new, $ovalue);
                foreach($nkeys as $nindex){
                        $matrix[$oindex][$nindex] = isset($matrix[$oindex - 1][$nindex - 1]) ?
                                $matrix[$oindex - 1][$nindex - 1] + 1 : 1;
                        if($matrix[$oindex][$nindex] > $maxlen){
                                $maxlen = $matrix[$oindex][$nindex];
                                $omax = $oindex + 1 - $maxlen;
                                $nmax = $nindex + 1 - $maxlen;
                        }
                }       
        }
        if($maxlen == 0) return array(array('d'=>$old, 'i'=>$new));
        return array_merge(
                $this->diff(array_slice($old, 0, $omax), array_slice($new, 0, $nmax)),
                array_slice($new, $nmax, $maxlen),
                $this->diff(array_slice($old, $omax + $maxlen), array_slice($new, $nmax + $maxlen)));
    }
    
    function show_test_results($attemptid, $context) {
        global $USER, $DB, $OUTPUT;
        $html = '';
        $html .= $this->update_results($attemptid);
        $results = $DB->get_records('flexible_gr_at_res', array('attemptid' => $attemptid));
        $html .= $OUTPUT->box_start();
        $html .= $this->safe_show_attempt_comment($attemptid);
        $html .= $this->safe_show_statistics($attemptid);
        $html .= $this->safe_show_rating($attemptid);
        $html .= $OUTPUT->box_end();
        foreach ($results as $result) {
            $html .= $OUTPUT->box_start();
            $test = $DB->get_record('question_gradertest_tests', array('id' => $result->testid));
            
            $html .= $this->safe_show_testname($test->name);
            $html .= $this->safe_show_testin($test->testin);
            $html .= $this->safe_show_testout($test->testout);
            $html .= $this->safe_show_studentout($result->studentout);
            $html .= $this->safe_show_diff($result->studentout, $test->testout);
            
            $html .= $this->safe_show_result($result);            
            
            $options = $this->default_comment_options();
            $options->area = self::$testcommentarea;
            $options->itemid = $result->id;
            $comment = new comment($options);
            
            $html .= $comment->output(true);
            $html .= $OUTPUT->box_end();
        }
        $html = str_replace("\n", '<br>', $html);
        return $html;
    }
    private function safe_show_attempt_comment($attemptid) {
        global $DB, $OUTPUT;
        $context = flexible_model::get_instance()->get_context();
        if(!has_capability('mod/flexible_grader:testingfeedback', $context)) {
            return '';
        }
        $html = '';
        if($DB->record_exists('comments', array('commentarea' => self::$attemptcommentarea, 'itemid' => $attemptid))) {
            $commentoptions = $this->default_comment_options();
            $commentoptions->itemid  = $attemptid;
            $comment = new comment($commentoptions);
            $html .= '<big><b>' . get_string('commentsfromgrader', 'flexible_autotester') . '</b></big>';
            $html .= $comment->output(true);
        }
        return $html;
    }
    private function safe_show_statistics($attemptid) {
        global $DB;
        $context = flexible_model::get_instance()->get_context();
        if(!has_capability('mod/flexible_grader:numberofpassedtests', $context)) {
            return '';
        }
        $pos = $DB->count_records('flexible_gr_at_res', array('attemptid' => $attemptid, 'testpassed' => 1));
        $total = 0;
        
        $attempt = $DB->get_record('flexible_attempts', array('id' => $attemptid), 'assigneeid');
        $assignee = $DB->get_record('flexible_assignee', array('id' => $attempt->assigneeid));
        if ($rec = $DB->get_record('flexible_gr_autotester', array('taskid' => $assignee->taskid))) {
            $gradertestrec = $DB->get_record('question_gradertest', array('questionid' => $rec->questionid));
            $total = $DB->count_records('question_gradertest_tests', array('gradertestid' => $gradertestrec->id));
        }
        $html = '<big><b>'
                . get_string('passedtests','flexible_autotester')
                . ' : '
                . $pos 
                . ' ' 
                . get_string('from','flexible_autotester')
                . ' '
                . $total
                . '</b></big>';
        return $html;
    }
    private function safe_show_testname($testname) {
        global $OUTPUT;
        $context = flexible_model::get_instance()->get_context();
        if(!has_capability('mod/flexible_grader:testnames', $context)) {
            return '';
        }
        return $OUTPUT->heading($testname);
    }
    private function safe_show_testout($testout) {
        global $OUTPUT;
        $context = flexible_model::get_instance()->get_context();
        if(!has_capability('mod/flexible_grader:testoutput', $context)) {
            return '';
        }
        $testout = str_replace("\r", '', $testout);
        $html = '';
        $html .= $OUTPUT->heading(get_string('testout', 'flexible_autotester'));
        $html .= '<br>' . $testout;
        return $html;
    }
    private function safe_show_testin($testin) {
        global $OUTPUT;
        $context = flexible_model::get_instance()->get_context();
        if(!has_capability('mod/flexible_grader:testinput', $context)) {
            return '';
        }
        $testout = str_replace("\r", '', $testin);
        $html = '';
        $html .= $OUTPUT->heading(get_string('testout', 'flexible_autotester'));
        $html .= '<br>' . $testout;
        return $html;
    }
    private function safe_show_studentout($studentout) {
        global $OUTPUT;
        $context = flexible_model::get_instance()->get_context();
        if(!has_capability('mod/flexible_grader:studentoutput', $context)) {
            return '';
        }
        $html = '';
        //$html .= '<br><b><big>' . get_string('studentout', 'flexible_autotester') . '</big></b>';
        $html .= $OUTPUT->heading(get_string('studentout', 'flexible_autotester'));
        $html .= '<br>' . $studentout . '<br>';
        return $html;
    }
    private function safe_show_diff($studentout, $testout) {
        global $OUTPUT;
        $context = flexible_model::get_instance()->get_context();
        if(!has_capability('mod/flexible_grader:diff', $context)) {
            return '';
        }
        $html = '';
        $testout = str_replace("\r", '',$testout);
        $testout = explode("\n", $testout);
        $studentout = explode("\n", $studentout);
        $diffarray = $this->diff($studentout, $testout);
        $html .= $OUTPUT->heading(get_string('diff', 'flexible_autotester'));
        foreach ($diffarray as $diffelement) {
            if(is_array($diffelement) && count($diffelement['d']) + count($diffelement['i']) == 0) {
                continue;
            }
            if(is_array($diffelement)) {
                if(count($diffelement['d']) > 0) {
                    $html .= '<div style="background : '
                            . self::$colorextra
                            . '">' 
                            . implode(" ", $diffelement['d']) 
                            . '</div>';
                }
                if(count($diffelement['i']) > 0) {
                    $html .= '<div style="background : '
                            . self::$colormissed
                            . '">' 
                            . implode(" ", $diffelement['i']) 
                            . '</div>';
                }
            }
            else {
                $html .= '<div style="background : '
                        . self::$colornormal
                        . '">' 
                        . $diffelement 
                        . '</div>';
            }
        }
        return $html;
    }
    private function safe_show_rating($attemptid) {
        global $DB, $OUTPUT;
        $context = flexible_model::get_instance()->get_context();
        if (!has_capability('mod/flexible_grader:rating', $context)) {
            return '';
        }
        $val = $DB->get_record('flexible_rating_values', array('attemptid' => $attemptid));
        if (!$val) {
            $rating = 0;
        }
        else {
            $rating = $val->value;
        }
        return '<br><b><big>' . get_string('graderrating', 'flexible_autotester') . ' : ' . $rating .'</big></b>';
    }
    private function safe_show_result($result) {
        $html = '<br>';
        
        
        $switchto = 1;
        $switchtext = get_string('settestpassed','flexible_autotester');
        if($result->testpassed) {
            $html .= '<b>' . get_string('testpassed', 'flexible_autotester') . '</b>';
            $switchto = 0;
            $switchtext = get_string('settestnotpassed','flexible_autotester');
        }
        else {
            $html .= '<b>' . get_string('testnotpassed', 'flexible_autotester') . '</b>';
        }
        if(has_capability('mod/flexible:grade', flexible_model::get_instance()->get_context())) {
            //print link 
            $page = optional_param('page', 'view', PARAM_TEXT);
            $cmid = flexible_model::get_instance()->get_cm()->id;
            $url = new moodle_url('view.php',array('id' => $cmid, 
                                                   'page' => $page,
                                                   'resultid' => $result->id,
                                                   'testpassed' => $switchto));
            $html .= '<br>';
            $html .= html_writer::link($url, $switchtext);
        }
        return $html;
    }
    private function update_results($attemptid) {
        
        global $DB;
        $html = '';
        $model = flexible_model::get_instance();
        if(has_capability('mod/flexible:grade', flexible_model::get_instance()->get_context())) {
            //print link 
            $resultid = optional_param('resultid', 0, PARAM_INT);
            $testpassed = optional_param('testpassed', 0, PARAM_INT);
            
            if($result = $DB->get_record('flexible_gr_at_res', array('id' => $resultid))) {
                $result->testpassed = $testpassed;
                $DB->update_record('flexible_gr_at_res', $result);
                //update common grade
                if($attempt = $DB->get_record('flexible_attempts', array('id' => $attemptid))) {
                    if($assignee = $DB->get_record('flexible_assignee', array('id' => $attempt->assigneeid))) {
                        if($rec = $DB->get_record('flexible_gr_autotester', array('taskid' => $assignee->taskid))) {
                            if($gradertestrec = $DB->get_record('question_gradertest', array('questionid' => $rec->questionid))) {
                                if($gradertests = $DB->get_records('question_gradertest_tests', array('gradertestid' => $gradertestrec->id))) {
                                    //update rativg value
                                    $totalweight = 0;
                                    foreach($gradertests as $test) {
                                        $totalweight += $test->weight;
                                    }
                                    $grade = 0;
                                    $results = $DB->get_records('flexible_gr_at_res', array('attemptid' => $attemptid));
                                    foreach ($results as $result) {
                                        if($result->testpassed) {
                                            $grade += 100 * ($gradertests[$result->testid]->weight) / ($totalweight);
                                        }
                                    }
                                    $criterions = $DB->get_records('flexible_criterions',
                                           array('flexibleid' => $model->get_flexible()->id,
                                                 'graderid' => $this->get_my_id()));
                                                 
                                    foreach ($criterions as $criterion) {
                                        $ratingvalue = $DB->get_record('flexible_rating_values', array('criterionid' => $criterion->id,
                                                                                                             'attemptid' => $attemptid));
                                        $ratingvalue->value = $grade;
                                        $ratingvalueid = $DB->update_record('flexible_rating_values', $ratingvalue);
                                    }
                                    
                                    //update attempt rating
                                    $criterions = $DB->get_records('flexible_criterions', array('flexibleid' => $model->get_flexible()->id));
                                    $totalcriterionweight = 0;
                                    foreach ($criterions as $criterion) {
                                        $totalcriterionweight += $criterion->weight;
                                    }
                                    $ratingvalues = $DB->get_records('flexible_rating_values', array('attemptid' => $attemptid));
                                    $attemptgrade = 0;
                                    foreach ($ratingvalues as $ratingvalue) {
                                        $attemptgrade += $ratingvalue->value * round($criterions[$ratingvalue->criterionid]->weight/$totalcriterionweight,2);
                                    }
                                    $attempt->ratingdate = time();
                                    $attempt->rating = $attemptgrade;
                                    $DB->update_record('flexible_attempts', $attempt);
                                }
                            }
                        }
                    }
                }
            }
        }
        return $html;
    }
    private function get_my_id() {
        global $DB;
        return $DB->get_record('flexible_graders', array('name' => 'autotester'))->id;
    }
}
