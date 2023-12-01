<?php

require_once(__DIR__ . '/php/autoload.php');

use Biusante\Medict\{Insert, Tei, Util};

$time_start = microtime(true);
$pars = Util::pars();

/*
Insert::truncate();
Insert::insert_titre('01686');
die();
*/
// regénérer les données des sources xml
foreach (glob(dirname(__DIR__) . '/medict_xml/xml/*.xml') as $xml_file) {
    $name = pathinfo($xml_file, PATHINFO_FILENAME);
    // pour l’instant le James Anglais n’est pas assez balisé
    if (strpos($name, "medict01686") === 0) continue;
    Tei::tei_events($xml_file);
}
// table des titres
Insert::dico_titre(__DIR__ . '/dico_titre.tsv');
// table des volumes
Insert::dico_volume(__DIR__ . '/dico_volume.tsv');
// vider le dico de termes
Insert::truncate();
// insérer les événements
Insert::insert_all();
// mise au point finale
Insert::optimize();
// dump des données SQL prêtes à importer ailleurs 
Insert::zip(__DIR__ . '/data_sql/');
echo date("H:i:s", microtime(true) - $time_start) . "\n";
