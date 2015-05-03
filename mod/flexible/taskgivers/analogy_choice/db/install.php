<?php
function xmldb_flexibletaskgivers_analogy_choice_install() {
    global $DB;
    $rec = new stdClass();
    $rec->name = 'analogy_choice';
    $rec->path = 'taskgivers/analogy_choice/analogy_choice.php';
    if(!$DB->record_exists('flexible_taskgivers', array('name' => $rec->name)))
        $DB->insert_record('flexible_taskgivers', $rec);
}