<?php

function xmldb_flexible_autotester_install() {
    global $DB;
    $rec = new stdClass();
    $rec->name = 'autotester';
    $rec->path = 'grader/autotester/autotester.php';
    if(!$DB->record_exists('flexible_graders', array('name' => $rec->name)))
        $DB->insert_record('flexible_graders', $rec);
}