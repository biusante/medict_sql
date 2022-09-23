<?php

require_once(__DIR__ . '/php/autoload.php');

use Biusante\Medict\{MedictInsert};

MedictInsert::dico_titre(); // remplir la table des titres
MedictInsert::dico_volume(); // extraire les infos de volume depuis la base anc

$bibl = [
    '37020d',
    '37020d~lat',
    '37020d~grc',
    '37020d~deu',
    '37020d~eng',
    '37020d~ita',
    '37020d~spa',
    '27898',
    '00152',
    '07399',
];

$bibl = [
    'pharma_019128',
    'extbnfrivet',
    'pharma_019129',
    '45392',
    '07410xC',
    '07410xM',
    '146144',
    'extalfobuchoz',
    'pharma_013686',
    '32546',
    'pharma_019127',
    '30944',
    '31873',
    'pharma_019428',
    '00216',
    '07399',
    '07399~hex',
    '00216~tab',
    '01686',
    '01686~ind',
    'pharma_000103',
    '01208',
    '08757',
    '08746',
    '00152',
];

MedictInsert::truncate(); // supprimer les données d’indexation
MedictInsert::insert_all();
MedictInsert::optimize();
return;
