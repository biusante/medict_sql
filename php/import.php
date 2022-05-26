<?php

/**
 * Classe pour construire la base de données pour les dictionnaires
 */

Medict::init();
// Medict::titres();
Medict::updates();
// XML test file to load
// $teifile = dirname(dirname(dirname(__FILE__))) . '/medict-xml/xml/medict37020d.xml';
// Medict::loadTei($teifile);
// Medict::loadOld(dirname(dirname(__FILE__)).'/lfs/export_livancpages_dico.csv');

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
        ':vedette' => null,
        ':livanc' => -1,
        ':nom_volume' => null,
        ':cote_volume' => null,
        ':annee_volume' => null,
        ':livancpages' => -1,
        ':page' => null,
        ':url' => null,
        ':page2' => null,
        ':pps' => 0,
        ':vedette_len' => null,
    );
    /** dico_index, insert courant, partagé par référence */
    static $dico_index = array(
        ':dico_titre' => -1,
        ':annee_titre' => -1,
        ':langue' => null,
        ':type' => -1,
        ':dico_entree' => -1,
        ':terme' => null,
        ':terme_sort' => null,
        ':terme_len' => null,
    );
    /** dico_sugg, insert courant, partagé par référence */
    static $dico_sugg = array(
        ':dico_entree' => -1,
        ':cote_volume' => null,
        ':terme1' => null,
        ':terme1_sort' => null,
        ':terme2' => null,
        ':terme2_sort' => null,
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
                PDO::ATTR_PERSISTENT => true,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
                // if true : big queries need memory
                // if false : multiple queries arre not allowed
                // PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
            ),
        );
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
        // normaliser les espaces
        $s = preg_replace('/[\s\-]+/', ' ', trim($s));
        // decomposer lettres et accents
        $s = Normalizer::normalize($s, Normalizer::FORM_D);
        // ne conserver que les lettres et les espaces
        $s = preg_replace("/[^\pL\s]/u", '', $s);
        return $s;
    }


    /**
     * Alimenter la base de données des dictionnaires avec les données déjà indexées,
     * commencer par parcourir la table des titres.
     * Si $cote non null, permet de filtrer (pour déboguage)
     */
    public static function titres($cote = null)
    {
        // vider les tables à remplir
        self::$pdo->query("TRUNCATE TABLE dico_index");
        self::$pdo->query("TRUNCATE TABLE dico_entree");
        self::$pdo->query("TRUNCATE TABLE dico_sugg");
        self::$page_count = 0;
        // préparer les requêtes
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
            self::$dico_entree[':dico_titre']  = self::$dico_index[':dico_titre'] = $dico_titre['id'];
            self::$dico_entree[':annee_titre'] = self::$dico_index[':annee_titre'] = $dico_titre['annee'];
            // boucler sur les volumes
            $sql = "SELECT clenum, cote, annee_iso, auteur FROM livanc WHERE ";
            if ($dico_titre['nb_volume'] < 2) {
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
                self::$dico_entree[':nom_volume'] = null; // absent de cette table, à prendre dans livancpages
                self::$dico_index[':langue'] = $dico_titre['langue_vedette']; // mettre à jour la langue de la vedette


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
        self::$pdo->query("SET unique_checks=0;");
        self::$pdo->query("SET foreign_key_checks=0;");
        // les propriétés de dico_titre et livanc doivent ici être déjà fixée
        self::$dico_entree[':nom_volume'] = null;
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
                    self::$dico_entree[':url'] = $page['url'];
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
                self::$dico_index[':langue'] = 'la';
                self::dico_entree();
                self::$dico_entree[':vedette'] = $veds[1];
                self::$dico_index[':langue'] = null; // fr
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
        } 
        else {
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
            self::$dico_index[':terme'] = $t;
            self::$dico_index[':terme_sort'] = '1' . self::sortable($t);
            self::$dico_index[':terme_len'] = mb_strlen($t, "utf-8");
            if (self::WRITE) self::$q['dico_index']->execute(self::$dico_index); // insérer le terme
            if (self::ECHO) echo ', ' . $t;
        }
        if (self::ECHO) echo "\n";
        // si plus d’une vedette écrire une suggestion
        $count = count($terms);
        if ($count > 1) {
            self::$dico_sugg[':cote_volume'] = self::$dico_entree[':cote_volume'];
            self::$dico_sugg[':dico_entree'] = self::$dico_index[':dico_entree'];
            // A, B, C : A->B, A->C, B->A, B->C, C->A, C->B
            for ($i = 0; $i < $count; $i++) {
                self::$dico_sugg[':terme1'] = $terms[$i];
                self::$dico_sugg[':terme1_sort'] = self::sortable(self::$dico_sugg[':terme1']);
                for ($j = 0; $j < $count; $j++) {
                    if ($terms[$i] ==  $terms[$j]) continue;
                    self::$dico_sugg[':terme2'] = $terms[$j];
                    self::$dico_sugg[':terme2_sort'] = self::sortable(self::$dico_sugg[':terme2']);
                    if (self::WRITE) self::$q['dico_sugg']->execute(self::$dico_sugg); // insérer le terme
                    if (self::ECHO) echo self::$dico_sugg[':cote_volume']
                        . "\t" . self::$dico_sugg[':dico_entree']
                        . "\t" . self::$dico_sugg[':terme1']
                        . "\t" . self::$dico_sugg[':terme2']."\n";
                }
    
            }
        }

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
     * Des updates après chargements
     */
    public static function updates()
    {
        self::$pdo->beginTransaction();
        // score des suggestions (les update avec des select sont spécialement compliqués avec MySQL)
        $qcount = self::$pdo->prepare("SELECT COUNT(*) AS COUNT FROM dico_sugg WHERE terme1_sort = ? AND terme2_sort = ?");
        $qup = self::$pdo->prepare("UPDATE dico_sugg SET score = ? WHERE id = ?");
        foreach  (self::$pdo->query("SELECT * FROM dico_sugg", PDO::FETCH_ASSOC) as $row) {
            $qcount->execute(array($row['terme1_sort'], $row['terme2_sort']));
            list($count) = $qcount->fetch();
            $qup->execute(array($count, $row['id']));
        }
        // loop on all
        self::$pdo->commit();
    }


    public static function loadTei($srcxml)
    {
        $dom = Build::dom($srcxml);
        $tsv = Build::transformDoc($dom, dirname(__FILE__) . '/medict2tsv.xsl');
        $srcname = pathinfo($srcxml, PATHINFO_FILENAME);
        // pour débogage
        $dsttsv = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'medict';
        if (!file_exists($dsttsv)) mkdir($dsttsv, 0777, true);
        $dsttsv .= DIRECTORY_SEPARATOR . $srcname . '.tsv';
        file_put_contents($dsttsv, $tsv);
        return;

        // get the page id 
        $livancpagesSel = self::$pdo->prepare("SELECT numauto, annee, cote FROM livancpages WHERE cote = ? AND page = ?");
        $livancpages = -1;
        $entryIns = self::$pdo->prepare("INSERT INTO entry (xmlid, pages, livancpages) VALUES (?, ?, ?);");
        $entryId = -1;
        $orthIns = self::$pdo->prepare("INSERT INTO orth (label, label_tr, small, , small_tr, entry, livancpages,) VALUES (?, ?, ?, ?, ?, ?, ?);");
        $termIns = self::$pdo->prepare("INSERT INTO term (label, sort, entry, pb) VALUES (?, ?, ?, ?);");
        $refIns = self::$pdo->prepare("INSERT INTO ref (target, entry, pb) VALUES (?, ?, ?);");


        $n = 1;
        $first = true;
        self::$pdo->beginTransaction();
        foreach (explode("\n", $tsv) as $l) {
            if ($first) { // skip first line
                $first = false;
                continue;
            }
            if (!$l) continue;
            $cell = explode("\t", $l);
            $object = $cell[0];
            if ($object == 'volume') {
                $year = $cell[2];
                $volumeIns->execute(array($srcname, $cell[1], $year));
                $volumeId = self::$pdo->lastInsertId();
            } else if ($object == 'pb') {
                $pbIns->execute(array($cell[1], $cell[2], $volumeId));
                $pbId = self::$pdo->lastInsertId();
            } else if ($object == 'entry') {
                $entryIns->execute(array($cell[1], $cell[2], $pbId));
                $entryId = self::$pdo->lastInsertId();
            } else if ($object == 'orth') {
                $orth = mb_strtoupper(mb_substr($cell[1], 0, 1)) . mb_strtolower(mb_substr($cell[1], 1));
                $sort = self::sortable($cell[1]);
                $orthIns->execute(array($orth, $sort, $orth, $sort, $entryId, $pbId, $year));
            } else if ($object == 'term') {
                $sort = self::sortable($cell[1]);
                $termIns->execute(array($cell[1], $sort, $entryId, $pbId));
            } else if ($object == 'ref') {
                $refIns->execute(array($cell[1], $entryId, $pbId));
            } else {
                echo "ligne: ", $n, "\n";
                print_r($cell);
                exit();
            }
            $n++;
        }
        self::$pdo->commit();
    }
}

/*
    Pour mémoire, update complexe limité en MySQL 
    MySQL Error 1093 - Can't specify target table for update in FROM clause
    Mieux vaut passer par une table temporaire

DROP TEMPORARY TABLE IF EXISTS counts;
CREATE TEMPORARY TABLE counts SELECT dico_titre.id, dico_titre.nomdico, COUNT(*) AS nb_volumes 
  FROM dico_titre, livanc 
  WHERE livanc.cotemere = dico_titre.cote GROUP BY livanc.cotemere;
UPDATE dico_titre SET dico_titre.nb_volumes=(SELECT nb_volumes FROM counts WHERE dico_titre.id=counts.id);
SELECT * FROM dico_titre;
     */


function startsWith($haystack, $needle)
{
    return substr($haystack, 0, strlen($needle)) === $needle;
}

/**
 * Different tools to build html sites
 */
class Build
{
    /** XSLTProcessors */
    private static $transcache = array();
    /** get a temp dir */
    private static $tmpdir;


    static function mois($num)
    {
        $mois = array(
            1 => 'janvier',
            2 => 'février',
            3 => 'mars',
            4 => 'avril',
            5 => 'mai',
            6 => 'juin',
            7 => 'juillet',
            8 => 'août',
            9 => 'septembre',
            10 => 'octobre',
            11 => 'novembre',
            12 => 'décembre',
        );
        return $mois[(int)$num];
    }

    /**
     * get a pdo link to an sqlite database with good options
     */
    static function pdo($file, $sql)
    {
        $dsn = "sqlite:" . $file;
        // if not exists, create
        if (!file_exists($file)) return self::sqlcreate($file, $sql);
        else return self::sqlopen($file, $sql);
    }

    /**
     * Open a pdo link
     */
    static private function sqlopen($file)
    {
        $dsn = "sqlite:" . $file;
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("PRAGMA temp_store = 2;");
        return $pdo;
    }

    /**
     * Renew a database with an SQL script to create tables
     */
    static function sqlcreate($file, $sql)
    {
        if (file_exists($file)) unlink($file);
        self::mkdir(dirname($file));
        $pdo = self::sqlopen($file);
        @chmod($file, 0775);
        $pdo->exec($sql);
        return $pdo;
    }

    /**
     * Get a DOM document with best options
     */
    static function dom($xmlfile)
    {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->substituteEntities = true;
        $dom->load($xmlfile, LIBXML_NOENT | LIBXML_NONET | LIBXML_NSCLEAN | LIBXML_NOCDATA | LIBXML_NOWARNING);
        return $dom;
    }

    /**
     * Build an xpath processor
     */
    static function xpath($dom)
    {
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace("tei", "http://www.tei-c.org/ns/1.0");
        $root = $dom->documentElement;
        foreach ($xpath->query('namespace::*', $root) as $node) {
            // echo $node->nodeName, " ", $node->nodeValue, "\n";
            if ($node->nodeName == 'xmlns') $xpath->registerNamespace("default", $node->nodeValue);
        }
        return $xpath;
    }

    /**
     * Xsl transform from xml file
     */
    static function transform($xmlfile, $xslfile, $dst = null, $pars = null)
    {
        return self::transformDoc(self::dom($xmlfile), $xslfile, $dst, $pars);
    }

    static public function transformXml($xml, $xslfile, $dst = null, $pars = null)
    {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->substituteEntities = true;
        $dom->loadXml($xml, LIBXML_NOENT | LIBXML_NONET | LIBXML_NSCLEAN | LIBXML_NOCDATA | LIBXML_NOWARNING);
        return self::transformDoc($dom, $xslfile, $dst, $pars);
    }

    /**
     * An xslt transformer with cache
     * TOTHINK : deal with errors
     */
    static public function transformDoc($dom, $xslfile, $dst = null, $pars = null)
    {
        if (!is_a($dom, 'DOMDocument')) {
            throw new Exception('Source is not a DOM document, use transform() for a file, or transformXml() for an xml as a string.');
        }
        $key = realpath($xslfile);
        // cache compiled xsl
        if (!isset(self::$transcache[$key])) {
            $trans = new XSLTProcessor();
            $trans->registerPHPFunctions();
            // allow generation of <xsl:document>
            if (defined('XSL_SECPREFS_NONE')) $prefs = XSL_SECPREFS_NONE;
            else if (defined('XSL_SECPREF_NONE')) $prefs = XSL_SECPREF_NONE;
            else $prefs = 0;
            if (method_exists($trans, 'setSecurityPreferences')) $oldval = $trans->setSecurityPreferences($prefs);
            else if (method_exists($trans, 'setSecurityPrefs')) $oldval = $trans->setSecurityPrefs($prefs);
            else ini_set("xsl.security_prefs",  $prefs);
            $xsldom = new DOMDocument();
            $xsldom->load($xslfile);
            $trans->importStyleSheet($xsldom);
            self::$transcache[$key] = $trans;
        }
        $trans = self::$transcache[$key];
        // add params
        if (isset($pars) && count($pars)) {
            foreach ($pars as $key => $value) {
                $trans->setParameter(null, $key, $value);
            }
        }
        // return a DOM document for efficient piping
        if (is_a($dst, 'DOMDocument')) {
            $ret = $trans->transformToDoc($dom);
        } else if ($dst != '') {
            self::mkdir(dirname($dst));
            $trans->transformToURI($dom, $dst);
            $ret = $dst;
        }
        // no dst file, return String
        else {
            $ret = $trans->transformToXML($dom);
        }
        // reset parameters ! or they will kept on next transform if transformer is reused
        if (isset($pars) && count($pars)) {
            foreach ($pars as $key => $value) $trans->removeParameter(null, $key);
        }
        return $ret;
    }

    /**
     * A safe mkdir dealing with rights
     */
    static function mkdir($dir)
    {
        if (is_dir($dir)) return $dir;
        if (!mkdir($dir, 0775, true)) throw new Exception("Directory not created: " . $dir);
        @chmod(dirname($dir), 0775);  // let @, if www-data is not owner but allowed to write
        return $dir;
    }

    /**
     * Recursive deletion of a directory
     * If $keep = true, keep directory with its acl
     */
    static function rmdir($dir, $keep = false)
    {
        $dir = rtrim($dir, "/\\") . DIRECTORY_SEPARATOR;
        if (!is_dir($dir)) return $dir; // maybe deleted
        if (!($handle = opendir($dir))) throw new Exception("Read impossible " . $file);
        while (false !== ($filename = readdir($handle))) {
            if ($filename == "." || $filename == "..") continue;
            $file = $dir . $filename;
            if (is_link($file)) throw new Exception("Delete a link? " . $file);
            else if (is_dir($file)) self::rmdir($file);
            else unlink($file);
        }
        closedir($handle);
        if (!$keep) rmdir($dir);
        return $dir;
    }


    /**
     * Recursive copy of folder
     */
    static function rcopy($srcdir, $dstdir)
    {
        $srcdir = rtrim($srcdir, "/\\") . DIRECTORY_SEPARATOR;
        $dstdir = rtrim($dstdir, "/\\") . DIRECTORY_SEPARATOR;
        self::mkdir($dstdir);
        $dir = opendir($srcdir);
        while (false !== ($filename = readdir($dir))) {
            if ($filename[0] == '.') continue;
            $srcfile = $srcdir . $filename;
            if (is_dir($srcfile)) self::rcopy($srcfile, $dstdir . $filename);
            else copy($srcfile, $dstdir . $filename);
        }
        closedir($dir);
    }
}
