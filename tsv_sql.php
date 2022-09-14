<?php

require (__DIR__.'/php/Biusante/Medict/MedictInsert.php');

use Biusante\Medict\{MedictInsert};

echo MedictInsert::sortable('Abildgaard (Pierre-Chrétien)'), "\n";
return;
// MedictInsert::dico_titre();
// MedictInsert::dico_volume();
MedictInsert::truncate();
MedictInsert::insert_titre('extbnfdechambre');
