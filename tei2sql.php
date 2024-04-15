<?php
/**
 * Pour débogage seulement.
 * Transforme les fichiers XML en événements
 */
require (__DIR__.'/php/Biusante/Medict/Tei.php');

use Biusante\Medict\{Tei};

$pars = Tei::pars();
foreach (glob($pars['xml_glob']) as $xml_file) {
    Tei::tei_events($xml_file);
}
