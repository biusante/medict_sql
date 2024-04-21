<?php
/**
 * Part of Medict https://github.com/biusante/medict-sql
 * Copyright (c) 2021 Université de Paris, BIU Santé
 * MIT License https://opensource.org/licenses/mit-license.php
 */

declare(strict_types=1);


foreach(glob($argv[1]) as $file) {
    Events::orthCap($file);
}

/**
 * Classe d'outils pour améliorer les données.
 */
class Events
{
    static $dic = [];

    static function orthCap($file)
    {
        echo "$file\n";
        $lines = file($file);
        $count = count($lines);
        $lines[] = ''; // ensure line +1
        $tsv = "";
        for ($l=0; $l<$count; $l++) {
            $line = $lines[$l];
            if (!str_starts_with($line, 'orth')) {
                $tsv .= $line;
                continue;
            }
            $row = str_getcsv($line, "\t");
            $orth = $row[1];
            $initial = mb_substr($orth, 0, 1);
            // tout cap
            if ($orth == mb_strtoupper($orth)) {
                $tsv .= "orth\t" . $initial .  mb_strtolower(mb_substr($orth, 1)) . "\t" . $row[2] . "\t\n";
                continue;
            }
            // minuscule au début
            if ($initial != mb_strtoupper($initial)) {
                $tsv .= "orth\t" . mb_strtoupper($initial) .  mb_substr($orth, 1) . "\t" . $row[2] . "\t\n";
                continue;
            }
            $tsv .= $line;
            continue;
    }
        file_put_contents($file, $tsv);
    }

    static function printDic() {
        arsort(self::$dic);
        $count = count(self::$dic);
        $l = 0;
        foreach (self::$dic as $key=>$count) {
            echo ++$l . "\t" . $key . "\t" . $count . "\n";
        }
    }

    static function parenthesis($file)
    {
        $lines = file($file);
        $count = count($lines);
        for ($l=0; $l<$count; $l++) {
            $line = $lines[$l];
            if (!str_starts_with($line, 'orth')) continue;
            $row = str_getcsv($line, "\t");
            if (!preg_match_all('/\([^\t\)]*\)/', $line, $matches)) continue;
            foreach ($matches[0] as $m) {
                if (isset(self::$dic[$m])) {
                    self::$dic[$m]++;
                }
                else {
                    self::$dic[$m] = 1;
                }
            }
        }
    }

    static function insert_orth($file)
    {
        echo "ALREADY DONE\n";
        return;
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

}