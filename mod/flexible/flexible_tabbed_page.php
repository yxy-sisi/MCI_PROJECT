<?php
require_once('model.php');
/** Class of view.php page, displays list of pages
 *  and content of current page
 */
class flexible_tabbed_page {
    private $currentpage;        // name of current page

    /** Standard constuctor for flexible_tabbed_page
     * @param $pages array of pages to be displayed
     */
    function flexible_tabbed_page() {
        global $DB,$PAGE,$USER;
        $id = optional_param('id', 0, PARAM_INT);           // course_module ID, or
        $p  = optional_param('p', 0, PARAM_INT);            // flexible instance ID
        $page = optional_param('page', 'view', PARAM_TEXT);     // set 'view' as default page

        if ($id) {
            $cm         = get_coursemodule_from_id('flexible', $id, 0, false, MUST_EXIST);
            $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
            $flexible  = $DB->get_record('flexible', array('id' => $cm->instance), '*', MUST_EXIST);
        } elseif ($p) {
            $flexible  = $DB->get_record('flexible', array('id' => $p), '*', MUST_EXIST);
            $course     = $DB->get_record('course', array('id' => $flexible->course), '*', MUST_EXIST);
            $cm         = get_coursemodule_from_instance('flexible', $flexible->id, $course->id, false, MUST_EXIST);
        } else {
            error(get_string('errornoid','flexible'));
        }
        
        flexible_model::get_instance()->cash_instance($flexible->id);
        flexible_model::get_instance()->cash_assignee_by_user_id($USER->id);

        require_login($course, true, $cm);
        $this->include_page($page);
        
        //$PAGE->navbar->add(get_string($this->currentpage, 'flexible'));
        
        // Add record to log
        add_to_log($course->id, 'flexible', 'view', "view.php?id=$cm->id&page=$page", $flexible->name, $cm->id);

        //$this->currentpage = $page;
        
        $PAGE->set_url('/mod/flexible/view.php', array('id' => $cm->id,'page'=>$page));
        $PAGE->set_title($course->shortname.': '.get_string('modulename','flexible').': '.$flexible->name);
        $PAGE->set_heading($course->fullname);
        $PAGE->set_button(update_module_button($cm->id, $course->id, get_string('modulename', 'flexible')));

    }
    
    private function include_page($page) {
        $pagetype = $page . '_page';
        if(!array_key_exists($page, flexible_model::$extpages)) {
            print_error(
                'errorunknownpage',
                'flexible',
                new moodle_url('/mod/flexible/view.php',
                    array(
                        'id'=>flexible_model::get_instance()->get_cm()->id,
                        'page' => 'view')));
        }
        $currentpath = flexible_model::$extpages[$page];
        require_once($currentpath);
        $this->currentpage = $page;
    }

    /** 
     * Displays content of the current page, if possible
     */
    function view() {
        global $PAGE;
        $pagetype = $this->currentpage . "_page";
        $model = flexible_model::get_instance();
        // Проверка стандартной capability на просмотр модуля
        require_capability('mod/flexible:view', $model->get_context());

        // Check available date or students
        if (!$model->is_opened()) {
            print_error('thismoduleisntopenedyet',
                    'flexible',
                    new moodle_url('/course/view.php', array('id'=>$model->get_cm()->course)),
                    null,
                    userdate(time()).' < '.userdate($model->get_flexible()->availabledate));
        }
        
        // Check abilities and execute page's logic
        $flexiblepage = new $pagetype($model->get_cm(),
                                            $model->get_flexible());
        $flexiblepage->require_ability_to_view();
        $flexiblepage->pre_view();
        // Display header
        echo $this->get_header($this->currentpage);
        // Display body
        $flexiblepage->view();
        // Display footer
        echo $this->get_footer();
    }

    /** Returns header
     */
    function get_header() {
        global $OUTPUT;
        $html = '';
        $html .= $OUTPUT->header();
        $instancename = flexible_model::get_instance()->get_flexible()->name;
        $header = $instancename . ' : '. get_string($this->currentpage, 'flexible')
                . $OUTPUT->help_icon($this->currentpage, 'flexible');
        $html .= $OUTPUT->heading($header);
        return $html;
    }

    /** Returns footer
     */
    function get_footer() {
        global $OUTPUT;
        $html = '';
        $html .= $OUTPUT->footer();
        return $html;
    }
}