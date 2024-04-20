<?php
/**
 * Part of Medict https://github.com/biusante/medict-sql
 * Copyright (c) 2021 Université de Paris, BIU Santé
 * MIT License https://opensource.org/licenses/mit-license.php
 */

declare(strict_types=1);
foreach(glob($argv[1]) as $file) {
    insert_orth($file);
}


function insert_orth($file)
{
    echo "$file\n";
    $lines = file($file);
    $count = count($lines);
    $lines[] = ''; // ensure line +1
    $tsv = "";
    for ($l=0; $l<$count; $l++) {
        $line = $lines[$l];
        $tsv .= $line;
        if (!str_starts_with($line, 'entry')) continue;
        if (str_starts_with($lines[$l+1], 'orth')) continue;
        $row = str_getcsv($line, "\t");
        if (str_starts_with($row[1], '[')) continue;
        $tsv .= "orth\t" . $row[1] . "\t\t\n";
    }
    file_put_contents($file, $tsv);
}
