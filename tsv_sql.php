<?php

require (__DIR__.'/php/Biusante/Medict/MedictInsert.php');

use Biusante\Medict\{MedictInsert};

// MedictInsert::truncate();
// MedictInsert::dico_titre();
// MedictInsert::dico_volume();

MedictInsert::insert_volume(__DIR__ . '/import/extbnfdechambrex050.tsv');
