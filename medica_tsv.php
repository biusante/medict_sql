<?php

require (__DIR__.'/php/Biusante/Medict/MedictPrepa.php');

use Biusante\Medict\{MedictPrepa};
// sort les données utiles de la base Medica pour écrire la base Medict
// faire une fois suffit
MedictPrepa::anc_select();
// tester 1
MedictPrepa::tsv_volume('extbnfdechambrex079');

$src_dir = dirname(__DIR__) . '/medict-xml/xml/';
/*
Medict::$pdo->exec("TRUNCATE dico_titre");
Medict::tsvInsert(dirname(__DIR__) . '/dico_titre.tsv', 'dico_titre');
// produire des tsv avec la table ancpages
Medict::prepare();
Medict::anc_tsv();
// à faire après, pour recouvri les cotes communes à anc
foreach (array(
    'medict37020d.xml',
    'medict37020d~index.xml',
    'medict00152.xml',
    'medict27898.xml',
    'medict07399.xml',
) as $src_basename) {
    $src_file = $src_dir . $src_basename;
    Medict::tei_tsv($src_file);
}
*/