<?php
global $CFG;
require_once('abstract_page.php');
require_once(dirname(dirname(__FILE__)) . '/model.php');
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot.'/lib/tablelib.php');

require_once(dirname(dirname(__FILE__)).'/additional/auditor_sync/auditor_sync.php');
class auditortasks_page extends abstract_page {
    var $flexible;
    var $mform;

    function __construct($cm, $flexible) {
        $this->flexible = $flexible;
        $this->cm = $cm;
    }

    function get_cap() {
        return 'mod/flexible:managetasks';
    }

    function has_satisfying_parameters() {
        global $DB,$USER;
        $flag = $this->flexible->flags & ACTIVATE_INDIVIDUAL_TASKS;
        if (!$flag) {
            $this->lasterror='errorindtaskmodeisdisabled';
            return false;
        }
        return true;
    }

    function pre_view() {
        $model = flexible_model::get_instance();
        $flexibleid = $model->get_flexible()->id;
        $this->mform = new auditortasks_form(
            null,
            array(
                'id' => $model->get_cm()->id,
                'flexibleid' => $flexibleid,
                'tasks' => $model->get_instance_tasks($flexibleid))
        );
    }
    /**
     * Показать форму для настройки соединения с сервером
     */
    function view() {
        global $DB;
        $model = flexible_model::get_instance();
        if ($data = $this->mform->get_data()) {
            echo auditor_sync::get_instance()->save_sync_data($data);
            $url = new moodle_url('view.php',
                array(
                    'page' => 'auditortasks',
                    'id' => required_param('id', PARAM_INT)
                )
            );
            echo html_writer::link($url, 'Показать связь заданий с Аудитором');
        }
        else {
            $this->mform->set_data(auditor_sync::get_instance()->get_auditor_tasks($model->get_flexible()->id));
            $this->mform->display();
        }
    }
}
class auditortasks_form extends moodleform {
    function definition(){
        $mform = $this->_form;
        $instance = $this->_customdata;
        $repeatarray = array();
        $repeatarray[] = $mform->createElement('header');
        $repeatarray[] = $mform->createElement('hidden', 'syncid', -1);
        $repeatarray[] = $mform->createElement('text', 'auditorvariantid', 'id варианта в Аудиторе');
        $tasks = $instance['tasks'];
        foreach ($tasks as $id => $task) {
            $tasks[$id] = '['.$id.'] '.$task->name;
        }
        $repeatarray[] = $mform->createElement('select', 'flexibletaskid', 'id задания в flexible', $tasks);
        $repeatarray[] = $mform->createElement('textarea', 'comments', 'Комментарий к заданию в Аудиторе');
        $repeatarray[] = $mform->createElement('checkbox', 'delete', 'Удалить связь с Аудитором');

        if (isset($instance['records'])){
            $repeatno = count ($instance['records']);
        } else {
            $repeatno = 5;
        }

        $repeateloptions = array();

        $repeateloptions['delete']['default'] = 0;
        $repeateloptions['delete']['disabledif'] = array('syncid', 'eq', -1);

        $this->repeat_elements($repeatarray, $repeatno,
            $repeateloptions, 'option_repeats', 'option_add_fields', 3);

        $mform->addElement('hidden', 'id', $instance['id']);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'flexibleid', $instance['flexibleid']);
        $mform->setType('flexibleid', PARAM_INT);

        $mform->addElement('hidden', 'page', 'auditortasks');
        $mform->setType('page', PARAM_TEXT);

        $this->add_action_buttons(true);
    }

    function validation($data, $files) {
        $errors = parent::validation($data, $files);
        for ($i = 0; $i < $data['option_repeats']; $i++) {
            if (!empty($data['auditorvariantid'][$i]) && !is_numeric($data['auditorvariantid'][$i])) {
                $errors["auditorvariantid[$i]"] = 'Это должно быть число';
            }
            if (!empty($data['flexibletaskid'][$i]) && !is_numeric($data['flexibletaskid'][$i])) {
                $errors["flexibletaskid[$i]"] = 'Это должно быть число';
            }
        }
        if (count($errors) > 0){
            return $errors;
        }
        else {
            return true;
        }
    }
}