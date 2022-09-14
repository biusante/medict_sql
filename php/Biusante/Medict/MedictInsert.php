<?php
/**
 * Part of Medict https://github.com/biusante/medict-sql
 * Copyright (c) 2021 Université de Paris, BIU Santé
 * MIT License https://opensource.org/licenses/mit-license.php
 */

declare(strict_types=1);

namespace Biusante\Medict;

use Exception, Normalizer, PDO;

include_once(__DIR__.'/MedictUtil.php');

/**
 * Classe pour charger les tsv préparés avec 
 */
class MedictInsert extends MedictUtil
{
    /** Propriétés du titre en cours de traitement */
    static $titre = null;
    /** Propriétés du volume en cours de traitement */
    static $volume = null;
    /** Table de données en cours de tritement */
    static $data = null;
    /** Dossier des fichiers tsv */
    static $tsv_dir;
    /** Insérer un terme */
    static $dico_terme = array(
        ':forme' => null,
        ':langue' => -1,
        ':sortable' => null,
        ':locutable' => null,
        ':taille' => -1,
        ':mots' => -1,
        ':betacode' => null,
    );
    /** Insérer une relation */
    static $dico_rel = array(
        ':dico_titre' => -1,
        ':volume_annee' => -1,
        ':dico_entree' => -1,
        ':page' => null,
        ':refimg' => -1,
        ':dico_terme' => -1,
        ':reltype' => -1,
        ':clique' => -1,
    );
    
    /** Insérer une entrée (champs en ordre de stabilité) */
    static $dico_entree = array(
        ':vedette' => null,
        ':dico_titre' => -1,
        ':dico_volume' => -1,
        ':volume_annee' => null,
        ':page' => null,
        ':refimg' => null,
        ':livancpages' => -1,
        ':pps' => 0,
        ':page2' => null,
    );
    /** Insérer les informations bibliographiques d’un volume */
    static $dico_volume = array(
        ':dico_titre' => -1,
        ':titre_nom' => null,
        ':titre_annee' => null,
        ':livanc' => -1,
        ':volume_cote' => -1,
        ':volume_soustitre' => -1,
        ':volume_annee' => -1,
    );

    public static function init()
    {
        self::connect();
        self::prepare();
        ini_set('memory_limit', '-1'); // nécessaire à ce script
        mb_internal_encoding("UTF-8");
        // Pour quoi ?
        // self::$grc_lat = include(__DIR__ . '/grc_lat.php');
    }

    /**
     * Efface le contenu des tables pour tout recharger à propre.
     */
    public static function truncate()
    {
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        foreach (array(
            'dico_terme',
            'dico_rel',
            'dico_entree',
        ) as $table) {
            self::$pdo->query("TRUNCATE TABLE $table");
        }

    }

    /**
     * Recharger la table dico_titre
     */
    static public function dico_titre()
    {
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        self::$pdo->exec("TRUNCATE dico_titre");
        self::insert_table(self::home() . 'dico_titre.tsv', 'dico_titre');
    }

    /**
     * Recharge dico_volumes
     */
    static public function dico_volume()
    {
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        self::$pdo->exec("TRUNCATE dico_volume");
        // supposons pour l’instant que l’ordre naturel est bon 
        $sql =  "SELECT * FROM dico_titre "; // ORDER BY annee
        $qdico_titre = self::$pdo->prepare($sql);
        $qdico_titre->execute(array());
        while ($dico_titre = $qdico_titre->fetch()) {

            self::$dico_volume[':dico_titre'] = $dico_titre['id'];
            self::$dico_volume[':titre_nom'] = $dico_titre['nom'];
            self::$dico_volume[':titre_annee'] = $dico_titre['annee'];
            // boucler sur les volumes
            $sql = "SELECT * FROM livanc WHERE ";
            if ($dico_titre['vols'] < 2) {
                $sql .= " cote = ?";
            } else {
                $sql .= " cotemere = ? ORDER BY cote";
            }
            $volq = self::$pdo->prepare($sql);
            $volq->execute(array($dico_titre['cote']));
            
            while ($volume = $volq->fetch(PDO::FETCH_ASSOC)) {

                // de quoi renseigner un enregistrement de volume
                self::$dico_volume[':volume_cote'] = $volume['cote'];
                $soustitre = null;
                if ($dico_titre['vol_re']) {
                    $titre = trim(preg_replace('@[\s]+@u', ' ', $volume['titre']));
                    preg_match('@'.$dico_titre['vol_re'].'@', $titre, $matches);
                    if (isset($matches[1]) && $matches[1]) {
                        $soustitre = trim($matches[1], ". \n\r\t\v\x00");
                    }
                }
                self::$dico_volume[':volume_soustitre'] = $soustitre;
                // livanc.annee : "An VII", livanc.annee_iso : "1798/1799"
                self::$dico_volume[':volume_annee'] = substr($volume['annee_iso'], 0, 4); 
                self::$dico_volume[':livanc'] = $volume['clenum'];
                try {
                    self::$q['dico_volume']->execute(self::$dico_volume);
                }
                catch(Exception $e) {
                    fwrite(STDERR, $e->__toString());
                    fwrite(STDERR, print_r(self::$dico_volume, true));
                    exit();
                }
                /*
                $id = self::$pdo->lastInsertId();
                self::$dico_entree[':dico_volume'] = $id;
                */
            }
        }
    }



    /**
     * Prépare les requêtes d’insertion
     */
    static function prepare()
    {
        // Inutile de vérifier les clés ici.
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS=0");
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
        // requête avec langue ou sans langue
        $sql = "SELECT id FROM dico_terme WHERE sortable = ? AND langue = ?";
        self::$q['forme_langue_id'] = self::$pdo->prepare($sql);
        $sql = "SELECT id FROM dico_terme WHERE sortable = ?";
        self::$q['forme_id'] = self::$pdo->prepare($sql);
    }

    /**
     * Rend l’identifiant d’un terme dans la table dico_terme, 
     * crée la ligne si nécessaire
     */
    public static function dico_terme($forme, $langue)
    {
        // Forme nulle ? 
        if (!$forme) {
            fwrite(STDERR, 'Erreur ? Terme vide p. ' . self::$dico_rel[':page']."\n");
            return null;
        }
        $sortable = self::sortable($forme);
        if (false) {
            // echo $forme. "\t" . $sortable. "\t". implode(' ', str_split($sortable)) . "\t" . bin2hex($sortable) . "\n";
        }
        if ($langue) {
            self::$q['forme_langue_id']->execute(array($sortable, $langue));
            $row = self::$q['forme_langue_id']->fetch(PDO::FETCH_NUM);
        } else {
            self::$q['forme_id']->execute(array($sortable));
            $row = self::$q['forme_id']->fetch(PDO::FETCH_NUM);
        }
        if ($row) { // le terme existe, retourner son identifiant
            return $row[0];
        }
        // normaliser l’accentuation (surtout pour le grec)
        $forme = Normalizer::normalize($forme, Normalizer::FORM_KC);
        self::$dico_terme[':forme'] = $forme;
        self::$dico_terme[':langue'] = self::$langs[$langue] ?? NULL;
        self::$dico_terme[':sortable'] = $sortable;
        self::$dico_terme[':taille'] = mb_strlen($sortable);
        // compter les mots non vides
        $wc = 0;
        // compter les mots $sortable, sinon strpos($sortable, " ") = -1
        $words = preg_split('@[^\p{L}]+@ui', $sortable);
        foreach ($words as $w) {
            if (isset(self::$stop[$w])) continue;
            $wc++;
        }
        self::$dico_terme[':mots'] = $wc;
        if ($wc > 1) {
            self::$dico_terme[':locutable'] = substr($sortable, strpos($sortable, " ") + 1);
        }
        else {
            self::$dico_terme[':locutable'] = null;
        }
        if ('grc' == $langue) { // betacode
            self::$dico_terme[':betacode'] = strtr($sortable, self::$grc_lat);
        }
        else {
            self::$dico_terme[':betacode'] = null;
        }
        try {
            self::$q['dico_terme']->execute(self::$dico_terme);
        }
        catch (Exception $e) {
            fwrite(STDERR, $e->__toString());
            fwrite(STDERR, implode("\t", self::$dico_entree));
            fwrite(STDERR, print_r(self::$dico_terme, true));
        }
        $id = self::$pdo->lastInsertId();
        return $id;
    }








    /**
     * Des updates après chargements
     */
    public static function optimize()
    {
        echo "Optimize… ";
        self::$pdo->exec("OPTIMIZE TABLE dico_terme;");
        echo "…optimize OK";
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
        /*
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
        */
    }


    public static function insert_titre($titre_cote)
    {
        $sql = "SELECT * FROM dico_titre WHERE cote = ?";
        $q = self::$pdo->prepare($sql);
        $q->execute(array($titre_cote));
        self::$titre = $q->fetch();
        if (!self::$titre) {
            throw new Exception("Pas de titre trouvé pour la cote : ".$titre_cote);
        }
        // effacer ici des données ?
        $dico_titre = self::$titre['id'];
        $sql = "SELECT volume_cote FROM dico_volume WHERE dico_titre = ?";
        $q = self::$pdo->prepare($sql);
        $q->execute(array($dico_titre));
        while ($row = $q->fetch()) {
            $tsv_file = self::tsv_file($row['volume_cote']);
            self::insert_volume($tsv_file);
        }
    }

    /**
     * Insérer une vedette.
     * La page de cette relation est celle de l’entrée courante
     */
    private static function insert_orth($forme, $langue=null)
    {
        self::$dico_rel[':dico_terme'] = self::dico_terme($forme, $langue);
        self::$dico_rel[':reltype'] = self::$reltype['orth'];
        self::$dico_rel[':page'] = self::$dico_entree[':page'];
        self::$dico_rel[':refimg'] = self::$dico_entree[':refimg'];
        self::$q['dico_rel']->execute(self::$dico_rel);
        // retourne l’identifiant de terme, peut servir ailleurs
        return self::$dico_rel[':dico_terme'];
    }

    private static function insert_ref($dico_terme, $page, $refimg)
    {
        self::$dico_rel[':dico_terme'] = $dico_terme;
        self::$dico_rel[':reltype'] = self::$reltype['ref'];
        self::$dico_rel[':page'] = $page;
        self::$dico_rel[':refimg'] = $refimg;
        self::$q['dico_rel']->execute(self::$dico_rel);
    }

    /**
     * Insérer le fichier TSV d’un volume. 
     * Attention, il faut avoir nettoyé les tables avant,
     * ou l’on produit des doublons.
     */

    public static function insert_volume($tsv_file)
    {
        if (!file_exists($tsv_file)) {
            throw new Exception("Fichier introuvable : ".$tsv_file);
        }
        // le nom de fichier doit être une cote de volume
        $tsv_name = pathinfo($tsv_file, PATHINFO_FILENAME);
        $volume_cote = $tsv_name;
        $sql = "SELECT * FROM dico_volume WHERE volume_cote = ?";
        $q = self::$pdo->prepare($sql);
        $q->execute(array($volume_cote));
        $rows = $q->fetchAll();
        if (!$rows || !count($rows)) {
            throw new Exception("Cote de volume inconnue pour ce fichier : ".$tsv_file);
        }
        if (count($rows) > 1) {
            throw new Exception("Erreur dans les données, essayer MedictInsert::truncate(). Plus de 1 volume pour la cote : ".$volume_cote);
        }
        self::$volume = $rows[0];
        $sql = "SELECT * FROM dico_titre WHERE id = ?";
        $q = self::$pdo->prepare($sql);
        $q->execute(array(self::$volume['dico_titre']));
        self::$titre = $q->fetch();
        if (!self::$titre) {
            throw new Exception("Erreur dans les données, essayer MedictInsert::truncate(). Rien dans la table dico_titre la cote de volume : ".$volume_cote);
        }

        // RAZ
        foreach (array(
            'dico_terme',
            'dico_rel',
            'dico_entree',
            'dico_volume',
        ) as $table) {
            array_walk(self::$$table, function (&$value, $key) {
                $value = NULL;
            });
        }

        self::$dico_entree[':dico_volume'] = self::$volume['id'];
        self::$dico_entree[':dico_titre'] = self::$volume['dico_titre'];
        self::$dico_entree[':volume_annee'] = self::$volume['volume_annee'];
        self::$dico_rel[':dico_titre'] = self::$volume['dico_titre'];
        self::$dico_rel[':volume_annee'] = self::$volume['volume_annee'];
        // Pas encore utilisé
        self::$dico_rel[':clique'] = 0;


        $orth_langue = self::$titre['orth_langue'];
        // forcer la langue par défaut ?
        // if (!$orth_lang) $orth_lang = 'fra';
        // valeurs par défaut
        echo "[insert_volume] ".$volume_cote.'… ';
        // Charger la totalité du fichier dans un tableau 
        // pour calculer la taille des entrées
        $handle = fopen($tsv_file, 'r');
        // tableau des orth rencontrées dans une entrées
        $orth = ['pour les fausse pages'];
        // tableau des ref rencontrés dans une entrée (évite les doublons)
        $ref = [];
        // page courante
        $page = null;
        $refimg = null;
        $livancpages = null;
        while (($row = fgetcsv($handle, null, "\t")) !== FALSE) {
            // echo implode("\t", $row)."\n";
            if ($row[0] == 'pb') {
                // garder la mémoire de la page courante
                $page = $row[1];
                $refimg = str_pad($row[2], 4, '0', STR_PAD_LEFT);
                if (!preg_match('/^\d\d\d\d$/', $refimg)) {
                    fwrite(STDERR, "$volume_cote\tp. $page\trefimg ???\t$refimg\n");
                    $refimg = null;
                }
                $livancpages = $row[3];
            }
            else if ($row[0] == 'orth') {
                $forme = trim($row[1], ' .,;'); // nettoyer les humains
                if (!$forme) continue;
                $terme_id = null;
                // terme avec langue 
                if ($row[2]) {
                    $terme_id = self::insert_orth($forme, $row[2]);
                }
                // langue par défaut
                else {
                    $terme_id =self::insert_orth($forme, $orth_langue);
                }
                // enregistrer la vedette, peut servir pour les renvois et les traductions, le no de page sera celle de l’entrée
                $orth[$terme_id] = $forme;
            }
            // il faut rentrer l’entrée avant tout (pour avoir son id SQL)
            else if ($row[0] == 'entry') {
                // pas vu de renvoi dans cette entrée, si plusieurs orth, les envoyer comme renvois
                if(!count($ref) && count($orth) >= 2) {
                    foreach ($orth as $dico_terme => $val) {
                        self::insert_ref(
                            $dico_terme,
                            self::$dico_entree[':page'], // page de l’entrée
                            self::$dico_entree[':refimg'],
                        );
                    }
                }
                // si pas d’événement orth depuis la dernière entrée, rentrer la vedette
                if (!count($orth) && self::$dico_entree[':vedette']) {
                    self::insert_orth(self::$dico_entree[':vedette'], $orth_langue);
                }
                // RAZ des états 
                $ref = [];
                $orth = [];
                // par précaution, un peu de nettoyage, si c’est des humains
                $vedette = trim($row[1], ' .,;');
                // titre forgé comme [Page de faux-titre]
                if (preg_match('/^\[[^]]*\]$/', $vedette)) $vedette = NULL;
                self::$dico_entree[':vedette'] = $vedette;
                if (!$vedette) continue;
                // on peut écrire une entrée maintenant
                self::$dico_entree[':page'] = $page;
                self::$dico_entree[':refimg'] = $refimg;
                self::$dico_entree[':livancpages'] = $livancpages;
                self::$dico_entree[':pps'] = $row[2];
                if ($row[2] > 0 && is_numeric($page)) {
                    self::$dico_entree[':page2'] = $page + $row[2];
                }
                else { // Ne pas oublier
                    self::$dico_entree[':page2'] = null;
                }
                try {
                    self::$q['dico_entree']->execute(self::$dico_entree);
                } catch (Exception $e) {
                    fwrite(STDERR, $e->__toString());
                    fwrite(STDERR, print_r(self::$dico_entree, true));
                }
                self::$dico_rel[':dico_entree'] = self::$pdo->lastInsertId();
                // echo  "dico entre->".self::$dico_rel[':dico_entree']."\n";
            }
            // traiter un renvoi
            else if ($row[0] == 'ref') {
                // premier ref de l’entrée
                // envoyer les orth dans la clique des renvois
                if (!count($ref) && count($orth) > 0) {
                    foreach ($orth as $dico_terme => $val) {
                        self::insert_ref(
                            $dico_terme,
                            self::$dico_entree[':page'], // page de l’entrée
                            self::$dico_entree[':refimg'],
                        );
                    }
                }
                // pas de orth, renvoyer la vedette d’entrée
                else if(!count($ref)) {
                    $dico_terme = self::dico_terme(
                        self::$dico_entree[':vedette'], 
                        $orth_langue // en ce cas langue par défaut
                    );
                    self::insert_ref(
                        $dico_terme, 
                        self::$dico_entree[':page'], // page de l’entrée
                        self::$dico_entree[':refimg'],
                    );
                }
                // si ref pas encore vu pour cette entrée, insérer
                $dico_terme = self::dico_terme($row[1], $orth_langue);
                if (!isset($ref[$dico_terme])) {
                    $ref[$dico_terme] = $row[1];
                    self::insert_ref(
                        $dico_terme, 
                        $page, // page courante
                        $refimg,
                    );
                } 
            }
            else {
                fwrite(STDERR, implode("\t", self::$dico_entree) . "\n");
                fwrite(STDERR, "???\t" . implode("\t", $row) . "\n");
            }
        }

        echo "…".$volume_cote."  OK\n";

        return;


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

MedictInsert::init();

