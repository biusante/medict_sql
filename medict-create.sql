USE db_medict;
SET SQL_MODE='ALLOW_INVALID_DATES'; -- '0000-00-00 00:00:00'
CREATE TABLE `livancpages` (
  `cote` varchar(32) NOT NULL,
  `refimg` varchar(32) DEFAULT NULL,
  `refbiogr` varchar(255) DEFAULT NULL,
  `refbiogrcom` varchar(50) DEFAULT NULL,
  `chapitre` text,
  `page` varchar(32) DEFAULT NULL,
  `pagecle` varchar(1) DEFAULT NULL,
  `pagimgtxt` text,
  `numauto` bigint unsigned NOT NULL DEFAULT '0',
  `dossier` varchar(45) DEFAULT NULL,
  `dico` varchar(45) DEFAULT NULL,
  `cre_date` date DEFAULT NULL,
  `cre_aut` varchar(64) DEFAULT NULL,
  `url` smallint unsigned DEFAULT NULL,
  `zoom` varchar(45) DEFAULT NULL,
  `mod_date` date DEFAULT NULL,
  `pagimg` varchar(1) DEFAULT NULL,
  `titre` text,
  `perio_tit` text,
  `perio_depouill` text,
  `annee` smallint DEFAULT NULL,
  `nomdico` varchar(255) DEFAULT NULL,
  `pagimgtxtcom` varchar(1) DEFAULT NULL,
  `pagimgcle` longtext,
  `pagimgtrad` longtext,
  `portrait` varchar(3) DEFAULT NULL,
  `largeur` int DEFAULT NULL,
  `hauteur` int DEFAULT NULL,
  `images_diffusees` varchar(5) NOT NULL DEFAULT 'jpg',
  `images_brutes` varchar(5) DEFAULT NULL,
  `archivageFile1TimestampModification` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `archivageFile2TimestampModification` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `archivageDataTimestampModification` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`numauto`),
  KEY `Index_cote` (`cote`),
  KEY `Index_refimg` (`refimg`),
  KEY `Index_page` (`page`),
  KEY `Index_pagecle` (`pagecle`),
  KEY `Index_dossier` (`dossier`),
  KEY `Index_dico` (`dico`),
  KEY `Index_cre_date` (`cre_date`),
  KEY `Index_cre_aut` (`cre_aut`),
  KEY `Index_url` (`url`),
  KEY `Index_zoom` (`zoom`),
  KEY `Index_mod_date` (`mod_date`),
  KEY `Index_pagimg` (`pagimg`),
  KEY `Index_annee` (`annee`),
  KEY `Index_pagimgtxtcom` (`pagimgtxtcom`),
  KEY `Index_portrait` (`portrait`),
  KEY `archivageFile1TimestampModification` (`archivageFile1TimestampModification`),
  KEY `archivageFile2TimestampModification` (`archivageFile2TimestampModification`),
  KEY `archivageDataTimestampModification` (`archivageDataTimestampModification`),
  KEY `largeur_index` (`largeur`),
  FULLTEXT KEY `Index_chapitre` (`chapitre`),
  FULLTEXT KEY `Index_pagimgtxt` (`pagimgtxt`),
  FULLTEXT KEY `Index_pagimgcle` (`pagimgcle`),
  FULLTEXT KEY `Index_pagimgtrad` (`pagimgtrad`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 PACK_KEYS=1;

CREATE TABLE `livanc` (
  `clenum` int unsigned NOT NULL,
  `datemod` date NOT NULL,
  `cote` varchar(50) DEFAULT '',
  `cotemere` varchar(50) DEFAULT NULL,
  `cotereelle` varchar(50) DEFAULT NULL,
  `externe` varchar(25) DEFAULT NULL,
  `biblio` varchar(5) DEFAULT NULL,
  `typedoc` varchar(25) DEFAULT NULL,
  `auteurscum` text,
  `url_externe` text,
  `titre` text,
  `commentaire` text,
  `commentaire2` text,
  `commentaire3` text,
  `editeur` text,
  `annee` varchar(50) DEFAULT NULL,
  `droits` text,
  `intro` text,
  `introscum` text,
  `manus_orig` text,
  `perio_tit` text,
  `perio_depouill` text,
  `refbiogr` int unsigned DEFAULT NULL,
  `maladie` varchar(100) DEFAULT NULL,
  `these_note` text,
  `tout` longtext,
  `statut` varchar(100) DEFAULT NULL,
  `auteur` text,
  `annee_iso` varchar(20) DEFAULT NULL,
  `droits_mat` text,
  `fille` varchar(10) DEFAULT NULL,
  `licence` varchar(250) DEFAULT NULL,
  `entree` date DEFAULT NULL,
  `img_vignette` varchar(50) DEFAULT NULL,
  `sous_groupe` varchar(50) DEFAULT NULL,
  `url_internetarchive` varchar(250) DEFAULT NULL,
  PRIMARY KEY (`clenum`),
  KEY `timestamp_modification` (`datemod`),
  KEY `cote` (`cote`),
  KEY `cotemere` (`cotemere`),
  KEY `statut` (`statut`),
  KEY `index_livanc_biblio` (`biblio`),
  KEY `index_livanc_maladie` (`maladie`),
  KEY `index_livanc_entree` (`entree`),
  KEY `index_livanc_sous_groupe` (`sous_groupe`),
  KEY `index_livanc_annee` (`annee`) USING BTREE,
  FULLTEXT KEY `idx_livanc_intro` (`intro`),
  FULLTEXT KEY `fulltext_livanc_intro` (`intro`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;


CREATE TABLE orth (
  -- Vedette
  numauto        BIGINT UNSIGNED NOT NULL,
  label          TEXT NOT NULL,         -- ! vedette telle qu’affichée
  label_sort     TEXT NOT NULL,         -- ! pour recherche et tri
  small          TEXT NOT NULL,         -- ! vedette courte
  small_sort     TEXT NOT NULL,         -- ! groupement pour termes complexes
  entry          BIGINT UNSIGNED NOT NULL REFERENCES entry(numauto), -- ? clé article source (parfois sans pour anciennes données)
  livancpages    BIGINT UNSIGNED NOT NULL REFERENCES livancpages(numauto), -- ! clé de page
  year           INTEGER NOT NULL,      -- ! année, pour tris

  PRIMARY KEY (numauto)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 PACK_KEYS=1;

DROP TABLE entry;
CREATE TABLE entry (
  numauto        BIGINT UNSIGNED NOT NULL,
  xmlid          TEXT NOT NULL
    COMMENT '! @xml:id, identifiant xml',
  livancpages    BIGINT UNSIGNED NOT NULL
    COMMENT '! clé de page de début',
  pages          INTEGER NOT NULL
    COMMENT '! nombre de pages supplémentaires',


  INDEX (livancpages) REFERENCES livancpages(numauto),
  PRIMARY KEY (numauto)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 PACK_KEYS=1
    COMMENT 'Article de dictionnaire';



CREATE TABLE IF NOT EXISTS `medict`.`orth` (
  `numauto` BIGINT(19) UNSIGNED NOT NULL,
  `label` TEXT NOT NULL COMMENT 'Vedette complète',
  `label_sort` TEXT NOT NULL,
  `small` TEXT NOT NULL,
  `small_sort` TEXT NOT NULL,
  `year` INT(11) NOT NULL,
  `livancpages` BIGINT(19) UNSIGNED NOT NULL,
  `entry` BIGINT(19) UNSIGNED NOT NULL,
  PRIMARY KEY (`numauto`),
  INDEX `orth_livancpages1_idx` (`livancpages` ASC) VISIBLE,
  INDEX `orth_entry1_idx` (`entry` ASC) VISIBLE)
ENGINE = MyISAM
DEFAULT CHARACTER SET = utf8mb3
COMMENT = 'Vedette'
PACK_KEYS = 1