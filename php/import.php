<?php

/**
 * Classe pour construire la base de données pour les dictionnaires
 */

// il faudrait une API ligne de commande plus sympa pour sélectionner les opérations

Medict::init();
Medict::$pdo->exec("TRUNCATE dico_titre");
Medict::tsvInsert(dirname(__DIR__) . '/dico_titre.tsv', 'dico_titre');
Medict::ancLoad(); // fait un truncate bien propre
$srcDir = dirname(dirname(__DIR__)) . '/medict-xml/xml/';
foreach (array(
    'medict37020d.xml',
    'medict00152.xml',
    'medict27898.xml',
    'medict07399.xml',
) as $srcBasename) {
    $srcFile = $srcDir . $srcBasename;
    Medict::loadTei($srcFile);
}
Medict::updates();

class Medict
{
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
    /** dico_entree, insert courant, partagé par référence */
    static $dico_entree = array(
        ':dico_titre' => -1,
        ':annee_titre' => -1,
        ':livanc' => -1,
        ':nom_volume' => null,
        ':cote_volume' => null,
        ':annee_volume' => null,
        ':livancpages' => -1,
        ':page' => null,
        ':refimg' => null,
        ':vedette' => null,
        ':page2' => null,
        ':pps' => 0,
        ':vedette_len' => null,
    );
    /** dico_index, insert courant, partagé par référence */
    static $dico_index = array(
        ':dico_titre' => -1,
        ':annee_titre' => -1,
        ':dico_entree' => -1,
        ':orth' => null,
        ':orth_lang' => null,
        ':orth_sort' => null,
        ':orth_len' => null,
    );
    /** dico_sugg, insert courant, partagé par référence */
    static $dico_sugg = array(
        ':dico_entree' => -1,
        ':src' => null,
        ':src_sort' => null,
        ':dst' => null,
        ':dst_sort' => null,
    );
    /** dico_trad, insert courant, partagé par référence */
    static $dico_trad = array(
        ':dico_entree' => -1,
        ':src' => null,
        ':src_sort' => null,
        ':src_lang' => null,
        ':dst' => null,
        ':dst_sort' => null,
        ':dst_lang' => null,
        ':dst_langno' => 10,
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
    /** Des mots vides à filtrer pour la colonne d’index */
    static $stop;
    /** Un compteur de pages procédées */
    static $page_count = 0;
    /** freqlist */
    static $freqs = array();

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
        self::$home = dirname(dirname(__FILE__)) . '/';

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

    /**
     * Prépare les requêtes d’insertion
     */
    static function prepare()
    {
        // insérer une entrée
        $sql = "INSERT INTO dico_entree 
        (" . str_replace(':', '', implode(', ', array_keys(self::$dico_entree))) . ") 
    VALUES (" . implode(', ', array_keys(self::$dico_entree)) . ");";
        self::$q['dico_entree'] = self::$pdo->prepare($sql);
        // insérer un terme dans l’index
        $sql = "INSERT INTO dico_index 
        (" . str_replace(':', '', implode(', ', array_keys(self::$dico_index))) . ") 
    VALUES (" . implode(', ', array_keys(self::$dico_index)) . ");";
        self::$q['dico_index'] = self::$pdo->prepare($sql);
        // insérer 2 termes liés dans une suggestion
        $sql = "INSERT INTO dico_sugg 
        (" . str_replace(':', '', implode(', ', array_keys(self::$dico_sugg))) . ") 
    VALUES (" . implode(', ', array_keys(self::$dico_sugg)) . ");";
        self::$q['dico_sugg'] = self::$pdo->prepare($sql);
        // traduction, vedette => tard
        $sql = "INSERT INTO dico_trad 
        (" . str_replace(':', '', implode(', ', array_keys(self::$dico_trad))) . ") 
    VALUES (" . implode(', ', array_keys(self::$dico_trad)) . ");";
        self::$q['dico_trad'] = self::$pdo->prepare($sql);
    }

    /**
     * Alimenter la base de données des dictionnaires avec les données déjà indexées,
     * commencer par parcourir la table des titres.
     * Si $cote non null, permet de filtrer (pour déboguage)
     */
    public static function ancLoad($cote = null)
    {
        // vider les tables à remplir
        self::$pdo->query("TRUNCATE TABLE dico_index");
        self::$pdo->query("TRUNCATE TABLE dico_entree");
        self::$pdo->query("TRUNCATE TABLE dico_sugg");
        self::$pdo->query("TRUNCATE TABLE dico_trad");
        self::$page_count = 0;
        self::prepare();
        // Charger les mots vides
        self::$stop = array_flip(explode("\n", file_get_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'stop.csv')));
        // Pour export tsv, nom des colonnes
        // if(self::ECHO) echo "entree.vedette	vedette.len	livancpages.nomdico	livancpages.annee	livancpages.page	mot.termes\n";

        // boucler sur la table des titres
        $pars = array();
        $sql =  "SELECT * FROM dico_titre "; // ORDER BY annee, supposons pour l’instant que l’ordre naturel est bon (ex: Vidal au bout)
        if ($cote) {
            $sql .= " WHERE cote LIKE ?";
            $pars[] = $cote;
        }
        $qdico_titre = self::$pdo->prepare($sql);
        $qdico_titre->execute($pars);

        while ($dico_titre = $qdico_titre->fetch()) {
            echo "[SQL load] ". $dico_titre['cote']. ', ' . $dico_titre['nom'] . "\n";
            if (!$dico_titre['orth_lang']) $dico_titre['orth_lang'] = 'fra';
            self::$dico_entree[':dico_titre']  = self::$dico_index[':dico_titre'] = $dico_titre['id'];
            self::$dico_entree[':annee_titre'] = self::$dico_index[':annee_titre'] = $dico_titre['annee'];
            // boucler sur les volumes
            $sql = "SELECT clenum, cote, annee_iso, auteur FROM livanc WHERE ";
            if ($dico_titre['vols'] < 2) {
                $sql .= " cote = ?";
            } else {
                $sql .= " cotemere = ? ORDER BY cote";
            }
            $volq = self::$pdo->prepare($sql);
            $volq->execute(array($dico_titre['cote']));
            while ($volume = $volq->fetch(PDO::FETCH_ASSOC)) {
                self::$dico_entree[':livanc'] = $volume['clenum'];
                self::$dico_entree[':cote_volume'] = $volume['cote'];
                self::$dico_entree[':annee_volume'] = substr($volume['annee_iso'], 0, 4); // livanc.annee : "An VII", livanc.annee_iso : "1798/1799"
                self::$dico_entree[':nom_volume'] = $dico_titre['nom_court']; // nom court sans date
                self::$dico_index[':orth_lang'] = $dico_titre['orth_lang']; // mettre à jour la langue de la vedette


                if (self::ECHO) fwrite(STDERR, $dico_titre['nom'] . "\t" . self::$dico_entree[':annee_volume'] . "\t" . self::$dico_entree[':cote_volume'] . "\n");
                $auteur = trim(preg_replace('@[\s]+@u', ' ', $volume['auteur']));
                self::livancpages(self::$dico_entree[':cote_volume'], $dico_titre['sep'], $auteur);
            }
        }
        // for freqs
        /*
        arsort(self::$freqs);
        $n = 1;
        foreach (self::$freqs as $key => $value){
            echo $n.'. '.$key.' ('.$value.")\n";
            if (++$n > 1000) break; 
        }
        */
    }

    /**
     * Traiter livancpages.chapitre pour regrouper les entrées sur plusieurs pages.
     * Ces "entrées" sont parfois constituées de plusieurs vedettes qui seront découpées
     * dans vedettes()
     */
    private static function livancpages($cote, $sep, $auteur = null)
    {
        $sep = trim($sep);
        // Lecture des pages d’un volume, dans l’ordre naturel
        $pageq = self::$pdo->prepare("SELECT * FROM livancpages WHERE cote = ? ORDER BY cote, refimg");
        $pageq->execute(array($cote));
        self::$pdo->beginTransaction();
        // self::$pdo->query("SET unique_checks=0;");
        self::$pdo->query("SET foreign_key_checks=0;");
        // les propriétés de dico_titre et livanc doivent ici être déjà fixée
        while ($page =  $pageq->fetch(PDO::FETCH_ASSOC)) {
            // provisoire, tant que toutes les infos de volume ne sont pas dans livanc
            if (self::$dico_entree[':nom_volume'] == null) {
                if ($page['nomdico']) self::$dico_entree[':nom_volume'] = $page['nomdico'];
                else if ($auteur) self::$dico_entree[':nom_volume'] = $auteur;
                else self::$dico_entree[':nom_volume'] = "???";
            }
            $chapitre = $page['chapitre'];
            // special split

            // supprimer les insertions éditeur
            $chapitre = preg_replace(
                array('@ *\[[^\]]+\]@u'),
                array(''),
                $chapitre
            );


            // Supprimer le renvois
            // Rangonus. Voyez Philologus.
            $chapitre = preg_replace(
                array('@[\./\)] (V\. |Voy.? |Voyez )[^\./;]+@u'),
                array(''),
                $chapitre
            );


            // supprimer un gros préfixe
            // Classe première. Les campaniformes. Section III. Genre VII. Le gloux / Genre VIII. L'alleluia
            if (startsWith(self::$dico_entree[':cote_volume'], 'pharma_019129')) {
                $chapitre = preg_replace(
                    array('@^.*?Genre[^\.]*\. *@u', '@^.*?Supplémentaire\. *@ui', '@ */ *[^/]*?Genre[^\.]*\. *@u', '@[^\.]+classe\. *@ui'),
                    array('',                       '',                           ' / ',                           ''),
                    $chapitre
                );
            }
            // supprimer un gros préfixe
            // Petit traité de matière médicale, ou des substances médicamenteuses indiquées dans le cours de ce dictionnaire. Division des substances médicamenteuses par ordre alphabétique, et d'après leur manière d'agir sur le corps humain. Médicamens composés / 
            else if (startsWith(self::$dico_entree[':cote_volume'], '57503')) {
                $chapitre = preg_replace(
                    array('@^.*Médicamens composés\P{L}*@u', '@^.*?Règne végétal\. *@ui', '@^.*Médicamens simples\P{L}*@u', '@Vocabulaire des matières contenues.*?@u'),
                    array('',                                '',                                '',                               ''),
                    $chapitre
                );
            }
            // Absorbants [A. Gubler] (bibliographie) [Raige-Delorme] / Absorbants (vaisseaux). Voy. Lymphatiques / Absorption [Jules Béclard]
            else if (startsWith(self::$dico_entree[':cote_volume'], 'extbnfdechambre')) {
                // $echo = (mb_strpos($chapitre, 'Voy.') !== false);
                // if ($echo) fwrite(STDERR, $chapitre."\n");
                $chapitre = preg_replace(
                    array('@ *\(bibliographie\)\.?@ui'),
                    array(''),
                    $chapitre
                );
                // if ($echo) fwrite(STDERR, $chapitre."\n");
            }
            //  H. - Habrioux; Hardy François; Hauterive Jean-Baptiste; Hélitas Jean; Heur (d') François; Hospital Gaspard; Houpin René; Hugon Jean; Hugon Joseph; Hugonnaud Jean; Hugonneau Martial / I. - Itier Jacques
            else if (startsWith(self::$dico_entree[':cote_volume'], '24374')) {
                $chapitre = preg_replace(
                    array('@( */ *)?[A-Z]\.[ \-]+@u'),
                    array(';'),
                    $chapitre
                );
            }


            // Rien d’indexé dans la page
            if ($chapitre == null || $chapitre == '') {
                self::vedettes();  // au cas où entree pendante
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
                    '@^[^\p{L}]+|[ \.]$@u', // garder (s’)
                    "@^ *(le |la |les |l’|l') *@ui", // Le , la , l’
                ),
                array('', ''),
                $veds,
            );
            // grouper les vedettes
            $veds = array_keys(array_flip($veds));
            // boucler sur les vedettes de la page
            $fin = count($veds);
            $pageneuve = false;
            for ($i = 0; $i < $fin; $i++) {
                // si entrée pendante = première vedette de la page
                if ($i == 0 && self::$dico_entree[':vedette'] != null && self::$dico_entree[':vedette'] == $veds[$i]) {
                    // mise à jour de la 2e page
                    self::$dico_entree[':pps']++;
                    self::$dico_entree[':page2'] = $page['page'];
                    // si plus d’une vedette dans la page, alors on est sûr que l’entrée pendante se finit là
                    if ($fin > 1) self::vedettes();  // écrire l’entrée pendante
                    continue;
                }
                // entrée pendante finie à la page précédente, on la sort, et on traite la première vedette de la page 
                else if ($i == 0 && self::$dico_entree[':vedette'] != null && self::$dico_entree[':vedette'] != $veds[$i]) {
                    self::vedettes();  // écrire l’entrée pendante
                }
                // si nouvelle vedette (1, ou 2), mettre à jour les donnée de page
                if (!$pageneuve) {
                    self::$page_count++;
                    self::$dico_entree[':livancpages'] = $page['numauto'];
                    self::$dico_entree[':page'] = $page['page'];
                    self::$dico_entree[':refimg'] = $page['refimg'];
                    $pageneuve = true;
                }
                self::$dico_entree[':vedette'] = $veds[$i];
                // si vedette après dans la page, ça peut partir
                if ($i < $fin - 1) self::vedettes();
            }
        }
        self::vedettes();  // si entree pendante
        self::$pdo->query("SET foreign_key_checks=1; ");
        self::$pdo->query("SET unique_checks=1;");
        self::$pdo->commit();
        return;
    }

    /**
     * Découper si nécessaires les vedettes pour quelques cas particuliers
     */
    public static function vedettes()
    {
        // sert de test dans l’automate qui rassemble les vedettes à travers les pages
        if (self::$dico_entree[':vedette'] == null) {
            self::$dico_entree[':vedette'] = null;
            self::$dico_entree[':page2'] = null;
            self::$dico_entree[':pps'] = 0;
            return;
        }
        // "16 Agaricus campestris. Le champignon champêtre", "17 Agaricus déliciosus. Champignon délicieux",  "18 Agaricus cantharellus. La cantharelle"
        if (startsWith(self::$dico_entree[':cote_volume'], 'pharma_019128')) {
            $line = preg_replace('@^[ 0-9\.]+@ui', '', self::$dico_entree[':vedette']);
            $veds = preg_split('@\. +@ui', $line);
            if (count($veds) == 2) {
                self::$dico_entree[':vedette'] = $veds[0];
                self::$dico_index[':orth_lang'] = 'lat';
                self::dico_entree();
                self::$dico_entree[':vedette'] = $veds[1];
                self::$dico_index[':orth_lang'] = 'fra'; // fr
                self::dico_entree();
            } else {
                foreach ($veds as $vedette) {
                    self::$dico_entree[':vedette'] = $vedette;
                    self::dico_entree();
                }
            }
        }
        // Coeur (maladies du) [U. Leblanc]. Des maladies du coeur et de ses enveloppes en particulier. Maladies du coeur appréciables par des lésions physiques. Maladies dites vitales. Phlegmasies du coeur et de ses enveloppes. De la cardite 
        else if (startsWith(self::$dico_entree[':cote_volume'], '34823')) {
            $veds = preg_split('@\.[ \-]+@ui', self::$dico_entree[':vedette']);
            foreach ($veds as $vedette) {
                self::$dico_entree[':vedette'] = $vedette;
                self::dico_entree();
            }
        } else {
            self::dico_entree();
        }

        // nettoyer les tableaux
        self::$dico_entree[':vedette'] = null;
        self::$dico_entree[':page2'] = null;
        self::$dico_entree[':pps'] = 0;
    }

    /**
     * self::$dico_entree doit ici être prêt pour être écrit
     */
    public static function dico_entree()
    {
        self::$dico_entree[':vedette_len'] = mb_strlen(self::$dico_entree[':vedette'], "utf-8");
        // insert entree
        if (self::WRITE) self::$q['dico_entree']->execute(self::$dico_entree);
        self::$dico_index[':annee_titre'] = self::$dico_entree[':annee_titre'];
        if (self::WRITE) self::$dico_index[':dico_entree'] = self::$pdo->lastInsertId();
        // En cas de log, pour vérifier
        if (self::ECHO) {
            // echo "<b>";
            echo mb_strtoupper(mb_substr(self::$dico_entree[':vedette'], 0, 1, 'UTF-8'), 'UTF-8'), mb_substr(self::$dico_entree[':vedette'], 1, NULL, 'UTF-8');
            echo "\t";
            echo mb_strlen(self::$dico_entree[':vedette']);
            echo "\t";
            echo self::$dico_entree[':nom_volume'];
            echo "\t";
            echo self::$dico_entree[':annee_volume'];
            echo "\t";
            if (self::$dico_entree[':page2'] != null) echo "pps. ", self::$dico_entree[':page'], "-", self::$dico_entree[':page2'];
            else echo "p. ", self::$dico_entree[':page'];
            echo "\t";
        }

        $vedette = self::$dico_entree[':vedette'];
        // si pas nom propre, tout en minuscule ? mais Banc de Galien ? Incube, ou Cochemar ?
        // $vedette = mb_strtolower(mb_substr($vedette, 0, 1, 'UTF-8'), 'UTF-8'). mb_substr($vedette, 1, NULL, 'UTF-8');

        // Cas à ne pas splitter sur la virgule etc
        if (
            startsWith(self::$dico_entree[':cote_volume'], '24374')
            || startsWith(self::$dico_entree[':cote_volume'], 'pharma_013686')
            || startsWith(self::$dico_entree[':cote_volume'], 'pharma_019127') // Liste des plantes observées au Mont d'Or, au Puy de Domme, & au Cantal, par M. le Monnier. 
            || startsWith(self::$dico_entree[':cote_volume'], 'pharma_019128') // Le pois à merveilles, à fruit noir. 
            || startsWith(self::$dico_entree[':cote_volume'], '146144')
            || startsWith(self::$dico_entree[':cote_volume'], 'extbnfrivet') //  Pilules hydragogues de M. Janin, oculiste de Lyon
            || startsWith(self::$dico_entree[':cote_volume'], 'pharma_p11247') // Stérogyl Stérogyl 10 et 15. Vidal (1940, p. 1788)
            || startsWith(self::$dico_entree[':cote_volume'], '34823')
            || mb_strpos($vedette, '(') !== false // Fuller (médecin anglais, 1654-1734)
        ) {
            $terms = array($vedette);
        } else {
            // unique
            $terms = array_flip(array_flip(preg_split('@,? +(ou|et|&) +|,[\-—– ]+@ui', $vedette)));
        }
        // filtrer les valeurs vides qui seraient sorties du split
        $terms2 = array();
        foreach ($terms as $t) {
            if ($t === NULL || $t === FALSE || $t === "") continue;
            if (isset(self::$stop[$t])) continue;
            $terms2[] = $t;
            /*
            if (isset(self::$freqs[$t])) self::$freqs[$t]++;
            else self::$freqs[$t] = 1;
            */
        }
        $terms = $terms2;

        // écrire la ou les vedettes dans l’index
        foreach ($terms as $t) {
            self::$dico_index[':orth'] = $t;
            self::$dico_index[':orth_sort'] = '1' . self::sortable($t);
            self::$dico_index[':orth_len'] = mb_strlen($t, "utf-8");
            if (self::ECHO) {
                print_r(self::$dico_index);
                echo ', ' . $t;
            }
            if (self::WRITE) self::$q['dico_index']->execute(self::$dico_index); // insérer le terme
        }
        if (self::ECHO) echo "\n";
        // si plus d’une vedette écrire une suggestion
        self::$dico_sugg[':dico_entree'] = self::$dico_index[':dico_entree'];
        self::sugg($terms);
        /*
    // splitter sur les mots ?
    $terms = array_flip(preg_split('@[^\p{L}\-]+@u', $vedette));
    foreach ($terms as $terme=>$value) {
      if (!$terme) continue;
      // mot vide
      if (isset(self::$stop[$terme])) continue;
      self::$dico_index[':terme'] = $terme; 
      self::$dico_index[':terme_sort'] = self::sortable($terme);
      // insert le terme
      if(self::WRITE) self::$q['dico_index']->execute(self::$dico_index);
      if(self::ECHO) echo ', '.$terme;
    }
    */
    }

    /**
     * Combinatoire des suggestions entre plusieurs termes
     */
    public static function sugg($terms)
    {
        $terms = array_keys(array_flip($terms));
        $count = count($terms);
        if ($count < 2) return;
        // A, B, C : A->B, A->C, B->A, B->C, C->A, C->B
        for ($i = 0; $i < $count; $i++) {
            self::$dico_sugg[':src'] = $terms[$i];
            self::$dico_sugg[':src_sort'] = self::sortable(self::$dico_sugg[':src']);
            for ($j = 0; $j < $count; $j++) {
                if ($terms[$i] ==  $terms[$j]) continue;
                self::$dico_sugg[':dst'] = $terms[$j];
                self::$dico_sugg[':dst_sort'] = self::sortable(self::$dico_sugg[':dst']);
                if (self::WRITE) self::$q['dico_sugg']->execute(self::$dico_sugg); // insérer le terme
                if (self::ECHO) echo self::$dico_sugg[':dico_entree']
                    . "\t" . self::$dico_sugg[':src']
                    . "\t" . self::$dico_sugg[':dst'] . "\n";
            }
        }
    }

    /**
     * Des updates après chargements
     */
    public static function updates()
    {
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
     * Charger un glossaire de traductions non consultable comme 
     */
    public static function loadGloss($teiFile)
    {
        $tsv = self::tsv($teiFile);
        // delet old ?

    }


    public static function tsv($teiFile)
    {
        $teiName = pathinfo($teiFile, PATHINFO_FILENAME);
        echo "Transform " . $teiName;
        // XML -> tsv, suite plate d’événements pour l’insertion
        $xml = new DOMDocument;
        $xml->load($teiFile);
        $xsl = new DOMDocument;
        $xsl->load(__DIR__ . '/medict2tsv.xsl');
        $proc = new XSLTProcessor;
        $proc->importStyleSheet($xsl);
        $tsv = $proc->transformToXML($xml);
        $dstTsv = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'medict';
        if (!file_exists($dstTsv)) mkdir($dstTsv, 0777, true);
        $dstTsv .= DIRECTORY_SEPARATOR . $teiName . '.tsv';
        file_put_contents($dstTsv, $tsv);
        echo " => " . $dstTsv . "\n";
        return $tsv;
    }

    public static function loadTei($teiFile)
    {
        $tsv = self::tsv($teiFile);
        $teiName = pathinfo($teiFile, PATHINFO_FILENAME);


        // quelques données à insérer
        $cote_volume = preg_replace('@^medict@', '', $teiName);
        self::$dico_entree[':cote_volume'] = $cote_volume;

        /* // marche pas
        // suppriner les données concernant cette cote
        echo "Delete old…";
        $q = self::$pdo->prepare("
        DELETE FROM dico_index WHERE dico_entree IN (
            SELECT id FROM dico_entree WHERE cote_volume = ?
        )
        ");
        $q->execute(array($cote_volume));

        $q = self::$pdo->prepare("
        DELETE FROM dico_sugg WHERE dico_entree IN (
            SELECT id FROM dico_entree WHERE cote_volume = ?
        )
        ");
        $q->execute(array($cote_volume));

        $q = self::$pdo->prepare("
        DELETE FROM dico_trad WHERE dico_entree IN (
            SELECT id FROM dico_entree WHERE cote_volume = ?
        )
        ");
        $q->execute(array($cote_volume));
        $q = self::$pdo->prepare("
        DELETE FROM dico_entree WHERE cote_volume = ?
        ");
        $q->execute(array($cote_volume));
        echo " …DONE.\n";
        */

        // rependre des données de la table de biblio
        $cote_livre = preg_replace('@x\d\d$@', '', $cote_volume);
        $q = self::$pdo->prepare("SELECT * FROM dico_titre WHERE cote = ?");
        $q->execute(array($cote_livre));
        // list($dico_titre, $annee_titre, $orth_lang) = $q->fetch(PDO::FETCH_NUM);
        $dico_titre = $q->fetch(PDO::FETCH_ASSOC);
        self::$dico_entree[':dico_titre'] = $dico_titre['id'];
        self::$dico_entree[':annee_titre'] =  $dico_titre['annee'];
        self::$dico_index[':dico_titre'] = $dico_titre['id'];
        self::$dico_index[':annee_titre'] = $dico_titre['annee'];
        self::$dico_entree[':nom_volume'] = $dico_titre['nom_court'];

        $orth_lang = $dico_titre['orth_lang'];
        if (!$orth_lang) $orth_lang = 'fra';
        // valeurs par défaut
        self::$dico_entree[':annee_volume'] = $dico_titre['annee'];
        self::$dico_entree[':livanc'] = null;

        // attaper des données, si possible
        $q = self::$pdo->prepare("SELECT clenum, annee_iso  FROM livanc WHERE cote = ?");
        $q->execute(array($cote_volume));
        $livanc = $q->fetch(PDO::FETCH_ASSOC);
        if ($livanc && count($livanc) > 0) {
            self::$dico_entree[':livanc'] = $livanc['clenum'];
            self::$dico_entree[':annee_volume'] = $livanc['annee_iso'];
        }

        echo "Start loading…";
        self::$pdo->beginTransaction();
        self::$pdo->query("SET foreign_key_checks=0;");
        self::prepare(); 
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
                $qlivancpages->execute(array($cote_volume, $refimg));
                $row = $qlivancpages->fetch(PDO::FETCH_ASSOC);
                if ($row && count($row) > 0) {
                    self::$dico_entree[':livancpages'] = $row['numauto'];
                }
                else {
                    self::$dico_entree[':livancpages'] = null;
                }
                self::$dico_entree[':refimg'] = $refimg;
                self::$dico_entree[':page'] = ltrim($cell[1], '0');
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
