<?php
/**
 * Part of Medict https://github.com/biusante/medict-sql
 * Copyright (c) 2021 Université de Paris, BIU Santé
 * MIT License https://opensource.org/licenses/mit-license.php
 */

declare(strict_types=1);

namespace Biusante\Medict;
include_once(__DIR__.'/MedictUtil.php');

/**
 * Classe pour charger les tsv préparés avec 
 */
class MedictInsert extends MedictUtil
{
    /** Propriétés du titre en cours de traitement */
    static $dico_titre = null;
    /** fichier tsv en cours d’écriture */
    static $ftsv;
    /** Dossier des fichiers tsv */
    static $tsv_dir;
    /** Insérer un terme */
    static $dico_terme = array(
        ':forme' => null,
        ':langue' => -1,
        ':sortable' => null,
        ':taille' => -1,
        ':mots' => -1,
        ':betacode' => null,
    );
    /** Insérer une relation */
    static $dico_rel = array(
        ':dico_terme' => -1,
        ':reltype' => -1,
        ':dico_titre' => -1,
        ':dico_entree' => -1,
        ':clique' => -1,
        ':volume_annee' => -1,
        ':page' => null,
        ':refimg' => -1,
    );
    
    /** Insérer une entrée */
    static $dico_entree = array(
        ':vedette' => null,
        ':dico_titre' => -1,
        ':dico_volume' => -1,
        ':page' => null,
        ':refimg' => null,
        ':page2' => null,
        ':pps' => 0,
        ':volume_annee' => null,
        ':livancpages' => -1,
    );
    /** Des mots vides à filtrer pour la colonne d’index */
    static $stop;

    public static function init()
    {
        self::connect(); 
        ini_set('memory_limit', -1); // nécessaire à ce script
        mb_internal_encoding("UTF-8");
        // Pour quoi ?
        // self::$grc_lat = include(__DIR__ . '/grc_lat.php');
        // Charger les mots vides
        // self::$stop = array_flip(explode("\n", file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'stop.csv')));
    }

    /**
     * Efface le contenu des tables pour tout recharger à propre.
     */
    public static function truncate()
    {

    }

    /**
     * Charge les tables dico_
     */
    public static function insert_()

    /**
     * Rend l’identifiant d’un terme dans la table dico_terme, 
     * crée la ligne si nécessaire
     */
    public static function terme_id($langue, $forme)
    {
        $sortable = self::sortable($forme);
        self::$q['terme_id']->execute(array($langue, $sortable));
        $row = self::$q['terme_id']->fetch(PDO::FETCH_NUM);
        if ($row) { // le terme existe, retourner son identifiant
            return $row[0];
        }
        // normaliser l’accentuation (surtout pour le grec)
        $forme = Normalizer::normalize($forme, Normalizer::FORM_KC);
        self::$dico_terme[':forme'] = $forme;
        self::$dico_terme[':langue'] = self::$langs[$langue];
        self::$dico_terme[':sortable'] = $sortable;
        self::$dico_terme[':taille'] = mb_strlen($sortable);
        // compter les mots non vides
        $wc = 0;
        $words = preg_split('@[^\p{L}]+@ui', $forme);
        foreach ($words as $w) {
            if (isset(self::$stop[$w])) continue;
            $wc++;
        }
        self::$dico_terme[':mots'] = $wc; 
        if ('grc' == $langue) { // betacode
            self::$dico_terme[':betacode'] = strtr($sortable, self::$grc_lat);
        }
        else {
            self::$dico_terme[':betacode'] = null;
        }
        self::$q['dico_sugg']->execute(self::$dico_sugg);
        $id = self::$pdo->lastInsertId();
        return $id;
    }


    /**
     * Prépare les requêtes d’insertion
     */
    static function prepare()
    {

        foreach (array(
            'dico_terme',
            'dico_rel',
            'dico_entree',
            'dico_volume',
        ) as $table) {
            $sql = "INSERT INTO $table 
    (" . str_replace(':', '', implode(', ', array_keys(self::$$table))) . ") 
    VALUES (" . implode(', ', array_keys(self::$$table)) . ");";
            // echo $sql, "\n";
            self::$q[$table] = self::$pdo->prepare($sql);
        }

        $sql = "SELECT id FROM dico_terme WHERE langue = ? AND sortable = ?";
        self::$q['terme_id'] = self::$pdo->prepare($sql);
    }





    /**
     * Des updates après chargements
     */
    public static function updates()
    {
        /*
    Pour mémoire, update complexe limité en MySQL 
    MySQL Error 1093 - Can't specify target table for update in FROM clause
    Mieux vaut passer par une table temporaire

DROP TEMPORARY TABLE IF EXISTS counts;
CREATE TEMPORARY TABLE counts SELECT dico_titre.id, dico_titre.nomdico, COUNT(*) AS vols
  FROM dico_titre, livanc 
  WHERE livanc.cotemere = dico_titre.cote GROUP BY livanc.cotemere;
UPDATE dico_titre SET dico_titre.vols=(SELECT vols FROM counts WHERE dico_titre.id=counts.id);
SELECT * FROM dico_titre;
     */

        // ALTER TABLE mydb.mytb ROW_FORMAT=Fixed;
        echo "Start sugg.score…";
        self::$pdo->beginTransaction();
        // score des suggestions (les update avec des select sont spécialement compliqués avec MySQL)
        $qcount = self::$pdo->prepare("SELECT COUNT(*) AS COUNT FROM dico_sugg WHERE src_sort = ? AND dst_sort = ?");
        $qup = self::$pdo->prepare("UPDATE dico_sugg SET score = ? WHERE id = ?");
        foreach (self::$pdo->query("SELECT * FROM dico_sugg", PDO::FETCH_ASSOC) as $row) {
            $qcount->execute(array($row['src_sort'], $row['dst_sort']));
            list($count) = $qcount->fetch(PDO::FETCH_NUM);
            $qup->execute(array($count, $row['id']));
        }
        // loop on all
        self::$pdo->commit();
        self::$pdo->exec("UPDATE dico_sugg SET cert=NULL;");
        self::$pdo->exec("UPDATE dico_sugg SET cert=TRUE
WHERE CONCAT('1', dst_sort) IN (SELECT orth_sort FROM dico_index) AND CONCAT('1', src_sort) IN (SELECT orth_sort FROM dico_index);");
        echo " …done.\n";
    }


    /**
     *         // vider les tables à remplir
        foreach (array(
            'dico_terme',
            'dico_rel',
            'dico_entree',
            'dico_volume',
        ) as $table) {
            self::$pdo->query("TRUNCATE TABLE $table");
        }
        self::prepare();

     */

    public static function load_tsv($tsv_file)
    {
        $tsv_name = pathinfo($tsv_file, PATHINFO_FILENAME);


        // quelques données à insérer
        $volume_cote = preg_replace('@^medict@', '', $tsv_name);
        self::$dico_entree[':volume_cote'] = $volume_cote;

        // rependre des données de la table de biblio
        $cote_livre = preg_replace('@x\d\d$@', '', $volume_cote);
        $q = self::$pdo->prepare("SELECT * FROM dico_titre WHERE cote = ?");
        $q->execute(array($cote_livre));
        // list($dico_titre, $annee_titre, $orth_lang) = $q->fetch(PDO::FETCH_NUM);
        $dico_titre = $q->fetch(PDO::FETCH_ASSOC);
        // Si le titre n’est pas connu dans la biblio, crier
        if (!$dico_titre) {
            throw new Exception("cote “{$cote_livre}” inconnue de la table dico_titre" );
        }
        $q = self::$pdo->prepare("DELETE FROM dico_index WHERE dico_titre = ?");
        $q->execute(array($dico_titre['id']));
        $q = self::$pdo->prepare("DELETE FROM dico_entree WHERE dico_titre = ?");
        $q->execute(array($dico_titre['id']));
        $q = self::$pdo->prepare("DELETE FROM dico_sugg WHERE dico_titre = ?");
        $q->execute(array($dico_titre['id']));
        $q = self::$pdo->prepare("DELETE FROM dico_trad WHERE dico_titre = ?");
        $q->execute(array($dico_titre['id']));

        self::$dico_entree[':dico_titre'] = $dico_titre['id'];
        self::$dico_sugg[':dico_titre'] = $dico_titre['id'];
        self::$dico_trad[':dico_titre'] = $dico_titre['id'];
        self::$dico_index[':dico_titre'] = $dico_titre['id'];
        self::$dico_entree[':titre_annee'] = $dico_titre['annee'];
        self::$dico_entree[':titre_nom'] = $dico_titre['nom'];
        // self::$dico_entree[':nom_volume'] = $dico_titre['nom_court'];

        $orth_lang = $dico_titre['orth_lang'];
        if (!$orth_lang) $orth_lang = 'fra';
        // valeurs par défaut
        self::$dico_entree[':volume_annee'] = $dico_titre['annee'];
        self::$dico_index[':volume_annee'] = $dico_titre['annee'];
        self::$dico_entree[':livanc'] = null;

        // attaper des données, si possible
        $q = self::$pdo->prepare("SELECT clenum, annee_iso  FROM livanc WHERE cote = ?");
        $q->execute(array($volume_cote));
        $livanc = $q->fetch(PDO::FETCH_ASSOC);
        if ($livanc && count($livanc) > 0) {
            self::$dico_entree[':livanc'] = $livanc['clenum'];
            self::$dico_entree[':volume_annee'] = $livanc['annee_iso'];
        }
        self::$dico_sugg[':volume_annee'] = self::$dico_entree[':volume_annee'];
        self::$dico_trad[':volume_annee'] = self::$dico_entree[':volume_annee'];
        self::$dico_index[':volume_annee'] = self::$dico_entree[':volume_annee'];

        echo "Start loading…";
        self::$pdo->beginTransaction();
        self::$pdo->query("SET foreign_key_checks=0;");
        // self::prepare(); 
        // préparer les requêtes d’insertion
        // get the page id, select by 
        $qlivancpages = self::$pdo->prepare("SELECT numauto FROM livancpages WHERE cote = ? AND refimg = ?");
        $orth_list = array(); // keep list of orth if multiple
        foreach (explode("\n", $tsv) as $l) {
            if (!$l) continue;
            $cell = explode("\t", $l);
            $object = $cell[0];
            /*
            if ($object == 'volume') {
                self::$dico_entree[':nom_volume'] = $cell[2];
            }
            */
            // fixer la page currente
            if ($object == 'pb') {
                $facs =  $cell[3];
                preg_match('@p=(\d+)@', $facs, $matches);
                $refimg = str_pad($matches[1], 4, '0', STR_PAD_LEFT);
                // un fichier XML peut ne pas avoir été indexé
                $qlivancpages->execute(array($volume_cote, $refimg));
                $row = $qlivancpages->fetch(PDO::FETCH_ASSOC);
                if ($row && count($row) > 0) {
                    self::$dico_entree[':livancpages'] = $row['numauto'];
                }
                else {
                    self::$dico_entree[':livancpages'] = null;
                }
                self::$dico_entree[':refimg'] = $refimg;
                self::$dico_entree[':page'] = ltrim($cell[1], '0');
                self::$dico_trad[':refimg'] = $refimg;
                self::$dico_trad[':page'] = ltrim($cell[1], '0');
                self::$dico_sugg[':refimg'] = $refimg;
                self::$dico_sugg[':page'] = ltrim($cell[1], '0');
            }
            // insert entry
            else if ($object == 'entry') {
                $orth_list = array();
                self::$dico_entree[':vedette'] = $cell[1];
                self::$dico_entree[':pps'] = $cell[2];
                if (!$cell[3]) $cell[3] = null;
                self::$dico_entree[':page2'] = ltrim($cell[3], '0');
                self::$q['dico_entree']->execute(self::$dico_entree);
                $dico_entree = self::$pdo->lastInsertId();
                self::$dico_index[':dico_entree'] = $dico_entree;
                self::$dico_sugg[':dico_entree'] = $dico_entree;
                self::$dico_trad[':dico_entree'] = $dico_entree;
            }
            // insert index
            else if ($object == 'orth') {
                $orth = $cell[1];
                $orth = mb_strtoupper(mb_substr($orth, 0, 1)) . mb_strtolower(mb_substr($orth, 1));
                $orth_sort = self::sortable($orth);
                $orth_list[$orth_sort] = $orth;
                // <orth xml:lang="…">, agira sur <foreign>
                if (isset($cell[2]) && $cell[2]) $orth_lang = $cell[2];
                
                self::$dico_index[':orth'] = $orth;
                self::$dico_index[':orth_lang'] = $orth_lang;
                self::$dico_index[':orth_sort'] = '1' . $orth_sort;
                self::$q['dico_index']->execute(self::$dico_index);
            }
            // insert locution in index
            else if ($object == 'term') {
                $orth = $cell[1];
                $orth_sort = self::sortable($orth);
                if (!isset($orth_list[$orth_sort])) {
                    self::$dico_index[':orth'] = $orth;
                    self::$dico_index[':orth_sort'] = '1' . $orth_sort;
                    self::$dico_index[':orth_lang'] = $orth_lang;
                    self::$q['dico_index']->execute(self::$dico_index);
                }
            }
            // traduction, on enregistre dans les 2 sens
            else if ($object == 'foreign') {
                $foreign = $cell[1];
                $foreign_sort = self::sortable($foreign);
                $foreign_lang = $cell[2];
                // ici on doit avoir toutes les vedettes
                foreach ($orth_list as $orth_sort => $orth) {
                    $langno = 10;

                    if (self::ECHO) {
                        echo "["  .  $orth_lang . "]" 
                        .  $orth 
                        . " => " 
                        . "[" . $foreign_lang . "]"
                        . $foreign
                        . "\n";
                    }

                    self::$dico_trad[':src'] = $orth;
                    self::$dico_trad[':src_sort'] = $orth_sort;
                    self::$dico_trad[':src_lang'] = $orth_lang;
                    self::$dico_trad[':dst'] = $foreign;
                    self::$dico_trad[':dst_sort'] = $foreign_sort;
                    self::$dico_trad[':dst_lang'] = $foreign_lang;
                    self::$dico_trad[':dst_langno'] = self::$langs[$foreign_lang] ?? 10;
                    self::$q['dico_trad']->execute(self::$dico_trad);

                    // lien inverse, uniquement pour latin et grec
                    // if ($foreign_lang != 'lat' && $foreign_lang != 'grc') continue;
                    self::$dico_trad[':src'] = $foreign;
                    self::$dico_trad[':src_sort'] = $foreign_sort;
                    self::$dico_trad[':src_lang'] = $foreign_lang;
                    self::$dico_trad[':dst'] = $orth;
                    self::$dico_trad[':dst_sort'] = $orth_sort;
                    self::$dico_trad[':dst_lang'] = $orth_lang;
                    self::$dico_trad[':dst_langno'] = self::$langs[$orth_lang] ?? 10;
                    self::$q['dico_trad']->execute(self::$dico_trad);
                }
            }
            // renvoi
            else if ($object == 'ref') {
                self::teiSugg($orth_list, $cell[1]);
            }
            // ce qu’il faut faire à la fin
            else if ($object == '/entry') {
                // lien de suggestion entre les vedettes
                foreach ($orth_list as $src_sort => $src) {
                    array_shift($orth_list);
                    foreach ($orth_list as $dst_sort => $dst) {
                        self::$dico_sugg[':src'] = $src;
                        self::$dico_sugg[':src_sort'] = $src_sort;
                        self::$dico_sugg[':dst'] = $dst;
                        self::$dico_sugg[':dst_sort'] = $dst_sort;
                        self::$q['dico_sugg']->execute(self::$dico_sugg);
                        self::$dico_sugg[':src'] = $dst;
                        self::$dico_sugg[':src_sort'] = $dst_sort;
                        self::$dico_sugg[':dst'] = $src;
                        self::$dico_sugg[':dst_sort'] = $src_sort;
                        self::$q['dico_sugg']->execute(self::$dico_sugg);
                    }
                }
            }
        }
        self::$pdo->commit();
        echo " …loaded.\n";
    }

    public static function teiSugg(&$orth_list, $foreign)
    {
        $foreign_sort = self::sortable($foreign);
        foreach ($orth_list as $orth_sort => $orth) {
            self::$dico_sugg[':src'] = $orth;
            self::$dico_sugg[':src_sort'] = $orth_sort;
            self::$dico_sugg[':dst'] = $foreign;
            self::$dico_sugg[':dst_sort'] = $foreign_sort;
            self::$q['dico_sugg']->execute(self::$dico_sugg);
            self::$dico_sugg[':src'] = $foreign;
            self::$dico_sugg[':src_sort'] = $foreign_sort;
            self::$dico_sugg[':dst'] = $orth;
            self::$dico_sugg[':dst_sort'] = $orth_sort;
            self::$q['dico_sugg']->execute(self::$dico_sugg);
        }
    }

}



