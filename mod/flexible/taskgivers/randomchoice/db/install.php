<?php

function xmldb_flexibletaskgivers_randomchoice_install() {
    global $DB;
    $rec = new stdClass();
    $rec->name = 'randomchoice';
    $rec->path = 'taskgivers/randomchoice/randomchoice.php';
    //$rec->langpath = 'taskgivers\randomchoice\lang';
    if(!$DB->record_exists('flexible_taskgivers', array('name' => $rec->name)))
        $DB->insert_record('flexible_taskgivers', $rec);
}