<?php

require (__DIR__.'/php/Biusante/Medict/MedictPrepa.php');

use Biusante\Medict\{MedictPrepa};

// sort les données utiles de la base Medica pour écrire la base Medict
// faire une fois suffit
// MedictPrepa::anc_dir();
// Génère des données tsv ingérables dans medict à partir de anc_dir
MedictPrepa::tsv_dir();

$src_dir = dirname(__DIR__) . '/medict-xml/xml/';
// à faire après, pour recouvri les cotes communes à anc
foreach (array(
    'medict37020d.xml',
    'medict37020d~index.xml',
    'medict00152.xml',
    'medict27898.xml',
    'medict07399.xml',
) as $src_basename) {
    $src_file = $src_dir . $src_basename;
    MedictPrepa::tsv_tei($src_file);
}
