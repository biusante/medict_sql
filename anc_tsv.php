<?php

require (__DIR__.'/php/Biusante/Medict/MedictPrepa.php');

use Biusante\Medict\{MedictPrepa};

// sort les données utiles de la base Medica pour écrire la base Medict
// faire une fois suffit
// MedictPrepa::anc_dir();
// Génère des données tsv ingérables dans medict à partir de anc_dir
// MedictPrepa::tsv_dir();


$src_dir = dirname(__DIR__) . '/medict-xml/xml/';
// à faire après, pour recouvrir les cotes communes à anc
foreach (glob($src_dir.'*.xml') as $src_file) {
    MedictPrepa::tsv_tei($src_file);
}
