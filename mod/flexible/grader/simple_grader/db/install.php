<?php

function xmldb_flexible_simple_grader_install() {
    global $DB;
    $rec = new stdClass();
    $rec->name = 'simple_grader';
    $rec->path = 'grader/simple_grader/simple_grader.php';
    if(!$DB->record_exists('flexible_graders', array('name' => $rec->name)))
        $DB->insert_record('flexible_graders', $rec);
}