<?php
/**
 * Part of Medict https://github.com/biusante/medict-sql
 * Copyright (c) 2021 Université de Paris, BIU Santé
 * MIT License https://opensource.org/licenses/mit-license.php
 */

declare(strict_types=1);

namespace Biusante\Medict;

use DOMDocument, Exception, XSLTProcessor;
// pour autoload facultatif
include_once(__DIR__.'/Util.php');

class Tei extends Util {
    public static function tei_events($tei_file)
    {
        $tei_name = pathinfo($tei_file, PATHINFO_FILENAME);
        $tei_name = preg_replace('@^medict@', '', $tei_name);
        echo "Transform " . $tei_name;
        // XML -> tsv, suite plate d’événements pour l’insertion
        $xml = new DOMDocument;
        $xml->load($tei_file);
        $xsl = new DOMDocument;
        $xsl->load(__DIR__ . '/medict2events.xsl');
        $proc = new XSLTProcessor;
        $proc->importStyleSheet($xsl);
        $events = $proc->transformToXML($xml);

        $events_file = self::events_file($tei_name);
        file_put_contents($events_file, $events);
        echo " => " . $events_file . "\n";
        return $events_file;
    }

}