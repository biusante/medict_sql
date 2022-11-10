<?php

require_once(__DIR__ . '/php/autoload.php');

use Biusante\Medict\{Insert};

/*
Insert::dico_titre(); // charger la table des titres
Insert::dico_volume(); // extraire les infos de volume depuis la base anc
Insert::truncate(); // supprimer les données d’indexation
Insert::insert_all(); // insérer tous les titres
Insert::optimize(); // mise au point finale
*/
Insert::zip(__DIR__ . '/data_sql/'); // exporter les données pour les importer 
return;
