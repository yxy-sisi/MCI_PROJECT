<?php
require_once(dirname(dirname(__FILE__)).'/grader/grader.php');
require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/comment/lib.php');
class remote_autotester extends grader{
    
    public function get_test_mode() {
        return FLEXIBLE_GRADER_INDIVIDUAL_TESTS;
    }
    
    public static function name() {
        return get_string('remote_autotester','flexible_remote_autotester');
    }
    public static function prefix() {
        return __CLASS__;
    }
    public static function validation($data, &$errors) {
        if(isset($data[self::prefix()]) && !isset($data['answerfile'])) {
            $errors['answerfile'] = get_string('fileanswermustbeenabled',
                                               'flexible_remote_autotester');
        }
        if (isset($data[self::prefix()]) && !isset($data['activateindividualtasks'])) {
            $errors['activateindividualtasks'] = get_string('individualtasksmustbeactivated',
                'flexible_remote_autotester');
        }
    }

    public function evaluate_attempt($attemptid) {
        global $DB;
        // get attempt files
        $attempt = $DB->get_record('flexible_attempts', array('id' => $attemptid));
        $assignee = $DB->get_record('flexible_assignee', array('id' => $attempt->assigneeid));
        $answerrec = $DB->get_record('flexible_answers', array('name' => 'answer_file'));
        if($submission = $DB->get_record('flexible_submissions', array('attemptid' => $attemptid, 'answerid' => $answerrec->id))) {
            $model = flexible_model::get_instance();
            $files = $model->get_files('submissionfiles', $submission->id);
            $this->send_xmlrpc($assignee->taskid, $attemptid, $files);
        }
    }

    private function special_iconv($string)
    {
        return iconv("CP1251", "UTF-8", $string);
    }

    private function send_xmlrpc($taskid, $attemptid, $files)
    {
        global $DB;
        $record = new stdClass();
        $record->attemptid = $attemptid;
        $record->timecreated = time();
        $record->id = $DB->insert_record('flexible_gr_ra', $record);

        $config = get_config("flexible_remote_autotester");
        if (!$config->ip || !$config->port || !$config->login || !$config->password) {
            print_error('connectionisntconfigured', 'flexible_remote_autotester');
            return;
        }

        require_once('kd_xmlrpc.php');
        list($success, $response) = XMLRPC_request(
            $config->ip . ':' . $config->port,
            'xmlrpc',
            'TestServer.getAttemptFiles',
            array(
                XMLRPC_prepare(md5($config->login)),
                XMLRPC_prepare(md5($config->password)),
                XMLRPC_prepare(intval($taskid)),
                XMLRPC_prepare(intval($attemptid)),
                XMLRPC_prepare($files, 'struct'))
        );
        if (!$success) {
            $response =  'Error ['. $response['faultCode'] . ']: ' . $response['faultString'];
        }
        $record->serverresponse = $response;
        $DB->update_record('flexible_gr_ra', $record);
        if (!$success) {
            print_error('errorwhilesendingxmlrpc', 'flexible_remote_autotester');
        }
        if (strpos($response, "401") !== FALSE) {
            print_error('xmlrpc401', 'flexible_remote_autotester');
        }
    }

    private function get_my_id() {
        global $DB;
        return $DB->get_record('flexible_graders', array('name' => 'remote_autotester'))->id;
    }
}
