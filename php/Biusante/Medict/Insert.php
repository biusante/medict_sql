<?php
/**
 * Part of Medict https://github.com/biusante/medict-sql
 * Copyright (c) 2021 Université de Paris, BIU Santé
 * MIT License https://opensource.org/licenses/mit-license.php
 */

declare(strict_types=1);

namespace Biusante\Medict;

use Exception, Normalizer, PDO, ZipArchive;

/**
 * Classe pour charger les tsv préparés avec 
 */
class Insert extends Util
{
    /** Chrono */
    private static $time_start;
    /** Line number for debug */
    private static $line;

    /** Propriétés du titre en cours de traitement */
    private static $titre = null;
    /** Table de données en cours de traitement */
    private static $data = null;
    /** Cache mémoire de termes */
    private static $terme_id = [];
    /** Insérer un terme */
    private static $dico_terme = array(
        C::_FORME => null,
        C::_LANGUE => -1,
        C::_DEFORME => null,
        C::_DELOC => null,
        C::_TAILLE => -1,
        C::_MOTS => -1,
        C::_UVJI => null,
    );
    /** Insérer une relation */
    private static $dico_rel = array(
        C::_DICO_TITRE => -1,
        C::_VOLUME_ANNEE => -1,
        C::_DICO_ENTREE => -1,
        C::_PAGE => null,
        C::_REFIMG => -1,
        C::_RELTYPE => -1,
        C::_CLIQUE => -1,
        C::_DICO_TERME => -1,
        C::_ORTH => null,
    );
    /** Cache de clique */
    private static $clique = null;
    /** Insérer une entrée (champs en ordre de stabilité) */
    private static $dico_entree = array(
        C::_VEDETTE => null,
        C::_DICO_TITRE => -1,
        C::_DICO_VOLUME => -1,
        C::_VOLUME_ANNEE => null,
        C::_PAGE => null,
        C::_REFIMG => null,
        C::_LIVANCPAGES => -1,
        C::_PPS => 0,
        C::_PAGE2 => null,
    );
    /** Insérer les informations bibliographiques d’un volume */
    private static $dico_volume = array(
        C::_DICO_TITRE => -1,
        C::_TITRE_NOM => null,
        C::_TITRE_ANNEE => null,
        C::_LIVANC => -1,
        C::_VOLUME_COTE => -1,
        C::_VOLUME_SOUSTITRE => -1,
        C::_VOLUME_ANNEE => -1,
    );

    /**
     * Inistialisation des variables statiques
     */
    public static function init()
    {
        self::$time_start = microtime(true);
        // prendre de la mémoire et du temps
        ini_set('memory_limit', '-1');
        mb_internal_encoding("UTF-8");
        // connexion
        self::connect();
        echo "Création des tables et index qui n’existeraient pas encore\n";
        $sql = file_get_contents(__DIR__.'/medict.sql');
        self::$pdo->exec($sql);
        echo "Préparation des requêtes d’insertion\n";
        self::prepare();
        // Charger une table de correspondance pour la translittératoin bétacode
        self::$grc_lat = include(__DIR__ . '/grc_lat.php');
    }

    /**
     * Prépare les requêtes d’insertion
     */
    static function prepare()
    {
        // Inutile de vérifier les clés ici.
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        foreach (array(
            C::DICO_TERME,
            C::DICO_REL,
            C::DICO_ENTREE,
            C::DICO_VOLUME,
        ) as $table) {
            $sql = "INSERT INTO $table 
    (" . str_replace(':', '', implode(', ', array_keys(self::$$table))) . ") 
    VALUES (" . implode(', ', array_keys(self::$$table)) . ");";
            // echo $sql, "\n";
            self::$q[$table] = self::$pdo->prepare($sql);
        }
        // requête avec langue ou sans langue
        $sql = "SELECT id FROM dico_terme WHERE deforme = ? AND langue = ?";
        self::$q['forme_langue_id'] = self::$pdo->prepare($sql);
        $sql = "SELECT id FROM dico_terme WHERE deforme = ?";
        self::$q['forme_id'] = self::$pdo->prepare($sql);
    }

    /**
     * Efface le contenu des tables pour tout recharger à propre.
     */
    public static function truncate()
    {
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        foreach (array(
            C::DICO_TERME,
            C::DICO_REL,
            C::DICO_ENTREE,
        ) as $table) {
            self::$pdo->query("TRUNCATE TABLE $table");
        }
    }

    /**
     * Recharger la table dico_titre
     */
    static public function dico_titre($titre_file)
    {
        echo "dico_titre, insertion de la table des titres\n";
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        self::$pdo->exec("TRUNCATE dico_titre");
        self::insert_table($titre_file, 'dico_titre');
    }

    /**
     * Charger les information de volumes depuis dico_volume.tsv
     */
    static public function dico_volume($volume_file)
    {
        echo "dico_volume, insertion de la table des volumes\n";
        // charger le fichier de volumes dans un index, clé = cote livre
        // first line, colums names
        $handle = fopen($volume_file, 'r');
        $sep = "\t";
        $volume_index = [];
        // première ligne, noms de colonnes
        $cols = fgetcsv($handle, null, $sep);
        $cols = array_flip($cols);

        while (($data = fgetcsv($handle, null, $sep)) !== FALSE) {
            $cell1 = trim($data[0]);
            if (count($data) == 0) continue;
            if (count($data) == 1 && !$cell1) continue;
            if ($cell1 && $cell1[0] == '#') continue;
            $titre_cote = $data[0];
            // hack pour ne pas perdre les 0 initiaux
            if ($titre_cote[0] === "_") $titre_cote = substr($titre_cote, 1);
            $volume_index[strval($titre_cote)][] = $data;
        }
        


        self::$pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        self::$pdo->exec("TRUNCATE dico_volume");
        // Boucler sur les titres
        $sql =  "SELECT * FROM dico_titre "; // ORDER BY annee
        $qdico_titre = self::$pdo->prepare($sql);
        $qdico_titre->execute(array());
        while ($dico_titre = $qdico_titre->fetch()) {
            self::$dico_volume[C::_DICO_TITRE] = $dico_titre['id'];
            self::$dico_volume[C::_TITRE_NOM] = $dico_titre['nom'];
            self::$dico_volume[C::_TITRE_ANNEE] = $dico_titre['annee'];
            // par défaut, info titre
            self::$dico_volume[C::_VOLUME_COTE] = $dico_titre['cote'];
            self::$dico_volume[C::_VOLUME_ANNEE] = $dico_titre['annee'];
            self::$dico_volume[C::_VOLUME_SOUSTITRE] = null;
            self::$dico_volume[C::_LIVANC] = $dico_titre['livanc'];

            $titre_cote = $dico_titre['cote'];

            // Titre sans info de volume (ou une seule), envoyer défaut
            if (!isset($volume_index[$titre_cote]) || count($volume_index[$titre_cote]) < 2) {
                try {
                    self::$q[C::DICO_VOLUME]->execute(self::$dico_volume);
                }
                catch(Exception $e) {
                    fwrite(STDERR, $e->__toString());
                    fwrite(STDERR, print_r(self::$dico_volume, true));
                    exit();
                }
                continue;
            }
            // ou bien charger des infos de volume
            else {
                foreach ($volume_index[$titre_cote] as $data) {
                    self::$dico_volume[C::_VOLUME_COTE] = $data[$cols['volume_cote']];
                    self::$dico_volume[C::_VOLUME_ANNEE] = $data[$cols['volume_annee']];
                    self::$dico_volume[C::_VOLUME_SOUSTITRE] = $data[$cols['volume_soustitre']];
                    self::$dico_volume[C::_LIVANC] = $data[$cols['livanc']];
                    try {
                        self::$q[C::DICO_VOLUME]->execute(self::$dico_volume);
                    }
                    catch(Exception $e) {
                        fwrite(STDERR, $e->__toString());
                        fwrite(STDERR, print_r(self::$dico_volume, true));
                        exit();
                    }
                }
                continue;
            }
        }
    }

    /**
     * Insère tous les mots de tous les dictionnaires
     */
    static function dico_terme()
    {

    }


    /**
     * Obtenir un identifiant de volume, le créer si nécessaire
     */
    static function volume_id($volume_cote)
    {

        $sql = "SELECT * FROM dico_volume WHERE volume_cote = ?";
        $q = self::$pdo->prepare($sql);
        $q->execute(array($volume_cote));
        $records = $q->fetchAll();
        // si pas de volume trouvé, titre avec 1 volume ?
        if (!$records || !count($records)) {
            self::$titre = self::get_titre($volume_cote);
            if (!self::$titre) {
                throw new Exception("Pas de titre trouvé pour le volume : ".$volume_cote);
            }
            self::$dico_volume[C::_DICO_TITRE] = self::$titre['id'];
            self::$dico_volume[C::_TITRE_NOM] = self::$titre['nom'];
            self::$dico_volume[C::_TITRE_ANNEE] = self::$titre['annee'];
            self::$dico_volume[C::_LIVANC] = null;
            self::$dico_volume[C::_VOLUME_COTE] = self::$titre['cote'];
            self::$dico_volume[C::_VOLUME_SOUSTITRE] = null;
            self::$dico_volume[C::_VOLUME_ANNEE] = self::$titre['annee']; 
            try {
                self::$q[C::DICO_VOLUME]->execute(self::$dico_volume);
            }
            catch(Exception $e) {
                fwrite(STDERR, $e->__toString());
                fwrite(STDERR, print_r(self::$dico_volume, true));
                exit();
            }
            $volume_id = self::$pdo->lastInsertId();
        }
        else if (count($records) > 1) {
            throw new Exception("Erreur dans les données, essayer MedictInsert::truncate(). Plus de 1 volume pour la cote : ".$volume_cote);
        }
        else {
            $volume = $records[0];
            $sql = "SELECT * FROM dico_titre WHERE id = ?";
            $q = self::$pdo->prepare($sql);
            $q->execute(array($volume['dico_titre']));
            self::$titre = $q->fetch();
            if (!self::$titre) {
                throw new Exception("Erreur dans les données, essayer MedictInsert::truncate(). Rien dans la table dico_titre pour la cote de volume : ".$volume_cote);
            }
            $volume_id = $volume['id'];
        }
        return $volume_id;
    }

    /**
     * Rend l’identifiant d’un terme dans la table dico_terme, 
     * crée la ligne si nécessaire
     */
    public static function terme_id($forme, $langue_iso)
    {
        // Forme nulle ? 
        if (!$forme) {
            fwrite(STDERR, 'Erreur ? Terme vide p. ' . self::$dico_rel[C::_PAGE]."\n");
            return null;
        }
        $deforme = self::deforme($forme);
        // passer la langue en nombre
        $langue_no = self::$langs[$langue_iso] ?? NULL;
        // tester cache mémoire
        $key = $langue_no . '_' . $deforme;
        if (isset(self::$terme_id[$key])) {
            return self::$terme_id[$key];
        }

        if ($langue_no) {
            self::$q['forme_langue_id']->execute(array($deforme, $langue_no));
            $row = self::$q['forme_langue_id']->fetch(PDO::FETCH_NUM);
            // if ($row) echo "$forme\t$langue\t$deforme\t$row[0]\n";
        } else {
            self::$q['forme_id']->execute(array($deforme));
            $row = self::$q['forme_id']->fetch(PDO::FETCH_NUM);
        }
        if ($row) { // le terme existe, retourner son identifiant
            // si alimentation incrémentale, mot en base mais pas en mémoire
            $terme_id = intval($row[0]);
            self::$terme_id[$key] = $terme_id;
            return $terme_id;
        }
        // normaliser l’accentuation (surtout pour le grec)
        $forme = Normalizer::normalize($forme, Normalizer::FORM_KC);
        // Assurer une majuscule initiale
        $forme = mb_convert_case(mb_substr($forme, 0, 1), MB_CASE_UPPER ) . mb_substr($forme, 1);
        self::$dico_terme[C::_FORME] = $forme;
        self::$dico_terme[C::_LANGUE] = $langue_no;
        self::$dico_terme[C::_DEFORME] = $deforme;
        self::$dico_terme[C::_TAILLE] = mb_strlen($deforme);
        // compter les mots non vides
        $wc = 0;
        // compter les mots $deforme, sinon strpos($deforme, " ") = -1
        $words = preg_split('@[^\p{L}]+@ui', $deforme);
        foreach ($words as $w) {
            if (isset(self::$stop[$w])) continue;
            $wc++;
        }
        self::$dico_terme[C::_MOTS] = $wc;
        $pos = strpos($deforme, " ");
        if ($pos !== false) {
            self::$dico_terme[C::_DELOC] = substr($deforme, $pos + 1);
        }
        else {
            self::$dico_terme[C::_DELOC] = null;
        }
        /*
        if ('grc' == $langue) { // betacode
            self::$dico_terme[C::_BETACODE] = strtr($deforme, self::$grc_lat);
        }
        else {
            self::$dico_terme[C::_BETACODE] = null;
        }
        */
        self::$dico_terme[C::_UVJI] = null;
        if (in_array(self::$titre['cote'], ['08746', '08757', '00152'])) {
            $uvji = strtr($deforme, ['j' => 'i', 'u' => 'v']);
            if ($uvji != $deforme) {
                self::$dico_terme[C::_UVJI] = $uvji;
                // echo "UVJI : $forme -> $uvji\n";
            }
        }
        try {
            self::$q[C::DICO_TERME]->execute(self::$dico_terme);
        }
        catch (Exception $e) {
            fwrite(STDERR, $e->__toString());
            fwrite(STDERR, implode("\t", self::$dico_entree));
            fwrite(STDERR, print_r(self::$dico_terme, true));
        }
        $terme_id = intval(self::$pdo->lastInsertId());
        self::$terme_id[$key] = $terme_id;
        return $terme_id;
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

    public static function insert_all()
    {
        $sql = "SELECT * FROM medict.dico_titre ORDER BY -import_ordre DESC, annee DESC;";
        $q = self::$pdo->prepare($sql);
        $q->execute([]);
        while ($row = $q->fetch()) {
            self::insert_titre($row['cote']);
        }
    }


    private static function get_titre($titre_cote)
    {
        $sql = "SELECT * FROM dico_titre WHERE cote = ?";
        $q = self::$pdo->prepare($sql);
        $q->execute(array($titre_cote));
        self::$titre = $q->fetch();
        return self::$titre;
    }

    public static function insert_titre($titre_cote)
    {
        $time_start = microtime(true);
        if (!self::get_titre($titre_cote)) {
            throw new Exception("Pas de titre trouvé pour la cote : ".$titre_cote);
        }
        echo "[insert_titre] ".$titre_cote . " (" . self::$titre['annee'] . ") " . self::$titre['nom'] . "\n";
        self::delete_titre($titre_cote);
        $dico_titre = self::$titre['id'];
        $sql = "SELECT volume_cote FROM dico_volume WHERE dico_titre = ?";
        $q = self::$pdo->prepare($sql);
        $q->execute(array($dico_titre));
        $done = false;
        while ($row = $q->fetch()) {
            $events_file = self::events_file($row['volume_cote']);
            self::insert_volume($events_file);
            $done = true;
        }
        // Pas de volumes connus de la pase anc, envoyer la cote comme volume
        if (!$done) {
            $events_file = self::events_file($titre_cote);
            self::insert_volume($events_file);
        }
    }

    public static function delete_titre($titre_cote)
    {
        if (!self::get_titre($titre_cote)) {
            throw new Exception("Pas de titre trouvé pour la cote : ".$titre_cote);
        }
        $dico_titre = self::$titre['id'];
        // effacer ici des données
        $q = self::$pdo->prepare(
            "DELETE FROM dico_rel WHERE dico_titre = ?"
        );
        $q->execute([$dico_titre]);
        $q = self::$pdo->prepare(
            "DELETE FROM dico_entree WHERE dico_titre = ?"
        );
        $q->execute([$dico_titre]);
        
    }



    /**
     * Insérer une vedette.
     * La page de cette relation est celle de l’entrée courante
     */
    private static function insert_orth($terme_id)
    {
        self::$dico_rel[C::_RELTYPE] = C::RELTYPE_ORTH;
        self::$dico_rel[C::_PAGE] = self::$dico_entree[C::_PAGE];
        self::$dico_rel[C::_REFIMG] = self::$dico_entree[C::_REFIMG];
        self::$dico_rel[C::_CLIQUE] = 0; // pas d’info de clique
        self::$dico_rel[C::_DICO_TERME] = $terme_id;
        self::$dico_rel[C::_ORTH] = true;
        try {
            self::$q[C::DICO_REL]->execute(self::$dico_rel);
        } catch (Exception $e) {
            fwrite(STDERR, "\nl. " . self::$line . "\n");
            fwrite(STDERR, $e->__toString());
            fwrite(STDERR, print_r(self::$dico_rel, true));
        }
    }

    /**
     * Insérer une relation mots associés liée par l’article
     * Ne sert qu’aux traductions
     */
    private static function insert_rel($reltype, $page, $refimg, $dico_terme, $orth=null)
    {
        self::$dico_rel[C::_CLIQUE] = 0; // pas d’info de clique
        self::$dico_rel[C::_RELTYPE] = $reltype;
        self::$dico_rel[C::_PAGE] = $page;
        self::$dico_rel[C::_REFIMG] = $refimg;
        self::$dico_rel[C::_ORTH] = $orth;
        self::$dico_rel[C::_DICO_TERME] = $dico_terme;
        self::$q[C::DICO_REL]->execute(self::$dico_rel);
    }

    /**
     * Obtenir un nouvel identifiant de clique
     */
    private static function clique()
    {
        if (self::$clique === null) {
            $sql = "SELECT MAX(clique) FROM dico_rel";
            self::$clique = self::$pdo->query($sql)->fetchColumn();
        }
        self::$clique++;
        return self::$clique;
    }

    /**
     * Insérer une clique.
     * $forme_liste = "Vedette | locution | renvoi";
     */
    private static function insert_clique($page, $refimg, $orth_ids, $forme_liste, $langue)
    {
        // collecter les termmes à lier
        $terme_ids = [];
        foreach(preg_split("/ *\| */", trim($forme_liste)) as $forme) {
            $terme_id = self::terme_id($forme, $langue);
            if (isset($orth_ids[$terme_id])) continue;
            $terme_ids[] = $terme_id;
        }
        $terme_ids = array_unique($terme_ids);
        // vérifier l’entrée
        if (!count($terme_ids)) {
            // pb de balisage, locution mal attrapée
            // fwrite(STDERR, "Clique, pas de mot trouvé dans : “$forme_liste\” p. $page\n");
            return null;
        }
        if (!$orth_ids || !count($orth_ids)) {
            fwrite(STDERR, "Clique, pas de vedette à relier ? p. $page, $forme_liste \n");
            return null;
        }
        self::$dico_rel[C::_RELTYPE] = C::RELTYPE_CLIQUE;
        self::$dico_rel[C::_PAGE] = $page;
        self::$dico_rel[C::_REFIMG] = $refimg;
        // si plus d’une vedette, ne pas créer le lien entre vedette ici,
        // (doublons, fausse page) multiplier les cliques
        foreach($orth_ids as $dico_terme => $forme) {
            // id clique
            self::$dico_rel[C::_CLIQUE] = self::clique();
            self::$dico_rel[C::_ORTH] = true;
            self::$dico_rel[C::_DICO_TERME] = $dico_terme;
            self::$q[C::DICO_REL]->execute(self::$dico_rel);
            // 
        }
        foreach($terme_ids as $dico_terme) {
            self::$dico_rel[C::_ORTH] = null;
            self::$dico_rel[C::_DICO_TERME] = $dico_terme;
            self::$q[C::DICO_REL]->execute(self::$dico_rel);
        }
    }

    /**
     * Insérer le fichier TSV d’un volume. 
     * Attention, il faut avoir nettoyé les tables avant,
     * ou l’on produit des doublons.
     */

    public static function insert_volume($events_file)
    {
        $time_start = microtime(true);

        if (!file_exists($events_file)) {
            fwrite(STDERR, "[insert_volume] Fichier introuvable : ".$events_file . "\n");
            return;
        }
        // RAZ
        foreach (array(
            C::DICO_TERME,
            C::DICO_REL,
            C::DICO_ENTREE,
            C::DICO_VOLUME,
        ) as $table) {
            array_walk(self::$$table, function (&$value, $key) {
                $value = NULL;
            });
        }


        // le nom de fichier doit être une cote de volume
        $events_name = pathinfo($events_file, PATHINFO_FILENAME);
        $volume_cote = $events_name;
        $sql = "SELECT * FROM dico_volume WHERE volume_cote = ?";
        $q = self::$pdo->prepare($sql);
        $q->execute(array($volume_cote));
        $records = $q->fetchAll();

        // volume inconnu de l’ancienne base, créer (nécessaire dans l’appli)
        if (!$records || !count($records)) {
            if (!self::get_titre($volume_cote)) {
                throw new Exception("Pas de titre trouvé pour la cote : ".$volume_cote);
            }
            $dico_titre = self::$titre['id'];
            // 1 seul volume, prendre l’année du titre
            $volume_annee = self::$titre['annee'];
            // self::$dico_entree[C::_DICO_VOLUME] = null;
            self::$dico_volume[C::_DICO_TITRE] = self::$titre['id'];
            self::$dico_volume[C::_TITRE_NOM] = self::$titre['nom'];
            self::$dico_volume[C::_TITRE_ANNEE] = self::$titre['annee'];
            self::$dico_volume[C::_LIVANC] = null;
            self::$dico_volume[C::_VOLUME_COTE] = self::$titre['cote'];
            self::$dico_volume[C::_VOLUME_SOUSTITRE] = null;
            self::$dico_volume[C::_VOLUME_ANNEE] = self::$titre['annee']; 
            try {
                self::$q[C::DICO_VOLUME]->execute(self::$dico_volume);
            }
            catch(Exception $e) {
                fwrite(STDERR, $e->__toString());
                fwrite(STDERR, print_r(self::$dico_volume, true));
                exit();
            }
            self::$dico_entree[C::_DICO_VOLUME] = self::$pdo->lastInsertId();
        }
        else if (count($records) > 1) {
            throw new Exception("Erreur dans les données, essayer MedictInsert::truncate(). Plus de 1 volume pour la cote : ".$volume_cote);
        }
        else {
            $volume = $records[0];
            $sql = "SELECT * FROM dico_titre WHERE id = ?";
            $q = self::$pdo->prepare($sql);
            $q->execute(array($volume['dico_titre']));
            self::$titre = $q->fetch();
            if (!self::$titre) {
                throw new Exception("Erreur dans les données, essayer MedictInsert::truncate(). Rien dans la table dico_titre pour la cote de volume : ".$volume_cote);
            }
            $dico_titre = $volume['dico_titre'];
            $volume_annee = $volume['volume_annee'];
            self::$dico_entree[C::_DICO_VOLUME] = $volume['id'];
        }

        // Des valeurs à fixer
        self::$dico_entree[C::_DICO_TITRE] = $dico_titre;
        self::$dico_entree[C::_VOLUME_ANNEE] = $volume_annee;
        self::$dico_rel[C::_DICO_TITRE] = $dico_titre;
        self::$dico_rel[C::_VOLUME_ANNEE] = $volume_annee;



        $orth_langue = self::$titre['orth_langue'];
        // forcer la langue par défaut ?
        // if (!$orth_langue) $orth_langue = 'fra';
        // valeurs par défaut
        echo "[insert_volume] ".$volume_cote.'… ';
        // Charger la totalité du fichier dans un tableau 
        // pour calculer la taille des entrées
        $handle = fopen($events_file, 'r');
        // tableau des orth rencontrées dans une entrées
        $orths = ['pour les fausse pages'];
        // tableau des traductions renconrées dans une entrée (évite les doublons)
        $foreigns = [];
        // page courante
        $page = null;
        $refimg = null;
        $livancpages = null;
        self::$line = 0;
        while (true) {
            $row = fgetcsv($handle, null, "\t");
            self::$line++;
            // ce qu’il faut faire avant une nouvelle entrée ou en fin de fichier
            if ($row===FALSE || $row[0] == C::ENTRY) {
                // si plusieurs orth, les envoyer en clique à la page du début d’entrée
                if(count($orths) >= 2) {
                    self::$dico_rel[C::_RELTYPE] = C::RELTYPE_CLIQUE;
                    self::$dico_rel[C::_PAGE] = self::$dico_entree[C::_PAGE];
                    self::$dico_rel[C::_REFIMG] = self::$dico_entree[C::_REFIMG];
                    self::$dico_rel[C::_CLIQUE] = self::clique();
                    self::$dico_rel[C::_ORTH] = true;
                    foreach ($orths as $dico_terme => $forme) {
                        self::$dico_rel[C::_DICO_TERME] = $dico_terme;
                        self::$q[C::DICO_REL]->execute(self::$dico_rel);
                    }
                }
                // si pas d’événement orth depuis la dernière entrée
                // envoyer la vedette
                if (!count($orths) && self::$dico_entree[C::_VEDETTE]) {
                    $terme_id = self::terme_id(self::$dico_entree[C::_VEDETTE], $orth_langue);
                    self::insert_orth($terme_id);
                }
            }
            // ne pas oublier de sortir si fin de fichier
            if ($row===FALSE) break;


            // ceci n’est pas une chaîne elsif mais une suite de if
            // un if finit généralement par un continue

            // événements connus sans effet ici
            if ($row[0] == 'object' || $row[0] == 'volume') {
                continue;
            }
            // saut de page, rien à rentrer, mais des variables à fixer
            if ($row[0] == C::PB) {
                // garder la mémoire de la page courante
                $page = ltrim(trim($row[1], ' '), '0');
                // si pas de refimg, on part silencieusement, ou on crie ?
                $refimg = str_pad($row[2], 4, '0', STR_PAD_LEFT);
                if(isset($row[3]) && $row[3]) $livancpages = $row[3];
                else $livancpages = null;
                // ici un pb, le dire
                if (!preg_match('/^\d\d\d\d$/', $refimg)) {
                    fwrite(STDERR, "$volume_cote\tp. $page\trefimg ???\t$refimg\n");
                    $refimg = null;
                }
                continue;
            }
            // À partir d’ici, une forme peut être utile
            $forme = preg_replace(
                // supprimer quelques caractères avant et après
                // laisser ) ]
                array('/^[\s]+/ui', '/[\s\.,;]+$/ui'),
                array('',            ''),
                $row[1], 
            ); // nettoyer les humains

            // Ouvrir une entrée
            if ($row[0] == C::ENTRY) {
                // RAZ des états 
                $orths = [];
                $foreigns = [];

                // titre forgé comme [Page de faux-titre]
                if (preg_match('/^\[[^]]*\]$/', $forme)) $forme = NULL;
                self::$dico_entree[C::_VEDETTE] = $forme;
                if (!$forme) continue; // vedette vide, ne pas créer d’entrée

                // on peut écrire une entrée maintenant
                self::$dico_entree[C::_PAGE] = $page;
                self::$dico_entree[C::_REFIMG] = $refimg;
                self::$dico_entree[C::_LIVANCPAGES] = $livancpages;
                self::$dico_entree[C::_PPS] = 0;
                self::$dico_entree[C::_PAGE2] = null;
                if (isset($row[2]) && $row[2] > 0) {
                    self::$dico_entree[C::_PPS] = $row[2];
                    if (is_numeric($page)) self::$dico_entree[C::_PAGE2] = $page + $row[2];
                }
                try {
                    self::$q[C::DICO_ENTREE]->execute(self::$dico_entree);
                } catch (Exception $e) {
                    fwrite(STDERR, $e->__toString());
                    fwrite(STDERR, print_r(self::$dico_entree, true));
                }
                // id entrée comme clé de regroupement des relations
                self::$dico_rel[C::_DICO_ENTREE] = self::$pdo->lastInsertId();
                // echo  "dico entre->".self::$dico_rel[C::_DICO_ENTREE]."\n";
                continue;
            }
            
            // Si rien ici, pê faut qu’on sort
            if (!$forme) continue;
            // langue locale ?
            $forme_langue = null;
            if(isset($row[2])) $forme_langue = $row[2];
            // défaut, langue des vedettes
            if (!$forme_langue) $forme_langue = $orth_langue; 
            $forme_id = self::terme_id($forme, $forme_langue);


            // vedette
            if ($row[0] == C::ORTH) {
                self::insert_orth($forme_id); // ajouter la relation vedette
                // enregistrer la vedette, peut servir pour les renvois et les traductions, le no de page sera celle de l’entrée
                $orths[$forme_id] = $forme;
                continue;
            }
            // si pas vu de <orth> jusqu’ici, prendre la vedette
            if (
                $row[0] == C::TERM 
             || $row[0] == C::FOREIGN 
             || $row[0] == C::CLIQUE
             || $row[0] == C::REF
            ) {
                // pas encore vu de orth ici ? pas bien
                // on prend la vedette qui va prendre une clique
                if (count($orths) < 1) {
                    $terme_id = self::terme_id(
                        self::$dico_entree[C::_VEDETTE], 
                        $orth_langue
                    );
                    // ne pas oublier d’enregistrer l’orth ici
                    self::insert_orth($terme_id);
                    $orths[$terme_id] = self::$dico_entree[C::_VEDETTE];
                }
            }
            // locution
            if ($row[0] == C::TERM) {
                // pb de balisage des locutions, ex: emploi substantif d’un adjectif
                if (isset($orths[$forme_id])) continue;
                // si déjà vu ne pas renvoyer ? Ou on laisse doublonner ?

                // si on veut dans la nomenclature
                // insert_rel($reltype, $page, $refimg, $dico_terme, $orth=null)
                self::insert_rel(C::RELTYPE_TERM, $page, $refimg, $forme_id);
                // Peupler des renvois avec les membres de la locution ?
                /*
                $words[] = $forme;
                $words = preg_split('@[^\p{L}]+@ui', $forme);
                foreach ($words as $w) {
                    if (isset(self::$stop[$w])) continue;
                    $id = self::terme_id($w, $forme_langue);
                    if (isset($ref[$id])) continue;
                    $ref[$id] = $w;
                    // s’il faut, un autre type de relation peut être nécessaire ici
                    // il faudrait aussi faire passer les renvois avant les mots de locution
                    self::insert_rel($id, C::TYPE_REF, $page, $refimg);
                }
                */
                continue;
            }
            // traduction
            if ($row[0] == C::FOREIGN) {
                // 1e traduction, envoyer les vedettes pour la clique de trad
                if (!count($foreigns)) {
                    foreach ($orths as $dico_terme => $forme) {
                        // insert_rel($reltype, $page, $refimg, $dico_terme, $orth=null)
                        self::insert_rel(
                            C::RELTYPE_TRANSLATE,
                            // page de l’entrée
                            self::$dico_entree[C::_PAGE],
                            self::$dico_entree[C::_REFIMG],
                            $dico_terme,
                            true,
                        );
                    }
                }
                // si traduction déjà vue ? on part
                if (isset($foreigns[$forme_id])) {
                    continue;
                }
                $foreigns[$forme_id] = $forme;
                // mot cherchable
                self::insert_rel(C::RELTYPE_FOREIGN, $page, $refimg, $forme_id);
                // clique de traduction
                self::insert_rel(C::RELTYPE_TRANSLATE, $page, $refimg, $forme_id);
                continue;
            }
            // ligne de clique à lier avec les vedettes
            if ($row[0] == C::CLIQUE || $row[0] == C::REF) {
                self::insert_clique($page, $refimg, $orths, $row[1], $orth_langue);
                continue;
            }

            // Défaut, rien
            fwrite(STDERR, implode("\t", self::$dico_entree) . "\n");
            fwrite(STDERR, "Commande inconnue :\t" . implode("\t", $row) . "\n");
        }

        echo "…". number_format(microtime(true) - $time_start, 3) . " s.\n";
        return;
    }

    /**
     * Dump des données importable dans phpMyAdmin
     */
    public static function zip($dst_dir)
    {
        $pars = self::pars();
        // 
        if (!isset($pars['mysqldump'])) {
            throw new Exception("Binaire mysqldump non trouvé dans le fichier de paramétrage ./_pars.php.");
        }
        // mysqldump ne crée pas lui-même un dossier
        if (!file_exists($dst_dir)) mkdir($dst_dir, 0777, true);
        $mysqldump = $pars['mysqldump'];
        $tables = ['dico_entree', 'dico_rel', 'dico_terme', 'dico_titre', 'dico_volume'];
        $base = 'medict';
        foreach ($tables as $table) {
            $sql_file = $dst_dir . 'medict_' . $table . '.sql';
            $cmd = "$mysqldump --user={$pars['user']} --password={$pars['password']} --host={$pars['host']} {$pars['dbname']}  $table --result-file=$sql_file";
            exec($cmd);
            [ 'filename' => $sql_name, 'basename' => $sql_fname ] = pathinfo($sql_file);
            $zip_file = $dst_dir . $sql_name . '.zip';
            echo $zip_file . ' <- ' . $sql_fname . "\n";
            if (file_exists($zip_file)) unlink($zip_file);
            $zip = new ZipArchive();
            $zip->open($zip_file, ZipArchive::CREATE);
            $zip->addFile($sql_file, $sql_fname);
            $zip->close();
        }
    }

}

Insert::init();

