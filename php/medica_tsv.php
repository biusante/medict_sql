<?php

/**
 * Classe pour construire la base de données pour les dictionnaires
 */

// il faudrait une API ligne de commande plus sympa pour sélectionner les opérations

Medict::init();
$src_dir = dirname(dirname(__DIR__)) . '/medict-xml/xml/';

Medict::$pdo->exec("TRUNCATE dico_titre");
Medict::tsvInsert(dirname(__DIR__) . '/dico_titre.tsv', 'dico_titre');
// produire des tsv avec la table ancpages
Medict::prepare();
Medict::anc_tsv();
// à faire après, pour recouvri les cotes communes à anc
foreach (array(
    'medict37020d.xml',
    'medict37020d~index.xml',
    'medict00152.xml',
    'medict27898.xml',
    'medict07399.xml',
) as $src_basename) {
    $src_file = $src_dir . $src_basename;
    Medict::tei_tsv($src_file);
}


class Medict
{
    /**Compteurs */
    static $count = array(
        'ref' => 0,
    );
    /** Mode write */
    const ECHO = false;
    const WRITE = true;
    /** Paramètres inmportés */
    static public $pars;
    /** SQLite link */
    static public $pdo;
    /** Home directory of project, absolute */
    static $home;
    /** Prepared statements shared between methods */
    static $q = array();
    /** Table de correspondances betacode */
    static $grc_lat;
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
    /** Insérer les informations bibliographiques d’un volume */
    static $dico_volume = array(
        ':dico_titre' => -1,
        ':titre_nom' => null,
        ':titre_annee' => null,
        ':volume_cote' => -1,
        ':volume_soustitre' => -1,
        ':volume_annee' => -1,
        ':livanc' => -1,
    );
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
    /** Des mots vides à filtrer pour la colonne d’index */
    static $stop;
    /** Des  */
    static $re_suff = "aine|aire|aise|aisse|aite|ale|ande|ane|ante|aque|arde|arse|asse|ate|atrice|ausse|ée|éée|elle|eine|enne|ente|ère|erte|ète|ette|eure|euse|ie|ienne|ière|igne|ile|ine|ise|ite|ive|oide|oise|oite|olle|onde|ongue|ouce|trice|ue|uë|une|uque|ure|use";

    public static function init()
    {
        ini_set('memory_limit', -1); // needed for this script
        self::$pars = include dirname(__FILE__) . '/pars.php';
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
        mb_internal_encoding("UTF-8");
        self::$grc_lat = include(__DIR__ . '/grc_lat.php');
        self::$home = dirname(dirname(__FILE__)) . '/';

        // check connection
        echo // self::$pdo->getAttribute(PDO::ATTR_SERVER_INFO), ' '
        self::$pdo->getAttribute(PDO::ATTR_DRIVER_NAME), ' ',
        self::$pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS), "\n";
        // Charger les mots vides
        self::$stop = array_flip(explode("\n", file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'stop.csv')));
        self::$tsv_dir = dirname(__DIR__).'/import/';
        if (!file_exists(self::$tsv_dir)) mkdir(self::$tsv_dir, 0777, true);
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
        // sortir les données sources
        $file = dirname(__DIR__).'/medica/medica_'.$cote.'.tsv';
        if (!file_exists(dirname($file))) mkdir(dirname($file), 0777, true);
        $fsrc = fopen($file, 'w');
        fwrite($fsrc, "page\trefimg\tchapitre\n");

        // Les données à produire
        $data = [];
        // Lecture des pages d’un volume, dans l’ordre naturel
        $pageq = self::$pdo->prepare("SELECT * FROM livancpages WHERE cote = ? ORDER BY cote, refimg");
        $pageq->execute(array($cote));
        while ($page =  $pageq->fetch(PDO::FETCH_ASSOC)) {
            // écrire le fichier source
            fwrite(
                $fsrc, 
                "{$page['page']}\t{$page['refimg']}\t{$page['chapitre']}\n"
            );
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
            if (startsWith(self::$dico_volume[':volume_cote'], '34823')) {
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
            if (startsWith(self::$dico_volume[':volume_cote'], 'pharma_019129')) {
                $chapitre = preg_replace(
                    array('@^.*?Genre[^\.]*\. *@u', '@^.*?Supplémentaire\. *@ui', '@ */ *[^/]*?Genre[^\.]*\. *@u', '@[^\.]+classe\. *@ui'),
                    array('',                       '',                           ' / ',                           ''),
                    $chapitre
                );
            }
            // supprimer un gros préfixe
            // Petit traité de matière médicale, ou des substances médicamenteuses indiquées dans le cours de ce dictionnaire. Division des substances médicamenteuses par ordre alphabétique, et d'après leur manière d'agir sur le corps humain. Médicamens composés / 
            else if (startsWith(self::$dico_volume[':volume_cote'], '57503')) {
                $chapitre = preg_replace(
                    array('@^.*Médicamens composés\P{L}*@u', '@^.*?Règne végétal\. *@ui', '@^.*Médicamens simples\P{L}*@u', '@Vocabulaire des matières contenues.*?@u'),
                    array('',                                '',                                '',                               ''),
                    $chapitre
                );
            }
            // Absorbants [A. Gubler] (bibliographie) [Raige-Delorme] / Absorbants (vaisseaux). Voy. Lymphatiques / Absorption [Jules Béclard]
            else if (startsWith(self::$dico_volume[':volume_cote'], 'extbnfdechambre')) {
                $chapitre = preg_replace(
                    array('@ *\(bibliographie\)\.?@ui', '/ *\[[^\]]+\]/u'),
                    array('', ''),
                    $chapitre
                );
                // if ($echo) fwrite(STDERR, $chapitre."\n");
            }
            //  H. - Habrioux; Hardy François; Hauterive Jean-Baptiste; Hélitas Jean; Heur (d') François; Hospital Gaspard; Houpin René; Hugon Jean; Hugon Joseph; Hugonnaud Jean; Hugonneau Martial / I. - Itier Jacques
            else if (startsWith(self::$dico_volume[':volume_cote'], '24374')) {
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
                    // Le , la , l’
                    "@^ *(le |la |les |l’|l') *@ui", 
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
                $line[1] = preg_replace(
                    array(
                        // [nom d’auteur]
                        '/ *\[[^\]]+\]/u',
                    ),
                    array (
                        '',
                        '',
                        '',
                    ), 
                    $line[1]);
                if (!$line[1]) continue;
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
                // entry OK, on oute
                $out[] = $line;
                // vedettes hiérarchiques, ne pas séparer
                if (
                    startsWith($cote, '24374')
                    || startsWith($cote, 'pharma_013686')
                    // Liste des plantes observées au Mont d'Or, au Puy de Domme, & au Cantal, par M. le Monnier. 
                    || startsWith($cote, 'pharma_019127') 
                    || startsWith($cote, '146144')
                    //  Pilules hydragogues de M. Janin, oculiste de Lyon
                    || startsWith($cote, 'extbnfrivet') 
                    // Stérogyl Stérogyl 10 et 15. Vidal (1940, p. 1788)
                    || startsWith($cote, 'pharma_p11247')
                    || startsWith($cote, '34823')
                    // Dechambre
                    || startsWith($cote, 'extbnfdechambre')
                    // Pancoucke
                    || startsWith($cote, '47661')
                    // Fuller (médecin anglais, 1654-1734)
                    || preg_match('/\([^\)]*( +(ou|et|&) +|,)/u', $line[1]) 
                ) {

                }
                // "16 Agaricus campestris. Le champignon champêtre", "17 Agaricus déliciosus. Champignon délicieux",  "18 Agaricus cantharellus. La cantharelle"
                else if (startsWith($cote, 'pharma_019128')) {
                    $s = preg_replace('@^[ 0-9\.]+@ui', '', $line[1]);
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
                    $orths = preg_split(
                        '/,? +(ou|et|&) +|,[\-—– ]+/ui', 
                        $line[1]
                    );
                    // si une seule vedette, inutile de détailler
                    if (count($orths) > 1) {
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


    /**
     * Charger une table avec des lignes tsv
     */
    static function tsvInsert($file, $table, $separator="\t")
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


function startsWith($haystack, $needle)
{
    return substr($haystack, 0, strlen($needle)) === $needle;
}

