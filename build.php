<?php

require_once(__DIR__ . '/php/autoload.php');

use Biusante\Medict\{Insert, Tei, Util};

$time_start = microtime(true);
$pars = Util::pars();
// regénérer les données des sources xml
/*
foreach (glob($pars['xml_glob']) as $xml_file) {
    Tei::tei_events($xml_file);
}
*/
// table des titres (./dico_titre.tsv)
Insert::dico_titre();
// table des volumes (./dico_volume.tsv)
Insert::dico_volume();
// supprimer les termes
Insert::truncate();
// insérer les événements
Insert::insert_all();
// mise au point finale
Insert::optimize();
// dump des données SQL prêtes à importer ailleurs 
Insert::zip(__DIR__ . '/data_sql/');
// timer
echo date("H:i:s", microtime(true) - $time_start) . "\n";
