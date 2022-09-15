<?php

require (__DIR__.'/php/Biusante/Medict/MedictInsert.php');

use Biusante\Medict\{MedictInsert};

MedictInsert::dico_titre(); // remplir la table des titres
MedictInsert::dico_volume(); // extraire les infos de volume depuis la base anc
MedictInsert::truncate(); // supprimer les données d’indexation

MedictInsert::insert_titre('extbnfdechambre');
MedictInsert::insert_titre('47661'); // Pancoucke
MedictInsert::insert_titre('32923'); // Jacoud
MedictInsert::insert_titre('34820'); // Adelon
MedictInsert::insert_titre('34823'); // Bouley
MedictInsert::insert_titre('extbnfadelon'); // Adelon
MedictInsert::insert_titre('pharma_p11247'); // Vidal
MedictInsert::insert_titre('35573'); // Pancoucke abbr
MedictInsert::insert_titre('34826'); // Andral
MedictInsert::insert_titre('07410xM'); // Encyclopédie Méthodique
MedictInsert::insert_titre('37029'); // Fabre
MedictInsert::insert_titre('47667'); // Pancoucke bio
MedictInsert::insert_titre('pharma_014023'); // Mérat
MedictInsert::insert_titre('00216'); // JamesFR

/*
MedictInsert::insert_titre('extbnfpoujol');
MedictInsert::insert_titre('61157');
*/
MedictInsert::optimize();
