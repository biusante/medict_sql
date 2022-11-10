# BIU Santé / Médica / Métadictionnaire : données

Cet entrepôt contient le code et les données pour alimenter la base MySQL pour le Métadictionnaire Médica.

## Chargement et génération

Méthode rapide : dans son instance MySQL, charger les tables (schéma et données) contenues ici [data_sql/](data_sql/). Les noms de table ont un préfixe dico_* pour éviter les collisions avec une base complexe.

**Regénération (~5mn)**

* Fournir ses paramètres (ex : connexion MySQL) en copiant [_pars.php](_pars.php) vers pars.php, et le renseigner
  <br/>$ cp _pars.php pars.php
  <br/>$ vi pars.php
* Lancer la génération des données avec le script [build.php](build.php)
  <br/>$ php build.php
* retrouver les tables générées dans [data_sql/](data_sql/).

Les étapes 

1. Générer les données à partir de fichiers XML/TEI
2. Charger la table des titres qui pilote l’insertion et la publication [dico_titre.tsv](dico_titre.tsv)
3. Charger les éventuelles information de volumes, pour les titres en plusieurs tomes [dico_volume.tsv](dico_volume.tsv)
4. Effacer toutes les données, notamment la table des mots indexés
5. Charger les volumes selon l’ordre défini dans [dico_titre.tsv](dico_titre.tsv), pour rentrer les données les plus fiables d’abord

## Arbre des fichiers

* [anc_sql/](anc_sql/) — export SQL des données de la base orginale Médica, laissé pour mémoire.
* [anc_tsv/](anc_tsv/) — données récupérées de la base orginale Médica, 1 fichier par volume, dans leur structure initiale (une ligne par page avec les titres structurants, généralement, les vedettes). Ces données sont archivées pour mémoire, leur traitement a été poussé le plus loin possible avec [Biusante\Medict\Anc](php/Biusante/Medict/Anc.php) pour alimenter le dossier ci dessous.
* [data_events/](data_events/) — données chargées dans la base SQL par l’automate [Biusante\Medict\Insert](php/Biusante/Medict/Insert.php). Ces fichiers partagent un même format, qu’ils proviennent de l’ancienne base Médica, ou des dictionnaires indexés finement en XML/TEI [medict-xml/xml](https://github.com/biusante/medict-xml/tree/main/xml). Les données anciennes peuvent être corrigées dans ces fichiers. De nouvelles données peuvent être produites dans ce format
* [data_sql/](data_sql/) — données SQL directement importable dans une base de données MySQL, par exemple avec PhpMySql.
* .gitattributes, .gitignore, README.md — 
* [medict.mwb](medict.mwb) — Schéma de la base de données au format [MySQL Workbench](https://www.mysql.com/products/workbench/).

## Format éditable “événements”

Toutes les données à charger dans la base relationnelle sont dans un format tabulaire d’“événements”,
au sens où toutes les lignes ne sont pas des données indépendantes, mais sont des sortes de commandes, produisant
un contexte pour les lignes suivantes. Ce format est réfléchi pour limiter les redondances, et faciliter la modification
humaine.

