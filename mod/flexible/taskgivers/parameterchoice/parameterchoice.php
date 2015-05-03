<?php
/**
 * Enables students to select tasks using search form.
 */
global $CFG;
require_once dirname(dirname(__FILE__)).'/taskgiver.php';
require_once($CFG->libdir.'/formslib.php');
class parameterchoice extends taskgiver{

    private $mform = NULL;
    private $message = "";

    public static function has_settings() {
        return true;
    }
    public static function show_tasks() {
        return true;
    }

    function parameter_search($cmid, $flexible) {
        $poasmodel = flexible_model::get_instance();
        if ($poasmodel->check_dates())
            return null;
        global $DB,$USER;

        $poasmodel->cash_instance($flexible->id);
        $poasmodel->cash_assignee_by_user_id($USER->id);
        $mform = new parametersearch_form(null, array('flexibleid' => $flexible->id, 'id' => $cmid));
        if($data = $mform->get_data()) {
            $tasks = $poasmodel->get_available_tasks($USER->id);
            if(count($tasks) == 0) {
                print_string('notasks', 'flexibletaskgivers_parameterchoice');
                return $mform;
            }
            $fields = parameterchoice::get_parameters_fields($flexible->id);
            if($fields) {
                $satisfyingtasks = array();
                // Tasks that match not all search fields
                $taskswithmismatch = array();
                foreach($fields as $field) {
                    $fieldelementname = 'field' . $field->id;
                    $fieldvalues = $DB->get_records('flexible_task_values', array('fieldid' => $field->id));
                    if($fieldvalues) {
                        if($field->ftype == LISTOFELEMENTS || $field->ftype == MULTILIST || $field->ftype == STR || $field->ftype == TEXT) {
                            foreach($fieldvalues as $fieldvalue) {
                                if(!isset($fieldvalue->value) || !isset($tasks[$fieldvalue->taskid])) {
                                    continue;
                                }
                                if($tasks[$fieldvalue->taskid]->hidden == 0) {
                                    $contains = strpos($fieldvalue->value, $data->$fieldelementname);
                                    if($contains !== false) {
                                        for($i = 0; $i < 5; $i++) {
                                            $satisfyingtasks[] = $fieldvalue->taskid;
                                        }
                                    }
                                    else {
                                        $taskswithmismatch[] = $fieldvalue->taskid;
                                    }
                                }
                            }
                        }
                        if ($field->ftype == NUMBER || $field->ftype == FLOATING || $field->ftype == DATE) {
                            foreach ($fieldvalues as $fieldvalue) {
                                if(empty($fieldvalue->value) || !isset($tasks[$fieldvalue->taskid])) {
                                    continue;
                                }
                                if ($tasks[$fieldvalue->taskid]->hidden == 0) {
                                    if ($data->$fieldelementname == $fieldvalue->value) {
                                        for($i = 0; $i < 5; $i++) {
                                            $satisfyingtasks[] = $fieldvalue->taskid;
                                        }
                                    }
                                    else {
                                        if ($data->$fieldelementname == 0) {
                                            continue;
                                        }
                                        else {
                                            for($dif = 1; $dif < 5; $dif++) {
                                                if(abs($data->$fieldelementname - $fieldvalue->value) / $data->$fieldelementname < (0.1 * $dif)) {
                                                    for($i = 0; $i < 5 - $dif; $i++)
                                                        $satisfyingtasks[] = $fieldvalue->taskid;
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                $taskswithmismatch = array_unique($taskswithmismatch);
                if (count ($fields) > 1 && count($taskswithmismatch) > 0) {
                    // If there are tasks that matched not all search fields, don't use them
                    $satisfyingtasks = array_diff($satisfyingtasks, $taskswithmismatch);
                }
                if (count($satisfyingtasks) > 0) {
                    shuffle($satisfyingtasks);
                    $taskid = $satisfyingtasks[rand(0, count($satisfyingtasks) - 1)];
                    $poasmodel->bind_task_to_assignee($USER->id, $taskid);
                    redirect(new moodle_url('/mod/flexible/view.php',array('id'=>$cmid,'page'=>'view')),null,0);
                }
                else { 
                    $this->message = get_string('nosatisfyingtasks','flexibletaskgivers_parameterchoice');
                }
            }
        }
        return $mform;
    }

    public function process_before_output($cmid, $flexible) {
        $model = flexible_model::get_instance();
        $hascaptohavetask = has_capability('mod/flexible:havetask', $model->get_context());
        if ($hascaptohavetask && !$model->check_dates()) {
            $this->mform = $this->parameter_search($cmid, $flexible);
        }
    }
    
    function process_before_tasks($cmid, $flexible) {
        if (isset($this->mform)) {
            if ($this->message) {
                echo $this->message;
            }
            $this->mform->display();
        }
    }
    //put your code here
    public function get_settings_form($id, $flexibleid) {
        return new taskgiver_form(null, 
                                  array('id' => $id,
                                        'flexibleid' => $flexibleid));
    }
    public function get_settings($flexibleid) {
        global $DB;
        $data = new stdClass();
        $fields = $DB->get_records('flexible_fields', array('flexibleid' => $flexibleid));
        foreach ($fields as $field) {
            $fieldname = 'field' . $field->id;
            if ($DB->record_exists('flexible_paramch', array('fieldid' => $field->id))) {
                $data->$fieldname = true;
            }
        }
        return $data;
    }
    public function save_settings($data){
        global $DB;
        $fields = $DB->get_records('flexible_fields', array('flexibleid' => $data->flexibleid));
        foreach ($fields as $field) {
            $fieldname = 'field' . $field->id;
            if(isset($data->$fieldname)) {
                if(!$DB->record_exists('flexible_paramch', array('fieldid' => $field->id))) {
                    $DB->insert_record('flexible_paramch', array('fieldid' => $field->id));
                }
            }
            else {
                $DB->delete_records('flexible_paramch', array('fieldid' => $field->id));
            }
        }
    }
    public static function get_parameters_fields($flexibleid) {
        global $DB;
        // get all fields for current flexible
        $allfields = $DB->get_records('flexible_fields', array('flexibleid' => $flexibleid));
        $fields = array();
        foreach ($allfields as $field) {
            // add fields that are parameters in $fields
            if ($DB->record_exists('flexible_paramch', array('fieldid' => $field->id))) {
                array_push($fields, $field);
            }
        }
        return $fields;
    }
    public function delete_settings($flexibleid) {
        global $DB;
        $fields = $DB->get_records('flexible_fields', array('flexibleid' => $flexibleid));
        foreach($fields as $field) {
            $DB->delete_records('flexible_paramch', array('fieldid' => $field->id));
        }
    }
}
class taskgiver_form extends moodleform {
    function definition() {
        $mform = $this->_form;
        $instance = $this->_customdata;
        $poasmodel= flexible_model::get_instance();
        global $DB;
        $mform->addElement('header', 'header', get_string('makefieldparameters','flexibletaskgivers_parameterchoice'));
        
        $fields = $DB->get_records('flexible_fields', array('flexibleid' => $instance['flexibleid']));
        
        foreach ($fields as $field) {
            if($field->random == 1) {
                $mform->addElement('static', 'field' . $field->id, $field->name, get_string('fieldisrandom', 'flexible'));
            }
            else {
                if($field->ftype == FILE) {
                    $mform->addElement('static', 'field' . $field->id, $field->name, get_string('fieldisfile', 'flexible'));
                }
                else {
                    $mform->addElement('checkbox', 'field' . $field->id, $field->name);
                }
            }
        }
        
        $mform->addElement('hidden', 'id', $instance['id']);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'flexibleid', $instance['flexibleid']);
        $mform->setType('flexibleid', PARAM_INT);
        
        $mform->addElement('hidden', 'page', 'taskgiversettings');
        $mform->setType('page', PARAM_TEXT);

        $this->add_action_buttons(false, get_string('savechanges', 'admin'));
    }
}
class parametersearch_form extends moodleform {
    function definition() {
        $mform = $this->_form;
        $instance = $this->_customdata;
        $poasmodel= flexible_model::get_instance();
        global $DB;
        
        $fields = parameterchoice::get_parameters_fields($instance['flexibleid']);
        if($fields) {
            $mform->addElement('header','header',get_string('inputparameters','flexibletaskgivers_parameterchoice'));
            $poasmodel= flexible_model::get_instance();
            foreach($fields as $field) {
                if($field->ftype!=MULTILIST && $field->ftype!=LISTOFELEMENTS)
                    if($field->ftype==DATE)
                        $mform->addElement('date_selector','field'.$field->id,$field->name);
                    else {
                        if(has_capability('mod/flexible:seefielddescription',get_context_instance(CONTEXT_MODULE,$instance['id'])))
                            $mform->addElement('text','field'.$field->id,$field->name.'('.$poasmodel->ftypes[$field->ftype].')'.$poasmodel->help_icon($field->description));
                        else
                            $mform->addElement('text','field'.$field->id,$field->name.'('.$poasmodel->ftypes[$field->ftype].')');
                    }
                else {
                    $opt=$poasmodel->get_field_variants($field->id);
                    $mform->addElement('select','field'.$field->id,$field->name,$opt);
                }
            }

            $mform->addElement('hidden', 'id', $instance['id']);
            $mform->setType('id', PARAM_INT);

            $mform->addElement('hidden', 'flexibleid', $instance['flexibleid']);
            $mform->setType('flexibleid', PARAM_INT);

            $mform->addElement('hidden', 'page', 'tasks');
            $mform->setType('page', PARAM_TEXT);

            $this->add_action_buttons(false, get_string('getrandomtask', 'flexible'));
        }
    }

    function validation($data, $files) {
        $errors = parent::validation($data, $files);
        global $DB;
        $fields = parameterchoice::get_parameters_fields($data['flexibleid']);
        foreach($fields as $field) {
            if(($field->ftype==FLOATING || $field->ftype==NUMBER ) && !is_numeric($data['field'.$field->id])) {
                if(strlen($data['field'.$field->id])>0) {
                    $errors['field'.$field->id]=get_string('errormustbefloat','flexible');
                    return $errors;
                }
            }
        }
        return true;
    }
}
?>
