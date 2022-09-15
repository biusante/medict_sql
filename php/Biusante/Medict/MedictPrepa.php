<?php
/**
 * Part of Medict https://github.com/biusante/medict-sql
 * Copyright (c) 2021 Université de Paris, BIU Santé
 * MIT License https://opensource.org/licenses/mit-license.php
 */

declare(strict_types=1);

namespace Biusante\Medict;

use DOMDocument, Exception, PDO, XSLTProcessor;

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

    public static function init()
    {
        self::connect();
        ini_set('memory_limit', '-1'); // needed for this script
        mb_internal_encoding("UTF-8");
    }


    /**
     * Boucle sur tous les exports medica pour produire un tsv chargeable 
     * dans la base.
     */
    public static function tsv_dir()
    {
        foreach (glob(self::home().'anc/anc_*.tsv') as $file) {
            $volume_cote = self::anc_cote($file);
            echo "Medict > tsv ". $file . "\n";
            self::tsv_volume($volume_cote);
        }
    }


    public static function anc_dir()
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
                self::anc_volume($volume['cote']);
                print($volume['cote']."\n");
            }
        }
        
    }

    private static function anc_file($volume_cote)
    {
        $file = self::home().'anc/anc_'.$volume_cote.'.tsv';
        if (!file_exists(dirname($file))) mkdir(dirname($file), 0777, true);
        return $file;
    }
    private static function anc_cote($anc_file)
    {
        preg_match('/anc_(.*)\.tsv$/', $anc_file, $matches);
        if (!isset($matches[1]) || !$matches[1]) {
            throw new Exception("Cote non trouvée dans le fichier " . $anc_file);
        }
        return $matches[1];
    }



    /**
     * Écrire les données d’un ancien volume dans un fichier
     */
    private static function anc_volume($volume_cote)
    {
        // sortir les données sources
        $anc_file = self::anc_file($volume_cote);
        // créer le dossier si nécessaire
        $fsrc = fopen($anc_file, 'w');
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


    private static function anc_sep($volume_cote)
    {
        if(
               self::starts_with($volume_cote, '01208')
            || self::starts_with($volume_cote, '01686')
            || self::starts_with($volume_cote, '146144')
            || self::starts_with($volume_cote, '20311')
            || self::starts_with($volume_cote, '21244')
            || self::starts_with($volume_cote, '21575')
            || self::starts_with($volume_cote, '26087')
            || self::starts_with($volume_cote, '269035')
            || self::starts_with($volume_cote, '27898')
            || self::starts_with($volume_cote, '31873')
            || self::starts_with($volume_cote, '32546')
            || self::starts_with($volume_cote, '34823')
            || self::starts_with($volume_cote, '35573')
            || self::starts_with($volume_cote, '37020c')
            || self::starts_with($volume_cote, '45392')
            || self::starts_with($volume_cote, '47661')
            || self::starts_with($volume_cote, '56140')
            || self::starts_with($volume_cote, '57503')
            || self::starts_with($volume_cote, 'extalfobuchoz')
            || self::starts_with($volume_cote, 'extalfodarboval')
            || self::starts_with($volume_cote, 'extbnfadelon')
            || self::starts_with($volume_cote, 'extbnfbeaude')
            || self::starts_with($volume_cote, 'extbnfdechambre')
            || self::starts_with($volume_cote, 'extbnfdezeimeris')
            || self::starts_with($volume_cote, 'extbnfnysten')
            || self::starts_with($volume_cote, 'extbnfpoujol')
            || self::starts_with($volume_cote, 'extbnfrivet')
            || self::starts_with($volume_cote, 'pharma_000103')
            || self::starts_with($volume_cote, 'pharma_006061')
            || self::starts_with($volume_cote, 'pharma_013686')
            || self::starts_with($volume_cote, 'pharma_014023')
            || self::starts_with($volume_cote, 'pharma_014236')
            || self::starts_with($volume_cote, 'pharma_019127')
            || self::starts_with($volume_cote, 'pharma_019128')
            || self::starts_with($volume_cote, 'pharma_019129')
            || self::starts_with($volume_cote, 'pharma_019428')
            || self::starts_with($volume_cote, 'pharma_p11247')
        ) return '/';
        if (
               self::starts_with($volume_cote, '00216')
            || self::starts_with($volume_cote, '07410xC')
            || self::starts_with($volume_cote, '07410xM')
            || self::starts_with($volume_cote, '27518')
            || self::starts_with($volume_cote, '30944')
            || self::starts_with($volume_cote, '32923')
            || self::starts_with($volume_cote, '34820')
            || self::starts_with($volume_cote, '34826')
            || self::starts_with($volume_cote, '37020b')
            || self::starts_with($volume_cote, '37020d')
            || self::starts_with($volume_cote, '37020d~index')
            || self::starts_with($volume_cote, '37029')
            || self::starts_with($volume_cote, '61157')
        ) return '.';
        if (
            self::starts_with($volume_cote, '47667')
        ) return "-";
        if (
            self::starts_with($volume_cote, '24374')
        ) return ';';
    }


    /**
     * Charge un export des données de livancpages pour un volume.
     * Produit un premier tableau d’événements, à reparser,
     * pour regrouper les entrées sur plusieurs pages.
     */
    public static function tsv_volume($volume_cote)
    {
        $anc_file = self::anc_file($volume_cote);
        if (!file_exists($anc_file)) {
            throw new Exception('Fichier non trouvé '.$anc_file);
        }
        $anc_sep = self::anc_sep($volume_cote);
        // Les données à produire
        $data = [];
        // boucler sur les ligne de fichier Medica
        $separator = "\t";
        $handle = fopen($anc_file, 'r');
        // first line, colums names
        $keys = fgetcsv($handle, null, $separator);
        while ((list($page, $refimg, $numauto, $chapitre) = fgetcsv($handle, null, $separator)) !== FALSE) {
            // Événement page
            if ($page == '[sans numérotation]' 
                || $page == '[page blanche]'
            ) {
                $page = '[s. pag.]';
            }
            if (!preg_match('/^\d\d\d\d$/', $refimg)) {
                fwrite(STDERR, "$volume_cote\tp. $page\trefimg ???\t$refimg\n");
                if ($refimg == '0103b') $refimg = '0104';
                if ($refimg == '0103c') $refimg = '0105';
            }
            $data[] = array(
                'pb',
                $page,
                $refimg,
                // "https://www.biusante.parisdescartes.fr/iiif/2/bibnum:" . $cote . ":" . $refimg . "/full/full/0/default.jpg",
                // "https://www.biusante.parisdescartes.fr/histmed/medica/page?" . $cote . '&p=' . $refimg,
                $numauto
            );

            $chapitre = trim($chapitre);
            // ne pas traiter les Errata et Addenda
            if (preg_match('/errata/iu', $chapitre)) continue;

            // traiter un chapitre


            // restaurer de la hiérachie dans les Bouley
            // tout est traité ici
            if (self::starts_with($volume_cote, '34823')) {
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
                    $pref = '';
                    $pos =  strrpos($veds[0], '.');
                    if (FALSE !== $pos) {
                        $pref = substr($veds[0], 0, $pos);
                    }
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
                    // NE PAS supprimer l’auteur
                    $data[] = array("entry", $v);
                }
                continue;
            }

            // supprimer un gros préfixe
            // Classe première. Les campaniformes. Section III. Genre VII. Le gloux / Genre VIII. L'alleluia
            if (self::starts_with($volume_cote, 'pharma_019129')) {
                $chapitre = preg_replace(
                    array('@^.*?Genre[^\.]*\. *@u', '@^.*?Supplémentaire\. *@ui', '@ */ *[^/]*?Genre[^\.]*\. *@u', '@[^\.]+classe\. *@ui'),
                    array('',                       '',                           ' / ',                           ''),
                    $chapitre
                );
            }
            // supprimer un gros préfixe
            // Petit traité de matière médicale, ou des substances médicamenteuses indiquées dans le cours de ce dictionnaire. Division des substances médicamenteuses par ordre alphabétique, et d'après leur manière d'agir sur le corps humain. Médicamens composés / 
            else if (self::starts_with($volume_cote, '57503')) {
                $chapitre = preg_replace(
                    array('@^.*Médicamens composés\P{L}*@u', '@^.*?Règne végétal\. *@ui', '@^.*Médicamens simples\P{L}*@u', '@Vocabulaire des matières contenues.*?@u'),
                    array('',                                '',                                '',                               ''),
                    $chapitre
                );
            }
            // Absorbants [A. Gubler] (bibliographie) [Raige-Delorme] / Absorbants (vaisseaux). Voy. Lymphatiques / Absorption [Jules Béclard]
            // On laisse (bibliographie) ?
            else if (self::starts_with($volume_cote, 'extbnfdechambre')) {
                /*
                $chapitre = preg_replace(
                    // NE PAS supprimer l’auteur

                    array('/ *\(bibliographie\)\.?/ui'),
                    array('', ''),
                    $chapitre
                );
                // if ($echo) fwrite(STDERR, $chapitre."\n");
                */
            }
            //  H. - Habrioux; Hardy François; Hauterive Jean-Baptiste; Hélitas Jean; Heur (d') François; Hospital Gaspard; Houpin René; Hugon Jean; Hugon Joseph; Hugonnaud Jean; Hugonneau Martial / I. - Itier Jacques
            else if (self::starts_with($volume_cote, '24374')) {
                $chapitre = preg_replace(
                    array('@( */ *)?[A-Z]\.[ \-]+@u'),
                    array(';'),
                    $chapitre
                );
            }
            // pour le split sur plusieurs vewdettes, pose des pbs
            // Couronne de Vénus, (la) 07410xM05
            $chapitre = preg_replace('/ *, *\(/ui', ' (', $chapitre);

            // Rien d’indexé dans la page
            if ($chapitre == null || $chapitre == '') {
                continue;
            }

            // Nettoyer des trucs ?

            // Spliter selon le séparateur de saisie
            // sépararteur '-'
            if ($anc_sep == '-') {
                $veds = preg_split('@ +- +@u', $chapitre);
            } 
            // séparateur '.'
            else if ($anc_sep == '.') {
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
            } 
            // séparateur '/'
            else if ($anc_sep == '/') {
                // Panckoucke 55 «  574 trichocéphale / trichomatique / trichuride / tricuspide / (valvule) »
                $chapitre = preg_replace('@ */ *\(@', ' (', $chapitre);
                $veds = preg_split('@ */ *@', $chapitre);
            } 
            // séparateur ;
            else if ($anc_sep == ';') {
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
        $data = self::livancpages3($data, $volume_cote);
        self::tsv_write(self::tsv_file($volume_cote), $data);
        return;
    }

    /**
     * Écrire des événement lexicograhiques dans un fichier
     */
    private static function tsv_write($file, &$data)
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
     * Réduire les sauts de page, taille des articles en nombre de pages
     */
    private static function livancpages2(&$data) {
        $out = [];
        $out_i = 0; // index à remplir dans $out
        $out_lastentry = 0; // index de la dernière entrée 
        $vedette = null; // vedette en cours
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
                // même vedette, incrémenter son compteur de pages
                if ($vedette == $line[1]) {
                    $out[$out_lastentry][2]++;
                }
                // vedette à sortir
                else {
                    $vedette = $line[1];
                    $out_lastentry = $out_i;
                    $out[$out_i++] = array('entry', $vedette, 0);
                    $pb = 0; // compteur de page à zéro
                    continue;
                }
            }
            if ($line[0] == 'pb') {
                $out[$out_i++] = $line;
            }
        }
        return $out;
    }

    /**
     * Découper la vedette en mots
     */
    public static function livancpages3(&$data, $volume_cote) {
        $out = [];
        for ($i = 0, $max = count($data); $i < $max; $i++) {
            $line = $data[$i];
            // récupérer la vedette et la découper si nécessaire
            if ($line[0] == 'entry') {
                $refs = null;
                // Arlemasaia. Voy. Armoise [H. Baillon]
                // nettoyer la vedette des renvois
                if (preg_match(
                    '/ (V\. |Voy\.? |Voyez )(.*)/u', 
                    $line[1], 
                    $matches)
                ) {
                    $line[1] = trim(
                        preg_replace('/ (V\. |Voy\.? |Voyez ).*/ui', '', $line[1]),
                        ' .'
                    );
                    // V. Anémie, anesthésie
                    // Érythroïde (Tunique). Voy. Crémaster et Testicule
                    // suprimer auteurs
                    $renvoi = preg_replace('/ *\[[^\]]+\] */u', '', $matches[2]);
                    $refs = preg_split(
                        '/,? +(ou|et|&) +|,[\-—– ]+/ui', 
                        $renvoi
                    );
                }
                // entry OK, on oute, et on ne touche plus à la vedette
                $out[] = $line;
                $s = preg_replace(
                    array(
                        // [nom d’auteur]
                        '/ *\[[^\]]+\] */u',
                        // pas une entrée
                        '/ *\(bibliographie\)\.?/ui',
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
                    self::starts_with($volume_cote, 'pharma_019129')
                ) {
                    // Le , la , l’
                    $s = preg_replace('/^ *(le |la |les |l’|l\') */ui', '', $s);
                }
                if (!$s) continue;

                // vedettes hiérarchiques, ne pas séparer
                if (
                    self::starts_with($volume_cote, '24374')
                    || self::starts_with($volume_cote, 'pharma_013686')
                    // Liste des plantes observées au Mont d'Or, au Puy de Domme, & au Cantal, par M. le Monnier. 
                    || self::starts_with($volume_cote, 'pharma_019127') 
                    || self::starts_with($volume_cote, '146144')
                    //  Pilules hydragogues de M. Janin, oculiste de Lyon
                    || self::starts_with($volume_cote, 'extbnfrivet') 
                    // Stérogyl Stérogyl 10 et 15. Vidal (1940, p. 1788)
                    || self::starts_with($volume_cote, 'pharma_p11247')
                    || self::starts_with($volume_cote, '34823')
                    // Dechambre
                    || self::starts_with($volume_cote, 'extbnfdechambre')
                    // Pancoucke
                    || self::starts_with($volume_cote, '47661')
                    // Fuller (médecin anglais, 1654-1734)
                    || preg_match('/\([^\)]*( +(ou|et|&) +|,)/u', $s) 
                ) {
                    // si nom d’auteur dans la vedette, le sortir du terme. 
                    if ($s != $line[1]) $out[] = ['orth', $s];
                }
                // "16 Agaricus campestris. Le champignon champêtre", "17 Agaricus déliciosus. Champignon délicieux",  "18 Agaricus cantharellus. La cantharelle"
                else if (self::starts_with($volume_cote, 'pharma_019128')) {
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


    public static function tsv_tei($tei_file)
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

        $tsv_file = self::tsv_file($tei_name);
        file_put_contents($tsv_file, $tsv);
        echo " => " . $tsv_file . "\n";
        return $tsv_file;
    }



}

MedictPrepa::init();


