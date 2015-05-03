<?php 

function distro_get_config() {

    $config = new stdClass();

    $config->installername = 'Moodle Windows Installer';
    $config->installerversion = '2014040400';
    $config->packname = 'Xampp Lite';
    $config->packversion = '1.8.2-4 Portable';
    $config->webname = 'Apache';
    $config->webversion = '2.4.4';
    $config->phpname = 'PHP';
    $config->phpversion = '5.4.25 (VC9 X86 32bit thread safe) + PEAR ';
    $config->dbname = 'MySQL';
    $config->dbversion = '5.5.32 (Community Server)';
    $config->moodlerelease = '2.8.5+ (Build: 20150428)';
    $config->moodleversion = '2014111005.11';
    $config->dbtype='mysqli';
    $config->dbhost='localhost';
    $config->dbuser='root';

    return $config;
}

function distro_pre_create_db($database, $dbhost, $dbuser, $dbpass, $dbname, $prefix, $dboptions, $distro) {

/// We need to change the database password in windows installer, only if != ''
    if ($dbpass !== '') {
        try {
            if ($database->connect($dbhost, $dbuser, '', 'mysql', $prefix, $dboptions)) {
                $sql = "UPDATE user SET password=password(?) WHERE user='root'";
                $params = array($dbpass);
                $database->execute($sql, $params);
                $sql = "flush privileges";
                $database->execute($sql);
            }
        } catch (Exception $ignore) {
        }
    }
}
?>
