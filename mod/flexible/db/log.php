<?php

defined('MOODLE_INTERNAL') || die();

// Install default common logging actions
$logs = array(
    array('module'=>'flexible', 'action'=>'add', 'mtable'=>'flexible', 'field'=>'name'),
    array('module'=>'flexible', 'action'=>'update', 'mtable'=>'flexible', 'field'=>'name'),
    array('module'=>'flexible', 'action'=>'view', 'mtable'=>'flexible', 'field'=>'name'),
    array('module'=>'flexible', 'action'=>'view all', 'mtable'=>'flexible', 'field'=>'name'),
);