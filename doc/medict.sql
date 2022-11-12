-- MySQL Script generated by MySQL Workbench
-- Sat Nov 12 13:12:43 2022
-- Model: Modèle, Medict    Version: 1.0
-- MySQL Workbench Forward Engineering

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- -----------------------------------------------------
-- Schema medict
-- -----------------------------------------------------

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
  `betacode` VARCHAR(512) NULL COMMENT 'Forme grecque, version latine désaccentuée',
  PRIMARY KEY (`id`),
  INDEX `dico_lang` (`langue` ASC),
  FULLTEXT INDEX `locutable` (`deloc`),
  INDEX `lookup` (`deforme` ASC, `langue` ASC),
  INDEX `taille` (`taille` ASC),
  INDEX `sortable` (`deforme` ASC),
  INDEX `volet_trad` (`langue` ASC, `deforme` ASC),
  CONSTRAINT `dico_lang1`
    FOREIGN KEY (`langue`)
    REFERENCES `dico_langue` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB
AUTO_INCREMENT = 406008
DEFAULT CHARACTER SET = utf8
COMMENT = 'Index de termes de tous types (vedettes, locutions, traductions, renvois…).\nLa table est en InnoDB parce que la configuration par défaut des index plein texte y est moins contraignante qu’en MyISAM.'
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
