<?php

require (__DIR__.'/php/Biusante/Medict/MedictInsert.php');

use Biusante\Medict\{MedictInsert};

// MedictInsert::dico_titre();
// MedictInsert::dico_volume();
MedictInsert::truncate();
MedictInsert::insert_titre('extbnfdechambre');
