<?php

function xmldb_flexible_remote_autotester_install() {
    global $DB;
    $rec = new stdClass();
    $rec->name = 'remote_autotester';
    $rec->path = 'grader/remote_autotester/remote_autotester.php';
    if(!$DB->record_exists('flexible_graders', array('name' => $rec->name)))
        $DB->insert_record('flexible_graders', $rec);

}