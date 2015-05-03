<?php


 // TODO: add flexible before every constant
defined('MOODLE_INTERNAL') || die();

define('PREVENT_LATE_CHOICE', 1);
define('RANDOM_TASKS_AFTER_CHOICEDATE', 2);
define('PREVENT_LATE', 4);
define('SEVERAL_ATTEMPTS', 8);
define('NOTIFY_TEACHERS', 16);
define('NOTIFY_STUDENTS', 32);
define('ACTIVATE_INDIVIDUAL_TASKS', 64);
define('SECOND_CHOICE', 128);
define('TEACHER_APPROVAL', 256);
define('ALL_ATTEMPTS_AS_ONE', 512);
define('MATCH_ATTEMPT_AS_FINAL', 1024);
define('FLEXIBLE_CYCLIC_RANDOM', 2048);

define('ADD_MODE', 0);
define('EDIT_MODE',1);
define('DELETE_MODE',2);
define('SHOW_MODE',3);
define('HIDE_MODE',4);

define('FULLRANDOM',0);
define('PARAMETERRANDOM',1);
define('STUDENTSCHOICE',2);

define('STR',0);
define('TEXT',1);
define('FLOATING',2);
define('NUMBER',3);
define('DATE',4);
define('FILE',5);
define('LISTOFELEMENTS',6);
define('MULTILIST',7);
define('CATEGORY',8);

define('TASK_RECIEVED',0);
define('ATTEMPT_DONE',1);
define('GRADE_DONE',2);

define('FLEXIBLE_NO_UNIQUENESS', 0);
define('FLEXIBLE_UNIQUENESS_GROUPS', 1);
define('FLEXIBLE_UNIQUENESS_GROUPINGS', 2);
define('FLEXIBLE_UNIQUENESS_COURSE', 3);

define('FLEXIBLE_CRITERION_OK', 1);
define('FLEXIBLE_CRITERION_CANT_BE_DELETED', 2);
define('FLEXIBLE_CRITERION_CANT_BE_CHANGED', 3);
define('FLEXIBLE_CRITERION_CANT_BE_CREATED', 4);
define('FLEXIBLE_CRITERION_DELETE', 8);
define('FLEXIBLE_CRITERION_CHANGE', 16);
define('FLEXIBLE_CRITERION_CREATE', 32);



require_once(dirname(dirname(dirname(__FILE__))).'/lib/navigationlib.php');
require_once('model.php');

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $flexible An object from the form in mod_form.php
 * @return int The id of the newly inserted flexible record
 */
function flexible_add_instance($flexible) {
    global $DB;
    $flexible->timecreated = time();
    $poasmodel = flexible_model::get_instance($flexible);
    $flexible->id = $poasmodel->add_instance();
    flexible_grade_item_update($flexible);
    return $flexible->id;

    $poasmodel = flexible_model::get_instance_by_id();
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param object $flexible An object from the form in mod_form.php
 * @return boolean Success/Fail
 */
function flexible_update_instance($flexible) {
    global $DB;
    $flexible->timemodified = time();
    $flexible->id = $flexible->instance;
    $poasmodel = flexible_model::get_instance($flexible);
    $id = $poasmodel->update_instance();
    flexible_grade_item_update($flexible);
    return $id;
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function flexible_delete_instance($id) {
    global $DB;
    $flexible = $DB->get_record('flexible', array('id'=>$id));
    $poasmodel = flexible_model::get_instance($flexible);
    flexible_grade_item_delete($flexible);
    return $poasmodel->delete_instance($id);
}

/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @return null
 * @todo Finish documenting this function
 */
function flexible_user_outline($course, $user, $mod, $flexible) {
    $return = new stdClass;
    $return->time = 0;
    $return->info = '';
    return $return;
}

/**
 * Print a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @return boolean
 * @todo Finish documenting this function
 */
function flexible_user_complete($course, $user, $mod, $flexible) {
    return true;
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in flexible activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @return boolean
 * @todo Finish documenting this function
 */
function flexible_print_recent_activity($course, $isteacher, $timestart) {
    return false;  //  True if anything was printed, otherwise false
}

/**
 * Return grade for given user or all users.
 *
 * @param int $assignmentid id of assignment
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * @return boolean
 * @todo Finish documenting this function
 **/
function flexible_cron () {
    //TODO ����������� ����
    if (file_exists(dirname(__FILE__).'/additional/auditor_sync/auditor_sync.php')) {
        require_once(dirname(__FILE__).'/additional/auditor_sync/auditor_sync.php');
        auditor_sync::get_instance()->synchronize();
    }
    return true;
}

/**
 * Must return an array of user records (all data) who are participants
 * for a given instance of flexible. Must include every user involved
 * in the instance, independient of his role (student, teacher, admin...)
 * See other modules as example.
 *
 * @param int $flexibleid ID of an instance of this module
 * @return mixed boolean/array of students
 */
function flexible_get_participants($flexibleid) {
    return false;
}


/**
 * This function returns if a scale is being used by one flexible
 * if it has support for grading and scales. Commented code should be
 * modified if necessary. See forum, glossary or journal modules
 * as reference.
 *
 * @param int $flexibleid ID of an instance of this module
 * @return mixed
 * @todo Finish documenting this function
 */
function flexible_scale_used($flexibleid, $scaleid) {
    global $DB;

    $return = false;

    //$rec = $DB->get_record("flexible", array("id" => "$flexibleid", "scale" => "-$scaleid"));
    //
    //if (!empty($rec) && !empty($scaleid)) {
    //    $return = true;
    //}

    return $return;
}

/**
 * Checks if scale is being used by any instance of flexible.
 * This function was added in 1.9
 *
 * This is used to find out if scale used anywhere
 * @param $scaleid int
 * @return boolean True if the scale is used by any flexible
 */
function flexible_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('flexible', 'grade', -$scaleid)) {
        return true;
    } else {
        return false;
    }
}

/**
 * Execute post-uninstall custom actions for the module
 * This function was added in 1.9
 *
 * @return boolean true if success, false on error
 */
// function flexible_uninstall() {
    // return true;
// }
function flexible_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_GROUPMEMBERSONLY:        return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_GRADE_HAS_GRADE:         return true;
        case FEATURE_GRADE_OUTCOMES:          return true;
        case FEATURE_BACKUP_MOODLE2:          return true;

        default: return null;
    }
}

function flexible_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload) {
    global $CFG, $DB;
    require_once("$CFG->libdir/resourcelib.php");

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_course_login($course, true, $cm);

    /* if ($filearea !== 'content') {
        // intro is handled automatically in pluginfile.php
        return false;
    } */

    $fs = get_file_storage();
    if($filearea=='flexiblefiles') {
        array_shift($args); // ignore revision - designed to prevent caching problems only
        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_flexible/$filearea/0/$relativepath";
    }
    if($filearea=='flexibletaskfiles') {

        $taskvalueid = (int)array_shift($args);
        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_flexible/$filearea/$taskvalueid/$relativepath";
        //echo "/$context->id/mod_flexible/$filearea/$taskvalueid/$relativepath";
    }
    if($filearea=='submissionfiles') {

        $submissionid = (int)array_shift($args);
        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_flexible/$filearea/$submissionid/$relativepath";
        //echo "/$context->id/mod_flexible/$filearea/$submissionid/$relativepath";
    }
    if($filearea=='commentfiles') {

        $attemptid = (int)array_shift($args);
        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_flexible/$filearea/$attemptid/$relativepath";
        //echo "/$context->id/mod_flexible/$filearea/$submissionid/$relativepath";
    }
    $file = $fs->get_file_by_hash(sha1($fullpath));
    /* if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {

        $resource = $DB->get_record('resource', array('id'=>$cminfo->instance), 'id, legacyfiles', MUST_EXIST);
       // if ($resource->legacyfiles != RESOURCELIB_LEGACYFILES_ACTIVE) {
           // return false;
       // }
        // if (!$file = resourcelib_try_file_migration('/'.$relativepath, $cm->id, $cm->course, 'mod_flexible', 'content', 0)) {
           // return false;
       // }
        // file migrate - update flag
        $resource->legacyfileslast = time();
        $DB->update_record('resource', $resource);
    } */

    // should we apply filters?
    $mimetype = $file->get_mimetype();
    if ($mimetype = 'text/html' or $mimetype = 'text/plain') {
        $filter = $DB->get_field('resource', 'filterfiles', array('id'=>$cm->instance));
    } else {
        $filter = 0;
    }

    // finally send the file
    send_stored_file($file, 86400, $filter, $forcedownload);
}

/**
 * Create grade item for given flexible
 *
 * @param object $flexible object with extra cmidnumber
 * @param mixed optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function flexible_grade_item_update($flexible, $grades=NULL) {

    $poasmodel = flexible_model::get_instance($flexible);
    return($poasmodel->grade_item_update($grades));

}

/**
 * Delete grade item for given flexible
 *
 * @param object $flexible object
 * @return object flexible
 */
function flexible_grade_item_delete($flexible) {
    $poasmodel = flexible_model::get_instance($flexible);
    return($poasmodel->grade_item_delete());
}

/**
 * Return grade for given user or all users.
 *
 * @param int $flexibleid id of flexible
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function flexible_get_user_grades($flexible, $userid=0) {
    global $CFG, $DB;

    if($userid) {
        // return user's last attempt rating
        $assignee = $DB->get_record('flexible_attempts',array('userid'=>$userid));
        $lastattempt = flexible_model::get_instance()->get_last_attempt($assignee->id);
        return $lastattempt->rating;
    }

}


/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $flexiblenode The node to add module settings to
 */
function flexible_extend_settings_navigation(settings_navigation $settings, navigation_node $flexiblenode) {

}
function flexible_comment_permissions($comment_param) {
    $return = array('post'=>true, 'view'=>true);
    return $return;
}
function flexible_comment_validate($comment_param) {
    return true;
}

function flexible_extend_navigation(navigation_node $navigation, $course, $module, $cm) {
    global $PAGE,$DB;
    $flexible  = $DB->get_record('flexible', array('id' => $cm->instance));
    if($flexible) {
        flexible_model::get_instance()->cash_instance($flexible->id);

        foreach (flexible_model::$extpages as $pagename => $pagepath) {
            require_once($pagepath);
            $pagetype = $pagename.'_page';
            // If user has ability to view $pagepath - add page on panel
            //$flexible  = $DB->get_record('flexible',
            //                                   array('id' => $cm->instance),
            //                                   '*',
            //                                   MUST_EXIST);
            if(!$pagetype::display_in_navbar()) {
                continue;
            }
            $pageinstance = new $pagetype($cm, $flexible);
            if ($pageinstance->has_ability_to_view()) {
                $navigation->add(get_string($pagename,'flexible'),
                                 new moodle_url('/mod/flexible/view.php',
                                                array('id' => $cm->id,
                                                      'page' => $pagename)));
            }
        }
    }
}