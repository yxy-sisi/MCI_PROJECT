<?php
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of analogy_choice
 *
 * @author Arkanif
 */
global $CFG;
require_once dirname(dirname(__FILE__)).'/taskgiver.php';
class analogy_choice extends taskgiver{

    public static function has_settings() {
        return true;
    }
    public static function show_tasks() {
        return true;
    }

    public function get_settings_form($id, $flexibleid) {
        return new taskgiver_form(null,
            array('id' => $id,
                'flexibleid' => $flexibleid));
    }

    public function get_settings($flexibleid) {
        global $DB;
        $data = new stdClass();
        $data->originalinstance = 0;
        $record = $DB->get_record('flexible_analogych', array('additionalid' => $flexibleid));
        if ($record) {
            $data->originalinstance = $record->originalid;
        }
        return $data;
    }

    public function save_settings($data){
        global $DB;
        if ($data->originalinstance != 0) {
            $record = new stdClass();
            $record->originalid = $data->originalinstance;
            $record->additionalid = $data->flexibleid;
            if (!$DB->record_exists('flexible_analogych', array('additionalid' => $record->additionalid))) {
                $DB->insert_record('flexible_analogych', $record);
                echo get_string('originalinstancesaved', 'flexibletaskgivers_analogy_choice');
            }
            else {
                $oldrec = $DB->get_record('flexible_analogych', array('additionalid' => $record->additionalid));
                $record->id = $oldrec->id;
                $DB->update_record('flexible_analogych', $record);
                echo get_string('originalinstancesaved', 'flexibletaskgivers_analogy_choice');
            }
        }
        else {
            $DB->delete_records('flexible_analogych', array('additionalid' => $data->flexibleid));
            echo get_string('originalinstancedeleted', 'flexibletaskgivers_analogy_choice');
        }
    }

    public function delete_settings($flexibleid) {
        global $DB;
        $DB->delete_records('flexible_analogych', array('additionalid' => $flexibleid));
    }

    function process_before_tasks($cmid, $flexible) {
        global $USER, $DB;
        $hascaptohavetask = has_capability('mod/flexible:havetask', flexible_model::get_instance()->get_context());
        $error = flexible_model::get_instance()->check_dates();
        if ($hascaptohavetask && !$error) {
            if (!flexible_model::user_have_active_task($USER->id, $flexible->id)) {
                $data = $this->get_settings($flexible->id);
                if ($data->originalinstance > 0) {
                    $baseassignee = $DB->get_record(
                        'flexible_assignee',
                        array(
                            'userid' => $USER->id,
                            'flexibleid' => $data->originalinstance,
                            'cancelled' => 0
                        ),
                        'id, taskid'
                        );
                    if ($baseassignee->taskid < 1) {
                        print_error(
                            'errornobasetasktaken',
                            'flexibletaskgivers_analogy_choice',
                            new moodle_url('/mod/flexible/view.php',
                                array(
                                    'id' => flexible_model::get_instance()->get_cm()->id,
                                    'page' => 'view')));
                    }
                    else {
                        $basetask = $DB->get_record('flexible_tasks', array('id' => $baseassignee->taskid), 'name');
                        $task = $DB->get_record('flexible_tasks', array('name' => $basetask->name, 'flexibleid' => $flexible->id));
                        if (!$task) {
                            print_error(
                                'errornotaskwithsamename',
                                'flexibletaskgivers_analogy_choice',
                                new moodle_url('/mod/flexible/view.php',
                                    array(
                                        'id' => flexible_model::get_instance()->get_cm()->id,
                                        'page' => 'view')));
                        }
                        flexible_model::get_instance()->bind_task_to_assignee($USER->id, $task->id);
                        redirect(new moodle_url('view.php',array('id' => $cmid,'page' => 'view')));
                    }
                } else {
                    print_error(
                        'errornobaseinstanceselected',
                        'flexibletaskgivers_analogy_choice',
                        new moodle_url('/mod/flexible/view.php',
                            array(
                                'id' => flexible_model::get_instance()->get_cm()->id,
                                'page' => 'view')));
                }
            }
        }

    }
}
class taskgiver_form extends moodleform {
    function definition() {
        $mform = $this->_form;
        $instance = $this->_customdata;
        $poasmodel= flexible_model::get_instance();
        global $DB;

        $mform->addElement('header', 'header', get_string('chosebaseinstance','flexibletaskgivers_analogy_choice'));


        $siblings = flexible_model::get_sibling_instances($instance['flexibleid']);
        $options = array(0 => '-');
        foreach ($siblings as $key => $instanceid) {
            // Get all instances with individual tasks mode activated
            $inst = flexible_model::get_flexible_by_id($instanceid);

            if ($inst && ($inst->flags & ACTIVATE_INDIVIDUAL_TASKS)) {
                $options[$inst->id] = '[' . $inst->id . '] ' . $inst->name;
            }
        }


        $select = $mform->addElement('select', 'originalinstance', get_string('originalinstance', 'flexibletaskgivers_analogy_choice'), $options);
        $mform->addHelpButton('originalinstance', 'originalinstance', 'flexibletaskgivers_analogy_choice');

        $mform->addElement('hidden', 'id', $instance['id']);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'flexibleid', $instance['flexibleid']);
        $mform->setType('flexibleid', PARAM_INT);

        $mform->addElement('hidden', 'page', 'taskgiversettings');
        $mform->setType('page', PARAM_TEXT);

        $this->add_action_buttons(false, get_string('savechanges', 'admin'));
    }
}