<?php

require_once(__DIR__ . '/php/autoload.php');

use Biusante\Medict\{Insert, Tei, Util};

$time_start = microtime(true);
$pars = Util::pars();

// regénérer les données des sources xml
foreach (glob($pars['xml_glob']) as $xml_file) {
    Tei::tei_events($xml_file);
}
// table des titres
Insert::dico_titre(__DIR__ . '/dico_titre.tsv');
// table des volumes
Insert::dico_volume(__DIR__ . '/dico_volume.tsv');
// vider les tables
Insert::truncate();
// insérer les événements
Insert::insert_all();
// Insert::insert_titre('00216');
// mise au point finale
Insert::optimize();
// dump des données SQL prêtes à importer ailleurs 
Insert::zip(__DIR__ . '/data_sql/');
echo date("H:i:s", microtime(true) - $time_start) . "\n";
