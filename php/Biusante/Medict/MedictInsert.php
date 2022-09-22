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
 * Classe pour charger les tsv préparés avec 
 */
class MedictInsert extends MedictUtil
{
    /** Propriétés du titre en cours de traitement */
    static $titre = null;
    /** Table de données en cours de traitement */
    static $data = null;
    /** Dossier des fichiers tsv */
    static $tsv_dir;
    /** Insérer un terme */
    static $dico_terme = array(
        C::_FORME => null,
        C::_LANGUE => -1,
        C::_DEFORME => null,
        C::_DELOC => null,
        C::_TAILLE => -1,
        C::_MOTS => -1,
        C::_BETACODE => null,
    );
    /** Insérer une relation */
    static $dico_rel = array(
        C::_DICO_TITRE => -1,
        C::_VOLUME_ANNEE => -1,
        C::_DICO_ENTREE => -1,
        C::_PAGE => null,
        C::_REFIMG => -1,
        C::_DICO_TERME => -1,
        C::_RELTYPE => -1,
        C::_ORTH => null,
        C::_CLIQUE => -1,
    );
    
    /** Insérer une entrée (champs en ordre de stabilité) */
    static $dico_entree = array(
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
    static $dico_volume = array(
        C::_DICO_TITRE => -1,
        C::_TITRE_NOM => null,
        C::_TITRE_ANNEE => null,
        C::_LIVANC => -1,
        C::_VOLUME_COTE => -1,
        C::_VOLUME_SOUSTITRE => -1,
        C::_VOLUME_ANNEE => -1,
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

            self::$dico_volume[C::_DICO_TITRE] = $dico_titre['id'];
            self::$dico_volume[C::_TITRE_NOM] = $dico_titre['nom'];
            self::$dico_volume[C::_TITRE_ANNEE] = $dico_titre['annee'];
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
                self::$dico_volume[C::_VOLUME_COTE] = $volume['cote'];
                $soustitre = null;
                if ($dico_titre['vol_re']) {
                    $titre = trim(preg_replace('@[\s]+@u', ' ', $volume['titre']));
                    preg_match('@'.$dico_titre['vol_re'].'@', $titre, $matches);
                    if (isset($matches[1]) && $matches[1]) {
                        $soustitre = trim($matches[1], ". \n\r\t\v\x00");
                    }
                }
                self::$dico_volume[C::_VOLUME_SOUSTITRE] = $soustitre;
                // livanc.annee : "An VII", livanc.annee_iso : "1798/1799"
                self::$dico_volume[C::_VOLUME_ANNEE] = substr($volume['annee_iso'], 0, 4); 
                self::$dico_volume[C::_LIVANC] = $volume['clenum'];
                try {
                    self::$q[C::DICO_VOLUME]->execute(self::$dico_volume);
                }
                catch(Exception $e) {
                    fwrite(STDERR, $e->__toString());
                    fwrite(STDERR, print_r(self::$dico_volume, true));
                    exit();
                }
                /*
                $id = self::$pdo->lastInsertId();
                self::$dico_entree[C::_DICO_VOLUME] = $id;
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
     * Rend l’identifiant d’un terme dans la table dico_terme, 
     * crée la ligne si nécessaire
     */
    public static function terme_id($forme, $langue)
    {
        // Forme nulle ? 
        if (!$forme) {
            fwrite(STDERR, 'Erreur ? Terme vide p. ' . self::$dico_rel[C::_PAGE]."\n");
            return null;
        }
        $deforme = self::deforme($forme, $langue);
        // passer la langue en nombre
        $langue = self::$langs[$langue] ?? NULL;
        if ($langue) {
            self::$q['forme_langue_id']->execute(array($deforme, $langue));
            $row = self::$q['forme_langue_id']->fetch(PDO::FETCH_NUM);
            // if ($row) echo "$forme\t$langue\t$deforme\t$row[0]\n";
        } else {
            self::$q['forme_id']->execute(array($deforme));
            $row = self::$q['forme_id']->fetch(PDO::FETCH_NUM);
        }
        if ($row) { // le terme existe, retourner son identifiant
            return $row[0];
        }
        // normaliser l’accentuation (surtout pour le grec)
        $forme = Normalizer::normalize($forme, Normalizer::FORM_KC);
        // Assurer une majuscule initiale
        $forme = mb_convert_case(mb_substr($forme, 0, 1),MB_CASE_UPPER ) . mb_substr($forme, 1);
        self::$dico_terme[C::_FORME] = $forme;
        self::$dico_terme[C::_LANGUE] = $langue;
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
        if ($wc > 1) {
            self::$dico_terme[C::_DELOC] = substr($deforme, strpos($deforme, " ") + 1);
        }
        else {
            self::$dico_terme[C::_DELOC] = null;
        }
        if ('grc' == $langue) { // betacode
            self::$dico_terme[C::_BETACODE] = strtr($deforme, self::$grc_lat);
        }
        else {
            self::$dico_terme[C::_BETACODE] = null;
        }
        try {
            self::$q[C::DICO_TERME]->execute(self::$dico_terme);
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
        echo "[insert_titre] ".$titre_cote.' préparation… ';
        self::delete_titre($titre_cote);
        echo " suppressions: ". number_format(microtime(true) - $time_start, 3) . " s.\n";
        $dico_titre = self::$titre['id'];
        $sql = "SELECT volume_cote FROM dico_volume WHERE dico_titre = ?";
        $q = self::$pdo->prepare($sql);
        $q->execute(array($dico_titre));
        $done = false;
        while ($row = $q->fetch()) {
            $tsv_file = self::tsv_file($row['volume_cote']);
            self::insert_volume($tsv_file);
            $done = true;
        }
        // Pas de volumes connus de la pase anc, envoyer la cote comme volume
        if (!$done) {
            $tsv_file = self::tsv_file($titre_cote);
            self::insert_volume($tsv_file);
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
        self::$dico_rel[C::_DICO_TERME] = $terme_id;
        self::$dico_rel[C::_RELTYPE] = C::TYPE_ORTH;
        self::$dico_rel[C::_PAGE] = self::$dico_entree[C::_PAGE];
        self::$dico_rel[C::_REFIMG] = self::$dico_entree[C::_REFIMG];
        self::$q[C::DICO_REL]->execute(self::$dico_rel);
    }

    /**
     * Insérer une relation mots associés
     */
    private static function insert_rel($dico_terme, $reltype, $page, $refimg, $orth=null)
    {
        self::$dico_rel[C::_DICO_TERME] = $dico_terme;
        self::$dico_rel[C::_RELTYPE] = $reltype;
        self::$dico_rel[C::_PAGE] = $page;
        self::$dico_rel[C::_REFIMG] = $refimg;
        self::$dico_rel[C::_ORTH] = $orth;
        self::$q[C::DICO_REL]->execute(self::$dico_rel);
    }


    /**
     * Insérer le fichier TSV d’un volume. 
     * Attention, il faut avoir nettoyé les tables avant,
     * ou l’on produit des doublons.
     */

    public static function insert_volume($tsv_file)
    {
        $time_start = microtime(true);

        if (!file_exists($tsv_file)) {
            fwrite(STDERR, "[insert_volume] Fichier introuvable : ".$tsv_file . "\n");
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
        $tsv_name = pathinfo($tsv_file, PATHINFO_FILENAME);
        $volume_cote = $tsv_name;
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

        // Pas encore utilisé
        self::$dico_rel[C::_CLIQUE] = 0;


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
        // tableau des traductions renconrées dans une entrée (évite les doublons)
        $foreign = [];
        // page courante
        $page = null;
        $refimg = null;
        $livancpages = null;
        while (($row = fgetcsv($handle, null, "\t")) !== FALSE) {
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
                $refimg = str_pad($row[2], 4, '0', STR_PAD_LEFT);
                if($row[3]) $livancpages = $row[3];
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
                array('/^\P{L}+/ui', '/\P{L}$/ui'),
                array('',            ''),
                $row[1], 
            ); // nettoyer les humains

            // Entrée, tout fermer de la précédente, ouvrir l’actuelle
            if ($row[0] == C::ENTRY) {
                // pas vu de renvoi dans cette entrée, si plusieurs orth, les envoyer comme renvois
                if(!count($ref) && count($orth) >= 2) {
                    foreach ($orth as $id => $val) {
                        self::insert_rel(
                            $id,
                            C::TYPE_REF,
                            self::$dico_entree[C::_PAGE], // page de l’entrée
                            self::$dico_entree[C::_REFIMG],
                            true,
                        );
                    }
                }
                // si pas d’événement orth depuis la dernière entrée
                // envoyer la vedette
                if (!count($orth) && self::$dico_entree[C::_VEDETTE]) {
                    $terme_id = self::terme_id(self::$dico_entree[C::_VEDETTE], $orth_langue);
                    self::insert_orth($terme_id);
                }
                // RAZ des états 
                $ref = [];
                $orth = [];
                $foreign = [];

                // titre forgé comme [Page de faux-titre]
                if (preg_match('/^\[[^]]*\]$/', $forme)) $forme = NULL;
                self::$dico_entree[C::_VEDETTE] = $forme;
                if (!$forme) continue; // vedette vide, ne pas créer d’entrée

                // on peut écrire une entrée maintenant
                self::$dico_entree[C::_PAGE] = $page;
                self::$dico_entree[C::_REFIMG] = $refimg;
                self::$dico_entree[C::_LIVANCPAGES] = $livancpages;
                self::$dico_entree[C::_PPS] = $row[2];
                if ($row[2] > 0 && is_numeric($page)) {
                    self::$dico_entree[C::_PAGE2] = $page + $row[2];
                }
                else { // Ne pas oublier
                    self::$dico_entree[C::_PAGE2] = null;
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
            $forme_langue = $row[2];
            // défaut, langue des vedettes
            if (!$forme_langue) $forme_langue = $orth_langue; 
            $forme_id =self::terme_id($forme, $forme_langue);


            // vedette
            if ($row[0] == C::ORTH) {
                self::insert_orth($forme_id); // ajouter la relation vedette
                // enregistrer la vedette, peut servir pour les renvois et les traductions, le no de page sera celle de l’entrée
                $orth[$forme_id] = $forme;
                continue;
            }
            // en cas de locution,  renvoi ou traduction
            // sans doute des trucs à factoriser
            // ici, pas de continue en fin d’if, la suite attend
            if (
                $row[0] == C::TERM 
             || $row[0] == C::FOREIGN 
             || $row[0] == C::REF
            ) {
                // pas encore vu de orth ici ? pas bien
                // on prend la vedette 
                if (count($orth) < 1) {
                    $terme_id = self::terme_id(
                        self::$dico_entree[C::_VEDETTE], 
                        $orth_langue
                    );
                    $orth[$terme_id] = self::$dico_entree[C::_VEDETTE];
                }
            }
            // traduction
            if ($row[0] == C::FOREIGN) {
                // 1e traduction, envoyer les vedettes dans la clique
                if (!count($foreign)) {
                    foreach ($orth as $id => $val) {
                        self::insert_rel(
                            $id,
                            C::TYPE_FOREIGN,
                            // page de l’entrée
                            self::$dico_entree[C::_PAGE],
                            self::$dico_entree[C::_REFIMG],
                            true,
                        );
                    }
                }
                // si traduciton déjà vue ? on part
                if (isset($foreign[$forme_id])) continue;
                $foreign[$forme_id] = $forme;
                self::insert_rel($forme_id, C::TYPE_FOREIGN, $page, $refimg);
                continue;
            }
            // Locutions ou renvois, remplir la clique avec les orth
            if (($row[0] == C::REF || $row[0] == C::TERM) && !count($ref)) {
                foreach ($orth as $id => $val) {
                    try {
                        self::insert_rel(
                            $id,
                            C::TYPE_REF,
                            self::$dico_entree[C::_PAGE], // page de l’entrée
                            self::$dico_entree[C::_REFIMG],
                            true,
                        );
                    } catch (Exception $e) {
                        echo $e;
                        print_r(self::$dico_entree);
                        echo implode("\t", $row);
                    }
                }
            }
            // traiter un renvoi
            if ($row[0] == C::REF) {
                // si ref déjà vu on part
                if (isset($ref[$forme_id])) continue;
                $ref[$forme_id] = $forme;
                self::insert_rel($forme_id, C::TYPE_REF, $page, $refimg);
                continue;
            }
            // locution
            if ($row[0] == C::TERM) {
                // si on veut dans la nomenclature
                self::insert_rel($forme_id, C::TYPE_TERM, $page, $refimg);
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
            // locution, on va voir si on veut ici des renvois en plus

            // Défaut, rien
            fwrite(STDERR, implode("\t", self::$dico_entree) . "\n");
            fwrite(STDERR, "???\t" . implode("\t", $row) . "\n");
        }

        echo "…". number_format(microtime(true) - $time_start, 3) . " s.\n";
        return;
    }


}

MedictInsert::init();

