<?php

require_once(__DIR__ . '/php/autoload.php');

use Biusante\Medict\{MedictInsert, MedictPrepa};


MedictInsert::dico_titre(); // remplir la table des titres
MedictInsert::dico_volume(); // extraire les infos de volume depuis la base anc
MedictInsert::truncate(); // supprimer les données d’indexation
MedictInsert::insert_all(); // insérer tous les titres
MedictInsert::optimize(); // mise au point finale
return;
