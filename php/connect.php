<?php
/** Paramètres locaux de connexions à la base MySQL */

return new PDO(
    "mysql:host=localhost;port=3306;dbname=medict",
    'root', 
    'root'
);