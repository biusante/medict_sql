<?php
/**
 * Génère des fichiers événements à partir des fichiers XML
 */
require (__DIR__.'/php/Biusante/Medict/Tei.php');

use Biusante\Medict\{Tei};

// https://github.com/biusante/medict-xml.git
$src_dir = dirname(__DIR__) . '/medict-xml/xml/';
// à faire après, pour recouvrir les cotes communes à anc
foreach (glob($src_dir.'*.xml') as $src_file) {
    Tei::tei_events($src_file);
}
