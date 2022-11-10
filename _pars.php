<?php
/** Paramètres locaux */

return array(
    'host' => 'localhost',
    'port' => '3306',
    'base' => 'medict',
    'user' => '?????',
    'pass' => '?????',
    // commande locale de dump pour exporter les données
    'mysqldump' => '"C:\\Program Files\\MySQL\\MySQL Server 5.7\\bin\\mysqldump.exe"',
    // chemin local vers un clone de https://github.com/biusante/medict-xml.git
    // motif de glob cf. https://www.php.net/manual/en/function.glob.php
    'xml_glob' => dirname(__DIR__) . '/medict-xml/xml/*.xml'
);