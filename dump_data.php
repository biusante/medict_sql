<?php

/**
 * Petit script pour préparer des données à importer dans PHP MyAdmin
 */
// assez local
$mysqldump = '"C:\\Program Files\\MySQL\\MySQL Server 5.7\\bin\\mysqldump.exe"';

$data_dir = __DIR__. DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR;
$pars = include __DIR__ . '/pars.php';
$tables = ['dico_entree', 'dico_rel', 'dico_terme', 'dico_titre', 'dico_volume'];
$base = 'medict';
foreach ($tables as $table) {
    $sql_file = $data_dir . 'medict_' . $table . '.sql';
    $cmd = "$mysqldump --user={$pars['user']} --password={$pars['password']} --host={$pars['host']} $base $table --result-file=$sql_file";
    exec($cmd);
    [ 'filename' => $sql_name, 'basename' => $sql_fname ] = pathinfo($sql_file);
    $zip_file = $data_dir . $sql_name . '.zip';
    echo $zip_file . ' <- ' . $sql_fname . "\n";
    if (file_exists($zip_file)) unlink($zip_file);
    $zip = new ZipArchive();
    $zip->open($zip_file, ZipArchive::CREATE);
    $zip->addFile($sql_file, $sql_fname);
    $zip->close();
}
