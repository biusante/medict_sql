<?php

/**
 * Part of Medict https://github.com/biusante/medict-sql
 * Copyright (c) 2021 Université de Paris, BIU Santé
 * MIT License https://opensource.org/licenses/mit-license.php
 */

declare(strict_types=1);

namespace Biusante\Medict;

use Exception, Normalizer, PDO;

/**
 * Méthodes partagées entre les scripts de productions de données
 */

mb_internal_encoding("UTF-8");
class Util
{
    /** Dossier parent du projet, fixé par self::init() */
    protected static $home;
    /** Dossier où trouver les données nouvelles, fixé par self::init() */
    protected static $events_dir;
    /** Paramètres inmportés */
    static public $pars;
    /** SQLite link */
    static public $pdo;
    /** Requête préparée partagées */
    static $q = array();
    /** Des mots vides, toujours utiles */
    static $stop;
    /** Table de correspondances betacode */
    static $grc_lat;
    /** Code int des langues en ordre de priorité pour la base Medict */
    static $langs = array(
        'fra' => 1,
        'lat' => 2,
        'grc' => 3,
        'eng' => 4,
        'deu' => 5,
        'spa' => 6,
        'ita' => 7,
    );

    /**
     * Intialize des champs statiques
     */
    public static function init()
    {
        self::$home = dirname(__DIR__, 3) . '/';
        self::$events_dir = self::$home . 'data_events/';
        // Charger les mots vides
        self::$stop = array_flip(explode("\n", file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'stop.csv')));
    }

    /**
     * Chemin d’un fichier événements à partir d’une cote de volume
     */
    protected static function events_file($volume_cote)
    {
        $file = self::$events_dir . $volume_cote.'.tsv';
        if (!file_exists(dirname($file))) mkdir(dirname($file), 0777, true);
        return $file;
    }

    /**
     * Prendre les paramètres
     */
    public static function pars()
    {
        // paramètres déjà chargés
        if (self::$pars && count(self::$pars)) return self::$pars;
        $pars_file = self::$home . 'pars.php';
        if (!file_exists($pars_file)) {
            throw new Exception("\n\nParamètres MySQL introuvables, attendus dans :\n$pars_file\ncf. modèle ./_pars.php\n\n");
        }
        self::$pars = include self::$home . 'pars.php';
        return self::$pars;
    }

    /**
     * Connexion à la base de données
     */
    public static function connect()
    {
        self::pars();
        self::$pdo =  new PDO(
            "mysql:host=" . self::$pars['host'] . ";port=" . self::$pars['port'] . ";dbname=" . self::$pars['base'],
            self::$pars['user'],
            self::$pars['password'],
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

    /**
     * Désaccentuation d’une forme, à partager avec l’application de diffusion
     */
    public static function deforme($s, $langue=null)
    {
        // bas de casse
        $s = mb_convert_case($s, MB_CASE_FOLD, "UTF-8");
        // décomposer lettres et accents
        $s = Normalizer::normalize($s, Normalizer::FORM_D);
        // ne conserver que les lettres et les espaces, et les traits d’union
        $s = preg_replace("/[^\p{L}\-\s]/u", '', $s);
        if ('lat' === $langue) {
            $s = strtr($s,
                array(
                    'œ' => 'e',
                    'æ' => 'e',
                    'j' => 'i',
                    'u' => 'v',
                )
            );
        } else {
            // ligatures
            $s = strtr(
                $s,
                array(
                    'œ' => 'oe',
                    'æ' => 'ae',
                )
            );
        }
        // normaliser les espaces
        $s = preg_replace('/[\s\-]+/', ' ', trim($s));
        return trim($s);
    }

    /**
     * Petit outil de base
     */
    public static function starts_with($haystack, $needle)
    {
        return substr($haystack, 0, strlen($needle)) === $needle;
    }

    /**
     * Charger une table tsv dans la base MySQL
     * La 1ère ligne doit avoir des colonnes qui existent dans $table
     */
    static function insert_table($file, $table, $separator="\t")
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
Util::init();
