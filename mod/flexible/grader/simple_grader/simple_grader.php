<?php
require_once(dirname(dirname(__FILE__)).'/grader/grader.php');

class simple_grader extends grader{
    
    public function get_test_mode() {
        return FLEXIBLE_GRADER_COMMON_TEST;
    }
    
    public static function name() {
        return get_string('simple_grader','flexible_simple_grader');
    }
    public static function prefix() {
        return __CLASS__;
    }
    public static function validation($data, &$errors) {
        if(isset($data[self::prefix()]) && !isset($data['answerfile']))
            $errors['answerfile'] = get_string('fileanswermustbeenabled',
                                               'flexible_simple_grader');
    }
    
    public function test_attempt($attemptid) {
    
        //TODO: get path to file from database
        $path = 'main.cpp';
        
        exec('cppcheck -v '.$path.' 2>&1', $arr);
        for($i = 1; $i < count($arr); $i++) {
            echo $arr[$i];
        }
        if(count($arr) == 0)
            return 100;
        else
            return 50;
    }
    
    // ����������� ����� ���������� ���������� (������ simple_test_result'��)
    private $testresults;
    private $successfultestscount;
    
    public function show_result($options) {
        //TODO: �������� ����� ��� ����� ����� �������, ��������� ����� ����� ���������� ��� �����
        $html = "";
        if($options & FLEXIBLE_GRADER_SHOW_RATING)
            $html += "<br>Rating : ".(100 * $successfultestscount / count($testresults));
        if($options & FLEXIBLE_GRADER_SHOW_NUMBER_OF_PASSED_TESTS)
            $html += "<br>Passed tests : ".$successfultestscount;
        
        foreach ($testresults as $testresult) {
            if($options & FLEXIBLE_GRADER_SHOW_TESTS_NAMES)
                $html += "<br>".$testresult->testname;
            if($options & FLEXIBLE_GRADER_SHOW_TEST_INPUT_DATA)
                $html += "<br>".$testresult->testinputdata;
        }
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
    function evaluate($submission,$flexibleid,$taskid=-1) {
        return array();
    }
        
    // ���������� ������ ������
    function show_tests($flexibleid,$taskid=-1){
    
    }
}