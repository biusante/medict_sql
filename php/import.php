<?php
/**
 * Classe pour construire la base de données avec les dictionnaires
 */
 
Medict::init(dirname(dirname(dirname(__FILE__))).'/medict/medict.sqlite');
Medict::loadTei(dirname(dirname(__FILE__)).'/xml/medict37020d.xml');
Medict::loadOld(dirname(dirname(__FILE__)).'/lfs/export_livancpages_dico.csv');

class Medict
{
  /** SQLite link */
  static public $pdo;
  /** Home directory of project, absolute */
  static $home;
  /** Database absolute path */
  static private $sqlfile;
  /** Creation */
  static private $create = "
PRAGMA encoding = 'UTF-8';
PRAGMA page_size = 8192;

CREATE TABLE ref (
  -- Renvoi
  id             INTEGER,               -- ! rowid auto
  target         TEXT NOT NULL,         -- ! @target
  entry2         INTEGER,               -- ! clé article de destination (résolu après insert)
  entry          INTEGER NOT NULL,      -- ! clé article source
  pb             INTEGER NOT NULL,      -- ! clé de page
  PRIMARY KEY(id ASC)
);
CREATE INDEX ref_entry ON ref(entry);
CREATE INDEX ref_entry2 ON ref(entry2);

CREATE TABLE term (
  -- Terme (plusieurs mots)
  id             INTEGER,               -- ! rowid auto
  label          TEXT NOT NULL,         -- ! vedette telle qu’affichée
  sort           TEXT NOT NULL,         -- ! pour recherche
  entry          INTEGER NOT NULL,      -- ! clé article source
  pb             INTEGER NOT NULL,      -- ! clé de page
  PRIMARY KEY(id ASC)
);
CREATE INDEX term_label ON term(label);
CREATE INDEX term_sort ON term(sort, label);


CREATE TABLE orth (
  -- Vedette
  id             INTEGER,               -- ! rowid auto
  label          TEXT NOT NULL,         -- ! vedette telle qu’affichée
  sort           TEXT NOT NULL,         -- ! pour recherche et tri
  small          TEXT NOT NULL,         -- ! vedette courte
  key            TEXT NOT NULL,         -- ! groupement
  entry          INTEGER,               -- ? clé article source (parfois sans pour anciennes données)
  pb             INTEGER NOT NULL,      -- ! clé de page
  year           INTEGER NOT NULL,      -- ! année, pour tris
  PRIMARY KEY(id ASC)
);
CREATE INDEX orth_label ON orth(label);
CREATE INDEX orth_sort ON orth(sort, label);
CREATE INDEX orth_small ON orth(small);
CREATE INDEX orth_key ON orth(key, sort, label);
CREATE INDEX orth_pb ON orth(pb);
CREATE INDEX orth_year ON orth(year, pb);


CREATE TABLE entry (
  -- Article
  id             INTEGER,               -- ! rowid auto
  xmlid          TEXT NOT NULL,         -- ! @xml:id, identifiant xml
  pb             INTEGER NOT NULL,      -- ! clé de page de début
  pages          INTEGER NOT NULL,      -- ! nombre de pages supplémentaires
  PRIMARY KEY(id ASC)
);

CREATE TABLE pb (
  -- Page
  id             INTEGER,               -- ! rowid auto
  n              TEXT NOT NULL,         -- ! @n, numéro de page
  facs           TEXT NOT NULL,         -- ! @facs, lien à l’image
  volume         INTEGER,               -- ! clé de volume
  PRIMARY KEY(id ASC)
);

CREATE TABLE volume (
  -- Volume
  id             INTEGER,               -- ! rowid auto
  filename       TEXT NOT NULL UNIQUE,  -- ! nom de fichier
  label          TEXT NOT NULL,         -- ! unique
  year           INTEGER NOT NULL,      -- ! année pour recherche chrono
  PRIMARY KEY(id ASC)
);
CREATE INDEX volume_year ON volume(year);

  
  ";
  
  public static function init($sqlite)
  {
    self::$home = dirname(dirname(__FILE__)).'/';
    self::$sqlfile = $sqlite;
    self::$pdo = Build::sqlcreate(self::$sqlfile, self::$create);
    mb_internal_encoding("UTF-8");
  }
  
  public static function sortable($utf8)
  {
    $utf8 = mb_strtolower($utf8);
    $tr = array(
      '« ' => '"',
      ' »' => '"',
      '«' => '"',
      '»' => '"',
    );
    $utf8 = strtr($utf8, $tr);
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $utf8);
    return $ascii;
  }

  public static function loadTei($srcxml)
  {
    $dom = Build::dom($srcxml);
    $tsv = Build::transformDoc($dom, dirname(__FILE__).'/medict2sql.xsl');
    $srcname = pathinfo($srcxml, PATHINFO_FILENAME);
    $dsttsv = dirname(__FILE__).'/'.$srcname.'.tsv';
    file_put_contents($dsttsv, $tsv);

    $volumeIns = self::$pdo->prepare("INSERT INTO volume (filename, label, year) VALUES (?, ?, ?);");
    $volumeId = -1;
    $year = -1;
    $pbIns = self::$pdo->prepare("INSERT INTO pb (n, facs, volume) VALUES (?, ?, ?);");
    $pbId = -1;
    $entryIns = self::$pdo->prepare("INSERT INTO entry (xmlid, pages, pb) VALUES (?, ?, ?);");
    $entryId = -1;
    $orthIns = self::$pdo->prepare("INSERT INTO orth (label, sort, small, key, entry, pb, year) VALUES (?, ?, ?, ?, ?, ?, ?);");
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
  
  /**
  "00216x01","James 1","1746","0246","191-192","O","Acanthus. Acanus. Acapnon. Acardios. Acari ou acarus. Acaricoba. Acarna. Acarnan. Acaron. Acartum. Acarus. Acatalepsia. Acatalis. Acatastatos. Acatera. Acatharsia. Acato ou araxos. Acaulis. Acaulos. Acazdir. Accatem. Accatum. Acceleratores urinae. Accessio. Accessorius"
"00216x01","James 1","1746","0247","193-194","O","Accessorius. Accessus. Accib. Accidens. Accipiter. Accipitrina ou praedatrix. Accretio. Accurtatoria. Accusatio. Acedia. Acephalos. Acer"
"00216x01","James 1","1746","0248","195-196","O","Acer. Aceratos. Acerbus. Acerides. Acerosus. Acescentia. Acesias. Acesis. Acesius. Aceso. Acesta. Acestides. Acestis. Acestoris. Acestra. Acestrides. Acetabulum"
"00216x01","James 1","1746","0249","197-198","O","Acetabulum. Acetaria. Acetarium scorbuticum. Acetosa"
"00216x01","James 1","1746","0250","199-200","O","Acetosa. Acetosa esurina. Acetosella"
"00216x01","James 1","1746","0251","201-202","O","Acetosella. Acetum"
"00216x01","James 1","1746","0252","203-204","","Acetum"
"00216x01","James 1","1746","0253","205-206","","Acetum"
"00216x01","James 1","1746","0254","207-208","","Acetum"
"00216x01","James 1","1746","0255","209-210","","Acetum"
"00216x01","James 1","1746","0256","211-212","","Acetum"
"00216x01","James 1","1746","0257","213-214","","Acetum"
"00216x01","James 1","1746","0258","215-216","","Acetum"
"00216x01","James 1","1746","0259","217-218","","Acetum"
"00216x01","James 1","1746","0260","219-220","","Acetum"
"00216x01","James 1","1746","0261","221-222","","Acetum"
"00216x01","James 1","1746","0262","223-224","","Acetum"
"00216x01","James 1","1746","0263","225-226","O","Acetum. Acetum radicatum. Achahi. Achamelech. Achanaca. Achaovan ou achaova. Achariston"
"00216x01","James 1","1746","0264","227-228","O","Achariston. Achates. Acheir. Achemenis. Achicolum. Achillea. Achillea montana. Achilleion. Achilleios. Achilleis. Achilles. Achillis. Achimbassi. Achiotl"
"00216x01","James 1","1746","0265","229-230","O","Achiotl. Achiote. Achlades. Achlys"
"00216x01","James 1","1746","0266","231-232","O","Achlys. Achmadium ou achimadium. Achne. Achor"
"00216x01","James 1","1746","0267","233-234","O","Achor. Achoristos. Achourou. Achras. Achreion. Achroi"
"00216x01","James 1","1746","0268","235-236","O","Achroi. Achromos. Achrous. Achy. Achyron. Acia. Acicys. Acida"
  */
  public static function loadOld($csvfile)
  {
    $handle = fopen($csvfile, "r");
    $volume = null;
    $volumeIns = self::$pdo->prepare("INSERT INTO volume (filename, label, year) VALUES (?, ?, ?);");
    $volumeId = -1;
    $pbIns = self::$pdo->prepare("INSERT INTO pb (n, facs, volume) VALUES (?, ?, ?);");
    $pbId = -1;
    $orthIns = self::$pdo->prepare("INSERT INTO orth (label, sort, small, key, pb, year) VALUES (?, ?, ?, ?, ?, ?);");
    
    self::$pdo->beginTransaction();
    $lastorth = '';
    while ($cell = fgetcsv($handle)) {
      if ($cell[0] == '32923X14') $cell[0] = '32923x14';
      // nouveau volume, insérer
      if ($volume != $cell[0]) {
        try {
          $volumeIns->execute(array($cell[0], $cell[1], $cell[2]));
        }
        catch (Exception $e) {
          echo "last volume ", $volume, "\n";
          print_r($cell);
        }
        $volumeId = self::$pdo->lastInsertId();
        $volume = $cell[0];
      }
      // $facs = 'https://iiif.archivelab.org/iiif/BIUSante_'.$cell[0].'$'.$cell[3].'/full/full/0/default.jpg';
      $facs = '//www.biusante.parisdescartes.fr/images/livres/'.$cell[0].'/'.$cell[3].'.jpg';
      $pbIns->execute(array($cell[4], $facs, $volumeId));
      $pbId = self::$pdo->lastInsertId();
      // if (!$cell[5]) continue; // article sur plusieurs pages
      
      if (strpos($cell[6], '/') !== false) {
        $words = preg_split('@ */ *@', $cell[6]);
      }
      // Coeur [A. Béclard], ne pas couper sur le point
      else if (strpos($cell[6], '[') !== false) {
        $words = explode('|', $cell[6]);
      }
      else {
        $words = explode('. ', $cell[6]);
      }
      
      foreach ($words as $orth) {
        if (!$orth) continue;
        if ($orth == $lastorth) continue;
        $lastorth = $orth;
        $sort = self::sortable($orth);
        preg_match('@(.*?)[\. \(\[,]@', $orth.' ', $matches);
        $small = $matches[1];
        $key = self::sortable($small);
        $orthIns->execute(array($orth, $sort, $small, $key, $pbId, $cell[2]));
      }
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
