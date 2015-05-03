<?php
global $CFG;
require_once('abstract_page.php');
require_once(dirname(dirname(__FILE__)) . '/model.php');
require_once($CFG->libdir.'/tablelib.php');
class tasksfields_page extends abstract_page {
    var $flexible;

    function tasksfields_page($cm,$flexible) {
        $this->flexible = $flexible;
        $this->cm=$cm;
    }
    function get_cap() {
        return 'mod/flexible:managetasksfields';
    }

    function has_satisfying_parameters() {
        $flag = $this->flexible->flags & ACTIVATE_INDIVIDUAL_TASKS;
        if (!$flag) {
            $this->lasterror = 'errorindtaskmodeisdisabled';
            return false;
        }
        return true;
    }

    function view() {
        $id = $this->cm->id;
        $this->view_controls($id);

        echo '<div id="flexible-taskfields">';
        $count = $this->view_table();
        echo '</div>';
        if ($count > 4) {
            $this->view_controls($id);
        }

    }
    private function view_controls($id) {
        global $OUTPUT;
        echo '<div align="center">';
        echo $OUTPUT->single_button(new moodle_url('view.php', array('id' => $id, 'page' => 'taskfieldedit')),
                                    get_string('addtaskfield','flexible'));
        /*echo $OUTPUT->single_button(new moodle_url('view.php', array('id' => $id, 'page' => 'categoryedit')),
                                    get_string('addcategoryfield','flexible'));*/
        echo '</div>';
    }
    private function view_table() {
        global $DB, $OUTPUT, $PAGE;
        $poasmodel = flexible_model::get_instance();
        $table = new flexible_table('mod-flexible-tasksfields');
        $table->baseurl = $PAGE->url;
        $columns = array(
                'name',
                'ftype',
                'range',
                'random',
                'secretfield',
                'showintable');
        $headers = array(
                get_string('taskfieldname','flexible') . $OUTPUT->help_icon('taskfieldname', 'flexible'),
                get_string('ftype','flexible') . $OUTPUT->help_icon('ftype', 'flexible'),
                get_string('range','flexible') . $OUTPUT->help_icon('range', 'flexible'),
                get_string('random','flexible') . $OUTPUT->help_icon('random', 'flexible'),
                get_string('secretfield','flexible') . $OUTPUT->help_icon('secretfield', 'flexible'),
                get_string('showintable','flexible') . $OUTPUT->help_icon('showintable', 'flexible'));
        
        $table->define_columns($columns);
        $table->define_headers($headers);
        $table->collapsible(true);
        $table->initialbars(true);
        $table->column_class('taskfieldname', 'name');
        $table->set_attribute('class', 'flexible-table taskfields-table');
        //$table->set_attribute('border', '1');
        //$table->set_attribute('width', '100%');

        $table->sortable(true, 'lastname');
        $table->no_sorting('range');
        $table->setup();

        if ($table->get_sql_sort()) {
            $sort = $table->get_sql_sort();
        }
        else {
            $sort = '';
        }
        $yes_icon = $OUTPUT->pix_icon('yes', get_string('yes'), 'mod_flexible');
        $no_icon = $OUTPUT->pix_icon('no', get_string('no'), 'mod_flexible');
        $fields = $DB->get_records('flexible_fields', array('flexibleid'=>$this->flexible->id), $sort);
        foreach($fields as $field) {

            $updateurl = new moodle_url('view.php',
                                        array('id' => $this->cm->id,
                                              'fieldid' => $field->id,
                                              'page' => 'taskfieldedit'),
                                        'u',
                                        'get');
            $deleteurl = new moodle_url('/mod/flexible/warning.php',
                                        array('id' => $this->cm->id,
                                              'fieldid' => $field->id,
                                              'action' => 'deletefield'),
                                        'd',
                                        'get');
            $updateicon = '<a href="' . $updateurl . '">' . '<img src="' .
                          $OUTPUT->pix_url('t/edit') . '" class="iconsmall" alt="' .
                          get_string('edit') . '" title="' . get_string('edit') .'" /></a>';
            $deleteicon = '<a href="'.$deleteurl.'">'.'<img src="'.$OUTPUT->pix_url('t/delete').
                            '" class="iconsmall" alt="'.get_string('delete').'" title="'.get_string('delete').'" /></a>';


            $name = $field->name.' '.$updateicon.' '.$deleteicon.' '.$poasmodel->help_icon($field->description);

            // $variants=$DB->get_records('flexible_variants',array('fieldid'=>$field->id),'sortorder','value');

            // $str='';
            // foreach ($variants as $variant) $str.=$variant->value."<br>";
            $range = '<b>[*]</b>';
            if($field->ftype == NUMBER || $field->ftype == FLOATING) {
                $range = '<b>[</b>'.$field->valuemin.'<b>, </b>'.$field->valuemax.'<b>]</b>';
            }
            if($field->ftype == MULTILIST || $field->ftype == LISTOFELEMENTS) {                
                $range = '<b>[</b>'.implode('<b>, </b>', $poasmodel->get_variants($field->id)) .'<b>]</b>'; 
            }

            
            $row = array($name,
                    $poasmodel->ftypes[$field->ftype],
                    $range,
                    $field->random == 1 ? $yes_icon : $no_icon,
                    $field->secretfield == 1 ? $yes_icon : $no_icon,
                    $field->showintable == 1 ? $yes_icon : $no_icon);
            $table->add_data($row);
        }
        $table->print_html();
        return count($fields);
    }
}