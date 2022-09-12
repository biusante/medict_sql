<?php

/**
 * Part of Medict https://github.com/biusante/medict-sql
 * Copyright (c) 2021 Université de Paris, BIU Santé
 * MIT License https://opensource.org/licenses/mit-license.php
 */

declare(strict_types=1);

namespace Biusante\Medict;

use Normalizer, PDO;

/**
 * Méthodes partagées entre les deux scripts de productions des données
 */
mb_internal_encoding("UTF-8");
MedictUtil::init();
class MedictUtil
{
    /** Dossier parent du projet */
    private static $home;
    /** Paramètres inmportés */
    static public $pars;
    /** SQLite link */
    static public $pdo;
    /** Prepared statements shared between methods */
    static $q = array();
    /** Table de correspondances betacode */
    static $grc_lat;
    /** Ordre des langues */
    static $langs = array(
        'fra' => 1,
        'lat' => 2,
        'grc' => 3,
        'eng' => 4,
        'deu' => 5,
        'spa' => 6,
        'ita' => 7,
    );
    /** Ordre des relations */
    static $reltypes = array(
        'orth' => 1,
        'foreign' => 2,
        'ref' => 3,
        'term' => 4,
        'inorth' => 5,
        'interm' => 6,
    );
    
    public static function init()
    {
        self::$home = dirname(dirname(dirname(__DIR__))) . '/';
    }

    public static function home()
    {
        return self::$home;
    }

    public static function connect()
    {
        self::$pars = include self::$home . 'pars.php';
        self::$pdo =  new PDO(
            "mysql:host=" . self::$pars['host'] . ";port=" . self::$pars['port'] . ";dbname=" . self::$pars['dbname'],
            self::$pars['user'],
            self::$pars['pass'],
            array(
                PDO::ATTR_PERSISTENT => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
                // if true : big queries need memory
                // if false : multiple queries arre not allowed
                // PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
            ),
        );
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        // self::$pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);

        // check connection
        echo // self::$pdo->getAttribute(PDO::ATTR_SERVER_INFO), ' '
        self::$pdo->getAttribute(PDO::ATTR_DRIVER_NAME), ' ',
        self::$pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS), "\n";
    }

    public static function sortable($s)
    {
        // bas de casse
        $s = mb_convert_case($s, MB_CASE_FOLD, "UTF-8");
        // ligatures
        $s = strtr(
            $s,
            array(
                'œ' => 'oe',
                'æ' => 'ae',
            )
        );
        // decomposer lettres et accents
        $s = Normalizer::normalize($s, Normalizer::FORM_D);
        // ne conserver que les lettres et les espaces
        $s = preg_replace("/[^\pL\s]/u", '', $s);
        // normaliser les espaces
        $s = preg_replace('/[\s\-]+/', ' ', trim($s));
        return $s;
    }

    function starts_with($haystack, $needle)
    {
        return substr($haystack, 0, strlen($needle)) === $needle;
    }

    /**
     * Charger une table avec des lignes tsv
     */
    static function tsv_insert($file, $table, $separator="\t")
    {
        // first line, colums names
        $handle = fopen($file, 'r');
        $cols = fgetcsv($handle, null, $separator);
        $count = count($cols);
        $sql = "INSERT INTO " . $table . " (" . implode(", ", $cols) . ") VALUES (?" . str_repeat(', ?', $count - 1) . ");";


        $stmt = self::$pdo->prepare($sql);
        self::$pdo->beginTransaction();
        while (($data = fgetcsv($handle, null, $separator)) !== FALSE) {
            $cell1 = trim($data[0]);
            if (count($data) == 0) continue;
            if (count($data) == 1 && !$cell1) continue;
            if ($cell1 && $cell1[0] == '#') continue;
            $values = array_slice($data, 0, $count);
            array_walk_recursive($values, function(&$value) {
                if ($value === "") return $value = NULL;
                // hack pour ne pas perdre les 0 initiaux
                if ($value[0] === "_") return $value = substr($value, 1);
            });
            $stmt->execute($values);
        }
        self::$pdo->commit();
    }

}