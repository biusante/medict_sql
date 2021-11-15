<?php
/**
 * Classe pour construire la base de données pour les dictionnaires
 */
 
Medict::init();
Medict::pages2db();
// XML test file to load
$teifile = dirname(dirname(dirname(__FILE__))) . '/medict-xml/xml/medict37020d.xml';
// Medict::loadTei($teifile);
// Medict::loadOld(dirname(dirname(__FILE__)).'/lfs/export_livancpages_dico.csv');

class Medict
{
  /** Paramètres inmportés */
  static public $pars;
  /** SQLite link */
  static public $pdo;
  /** Home directory of project, absolute */
  static $home;
  /** Prepared statements shared between methods */
  static $q = array();
  /** Table entrée, une ligne à écrire, partagée par référence */
  static $entree = array(
    ':livanc' => -1,
    ':cote' => null,
    ':nomdico' => null, 
    ':annee' => -1, 
    ':livancpages' => -1, 
    ':page' => null, 
    ':url' => null, 
    ':vedette' => null, 
    ':page2' => null,
    ':taille' => 0,
  );
  /** Table mot, une ligne à écrire, partagée par référence */
  static $mot = array (
    ':livanc' => -1,
    ':langue' => null, 
    ':annee' => -1, 
    ':entree' => -1,
    ':terme' => null, 
    ':terme_sort' => null,
  );
  /** Des mots vides à filtrer pour la colonne d’index */
  static $stop;

  
  public static function init()
  {
    ini_set('memory_limit', -1); // needed for this script
    self::$pars = include dirname(__FILE__).'/pars.php';
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
    self::$home = dirname(dirname(__FILE__)).'/';
    
    // check connection
    echo // self::$pdo->getAttribute(PDO::ATTR_SERVER_INFO), ' '
         self::$pdo->getAttribute(PDO::ATTR_DRIVER_NAME), ' ',
         self::$pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS), "\n"
    ;

  }
  
  public static function sortable($utf8)
  {
    $utf8 = mb_strtolower($utf8, 'UTF-8');
    $tr = array(
      '« ' => '"',
      ' »' => '"',
      '«' => '"',
      '»' => '"',
      'à' => 'a',
      'ä' => 'a',
      'â' => 'a',
      'é' => 'e',
      'è' => 'e',
      'ê' => 'e',
      'ë' => 'e',
      'î' => 'i',
      'ï' => 'i',
      'ô' => 'o',
      'ö' => 'o',
      'ü' => 'u',
      'û' => 'u',
      'ÿ' => 'y',
    );
    $sortable = strtr($utf8, $tr);
    // pb avec les accents, passera pas pour le grec
    // $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $utf8);
    return $sortable;
  }

  
  /**
   * Alimenter la base de données des dictionnaires avec les données déjà indexées
   */
  public static function pages2db()
  {
    // Charger les mots vides
    self::$stop = array_flip(explode("\n",file_get_contents(dirname(__FILE__).DIRECTORY_SEPARATOR.'stop.csv')));
    // Requêtes
    $selVol = self::$pdo->prepare("SELECT clenum, auteur FROM livanc WHERE cote = ?");
    $sql = "INSERT INTO entree (" . str_replace(':', '', implode(', ', array_keys(self::$entree))) . ") VALUES (" . implode(', ', array_keys(self::$entree)) . ");";
    self::$q['entree'] = self::$pdo->prepare($sql);
    $sql = "INSERT INTO mot (" . str_replace(':', '', implode(', ', array_keys(self::$mot))) . ") VALUES (" . implode(', ', array_keys(self::$mot)) . ");";
    self::$q['mot'] = self::$pdo->prepare($sql);
    $selPage = self::$pdo->prepare("SELECT * FROM livancpages");
    $selPage->execute();
    self::$pdo->beginTransaction();
    $pages = 0;

    while($page =  $selPage->fetch(PDO::FETCH_ASSOC)) {
      // changement de volume
      if ($page['cote'] != self::$entree[':cote']) {
        $selVol->execute(array($page['cote']));
        list($livanc, $auteur) = $selVol->fetch(PDO::FETCH_NUM);
        $auteur = trim(preg_replace('@[\s]+@u', ' ', $auteur));
        echo $livanc,"\t",$page['cote'],"\t",$page['nomdico'],"\t",$page['annee'],"\n";
        self::scri();  // au cas où entree pendante
        self::$entree[':livanc']= $livanc;
        self::$entree[':cote'] = $page['cote'];
        if ($page['nomdico']) self::$entree[':nomdico'] = $page['nomdico'];
        else if ($auteur) self::$entree[':nomdico'] = $auteur;
        self::$entree[':annee'] = $page['annee'];
      }
      $chapitre = $page['chapitre'];
      // Rien d’indexé dans la page
      if ($chapitre == null || $chapitre == '') {
        self::scri();  // au cas où entree pendante
        continue;
      }
      // Nettoyer des trucs ?
      $chapitre = preg_replace('@V\. *@u', '', $chapitre);
      // split chapitre
      if (strpos($chapitre, ';') !== false) {
        $veds = preg_split('@[  ]*; *@u', $chapitre);
      }
      else if (strpos($chapitre, '/') !== false) {
        $veds = preg_split('@ */ *@', $chapitre);
      }
      // Coeur [A. Béclard], ne pas couper sur le point
      else if (strpos($chapitre, '[') !== false) {
        $veds = explode('|', $chapitre);
      }
      else {
        $veds =preg_split('@\. +@u', $chapitre);
      }
      // unifier les vedettes, notamment en cas de V.
      $veds = array_keys(array_flip($veds));
      // boucler sur les vedettes de la page
      $fin = count($veds);
      $pageneuve = false;
      for ($i = 0; $i < $fin; $i++) {
        // si entrée pendante = première vedette de la page
        if ($i == 0 && self::$entree[':vedette'] != null && self::$entree[':vedette'] == $veds[$i]) {
          // mise à jour de la 2e page
          self::$entree[':taille']++;
          self::$entree[':page2'] = $page['page'];
          // si plus d’une vedette dans la page, alors on est sûr que l’entrée pendante se finit là
          if ($fin > 1) self::scri();  // écrire l’entrée pendante
          continue; 
        }
        // entrée pendante finie à la page précédente, on la sort, et on traite la première vedette de la page 
        else if ($i == 0 && self::$entree[':vedette'] != null && self::$entree[':vedette'] != $veds[$i]) {
          self::scri();  // écrire l’entrée pendante
        }
        // si nouvelle vedette (1, ou 2), mettre à jour les donnée de page
        if (!$pageneuve) {
          $pages++;
          self::$entree[':livancpages'] = $page['numauto'];
          self::$entree[':page'] = $page['page'];
          self::$entree[':url'] = $page['url'];
          $pageneuve = true;
        }
        self:: $entree[':vedette'] = $veds[$i];
        // si vedette après dans la page, ça peut partir
        if ($i < $fin - 1) self::scri();
      }
    }
    self::$pdo->commit();
    echo "\n";
    echo $pages," pages";
    return;
  }

  /**
   * Écrire les vedettes et l’index
   */
  public static function scri()
  {
    $db = true;
    if (self::$entree[':vedette'] == null) {
      self::$entree[':vedette'] = null;
      self::$entree[':page2'] = null;
      self::$entree[':taille'] = 0;
      return;
    }
    // insert entree
    if($db) self::$q['entree']->execute(self::$entree);
    self::$mot[':livanc'] = self::$entree[':livanc'];
    self::$mot[':annee'] = self::$entree[':annee'];
    if($db) self::$mot[':entree'] = self::$pdo->lastInsertId();
    self::$mot[':langue'] = ''; 
    // indexation des termes
    $vedette = trim(preg_replace(
      array("@\[[^\]]*\]@u", "@\s+@u"),
      array(" ",            " "),
      self::$entree[':vedette'],
    ));
    // si pas nom propre, tout en minuscule ? mais Banc de Galien ? Incube, ou Cochemar ?
    // $vedette = mb_strtolower(mb_substr($vedette, 0, 1, 'UTF-8'), 'UTF-8'). mb_substr($vedette, 1, NULL, 'UTF-8');

    $terms = array_flip(preg_split('@[^\p{L}\-]+@u', $vedette));
    foreach ($terms as $terme=>$value) {
      if (!$value) continue;
      // mot vide
      if (isset(self::$stop[$terme])) {
        unset($terms[$terme]);
        continue;
      }
      self::$mot[':terme'] = $terme; 
      self::$mot[':terme_sort'] = self::sortable($terme);
      // insert le terme
      if($db) self::$q['mot']->execute(self::$mot);
    }
    // En cas de log, pour vérifier
    if (!$db) {
      // echo "<b>";
      echo mb_strtoupper(mb_substr(self::$entree[':vedette'], 0, 1, 'UTF-8'), 'UTF-8'), mb_substr(self::$entree[':vedette'], 1, NULL, 'UTF-8');
      echo "\t";
      echo self::$entree[':nomdico'];
      echo "\t";
      echo self::$entree[':annee'];
      echo "\t";
      if (self::$entree[':page2'] != null) echo "pps. ",self::$entree[':page'],"-",self::$entree[':page2'];
      else echo "p. ",self::$entree[':page'];
      echo "\t";
      echo implode(", ", array_keys($terms));
      echo "\n";
    }

    // nettoyer les tableaux
    self::$entree[':vedette'] = null;
    self::$entree[':page2'] = null;
    self::$entree[':taille'] = 0;
  }
  
  public static function loadTei($srcxml)
  {
    $dom = Build::dom($srcxml);
    $tsv = Build::transformDoc($dom, dirname(__FILE__).'/medict2tsv.xsl');
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
      }
      else if ($object == 'pb') {
        $pbIns->execute(array($cell[1], $cell[2], $volumeId));
        $pbId = self::$pdo->lastInsertId();
      }
      else if ($object == 'entry') {
        $entryIns->execute(array($cell[1], $cell[2], $pbId));
        $entryId = self::$pdo->lastInsertId();
      }
      else if ($object == 'orth') {
        $orth = mb_strtoupper(mb_substr($cell[1], 0, 1)).mb_strtolower(mb_substr($cell[1], 1));
        $sort = self::sortable($cell[1]);
        $orthIns->execute(array($orth, $sort, $orth, $sort, $entryId, $pbId, $year));
      }
      else if ($object == 'term') {
        $sort = self::sortable($cell[1]);
        $termIns->execute(array($cell[1], $sort, $entryId, $pbId));
      }
      else if ($object == 'ref') {
        $refIns->execute(array($cell[1], $entryId, $pbId));
      }
      else {
        echo "ligne: ", $n, "\n";
        print_r($cell);
        exit();
      }
      $n++;
    }
    self::$pdo->commit();
  }


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
    $dsn = "sqlite:".$file;
    // if not exists, create
    if (!file_exists($file)) return self::sqlcreate($file, $sql);
    else return self::sqlopen($file, $sql);
  }
    
  /**
   * Open a pdo link
   */
  static private function sqlopen($file)
  {
    $dsn = "sqlite:".$file;
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
    @chmod($sqlite, 0775);
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
    $dom->formatOutput=true;
    $dom->substituteEntities=true;
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
    foreach ($xpath->query('namespace::*', $root) as $node ) {
      // echo $node->nodeName, " ", $node->nodeValue, "\n";
      if ($node->nodeName == 'xmlns') $xpath->registerNamespace("default", $node->nodeValue);
    }
    return $xpath;
  }
  
  /**
   * Xsl transform from xml file
   */
  static function transform($xmlfile, $xslfile, $dst=null, $pars=null)
  {
    return self::transformDoc(self::dom($xmlfile), $xslfile, $dst, $pars);
  }

  static public function transformXml($xml, $xslfile, $dst=null, $pars=null)
  {
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput=true;
    $dom->substituteEntities=true;
    $dom->loadXml($xml, LIBXML_NOENT | LIBXML_NONET | LIBXML_NSCLEAN | LIBXML_NOCDATA | LIBXML_NOWARNING);
    return self::transformDoc($dom, $xslfile, $dst, $pars);
  }

  /**
   * An xslt transformer with cache
   * TOTHINK : deal with errors
   */
  static public function transformDoc($dom, $xslfile, $dst=null, $pars=null)
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
      if(method_exists($trans, 'setSecurityPreferences')) $oldval = $trans->setSecurityPreferences($prefs);
      else if(method_exists($trans, 'setSecurityPrefs')) $oldval = $trans->setSecurityPrefs($prefs);
      else ini_set("xsl.security_prefs",  $prefs);
      $xsldom = new DOMDocument();
      $xsldom->load($xslfile);
      $trans->importStyleSheet($xsldom);
      self::$transcache[$key] = $trans;
    }
    $trans = self::$transcache[$key];
    // add params
    if(isset($pars) && count($pars)) {
      foreach ($pars as $key => $value) {
        $trans->setParameter(null, $key, $value);
      }
    }
    // return a DOM document for efficient piping
    if (is_a($dst, 'DOMDocument')) {
      $ret = $trans->transformToDoc($dom);
    }
    else if ($dst != '') {
      self::mkdir(dirname($dst));
      $trans->transformToURI($dom, $dst);
      $ret = $dst;
    }
    // no dst file, return String
    else {
      $ret =$trans->transformToXML($dom);
    }
    // reset parameters ! or they will kept on next transform if transformer is reused
    if(isset($pars) && count($pars)) {
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
    if (!mkdir($dir, 0775, true)) throw new Exception("Directory not created: ".$dir);
    @chmod(dirname($dir), 0775);  // let @, if www-data is not owner but allowed to write
    return $dir;
  } 

  /**
   * Recursive deletion of a directory
   * If $keep = true, keep directory with its acl
   */
  static function rmdir($dir, $keep = false) {
    $dir = rtrim($dir, "/\\").DIRECTORY_SEPARATOR;
    if (!is_dir($dir)) return $dir; // maybe deleted
    if(!($handle = opendir($dir))) throw new Exception("Read impossible ".$file);
    while(false !== ($filename = readdir($handle))) {
      if ($filename == "." || $filename == "..") continue;
      $file = $dir.$filename;
      if (is_link($file)) throw new Exception("Delete a link? ".$file);
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
  static function rcopy($srcdir, $dstdir) {
    $srcdir = rtrim($srcdir, "/\\").DIRECTORY_SEPARATOR;
    $dstdir = rtrim($dstdir, "/\\").DIRECTORY_SEPARATOR;
    self::mkdir($dstdir);
    $dir = opendir($srcdir);
    while(false !== ($filename = readdir($dir))) {
      if ($filename[0] == '.') continue;
      $srcfile = $srcdir.$filename;
      if (is_dir($srcfile)) self::rcopy($srcfile, $dstdir.$filename);
      else copy($srcfile, $dstdir.$filename);
    }
    closedir($dir);
  }

}

?>
