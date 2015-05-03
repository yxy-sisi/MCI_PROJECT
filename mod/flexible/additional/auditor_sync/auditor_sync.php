<?php
require_once(dirname(dirname(dirname(__FILE__))).'/model.php');
/**
 * Плагин для синхронизации с заданиями Аудитора
 */
class auditor_sync {

    // Singletone
    private static $instance = null;
    private function __construct() {

    }

    /**
     * @static
     * @return auditor_sync
     */
    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Импортировать задание из Аудитора
     *
     * @access public
     * @param $taskrecord данные о задании. Соответствуют записям таблицы flexible_tasks
     * с двумя дополнительными полями - id в базе данных Аудитора и comments
     * @return mixed идентификатор вставленной записи
     */
    public function import_task($taskrecord) {
        global $DB;

        $auditorrecord = new stdClass();
        $auditorrecord->auditorvariantid = $taskrecord->id;
        unset($taskrecord->id);
        $auditorrecord->comments = $taskrecord->comments;
        unset($taskrecord->comments);
        $auditorrecord->flexibletaskid = flexible_model::get_instance()->add_task($taskrecord);

        $DB->insert_record('auditor_sync_tasks', $auditorrecord);

        return $auditorrecord->flexibletaskid;
    }

    /**
     * Выполнить синхронизацию заданий (вызывается по cron)
     *
     * @access public
     */
    public function synchronize() {
        // Подключить файл с конфигурацией соединения с внешней базой данных
        $config = array('server'=>'', 'database' => '', 'dbuser' => '', 'dbpass' => '');
        require_once('config.php');
        $error = $this->connect_auditor($config['server'], $config['dbuser'], $config['dbpass'], $config['database']);
        if ($error == false) {
            global $DB;
            $storedtasks = $DB->get_records('auditor_sync_tasks');
            $sql = 'SELECT * FROM variants';
            if (!($result = mysql_query($sql))) {
                echo get_string('errorcantrunquery', 'flexible');
                echo ' '.$sql;
            }
            else{
                // Для каждого задания из аудитора
                while ($actualtask = mysql_fetch_assoc($result)) {
                    // найти те задания flexible, которые были импортироаны из него
                    $taskstosync = $this->get_stored_tasks_by_auditor_variant_id($actualtask['id'], $storedtasks);

                    // и для всех таких заданий обновить комментарии и описание задачи
                    foreach ($taskstosync as $tasktosync) {
                        if ($tasktosync->comments != $actualtask['comments']) {
                            $tasktosync->comments = $actualtask['comments'];
                            //echo 'UPDATE RECORD auditor_sync_tasks ',print_r($tasktosync);
                            $DB->update_record('auditor_sync_tasks', $tasktosync);
                        }
                        $flexibletask = $DB->get_record('flexible_tasks', array('id' => $tasktosync->flexibletaskid), 'id, description');
                        if ($flexibletask) {
                            if ($flexibletask->description != $actualtask['description']) {
                                $flexibletask->description = $actualtask['description'];
                                //echo 'UPDATE RECORD flexible_tasks ',print_r($flexibletask);
                                $DB->update_record('flexible_tasks', $flexibletask);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Получить все задания в Moodle, которые базируются на заданиях аудитора
     *
     * @access public
     * @param $id идентификатор задания в Аудиторе
     * @param $storedtasks все импортированные задания
     * @return array задания flexible, базирующиеся на указанном задании Аудитора
     */
    public function get_stored_tasks_by_auditor_variant_id($id, $storedtasks) {
        $tasks = array();
        foreach ($storedtasks as $storedtask) {
            if ($storedtask->auditorvariantid == $id) {
                $tasks[$storedtask->id] = $storedtask;
            }
        }
        return $tasks;
    }

    /**
     * Подключение к серверу базы данных и базе данных
     *
     * @access public
     * @param $server адрес сервера
     * @param $user имя пользователя БД
     * @param $password пароль пользователя БД
     * @param $database имя БД
     * @return bool|string текст ошибки или false, если ошибок подключения не возникло
     */
    public function connect_auditor($server, $user, $password, $database) {
        if ($db = mysql_connect($server, $user, $password)) {
            mysql_set_charset('UTF8');
            if (!mysql_select_db($database, $db)) {
                return
                    get_string('errorcantconnecttodatabase', 'flexible').
                    ' - '.
                    mysql_error();
            }
            else {
                return false;
            }
        }
        else {
            return
                get_string('errorcantconnecttoserver', 'flexible').
                ' - '.
                mysql_error();
        }
    }

    /**
     * Получить массив полей экземпляра задания, которые могут хранить уровень сложности.
     * Такие поля имеют тип MULTILIST
     *
     * @access public
     * @param int $flexibleid идентификатор экземпляра задания
     * @return array массив вида [id] => название поля
     */
    public function get_possible_kc_fields($flexibleid) {
        global $DB;
        $fields = $DB->get_records(
            'flexible_fields',
            array('flexibleid' => $flexibleid, 'ftype' => MULTILIST),
            'id',
            'id, name'
        );
        foreach ($fields as $k => $v) {
            $fields[$k] = $v->name;
        }
        return $fields;
    }

    /**
     * Получить данные для формы о синхронизируемых заданиях
     *
     * @access public
     * @param $flexibleid id flexible'a
     * @return stdClass
     */
    public function get_auditor_tasks($flexibleid) {
        global $DB;
        $sql =
            "SELECT au.*
            FROM {auditor_sync_tasks} au
            JOIN {flexible_tasks} ts ON au.flexibletaskid = ts.id
            WHERE ts.flexibleid = ?";
        $records = $DB->get_records_sql($sql, array($flexibleid));
        $data = new stdClass();
        $i = 0;
        foreach ($records as $record) {
            $data->syncid[$i] = $record->id;
            $data->auditorvariantid[$i] = $record->auditorvariantid;
            $data->flexibletaskid[$i] = $record->flexibletaskid;
            $data->comments[$i] = $record->comments;
            $i++;
        }
        return $data;
    }

    /**
     * Обновить связи с Аудитором.
     * @access public
     * @param $data данные формы
     * @return string отчет о действиях в формате HTML
     */
    public function save_sync_data($data) {
        global $DB;
        $html = '';
        for ($i = 0; $i < $data->option_repeats; $i++) {
            // Если связь новая
            if ($data->syncid[$i] == -1) {
                // Добавить новую связь
                $record = new stdClass();
                $record->auditorvariantid = $data->auditorvariantid[$i];
                $record->flexibletaskid = $data->flexibletaskid[$i];
                $record->comments = $data->comments[$i];
                if (!empty($record->auditorvariantid) && !empty($record->flexibletaskid)) {
                    $id = $DB->insert_record('auditor_sync_tasks', $record);
                    $html .= "Добавлена связь id=$id ($record->auditorvariantid => $record->flexibletaskid)<br/>";
                }
            }
            // Есди связь старая и удалять ее не требуется
            if ($data->syncid[$i] > -1 && (!isset($data->delete) || (isset($data->delete) && !isset($data->delete[$i])))) {
                // Обновить связь
                $record = new stdClass();
                $record->id = $data->syncid[$i];
                $record->auditorvariantid = $data->auditorvariantid[$i];
                $record->flexibletaskid = $data->flexibletaskid[$i];
                $record->comments = $data->comments[$i];
                if (!empty($record->auditorvariantid) && !empty($record->flexibletaskid)) {
                    $DB->update_record('auditor_sync_tasks', $record);
                    $html .= "Обновлена связь id=$record->id ($record->auditorvariantid => $record->flexibletaskid)<br/>";
                }
            }
            // Есди связь старая и ее требуется удалить
            if ($data->syncid[$i] > -1 && isset($data->delete) && isset($data->delete[$i])) {
                // Удалить связь
                $id = $data->syncid[$i];
                $DB->delete_records('auditor_sync_tasks', array('id' => $id));
                $html .= "Удалена связь id=$id ($record->auditorvariantid => $record->flexibletaskid)<br/>";
            }
        }
        return $html;
    }
}