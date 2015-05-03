<?php

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once('flexible_tabbed_page.php');
$pagemanager = new flexible_tabbed_page();
$pagemanager->view(); // Display page