<?php

/**
 * Petit script pour préparer des données à importer dans PHP MyAdmin
 */

$data_dir = __DIR__.'/data/';
foreach (glob($data_dir . "*.sql") as $sql_file) {
    [ 'filename' => $sql_name, 'basename' => $sql_fname ] = pathinfo($sql_file);
    $zip_file = $data_dir . $sql_name . '.zip';
    echo $zip_file . ' <- ' . $sql_fname . "\n";
    if (file_exists($zip_file)) unlink($zip_file);
    $zip = new ZipArchive();
    $zip->open($zip_file, ZipArchive::CREATE);
    $zip->addFile($sql_file, $sql_fname);
    $zip->close();
}
