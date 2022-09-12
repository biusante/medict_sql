<?php
/**
 * Part of Medict https://github.com/biusante/medict-sql
 * Copyright (c) 2021 Université de Paris, BIU Santé
 * MIT License https://opensource.org/licenses/mit-license.php
 */

declare(strict_types=1);

namespace Biusante\Medict;

use DOMDocument, PDO, XSLTProcessor;

include_once(__DIR__.'/MedictUtil.php');


/**
 * Prépare des données à insérer dans la base de données Medict
 * à partir des tables Medica. Il faut donc avoir une connexion
 * en lecture seule aux tables Medica.
 * 1. 
 */

class MedictPrepa extends MedictUtil
{
    /** Propriétés du titre en cours de traitement */
    static $dico_titre = null;
    /** fichier tsv en cours d’écriture */
    static $ftsv;
    /** Dossier des fichiers tsv */
    static $tsv_dir;
    /** Des mots vides à filtrer pour la colonne d’index */
    static $stop;

    public static function init()
    {
        self::connect();
        ini_set('memory_limit', '-1'); // needed for this script
        mb_internal_encoding("UTF-8");
        // Charger les mots vides
        self::$stop = array_flip(explode("\n", file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'stop.csv')));
        /*
        self::$tsv_dir = dirname(__DIR__).'/import/';
        if (!file_exists(self::$tsv_dir)) mkdir(self::$tsv_dir, 0777, true);
        */
    }


    /**
     * 
     */
    public static function write_medica()
    {

    }


    public static function anc_write()
    {
        $separator = "\t";
        $titre_file = self::home() . 'dico_titre.tsv';
        $handle = fopen($titre_file, 'r');
        // first line, colums names
        $keys = fgetcsv($handle, null, $separator);
        while (($values = fgetcsv($handle, null, $separator)) !== FALSE) {
            $titre = array_combine($keys, $values);

            $titre_cote = ltrim($titre['cote'], ' _');
            $titre_vols = $titre['vols'];
            // boucler sur les volumes dans livanc
            $sql = "SELECT * FROM livanc WHERE ";
            if ($titre_vols < 2) {
                $sql .= " cote = ?";
            } else {
                $sql .= " cotemere = ? ORDER BY cote";
            }
            $volq = self::$pdo->prepare($sql);
            $volq->execute(array($titre_cote));
            while ($volume = $volq->fetch(PDO::FETCH_ASSOC)) {
                self::volume_write($volume['cote']);
                print($volume['cote']."\n");
            }
        }
        
    }

    /**
     * Écrire les données d’un ancien volume dans un fichier
     */
    private static function volume_write($volume_cote)
    {
        // sortir les données sources
        $file = self::home().'medica/medica_'.$volume_cote.'.tsv';
        // créer le dossier si nécessaire
        if (!file_exists(dirname($file))) mkdir(dirname($file), 0777, true);
        $fsrc = fopen($file, 'w');
        fwrite($fsrc, "page\trefimg\tnumauto\tchapitre\n");
        $pageq = self::$pdo->prepare("SELECT * FROM livancpages WHERE cote = ? ORDER BY cote, refimg");
        $pageq->execute(array($volume_cote));
        while ($page =  $pageq->fetch(PDO::FETCH_ASSOC)) {
            // écrire le fichier source
            fwrite(
                $fsrc, 
                "{$page['page']}\t{$page['refimg']}\t{$page['numauto']}\t{$page['chapitre']}\n"
            );
        }
    }

    /**
     * Alimenter la base de données des dictionnaires avec les données déjà indexées,
     * commencer par parcourir la table des titres.
     * Si $cote non null, permet de filtrer (pour déboguage)
     */
    public static function anc_tsv()
    {

        // boucler sur la table des titres pour attraper les lignes concernées
        // dans la table Medica des volumes
        // $pars = array();

        // supposons pour l’instant que l’ordre naturel est bon 
        $sql =  "SELECT * FROM dico_titre "; // ORDER BY annee
        $pars = [];
        // filtrer pour une seule cote ? NON TESTÉ ? manque DELETE
        /*
        if ($cote) { 
            $sql .= " WHERE cote LIKE ?";
            $pars[] = $cote;
        }
        */
        $qdico_titre = self::$pdo->prepare($sql);
        $qdico_titre->execute($pars);

        while (self::$dico_titre = $qdico_titre->fetch()) {
            echo "[SQL load] ". self::$dico_titre['cote']. ', ' . self::$dico_titre['nom'] . "\n";

            self::$dico_volume[':dico_titre'] = self::$dico_titre['id'];
            self::$dico_volume[':titre_nom'] = self::$dico_titre['nom'];
            self::$dico_volume[':titre_annee'] = self::$dico_titre['annee'];
            self::$dico_entree[':dico_titre'] = self::$dico_titre['id'];
            self::$dico_rel[':dico_titre'] = self::$dico_titre['id'];

            if (!self::$dico_titre['orth_langue']) self::$dico_titre['orth_langue'] = 'fra';

            // boucler sur les volumes dans livanc
            $sql = "SELECT * FROM livanc WHERE ";
            if (self::$dico_titre['vols'] < 2) {
                $sql .= " cote = ?";
            } else {
                $sql .= " cotemere = ? ORDER BY cote";
            }
            $volq = self::$pdo->prepare($sql);
            $volq->execute(array(self::$dico_titre['cote']));


            while ($volume = $volq->fetch(PDO::FETCH_ASSOC)) {

                // de quoi renseigner un enregistrement de volume
                self::$dico_volume[':volume_cote'] = $volume['cote'];
                $soustitre = null;
                if (self::$dico_titre['vol_re']) {
                    $titre = trim(preg_replace('@[\s]+@u', ' ', $volume['titre']));
                    preg_match('@'.self::$dico_titre['vol_re'].'@', $titre, $matches);
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
                    print($e);
                    print_r($volume);
                    print_r(self::$dico_volume);
                    exit();
                }
                $id = self::$pdo->lastInsertId();
                self::$dico_entree[':dico_volume'] = $id;

                // boucler sur les pages du volume
                self::livancpages(
                    self::$dico_volume[':volume_cote'], 
                    self::$dico_titre['sep']
                );
            }
        }
    }

    /**
     * Lit les informations page à page de livancpages.chapitre.
     * Écrit les données sources pour débogage.
     * Produit un premier tableau d’événements, à reparser,
     * pour regrouper les entrées sur plusieurs pages.
     */
    private static function livancpages($cote, $sep)
    {
        $sep = trim($sep);

        // Les données à produire
        $data = [];
        while(false) { 
        // Lecture des pages d’un volume, dans l’ordre naturel
            $refimg = str_pad($page['refimg'], 4, '0', STR_PAD_LEFT);

            // Événement page
            $p = $page['page'];
            if ($p == '[sans numérotation]' 
                || $p == '[page blanche]'
            ) {
                $p = '[s. pag.]';
            }
            $data[] = array(
                'pb',
                $p,
                $refimg,
                // "https://www.biusante.parisdescartes.fr/iiif/2/bibnum:" . $cote . ":" . $refimg . "/full/full/0/default.jpg",
                // "https://www.biusante.parisdescartes.fr/histmed/medica/page?" . $cote . '&p=' . $refimg,
                $page['numauto']
            );

            // traiter un chapitre
            $chapitre = $page['chapitre'];


            // restaurer de la hiérachie dans les Bouley
            // tout est traité ici
            if (self::starts_with(self::$dico_volume[':volume_cote'], '34823')) {
                // 438	0442	Vendéenne [A. Sanson] / Variété maraichine
                // 439	0443	Vendéenne [A. Sanson]. Variété maraichine
                $chapitre = preg_replace('@\] / @', ']. ', $chapitre);
                $chapitre = preg_replace('@\]$@', ']. ', $chapitre);
                $chapitre = preg_replace('/\] /', ']. ', $chapitre);
                $chapitre = preg_replace('/\](\p{L})/u', ']. $1', $chapitre);
                // 214	0218	Utérus [P. J. Cadiot]. Pathologie. Inflammation de l'utérus. Métrite. Métro-péritonite / Renversement de la matrice
                // 215	0219	Utérus [P. J. Cadiot]. Pathologie. Renversement de la matrice
                // 117	0121	Javart [Henri-Marie Bouley]. Du javart cartilagineux. Traitement du javart cartilagineux. Méthode par les caustiques potentiels / Méthode chirurgicale
                // 118	0122	Javart [Henri-Marie Bouley]. Du javart cartilagineux. Traitement du javart cartilagineux. Méthode chirurgicale

                $veds = preg_split('@ */ *@', $chapitre);
                /* si plus de 2 vedettes, ajouter un préfixe aux intermédiaire
                   mais laisse la dernière se renseigner avec la suivante */
                if (count($veds) > 2) {
                    $veds[0] = trim($veds[0], " \t.");
                    $pref = substr($veds[0], 0, strrpos($veds[0], '.'));
                    /*
                    $matches = [];
                    // print_r($matches);
                    preg_match('/^.*?\]\.[^\.]+/', $veds[0], $matches);
                    if (!isset($matches[0])) {
                        // echo $chapitre, "\n";
                    }
                    */
                    for ($i = 1; $i < count($veds) - 1; $i++) {
                        // nouvel article, ne rien faire
                        if (strpos($veds[$i], '[') !== false) break;
                        // restaurer article préfixe (?)
                        $veds[$i] = $pref . '. ' . $veds[$i];
                    }
                }
                foreach($veds as $v) {
                    // supprimer l’auteur
                    $v = preg_replace('/ *\[[^\]]+\]/u', '', $v);
                    if (!$v) continue;
                    $data[] = array("entry", $v);
                }
                continue;
            }

            // supprimer un gros préfixe
            // Classe première. Les campaniformes. Section III. Genre VII. Le gloux / Genre VIII. L'alleluia
            if (self::starts_with(self::$dico_volume[':volume_cote'], 'pharma_019129')) {
                $chapitre = preg_replace(
                    array('@^.*?Genre[^\.]*\. *@u', '@^.*?Supplémentaire\. *@ui', '@ */ *[^/]*?Genre[^\.]*\. *@u', '@[^\.]+classe\. *@ui'),
                    array('',                       '',                           ' / ',                           ''),
                    $chapitre
                );
            }
            // supprimer un gros préfixe
            // Petit traité de matière médicale, ou des substances médicamenteuses indiquées dans le cours de ce dictionnaire. Division des substances médicamenteuses par ordre alphabétique, et d'après leur manière d'agir sur le corps humain. Médicamens composés / 
            else if (self::starts_with(self::$dico_volume[':volume_cote'], '57503')) {
                $chapitre = preg_replace(
                    array('@^.*Médicamens composés\P{L}*@u', '@^.*?Règne végétal\. *@ui', '@^.*Médicamens simples\P{L}*@u', '@Vocabulaire des matières contenues.*?@u'),
                    array('',                                '',                                '',                               ''),
                    $chapitre
                );
            }
            // Absorbants [A. Gubler] (bibliographie) [Raige-Delorme] / Absorbants (vaisseaux). Voy. Lymphatiques / Absorption [Jules Béclard]
            else if (self::starts_with(self::$dico_volume[':volume_cote'], 'extbnfdechambre')) {
                $chapitre = preg_replace(
                    array('@ *\(bibliographie\)\.?@ui', '/ *\[[^\]]+\]/u'),
                    array('', ''),
                    $chapitre
                );
                // if ($echo) fwrite(STDERR, $chapitre."\n");
            }
            //  H. - Habrioux; Hardy François; Hauterive Jean-Baptiste; Hélitas Jean; Heur (d') François; Hospital Gaspard; Houpin René; Hugon Jean; Hugon Joseph; Hugonnaud Jean; Hugonneau Martial / I. - Itier Jacques
            else if (self::starts_with(self::$dico_volume[':volume_cote'], '24374')) {
                $chapitre = preg_replace(
                    array('@( */ *)?[A-Z]\.[ \-]+@u'),
                    array(';'),
                    $chapitre
                );
            }


            // Rien d’indexé dans la page
            if ($chapitre == null || $chapitre == '') {
                continue;
            }

            // Nettoyer des trucs ?

            // Spliter selon le séparateur de saisie
            if ($sep == '-') {
                $veds = preg_split('@ +- +@u', $chapitre);
            } else if ($sep == '.') {
                // protéger les '.' dans les parenthèses
                $chapitre = preg_replace_callback(
                    '@\([^\)]*\)@',
                    function ($matches) {
                        return preg_replace('@\.@', '£', $matches[0]);
                    },
                    $chapitre
                );
                $veds = preg_split('@\. +@u', $chapitre);
                $veds = preg_replace('@£@', '.', $veds);
            } else if ($sep == '/') {
                // Panckoucke 55 «  574 trichocéphale / trichomatique / trichuride / tricuspide / (valvule) »
                $chapitre = preg_replace('@ */ *\(@', ' (', $chapitre);
                $veds = preg_split('@ */ *@', $chapitre);
            } else if ($sep == ';') {
                $veds = preg_split('@ *; *@', $chapitre);
            }

            $veds = preg_replace(
                array(
                    // ne pas supprimer \[
                    // '@^[^\p{L}]+|[ \.]$@u', // garder (s’)
                    // V - 
                    '/^[A-Z]$/u',
                    '/^[A-Z][ ][^\p{L}]*/u',
                    // Thorax ou Poitrine (fig. 2160)
                    '/ *\(fig\.[^\)]\)/ui'
                ),
                array(
                    '', 
                    '', 
                    '', 
                    '', 
                ),
                $veds,
            );
            // on tente d’écrire
            foreach($veds as $vedette) {
                if (!trim($vedette, ' .,')) continue;
                $data[] = array("entry", $vedette);
            }

        }
        $data = self::livancpages2($data);
        $data = self::livancpages3($data);
        self::tsv_write(self::$tsv_dir . $cote.'.tsv', $data);
        return;
    }

    /**
     * Écrire des événement lexicograhiques dans un fichier
     */
    public static function tsv_write($file, $data)
    {
        $width = 4;
        $out = fopen($file, 'w');
        foreach ($data as $row) {
            $c = count($row);
            $line = '';
            $line .= implode("\t", $row);
            $line .= substr("\t\t\t\t\t\t", 0, $width - $c);
            $line .= "\n";
            fwrite($out, $line);
        }
        fclose($out);
    }

    /**
     * Réduire les sauts de page
     */
    public static function livancpages2($data) {
        $out = [];
        $vedette = null;
        $pb = 0;
        for ($i = 0, $max = count($data); $i < $max; $i++) {
            $line = $data[$i];
            if ($line[0] == 'entry') {
                if (!$line[1]) {
                    continue; // what ?
                }
                // pour Bouley (et Dechambre ?)
                // juste avant un saut de ligne 
                // alors le 2e intitulé est meilleur
                if ($i < $max - 2
                    && $data[$i + 1][0] == 'pb'
                    && $data[$i + 2][0] == 'entry'
                    && strpos($data[$i + 2][1], $line[1]) > 0
                ) {
                    $line = $data[$i + 2];
                }


                if ($vedette != $line[1]) {
                    $vedette = $line[1];
                    $out[] = $line;
                    $pb = 0;
                    continue;
                }
            }
            if ($line[0] == 'pb') {
                $out[] = $line;
                $pb++;
            }
        }
        return $out;
    }

    /**
     * Découper la vedette en mots
     */
    public static function livancpages3($data) {
        $out = [];
        $cote = self::$dico_volume[':volume_cote'];
        for ($i = 0, $max = count($data); $i < $max; $i++) {
            $line = $data[$i];
            // récupérer la vedette et la découper si nécessaire
            if ($line[0] == 'entry') {
                $refs = null;
                // nettoyer la vedette des renvois
                if (preg_match(
                    '/ (V\. |Voy\.? |Voyez )([^\.\/;]+)/u', 
                    $line[1], 
                    $matches)
                ) {
                    $line[1] = trim(
                        preg_replace('/ (V\. |Voy\.? |Voyez )([^\.\/;]+)/ui', '', $line[1]),
                        ' .'
                    );
                    // V. Anémie, anesthésie
                    // Érythroïde (Tunique). Voy. Crémaster et Testicule
                    $refs = preg_split(
                        '/,? +(ou|et|&) +|,[\-—– ]+/ui', 
                        $matches[2]
                    );
                }
                // entry OK, on oute, et on ne touche plus à la vedette
                $out[] = $line;
                $s = preg_replace(
                    array(
                        // [nom d’auteur]
                        '/ *\[[^\]]+\]/u',
                        // Poplité (anat.)
                        // '/ *\((path|anat|)\.\) */ui',
                    ), 
                    array(
                        '',
                        '',
                    ),
                    $line[1]
                );
                if (
                    self::starts_with($cote, 'pharma_019129')
                ) {
                    // Le , la , l’
                    $s = preg_replace('/^ *(le |la |les |l’|l\') */ui', '', $s);
                }
                if (!$s) continue;

                // vedettes hiérarchiques, ne pas séparer
                if (
                    self::starts_with($cote, '24374')
                    || self::starts_with($cote, 'pharma_013686')
                    // Liste des plantes observées au Mont d'Or, au Puy de Domme, & au Cantal, par M. le Monnier. 
                    || self::starts_with($cote, 'pharma_019127') 
                    || self::starts_with($cote, '146144')
                    //  Pilules hydragogues de M. Janin, oculiste de Lyon
                    || self::starts_with($cote, 'extbnfrivet') 
                    // Stérogyl Stérogyl 10 et 15. Vidal (1940, p. 1788)
                    || self::starts_with($cote, 'pharma_p11247')
                    || self::starts_with($cote, '34823')
                    // Dechambre
                    || self::starts_with($cote, 'extbnfdechambre')
                    // Pancoucke
                    || self::starts_with($cote, '47661')
                    // Fuller (médecin anglais, 1654-1734)
                    || preg_match('/\([^\)]*( +(ou|et|&) +|,)/u', $s) 
                ) {
                    // si nom d’auteur dans la vedette, le sortir du terme. 
                    if ($s != $line[1]) $out[] = ['orth', $s];
                }
                // "16 Agaricus campestris. Le champignon champêtre", "17 Agaricus déliciosus. Champignon délicieux",  "18 Agaricus cantharellus. La cantharelle"
                else if (self::starts_with($cote, 'pharma_019128')) {
                    $s = preg_replace('@^[ 0-9\.]+@ui', '', $s);
                    $orths = preg_split('@\. +@ui', $s);
                    if (count($orths) == 2) {
                        $out[] = ['orth', $orths[0], 'lat'];
                        $out[] = ['orth', $orths[1], 'fra'];
                    } else {
                        foreach ($orths as $orth) {
                            $out[] = ['orth', $orth];
                        }
                    }
                }

                else {
                    $orths = preg_split('/,? +(ou|et|&) +|,[\-—– ]+/ui', $s);
                    // si une seule vedette, inutile de détailler
                    if (count($orths) > 1 || $s != $line[1]) {
                        foreach ($orths as $o) {
                            if ($o === NULL || $o === FALSE || $o === "") continue;
                            if (isset(self::$stop[$o])) continue;
                            $out[] = ['orth', trim($o, ' .,;')];
                        }
                    }
                    
                }
                // Renvois
                if ($refs !== null) {
                    foreach($refs as $ref) {
                        self::$count['ref']++;
                        $out[] = ['ref', trim($ref, ' .,;')];
                    }
                }
            }
            else {
                $out[] = $line;
            }
        }
        return $out;
    }


    public static function tei_tsv($tei_file)
    {
        $tei_name = pathinfo($tei_file, PATHINFO_FILENAME);
        $tei_name = preg_replace('@^medict@', '', $tei_name);
        echo "Transform " . $tei_name;
        // XML -> tsv, suite plate d’événements pour l’insertion
        $xml = new DOMDocument;
        $xml->load($tei_file);
        $xsl = new DOMDocument;
        $xsl->load(__DIR__ . '/medict2tsv.xsl');
        $proc = new XSLTProcessor;
        $proc->importStyleSheet($xsl);
        $tsv = $proc->transformToXML($xml);

        $tsv_file = self::$tsv_dir . $tei_name . '.tsv';
        file_put_contents($tsv_file, $tsv);
        echo " => " . $tsv_file . "\n";
        return $tsv_file;
    }



}

MedictPrepa::init();


