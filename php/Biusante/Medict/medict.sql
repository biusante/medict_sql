-- MySQL Script generated by MySQL Workbench
-- Tue Dec 19 14:11:41 2023
-- Model: Modèle, Medict    Version: 1.0
-- MySQL Workbench Forward Engineering

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- -----------------------------------------------------
-- Schema medict
-- -----------------------------------------------------

-- -----------------------------------------------------
-- Table `livanc`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `livanc` (
  `clenum` INT UNSIGNED NOT NULL,
  `datemod` DATE NOT NULL,
  `cote` VARCHAR(50) NULL DEFAULT '',
  `cotemere` VARCHAR(50) NULL DEFAULT NULL,
  `cotereelle` VARCHAR(50) NULL DEFAULT NULL,
  `externe` VARCHAR(25) NULL DEFAULT NULL,
  `biblio` VARCHAR(5) NULL DEFAULT NULL,
  `typedoc` VARCHAR(25) NULL DEFAULT NULL,
  `auteurscum` TEXT NULL DEFAULT NULL,
  `url_externe` TEXT NULL DEFAULT NULL,
  `titre` TEXT NULL DEFAULT NULL,
  `commentaire` TEXT NULL DEFAULT NULL,
  `commentaire2` TEXT NULL DEFAULT NULL,
  `commentaire3` TEXT NULL DEFAULT NULL,
  `editeur` TEXT NULL DEFAULT NULL,
  `annee` VARCHAR(50) NULL DEFAULT NULL,
  `droits` TEXT NULL DEFAULT NULL,
  `intro` TEXT NULL DEFAULT NULL,
  `introscum` TEXT NULL DEFAULT NULL,
  `manus_orig` TEXT NULL DEFAULT NULL,
  `perio_tit` TEXT NULL DEFAULT NULL,
  `perio_depouill` TEXT NULL DEFAULT NULL,
  `refbiogr` INT(10) UNSIGNED NULL DEFAULT NULL,
  `maladie` VARCHAR(100) NULL DEFAULT NULL,
  `these_note` TEXT NULL DEFAULT NULL,
  `tout` LONGTEXT NULL DEFAULT NULL,
  `statut` VARCHAR(100) NULL DEFAULT NULL,
  `auteur` TEXT NULL DEFAULT NULL,
  `annee_iso` VARCHAR(20) NULL DEFAULT NULL,
  `droits_mat` TEXT NULL DEFAULT NULL,
  `fille` VARCHAR(10) NULL DEFAULT NULL,
  `licence` VARCHAR(250) NULL DEFAULT NULL,
  `entree` DATE NULL DEFAULT NULL,
  `img_vignette` VARCHAR(50) NULL DEFAULT NULL,
  `sous_groupe` VARCHAR(50) NULL DEFAULT NULL,
  `url_internetarchive` VARCHAR(250) NULL DEFAULT NULL,
  PRIMARY KEY (`clenum`),
  INDEX `timestamp_modification` (`datemod` ASC),
  INDEX `cote` (`cote` ASC),
  INDEX `cotemere` (`cotemere` ASC),
  INDEX `statut` (`statut` ASC),
  INDEX `index_livanc_biblio` (`biblio` ASC),
  INDEX `index_livanc_maladie` (`maladie` ASC),
  INDEX `index_livanc_entree` (`entree` ASC),
  INDEX `index_livanc_sous_groupe` (`sous_groupe` ASC),
  INDEX `index_livanc_annee` USING BTREE (`annee`),
  FULLTEXT INDEX `idx_livanc_intro` (`intro`),
  FULLTEXT INDEX `fulltext_livanc_intro` (`intro`))
ENGINE = MyISAM
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `dico_titre`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `dico_titre` (
  `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Clé prinaire incrémentée automatiquement',
  `cote` VARCHAR(32) NULL DEFAULT NULL COMMENT 'Cote du dictionnaire',
  `nom` VARCHAR(255) NOT NULL COMMENT 'Nom court du dictionnaire, affichable',
  `annee` SMALLINT(6) UNSIGNED NOT NULL COMMENT 'Date de parution du premier volume (clé de tri)',
  `import_ordre` SMALLINT UNSIGNED NULL COMMENT 'Numéro d’ordre pour l’import (priorité aux vedettes récentes et relues)',
  `vols` SMALLINT(6) NOT NULL DEFAULT '1' COMMENT 'Nombre de volumes',
  `pages` MEDIUMINT(9) NULL DEFAULT NULL COMMENT 'Nombre de pages',
  `entrees` MEDIUMINT(9) NULL DEFAULT NULL COMMENT 'Nombre d’entrées',
  `nomdate` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Nom alternatif (avec date)',
  `an_max` SMALLINT(6) UNSIGNED NULL DEFAULT NULL COMMENT 'Date de parution du dernier volume si plusieurs sur plusieurs années',
  `class` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Type de dictionnaire, mots clés séparés d’espaces, utilisés dans le formulaire de sélection des titres',
  `orth_langue` CHAR(3) NULL DEFAULT NULL COMMENT 'Code de langue des vedettes si différentes du français (la, grc), 1 cas \"fr,la\" pharma_019128',
  `entry_langue` CHAR(3) NULL DEFAULT NULL COMMENT 'Code langue des articles si différent du français (James en, la)',
  `bibl` TEXT NULL COMMENT 'Ligne bibliographique ISBD',
  `livanc` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Clé dans la table livanc',
  PRIMARY KEY (`id`),
  INDEX `livanc` (`livanc` ASC),
  INDEX `import` (`import_ordre` ASC, `annee` ASC))
ENGINE = MyISAM
AUTO_INCREMENT = 52
DEFAULT CHARACTER SET = utf8
COMMENT = 'Table des titres des dictionnaires (pouvant concerner plusieurs volumes),  avec informations spéciales dico';


-- -----------------------------------------------------
-- Table `livancpages`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `livancpages` (
  `cote` VARCHAR(32) NOT NULL,
  `refimg` VARCHAR(32) NULL DEFAULT NULL,
  `refbiogr` VARCHAR(255) NULL DEFAULT NULL,
  `refbiogrcom` VARCHAR(50) NULL DEFAULT NULL,
  `chapitre` TEXT NULL DEFAULT NULL,
  `page` VARCHAR(32) NULL DEFAULT NULL,
  `pagecle` VARCHAR(1) NULL DEFAULT NULL,
  `numauto` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pagimgtxt` TEXT NULL DEFAULT NULL,
  `dossier` VARCHAR(45) NULL DEFAULT NULL,
  `dico` VARCHAR(45) NULL DEFAULT NULL,
  `cre_date` DATE NULL DEFAULT NULL,
  `cre_aut` VARCHAR(64) NULL DEFAULT NULL,
  `url` SMALLINT(5) UNSIGNED NULL DEFAULT NULL,
  `zoom` VARCHAR(45) NULL DEFAULT NULL,
  `mod_date` DATE NULL DEFAULT NULL,
  `pagimg` VARCHAR(1) NULL DEFAULT NULL,
  `titre` TEXT NULL DEFAULT NULL,
  `perio_tit` TEXT NULL DEFAULT NULL,
  `perio_depouill` TEXT NULL DEFAULT NULL,
  `annee` SMALLINT(6) NULL DEFAULT NULL,
  `nomdico` VARCHAR(255) NULL DEFAULT NULL,
  `pagimgtxtcom` VARCHAR(1) NULL DEFAULT NULL,
  `pagimgcle` LONGTEXT NULL DEFAULT NULL,
  `pagimgtrad` LONGTEXT NULL DEFAULT NULL,
  `portrait` VARCHAR(3) NULL DEFAULT NULL,
  `largeur` INT(11) NULL DEFAULT NULL,
  `hauteur` INT(11) NULL DEFAULT NULL,
  `images_diffusees` VARCHAR(5) NOT NULL DEFAULT 'jpg',
  `images_brutes` VARCHAR(5) NULL DEFAULT NULL,
  `archivageFile1TimestampModification` TIMESTAMP NULL DEFAULT NULL,
  `archivageFile2TimestampModification` TIMESTAMP NULL DEFAULT NULL,
  `archivageDataTimestampModification` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`numauto`),
  INDEX `Index_cote` (`cote` ASC),
  INDEX `Index_refimg` (`refimg` ASC),
  INDEX `Index_page` (`page` ASC),
  INDEX `Index_pagecle` (`pagecle` ASC),
  INDEX `Index_dossier` (`dossier` ASC),
  INDEX `Index_dico` (`dico` ASC),
  INDEX `Index_cre_date` (`cre_date` ASC),
  INDEX `Index_cre_aut` (`cre_aut` ASC),
  INDEX `Index_url` (`url` ASC),
  INDEX `Index_zoom` (`zoom` ASC),
  INDEX `Index_mod_date` (`mod_date` ASC),
  INDEX `Index_pagimg` (`pagimg` ASC),
  INDEX `Index_annee` (`annee` ASC),
  INDEX `Index_pagimgtxtcom` (`pagimgtxtcom` ASC),
  INDEX `Index_portrait` (`portrait` ASC),
  INDEX `archivageFile1TimestampModification` (`archivageFile1TimestampModification` ASC),
  INDEX `archivageFile2TimestampModification` (`archivageFile2TimestampModification` ASC),
  INDEX `archivageDataTimestampModification` (`archivageDataTimestampModification` ASC),
  INDEX `largeur_index` (`largeur` ASC),
  INDEX `livancpages_cote_refimg` (`cote` ASC, `page` ASC),
  FULLTEXT INDEX `Index_chapitre` (`chapitre`),
  FULLTEXT INDEX `Index_pagimgtxt` (`pagimgtxt`),
  FULLTEXT INDEX `Index_pagimgcle` (`pagimgcle`),
  FULLTEXT INDEX `Index_pagimgtrad` (`pagimgtrad`))
ENGINE = MyISAM
AUTO_INCREMENT = 8139525
DEFAULT CHARACTER SET = utf8
PACK_KEYS = 1;


-- -----------------------------------------------------
-- Table `dico_volume`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `dico_volume` (
  `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Clé prinaire incrémentée automatiquement',
  `dico_titre` SMALLINT UNSIGNED NOT NULL COMMENT 'Lien au dictionnaire source',
  `titre_nom` VARCHAR(255) NOT NULL COMMENT 'Titre, nom court, redondance dico_titre',
  `titre_annee` SMALLINT UNSIGNED NOT NULL COMMENT 'Titre, année, redondance dico_titre',
  `livanc` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Lien à la tabéle livanc',
  `volume_cote` VARCHAR(32) NOT NULL COMMENT 'Volume, cote, nécessaire pour construire url',
  `volume_soustitre` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Volume, partie du titre à ajouter à propos du volume',
  `volume_annee` SMALLINT UNSIGNED NOT NULL COMMENT 'Volume, année de publication',
  PRIMARY KEY (`id`),
  INDEX `dico_titre` (`dico_titre` ASC),
  INDEX `livanc` (`livanc` ASC),
  INDEX `cote` (`volume_cote` ASC))
ENGINE = MyISAM
AUTO_INCREMENT = 385345
DEFAULT CHARACTER SET = utf8
COMMENT = 'Volume, informations suffisant à construire une référence bibliographique (sans n° de page)'
PACK_KEYS = 1;


-- -----------------------------------------------------
-- Table `dico_entree`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `dico_entree` (
  `id` MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Clé prinaire incrémentée automatiquement',
  `dico_titre` SMALLINT UNSIGNED NOT NULL COMMENT 'Lien au titre source',
  `dico_volume` SMALLINT UNSIGNED NOT NULL COMMENT 'Lien à la table de volume (pour ref biblio)',
  `volume_annee` SMALLINT UNSIGNED NOT NULL COMMENT 'Volume, année de publication, nécessaire pour tri, redondance dico_volume',
  `vedette` TEXT NOT NULL COMMENT 'Un ou plusieurs mots en vedette identifiant l’article dans l’ordre alphabétique de l’ouvrage',
  `page` VARCHAR(32) NOT NULL COMMENT 'Page de début de l’entrée',
  `refimg` SMALLINT UNSIGNED NOT NULL COMMENT 'No d’image en paramètre d’url',
  `livancpages` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Lien à la table des pages',
  `pps` SMALLINT UNSIGNED NULL DEFAULT NULL COMMENT 'Nombre de saut de pages dans l’article',
  `page2` VARCHAR(32) NULL DEFAULT NULL COMMENT 'Page de fin de l’entrée si différente du début',
  PRIMARY KEY (`id`),
  INDEX `livancpages` (`livancpages` ASC),
  INDEX `vedette` (`vedette`(100) ASC),
  INDEX `dico_volume` (`dico_volume` ASC),
  INDEX `dico_titre` (`dico_titre` ASC))
ENGINE = MyISAM
AUTO_INCREMENT = 385345
DEFAULT CHARACTER SET = utf8
COMMENT = 'Entrée dans un ouvrage, liée à la page de la vedette  (volet entrées de l’interface)'
PACK_KEYS = 1;


-- -----------------------------------------------------
-- Table `dico_reltype`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `dico_reltype` (
  `id` TINYINT UNSIGNED NOT NULL,
  `nom` VARCHAR(50) NOT NULL COMMENT 'Intitulé',
  PRIMARY KEY (`id`))
ENGINE = MyISAM
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_bin
COMMENT = 'Désignation des types de relations (facultatif, pour cohérence modèle)';


-- -----------------------------------------------------
-- Table `dico_langue`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `dico_langue` (
  `id` TINYINT UNSIGNED NOT NULL,
  `code` CHAR(3) NOT NULL COMMENT 'Code langue sur 3 caractères',
  PRIMARY KEY (`id`))
ENGINE = MyISAM
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_bin
COMMENT = 'Code de langue avec numéro d’ordre de priorité (facultatif, pour cohérence modèle)';


-- -----------------------------------------------------
-- Table `dico_terme`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `dico_terme` (
  `id` MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Clé prinaire incrémentée automatiquement',
  `forme` VARCHAR(1024) NOT NULL COMMENT 'Forme affichable originale (accents et majuscules)',
  `langue` TINYINT UNSIGNED NULL COMMENT 'N° langue du terme',
  `deforme` VARCHAR(512) NOT NULL COMMENT 'Clé identifiante, minuscules sans accents',
  `deloc` VARCHAR(512) NULL COMMENT 'Pour recherche dans les locutions, minuscules latines sans accents',
  `taille` SMALLINT UNSIGNED NOT NULL COMMENT 'Taille du terme en caractères',
  `mots` TINYINT UNSIGNED NOT NULL COMMENT 'Nombre de mots pleins dans le terme',
  `uvji` VARCHAR(512) NULL COMMENT 'Clé de regroupement pour forme latine sans uj :  u -> v, j -> i',
  `inverse` VARCHAR(512) NOT NULL COMMENT 'Lettres de deforme en ordre inverse, pour recherche par terminaison',
  `betacode` VARCHAR(512) NULL COMMENT 'Pour les vedettes grecques, translittération betacode',
  PRIMARY KEY (`id`),
  INDEX `dico_lang` (`langue` ASC),
  FULLTEXT INDEX `locutable` (`deloc`),
  INDEX `lookup` (`deforme` ASC, `langue` ASC),
  INDEX `taille` (`taille` ASC),
  INDEX `sortable` (`deforme` ASC),
  INDEX `volet_trad` (`langue` ASC, `deforme` ASC),
  INDEX `uvji` (`uvji` ASC),
  INDEX `inverse` (`inverse` ASC),
  INDEX `betacode` (`betacode` ASC),
  CONSTRAINT `dico_lang1`
    FOREIGN KEY (`langue`)
    REFERENCES `dico_langue` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB
AUTO_INCREMENT = 406008
DEFAULT CHARACTER SET = utf8
COMMENT = 'Index de termes de tous types (vedettes, locutions, traductions, renvois…). La table est en InnoDB parce que la configuration par défaut des index plein texte y est moins contraignante qu’en MyISAM.'
PACK_KEYS = 1;


-- -----------------------------------------------------
-- Table `dico_rel`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `dico_rel` (
  `id` MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Clé principale auto',
  `dico_titre` SMALLINT UNSIGNED NOT NULL COMMENT 'Clé titre dictionnaire, nécessaire pour filtrage efficace.',
  `volume_annee` SMALLINT UNSIGNED NOT NULL COMMENT 'Année de publication du volume, pour tri, redondance dico_volume',
  `dico_entree` MEDIUMINT UNSIGNED NOT NULL COMMENT 'Article source de la relation',
  `page` CHAR(16) NOT NULL COMMENT 'N° de page où figure  la relation, ',
  `refimg` SMALLINT UNSIGNED NOT NULL COMMENT 'No séquentiel d’image de la page, en paramètre d’url',
  `dico_terme` MEDIUMINT UNSIGNED NOT NULL COMMENT 'Forme graphique et langue du terme lié',
  `reltype` TINYINT UNSIGNED NOT NULL COMMENT 'Type de relation (vedette, suggestion, traduction…)',
  `orth` TINYINT NULL COMMENT 'Si vedette dans une clique, un drapeau pour ne pas prendre cette relation dans un groupe',
  `clique` INT UNSIGNED NULL COMMENT 'Clé de regroupement de plusieurs termes dans cette relation',
  PRIMARY KEY (`id`),
  INDEX `dico_entree` (`dico_entree` ASC),
  INDEX `dico_titre` (`dico_titre` ASC),
  INDEX `dico_reltype` (`reltype` ASC),
  INDEX `dico_terme` (`dico_terme` ASC),
  INDEX `volet_index` (`dico_terme` ASC, `dico_titre` ASC, `reltype` ASC, `orth` ASC, `dico_entree` ASC),
  INDEX `volet_entree` (`dico_terme` ASC, `reltype` ASC, `dico_titre` ASC, `volume_annee` ASC, `dico_entree` ASC),
  INDEX `volet_trad` (`dico_terme` ASC, `reltype` ASC, `volume_annee` ASC, `dico_entree` ASC),
  INDEX `volet_sugg1` (`reltype` ASC, `dico_terme` ASC, `clique` ASC, `volume_annee` ASC, `refimg` ASC),
  INDEX `clique` (`clique` ASC, `reltype` ASC, `dico_terme` ASC))
ENGINE = MyISAM
DEFAULT CHARACTER SET = utf8
COMMENT = 'Relation typée entre un terme et une entrée. ';


SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
