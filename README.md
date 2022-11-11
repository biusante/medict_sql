# BIU Santé / Médica / Métadictionnaire : données

Cet entrepôt contient le code et les données pour alimenter la base MySQL pour le Métadictionnaire Médica,
servie par l’application web [medict](https://github.com/biusante/medict#readme).

## Chargement rapide

Dans son instance MySQL, charger les tables (schéma et données) contenues dans [data_sql/](data_sql/). Les noms de table ont un préfixe dico_* pour éviter les collisions avec une base complexe. Chaque table est zippée comme conseillé par PhpMyAdmin en cas d’import en ligne.

## Dépendances

PHP en ligne de commande, version récente (7.3+)
 * Windows, ajouter le chemin du programme php.exe dans son “[Path](https://www.php.net/manual/fr/faq.installation.php#faq.installation.addtopath)”
 * Linux, ajouter le paquet php ligne de commande de sa distribution, ex pour Debian : apt install php-cli

Modules PHP
  * intl — pour normalisation du grec, [Normalizer](https://www.php.net/manual/fr/class.normalizer.php)
  * pdo_mysql — connexion à la base de données
  * mbstring — traitement de chaînes unicode
  * xsl — xsltproc, pour transformations XML/TEI

## Génération des tables relationnelles

* Récupérer l’entrepôt des fichiers xml/tei des dictionnaires balisés finement
  <br/>mes_source$ git clone https://github.com/biusante/medict_xml.git
* Récupérer cet entrepôt
  <br/>mes_source$ git clone https://github.com/biusante/medict_sql.git
* Entrer dans le dossier de génération des données
  <br/>mes_source$ cd medict_sql
* Fournir ses paramètres (ex : connexion MySQL) en copiant [_pars.php](_pars.php) vers pars.php, et le renseigner
  <br/>medict_sql$ cp _pars.php pars.php
  <br/>medict_sql$ vi pars.php
* Lancer la génération des données avec le script [build.php](build.php)
  <br/>medict_sql$ php build.php
  <br/>Attendre une vingtaine de minutes à voir se charger les volumes (le premier, Littré 1873, prend 300 s.)
* retrouver les tables générées dans [data_sql/](data_sql/).

Les étapes 

1. Générer les données à partir de fichiers XML/TEI
2. Charger la table des titres qui pilote l’insertion [dico_titre.tsv](dico_titre.tsv)
3. Charger les éventuelles informations de volumes, pour les titres en plusieurs tomes [dico_volume.tsv](dico_volume.tsv)
4. Effacer toutes les données, notamment la table des mots indexés
5. Charger les volumes selon l’ordre défini dans [dico_titre.tsv](dico_titre.tsv)

## Arbre des fichiers

* [data_sql/](data_sql/) — GÉNÉRÉ, données SQL directement importable dans une base de données MySQL, par exemple avec PhpMySql.
* [pars.php](pars.php) — MODIFIABLE, fichier obligatoire à créer avec les paramètre de connexion et des chemins, sur le modèle de [_pars.php](_pars.php).
* [build.php](build.php) — script de génération de la totalité des données.
* [dico_titre.tsv](dico_titre.tsv) — MODIFIABLE, données bibliographiques par titre, copier dans la base de données, utilisé dans l’application.
* [dico_volume.tsv](dico_volume.tsv) — MODIFIABLE, données bibliographiques pour titres de plus d’un volume.
* [data_events/](data_events/) — MODIFIABLE et GÉNÉRÉ (source xml), données chargées dans la base SQL par l’automate [Biusante\Medict\Insert](php/Biusante/Medict/Insert.php). Ces fichiers partagent un même format, qu’ils proviennent de l’ancienne base Médica, ou des dictionnaires indexés finement en XML/TEI [medict-xml/xml](https://github.com/biusante/medict-xml/tree/main/xml). Les données anciennes peuvent être corrigées dans ces fichiers. De nouvelles données peuvent être produites dans ce format.
* [exports/](exports/) — GÉNÉRÉ, des fichiers qui ont été demandé pour une vue sur les données.
* [medict.mwb](medict.mwb), [medict.svg](medict.svg), [medict.png](medict.png) — Schéma de la base de données au format [MySQL Workbench](https://www.mysql.com/products/workbench/), avec des vues image vectorielle (svg) ou matricielle (png).
* [anc_sql/](anc_sql/) — ARCHIVÉ, export SQL des données de la base orginale Médica, laissé pour mémoire.
* [anc_tsv/](anc_tsv/) — ARCHIVÉ, données récupérées de la base orginale Médica, 1 fichier par volume, dans leur structure initiale (une ligne par page avec les titres structurants, généralement, les vedettes). Ces données sont archivées pour mémoire, leur traitement a été poussé le plus loin possible avec [Biusante\Medict\Anc](php/Biusante/Medict/Anc.php) pour alimenter [data_events/](data_events/).
* .gitattributes, .gitignore, README.md — fichiers git

## Format éditable “événements”

Toutes les données à charger dans la base relationnelle sont dans un format tabulaire d’“événements”,
au sens où toutes les lignes ne sont pas des données indépendantes, mais sont des sortes de commandes, produisant
un contexte pour les lignes suivantes (ex: un saut de page est déclaré une fois pour toutes les entrées qui suivent,
jusqu’au suivant ou la fin).
Ce format est réfléchi pour limiter les redondances, et faciliter la modification humaine. 

|commande | paramètre 1 | paramètre 2 | par3 |
|--- | --- | --- | --- |
|pb | 754 | 768 | |
|saut de page | n° page affiché (décimal, romain, etc…) | “refimg”, numéro décimal séquentiel pour url, ex iiif https://www.biusante.parisdescartes.fr/histoire/medica/resultats/index.php?do=page&cote=37020d&p=768 | |
|entry | Hydro-entérocèle, Hydrentérocèle | 1 | |
| | vedettes (un ou plusieurs mot) | nombre de pages de l’entrée | |
|orth | Hydro-entérocèle |  | |
|orth | Hydrentérocèle |  | |
| | Hydrencéphalocèle |  | |
|foreign | hydro-enterocele | lat | |
|foreign | hydrenterocele | lat | |
| | traduction <-> (orth1, orth2) | code langue 3 c. | |
| entry	| Hydrogène
| clique	| Hydrogène arsénié \| Arséniure \| Arséniure d’hydrogène | |
| | mots liés (Hydrogène, Hydrogène arsénié, Arséniure, Arséniure d’hydrogène) | |

## ordre d’insertion

L’ordre d’insertion des dictionnaires est significatif, car chaque mot entré renseigne un dictionnaire paratgé ; or les graphies sont inégalement précises 
selon l’âge des données (ex : les accents). On commence donc par les dictionnaires indexés finement (avec le plus de données, donc les plus longs à charger),
ensuite en ordre chronologiqure du plus récent au plus ancien. Cet ordre est piloté par [dico_titre.tsv](dico_titre.tsv), selon la colonne **import_ordre** (1 = premier, 10 = après), puis **annee** (le plus récent réputé le plus exact).

~~~~
[insert_titre] 37020d (1873) <u>Littré</u> <u>Robin</u> 13e éd.
[insert_volume] 37020d… …314.128 s.
[insert_titre] 37020d~lat (1873) <u>Littré</u> <u>Robin</u> 13e éd. Gloss. lat.
[insert_volume] 37020d~lat… …13.214 s.
[insert_titre] 37020d~grc (1873) <u>Littré</u> <u>Robin</u> 13e éd. Gloss. gr.
[insert_volume] 37020d~grc… …8.188 s.
[insert_titre] 37020d~deu (1873) <u>Littré</u> <u>Robin</u> 13e éd. Gloss. all.
[insert_volume] 37020d~deu… …20.769 s.
[insert_titre] 37020d~eng (1873) <u>Littré</u> <u>Robin</u> 13e éd. Gloss. angl.
[insert_volume] 37020d~eng… …10.270 s.
[insert_titre] 37020d~ita (1873) <u>Littré</u> <u>Robin</u> 13e éd. Gloss. ital.
[insert_volume] 37020d~ita… …17.729 s.
[insert_titre] 37020d~spa (1873) <u>Littré</u> <u>Robin</u> 13e éd. Gloss. esp.
[insert_volume] 37020d~spa… …18.981 s.
[insert_titre] 27898 (1908) <u>Littré</u> <u>Gilbert</u> 21e éd.
[insert_volume] 27898… …89.964 s.
[insert_titre] 269035 (1924) Larousse médical illustré
[insert_volume] 269035… …13.381 s.
[insert_titre] 26087 (1917) Larousse de guerre
[insert_volume] 26087… …0.884 s.
[insert_titre] pharma_p11247 (1914) <u>Vidal</u>
[insert_volume] pharma_p11247x1914… …1.893 s.
[insert_volume] pharma_p11247x1920… …1.583 s.
[insert_volume] pharma_p11247x1921… …1.104 s.
[insert_volume] pharma_p11247x1923… …2.448 s.
[insert_volume] pharma_p11247x1924… …1.442 s.
[insert_volume] pharma_p11247x1925… …1.064 s.
[insert_volume] pharma_p11247x1927… …2.167 s.
[insert_volume] pharma_p11247x1928… …1.917 s.
[insert_volume] pharma_p11247x1929… …1.890 s.
[insert_volume] pharma_p11247x1931… …2.525 s.
[insert_volume] pharma_p11247x1932… …5.674 s.
[insert_volume] pharma_p11247x1933… …5.137 s.
[insert_volume] pharma_p11247x1934… …4.368 s.
[insert_volume] pharma_p11247x1935… …4.464 s.
[insert_volume] pharma_p11247x1936… …5.450 s.
[insert_volume] pharma_p11247x1937… …5.231 s.
[insert_volume] pharma_p11247x1937suppl… …0.208 s.
[insert_volume] pharma_p11247x1938… …5.198 s.
[insert_volume] pharma_p11247x1939… …4.823 s.
[insert_volume] pharma_p11247x1940… …4.871 s.
[insert_volume] pharma_p11247x1943… …5.212 s.
[insert_titre] 24374 (1895) <u>Fage</u>
[insert_volume] 24374… …6.590 s.
[insert_titre] 21244 (1895) <u>Carnoy</u>
[insert_volume] 21244… …0.467 s.
[insert_titre] 56140 (1888) <u>Guilland</u>
[insert_volume] 56140… …4.426 s.
[insert_titre] 21575 (1887) <u>Labarthe</u>
[insert_volume] 21575x01… …2.950 s.
[insert_volume] 21575x02… …3.086 s.
[insert_titre] 20311 (1885) <u>Dechambre</u> & al. Dict. usuel
[insert_volume] 20311… …34.471 s.
[insert_titre] pharma_006061 (1883) <u>Dujardin-Beaumetz</u>
[insert_volume] pharma_006061x01… …4.103 s.
[insert_volume] pharma_006061x02… …1.286 s.
[insert_volume] pharma_006061x03… …2.073 s.
[insert_volume] pharma_006061x04… …3.672 s.
[insert_volume] pharma_006061x05… …4.105 s.
[insert_titre] 27518 (1867) <u>Bouchut</u> & <u>Després</u>
[insert_volume] 27518… …3.957 s.
[insert_titre] 37020c (1865) <u>Nysten</u> <u>Littré</u> <u>Robin</u> 12e éd.
[insert_volume] 37020c… …12.755 s.
[insert_titre] 32923 (1864) <u>Jaccoud</u>
[insert_volume] 32923x01… …0.158 s.
[insert_volume] 32923x02… …0.074 s.
[insert_volume] 32923x03… …0.083 s.
[insert_volume] 32923x04… …0.058 s.
[insert_volume] 32923x05… …0.092 s.
[insert_volume] 32923x06… …0.115 s.
[insert_volume] 32923x07… …0.070 s.
[insert_volume] 32923x08… …0.038 s.
[insert_volume] 32923x09… …0.073 s.
[insert_volume] 32923x10… …0.082 s.
[insert_volume] 32923x11… …0.075 s.
[insert_volume] 32923x12… …0.061 s.
[insert_volume] 32923x13… …0.062 s.
[insert_volume] 32923x14… …0.110 s.
[insert_volume] 32923x15… …0.047 s.
[insert_volume] 32923x16… …0.048 s.
[insert_volume] 32923x17… …0.075 s.
[insert_volume] 32923x18… …0.069 s.
[insert_volume] 32923x19… …0.090 s.
[insert_volume] 32923x20… …0.081 s.
[insert_volume] 32923x21… …0.068 s.
[insert_volume] 32923x22… …0.058 s.
[insert_volume] 32923x23… …0.062 s.
[insert_volume] 32923x24… …0.071 s.
[insert_volume] 32923x25… …0.041 s.
[insert_volume] 32923x26… …0.037 s.
[insert_volume] 32923x27… …0.034 s.
[insert_volume] 32923x28… …0.046 s.
[insert_volume] 32923x29… …0.042 s.
[insert_volume] 32923x30… …0.052 s.
[insert_volume] 32923x31… …0.025 s.
[insert_volume] 32923x32… …0.130 s.
[insert_volume] 32923x33… …0.096 s.
[insert_volume] 32923x34… …0.027 s.
[insert_volume] 32923x35… …0.046 s.
[insert_volume] 32923x36… …0.051 s.
[insert_volume] 32923x37… …0.033 s.
[insert_volume] 32923x38… …0.030 s.
[insert_volume] 32923x39… …0.053 s.
[insert_volume] 32923x40… …0.104 s.
[insert_titre] extbnfdechambre (1864) <u>Dechambre</u> & al.
[insert_volume] extbnfdechambrex001… …0.696 s.
[insert_volume] extbnfdechambrex002… …0.967 s.
[insert_volume] extbnfdechambrex003… …0.952 s.
[insert_volume] extbnfdechambrex004… …0.516 s.
[insert_volume] extbnfdechambrex005… …1.003 s.
[insert_volume] extbnfdechambrex006… …1.193 s.
[insert_volume] extbnfdechambrex007… …0.872 s.
[insert_volume] extbnfdechambrex008… …1.098 s.
[insert_volume] extbnfdechambrex009… …1.270 s.
[insert_volume] extbnfdechambrex010… …1.603 s.
[insert_volume] extbnfdechambrex011… …1.506 s.
[insert_volume] extbnfdechambrex012… …1.250 s.
[insert_volume] extbnfdechambrex013… …0.588 s.
[insert_volume] extbnfdechambrex014… …0.604 s.
[insert_volume] extbnfdechambrex015… …1.050 s.
[insert_volume] extbnfdechambrex016… …0.912 s.
[insert_volume] extbnfdechambrex017… …0.877 s.
[insert_volume] extbnfdechambrex018… …0.776 s.
[insert_volume] extbnfdechambrex019… …0.780 s.
[insert_volume] extbnfdechambrex020… …1.013 s.
[insert_volume] extbnfdechambrex021… …0.433 s.
[insert_volume] extbnfdechambrex022… …0.573 s.
[insert_volume] extbnfdechambrex023… …0.619 s.
[insert_volume] extbnfdechambrex024… …1.161 s.
[insert_volume] extbnfdechambrex025… …1.651 s.
[insert_volume] extbnfdechambrex026… …0.953 s.
[insert_volume] extbnfdechambrex027… …0.523 s.
[insert_volume] extbnfdechambrex028… …0.800 s.
[insert_volume] extbnfdechambrex029… …1.292 s.
[insert_volume] extbnfdechambrex030… …1.941 s.
[insert_volume] extbnfdechambrex031… …0.437 s.
[insert_volume] extbnfdechambrex032… …0.741 s.
[insert_volume] extbnfdechambrex033… …0.866 s.
[insert_volume] extbnfdechambrex034… …0.713 s.
[insert_volume] extbnfdechambrex035… …0.620 s.
[insert_volume] extbnfdechambrex036… …0.667 s.
[insert_volume] extbnfdechambrex037… …1.235 s.
[insert_volume] extbnfdechambrex038… …0.854 s.
[insert_volume] extbnfdechambrex039… …1.046 s.
[insert_volume] extbnfdechambrex040… …0.137 s.
[insert_volume] extbnfdechambrex041… …0.101 s.
[insert_volume] extbnfdechambrex042… …2.083 s.
[insert_volume] extbnfdechambrex043… …1.081 s.
[insert_volume] extbnfdechambrex044… …1.043 s.
[insert_volume] extbnfdechambrex045… …1.312 s.
[insert_volume] extbnfdechambrex046… …0.956 s.
[insert_volume] extbnfdechambrex047… …1.677 s.
[insert_volume] extbnfdechambrex048… …2.056 s.
[insert_volume] extbnfdechambrex049… …0.567 s.
[insert_volume] extbnfdechambrex050… …1.644 s.
[insert_volume] extbnfdechambrex051… …0.598 s.
[insert_volume] extbnfdechambrex052… …1.445 s.
[insert_volume] extbnfdechambrex053… …1.120 s.
[insert_volume] extbnfdechambrex054… …1.532 s.
[insert_volume] extbnfdechambrex055… …0.937 s.
[insert_volume] extbnfdechambrex056… …0.750 s.
[insert_volume] extbnfdechambrex057… …0.849 s.
[insert_volume] extbnfdechambrex058… …0.567 s.
[insert_volume] extbnfdechambrex059… …0.815 s.
[insert_volume] extbnfdechambrex060… …0.406 s.
[insert_volume] extbnfdechambrex061… …0.950 s.
[insert_volume] extbnfdechambrex062… …0.780 s.
[insert_volume] extbnfdechambrex063… …0.916 s.
[insert_volume] extbnfdechambrex064… …0.506 s.
[insert_volume] extbnfdechambrex065… …1.438 s.
[insert_volume] extbnfdechambrex066… …1.310 s.
[insert_volume] extbnfdechambrex067… …0.638 s.
[insert_volume] extbnfdechambrex068… …0.427 s.
[insert_volume] extbnfdechambrex069… …0.551 s.
[insert_volume] extbnfdechambrex070… …0.704 s.
[insert_volume] extbnfdechambrex071… …1.499 s.
[insert_volume] extbnfdechambrex072… …1.142 s.
[insert_volume] extbnfdechambrex073… …1.268 s.
[insert_volume] extbnfdechambrex074… …0.945 s.
[insert_volume] extbnfdechambrex075… …0.955 s.
[insert_volume] extbnfdechambrex076… …1.345 s.
[insert_volume] extbnfdechambrex077… …0.725 s.
[insert_volume] extbnfdechambrex078… …0.979 s.
[insert_volume] extbnfdechambrex079… …1.415 s.
[insert_volume] extbnfdechambrex080… …0.504 s.
[insert_volume] extbnfdechambrex081… …0.652 s.
[insert_volume] extbnfdechambrex082… …0.503 s.
[insert_volume] extbnfdechambrex083… …0.432 s.
[insert_volume] extbnfdechambrex084… …1.354 s.
[insert_volume] extbnfdechambrex085… …2.047 s.
[insert_volume] extbnfdechambrex086… …1.966 s.
[insert_volume] extbnfdechambrex087… …1.195 s.
[insert_volume] extbnfdechambrex088… …1.254 s.
[insert_volume] extbnfdechambrex089… …1.422 s.
[insert_volume] extbnfdechambrex090… …1.285 s.
[insert_volume] extbnfdechambrex091… …0.878 s.
[insert_volume] extbnfdechambrex092… …0.787 s.
[insert_volume] extbnfdechambrex093… …0.219 s.
[insert_volume] extbnfdechambrex094… …1.249 s.
[insert_volume] extbnfdechambrex095… …1.057 s.
[insert_volume] extbnfdechambrex096… …2.077 s.
[insert_volume] extbnfdechambrex097… …1.382 s.
[insert_volume] extbnfdechambrex098… …0.889 s.
[insert_volume] extbnfdechambrex099… …0.987 s.
[insert_volume] extbnfdechambrex100… …1.629 s.
[insert_titre] pharma_014236 (1859) <u>Roussel</u>
[insert_volume] pharma_014236x01… …1.527 s.
[insert_volume] pharma_014236x02… …1.855 s.
[insert_volume] pharma_014236x03… …2.088 s.
[insert_volume] pharma_014236x04… …2.999 s.
[insert_volume] pharma_014236x05… …3.483 s.
[insert_titre] extbnfpoujol (1857) <u>Poujol</u>
[insert_volume] extbnfpoujol… …0.869 s.
[insert_titre] 34823 (1856) <u>Bouley</u> & <u>Reynal</u>
[insert_volume] 34823x01… …0.905 s.
[insert_volume] 34823x02… …1.378 s.
[insert_volume] 34823x03… …0.720 s.
[insert_volume] 34823x04… …0.493 s.
[insert_volume] 34823x05… …0.529 s.
[insert_volume] 34823x06… …0.560 s.
[insert_volume] 34823x07… …0.703 s.
[insert_volume] 34823x08… …0.908 s.
[insert_volume] 34823x09… …0.418 s.
[insert_volume] 34823x10… …0.470 s.
[insert_volume] 34823x11… …0.849 s.
[insert_volume] 34823x12… …1.016 s.
[insert_volume] 34823x13… …0.367 s.
[insert_volume] 34823x14… …0.326 s.
[insert_volume] 34823x15… …0.470 s.
[insert_volume] 34823x16… …0.468 s.
[insert_volume] 34823x17… …0.183 s.
[insert_volume] 34823x18… …0.137 s.
[insert_volume] 34823x19… …0.486 s.
[insert_volume] 34823x20… …0.624 s.
[insert_volume] 34823x21… …1.054 s.
[insert_volume] 34823x22… …0.855 s.
[insert_titre] 37020b (1855) <u>Nysten</u> <u>Littré</u> <u>Robin</u> 10e éd.
[insert_volume] 37020b… …15.263 s.
[insert_titre] 37029 (1850) <u>Fabre</u>
[insert_volume] 37029x01… …0.166 s.
[insert_volume] 37029x02… …0.110 s.
[insert_volume] 37029x03… …0.096 s.
[insert_volume] 37029x04… …0.110 s.
[insert_volume] 37029x05… …0.153 s.
[insert_volume] 37029x06… …0.130 s.
[insert_volume] 37029x07… …0.116 s.
[insert_volume] 37029x08… …0.070 s.
[insert_volume] 37029xsup… …0.129 s.
[insert_titre] extbnfbeaude (1849) <u>Beaude</u>
[insert_volume] extbnfbeaudex01… …8.217 s.
[insert_volume] extbnfbeaudex02… …13.012 s.
[insert_titre] extalfodarboval (1838) <u>Hurtrel d’Arboval</u> 2e éd.
[insert_volume] extalfodarbovalx01… …1.037 s.
[insert_volume] extalfodarbovalx02… …1.359 s.
[insert_volume] extalfodarbovalx03… …1.005 s.
[insert_volume] extalfodarbovalx04… …0.769 s.
[insert_volume] extalfodarbovalx05… …1.017 s.
[insert_volume] extalfodarbovalx06… …0.646 s.
[insert_titre] extbnfnysten (1833) <u>Nysten</u> <u>Bricheteau</u> 5e éd.
[insert_volume] extbnfnysten… …9.849 s.
[insert_titre] 34820 (1832) <u>Adelon</u> & al. 2e éd.
[insert_volume] 34820x01… …0.116 s.
[insert_volume] 34820x02… …0.080 s.
[insert_volume] 34820x03… …0.050 s.
[insert_volume] 34820x04… …0.089 s.
[insert_volume] 34820x05… …0.086 s.
[insert_volume] 34820x06… …0.080 s.
[insert_volume] 34820x07… …0.087 s.
[insert_volume] 34820x08… …0.123 s.
[insert_volume] 34820x09… …0.074 s.
[insert_volume] 34820x10… …0.071 s.
[insert_volume] 34820x11… …0.047 s.
[insert_volume] 34820x12… …0.081 s.
[insert_volume] 34820x13… …0.046 s.
[insert_volume] 34820x14… …0.057 s.
[insert_volume] 34820x15… …0.042 s.
[insert_volume] 34820x16… …0.041 s.
[insert_volume] 34820x17… …0.047 s.
[insert_volume] 34820x18… …0.067 s.
[insert_volume] 34820x19… …0.047 s.
[insert_volume] 34820x20… …0.069 s.
[insert_volume] 34820x21… …0.029 s.
[insert_volume] 34820x22… …0.039 s.
[insert_volume] 34820x23… …0.047 s.
[insert_volume] 34820x24… …0.028 s.
[insert_volume] 34820x25… …0.038 s.
[insert_volume] 34820x26… …0.052 s.
[insert_volume] 34820x27… …0.046 s.
[insert_volume] 34820x28… …0.110 s.
[insert_volume] 34820x29… …0.090 s.
[insert_volume] 34820x30… …0.119 s.
[insert_titre] 57503 (1829) <u>Coster</u>
[insert_volume] 57503… …2.624 s.
[insert_titre] 34826 (1829) <u>Andral</u> & al.
[insert_volume] 34826x01… …0.164 s.
[insert_volume] 34826x02… …0.081 s.
[insert_volume] 34826x03… …0.259 s.
[insert_volume] 34826x04… …0.100 s.
[insert_volume] 34826x05… …0.154 s.
[insert_volume] 34826x06… …0.126 s.
[insert_volume] 34826x07… …0.078 s.
[insert_volume] 34826x08… …0.043 s.
[insert_volume] 34826x09… …0.116 s.
[insert_volume] 34826x10… …0.109 s.
[insert_volume] 34826x11… …0.138 s.
[insert_volume] 34826x12… …0.178 s.
[insert_volume] 34826x13… …0.132 s.
[insert_volume] 34826x14… …0.138 s.
[insert_volume] 34826x15… …0.174 s.
[insert_titre] pharma_014023 (1829) <u>Mérat</u> & <u>de Lens</u>
[insert_volume] pharma_014023x01… …12.276 s.
[insert_volume] pharma_014023x02… …15.048 s.
[insert_volume] pharma_014023x03… …21.241 s.
[insert_volume] pharma_014023x04… …13.937 s.
[insert_volume] pharma_014023x05… …10.680 s.
[insert_volume] pharma_014023x06… …26.473 s.
[insert_volume] pharma_014023x07… …15.187 s.
[insert_titre] extbnfdezeimeris (1828) <u>Dezeimeris</u> & al.
[insert_volume] extbnfdezeimerisx01… …2.227 s.
[insert_volume] extbnfdezeimerisx02… …1.975 s.
[insert_volume] extbnfdezeimerisx03… …2.433 s.
[insert_volume] extbnfdezeimerisx04… …1.938 s.
[insert_titre] 61157 (1823) <u>Bégin</u> & al.
[insert_volume] 61157… …15.954 s.
[insert_titre] 35573 (1821) <u>Panckoucke</u> Dict. abrégé
[insert_volume] 35573x01… …0.166 s.
[insert_volume] 35573x02… …0.142 s.
[insert_volume] 35573x03… …0.226 s.
[insert_volume] 35573x04… …0.211 s.
[insert_volume] 35573x05… …0.224 s.
[insert_volume] 35573x06… …0.208 s.
[insert_volume] 35573x07… …0.140 s.
[insert_volume] 35573x08… …0.180 s.
[insert_volume] 35573x09… …0.144 s.
[insert_volume] 35573x10… …0.248 s.
[insert_volume] 35573x11… …0.174 s.
[insert_volume] 35573x12… …0.178 s.
[insert_volume] 35573x13… …0.212 s.
[insert_volume] 35573x14… …0.289 s.
[insert_volume] 35573x15… …0.196 s.
[insert_titre] extbnfadelon (1821) <u>Adelon</u> & al.
[insert_volume] extbnfadelonx001… …0.312 s.
[insert_volume] extbnfadelonx002… …0.275 s.
[insert_volume] extbnfadelonx003… …0.389 s.
[insert_volume] extbnfadelonx004… …0.243 s.
[insert_volume] extbnfadelonx005… …0.274 s.
[insert_volume] extbnfadelonx006… …0.474 s.
[insert_volume] extbnfadelonx007… …0.182 s.
[insert_volume] extbnfadelonx008… …0.226 s.
[insert_volume] extbnfadelonx009… …0.071 s.
[insert_volume] extbnfadelonx010… …0.136 s.
[insert_volume] extbnfadelonx011… …0.173 s.
[insert_volume] extbnfadelonx012… …0.229 s.
[insert_volume] extbnfadelonx013… …0.144 s.
[insert_volume] extbnfadelonx014… …0.175 s.
[insert_volume] extbnfadelonx015… …0.200 s.
[insert_volume] extbnfadelonx016… …0.227 s.
[insert_volume] extbnfadelonx017… …0.144 s.
[insert_volume] extbnfadelonx018… …0.229 s.
[insert_volume] extbnfadelonx019… …0.277 s.
[insert_volume] extbnfadelonx020… …0.240 s.
[insert_volume] extbnfadelonx021… …0.180 s.
[insert_titre] 47667 (1820) <u>Panckoucke</u> Biogr.
[insert_volume] 47667x01… …4.064 s.
[insert_volume] 47667x02… …3.267 s.
[insert_volume] 47667x03… …2.190 s.
[insert_volume] 47667x04… …2.078 s.
[insert_volume] 47667x05… …2.095 s.
[insert_volume] 47667x06… …1.940 s.
[insert_volume] 47667x07… …2.152 s.
[insert_titre] 47661 (1812) <u>Panckoucke</u>
[insert_volume] 47661x01… …0.185 s.
[insert_volume] 47661x02… …0.312 s.
[insert_volume] 47661x03… …0.321 s.
[insert_volume] 47661x04… …0.170 s.
[insert_volume] 47661x05… …0.182 s.
[insert_volume] 47661x06… …0.136 s.
[insert_volume] 47661x07… …0.272 s.
[insert_volume] 47661x08… …0.173 s.
[insert_volume] 47661x09… …0.107 s.
[insert_volume] 47661x10… …0.117 s.
[insert_volume] 47661x11… …0.095 s.
[insert_volume] 47661x12… …0.140 s.
[insert_volume] 47661x13… …0.109 s.
[insert_volume] 47661x14… …0.144 s.
[insert_volume] 47661x15… …0.094 s.
[insert_volume] 47661x16… …0.128 s.
[insert_volume] 47661x17… …0.109 s.
[insert_volume] 47661x18… …0.056 s.
[insert_volume] 47661x19… …0.083 s.
[insert_volume] 47661x20… …0.075 s.
[insert_volume] 47661x21… …0.066 s.
[insert_volume] 47661x22… …0.109 s.
[insert_volume] 47661x23… …0.111 s.
[insert_volume] 47661x24… …0.186 s.
[insert_volume] 47661x25… …0.237 s.
[insert_volume] 47661x26… …0.179 s.
[insert_volume] 47661x27… …0.217 s.
[insert_volume] 47661x28… …0.143 s.
[insert_volume] 47661x29… …0.116 s.
[insert_volume] 47661x30… …0.137 s.
[insert_volume] 47661x31… …0.093 s.
[insert_volume] 47661x32… …0.140 s.
[insert_volume] 47661x33… …0.169 s.
[insert_volume] 47661x34… …0.231 s.
[insert_volume] 47661x35… …0.226 s.
[insert_volume] 47661x36… …0.145 s.
[insert_volume] 47661x37… …0.254 s.
[insert_volume] 47661x38… …0.137 s.
[insert_volume] 47661x39… …0.239 s.
[insert_volume] 47661x40… …0.185 s.
[insert_volume] 47661x41… …0.205 s.
[insert_volume] 47661x42… …0.151 s.
[insert_volume] 47661x43… …0.153 s.
[insert_volume] 47661x44… …0.207 s.
[insert_volume] 47661x45… …0.275 s.
[insert_volume] 47661x46… …0.257 s.
[insert_volume] 47661x47… …0.256 s.
[insert_volume] 47661x48… …0.093 s.
[insert_volume] 47661x49… …0.367 s.
[insert_volume] 47661x50… …0.248 s.
[insert_volume] 47661x51… …0.264 s.
[insert_volume] 47661x52… …0.218 s.
[insert_volume] 47661x53… …0.226 s.
[insert_volume] 47661x54… …0.272 s.
[insert_volume] 47661x55… …0.222 s.
[insert_volume] 47661x56… …0.250 s.
[insert_volume] 47661x57… …0.205 s.
[insert_volume] 47661x58… …0.315 s.
[insert_volume] 47661x59… …2.759 s.
[insert_volume] 47661x60… …21.001 s.
[insert_titre] 37019 (1806) <u>Capuron</u>
[insert_volume] 37019… …7.912 s.
[insert_titre] 37019~lat (1806) <u>Capuron</u> Mots latins
[insert_volume] Fichier introuvable : C:\code\medict-sql/data_events/37019~lat.tsv
[insert_titre] 37019~grc (1806) <u>Capuron</u> Mots grecs
[insert_volume] Fichier introuvable : C:\code\medict-sql/data_events/37019~grc.tsv
[insert_titre] 37019~syn (1806) <u>Capuron</u> Syn.
[insert_volume] Fichier introuvable : C:\code\medict-sql/data_events/37019~syn.tsv
[insert_titre] pharma_019128 (1803) Plantes alimentaires
[insert_volume] pharma_019128x01… …9.407 s.
[insert_volume] pharma_019128x02… …0.326 s.
[insert_titre] extbnfrivet (1803) <u>Rivet</u>
[insert_volume] extbnfrivetx01… …1.329 s.
[insert_volume] extbnfrivetx02… …1.215 s.
[insert_titre] pharma_019129 (1797) <u>Tournefort</u> <u>Jolyclerc</u>
[insert_volume] pharma_019129x01… …0.365 s.
[insert_volume] pharma_019129x02… …0.283 s.
[insert_volume] pharma_019129x03… …0.308 s.
[insert_volume] pharma_019129x04… …2.172 s.
[insert_volume] pharma_019129x05… …1.981 s.
[insert_volume] pharma_019129x06… …1.409 s.
[insert_titre] 45392 (1793) <u>Lavoisien</u>
[insert_volume] 45392… …5.215 s.
[insert_titre] 07410xC (1790) Enc. méthod. chirurgie
[insert_volume] 07410xC01… …0.964 s.
[insert_volume] 07410xC02… …0.627 s.
[insert_volume] 07410xC03… …0.008 s.
[insert_titre] 07410xM (1787) Enc. méthod. médecine
[insert_volume] 07410xM01… …1.570 s.
[insert_volume] 07410xM02… …2.048 s.
[insert_volume] 07410xM03… …3.398 s.
[insert_volume] 07410xM04… …3.120 s.
[insert_volume] 07410xM05… …3.243 s.
[insert_volume] 07410xM06… …1.821 s.
[insert_volume] 07410xM07… …2.221 s.
[insert_volume] 07410xM08… …2.574 s.
[insert_volume] 07410xM09… …0.368 s.
[insert_volume] 07410xM10… …1.861 s.
[insert_volume] 07410xM11… …2.468 s.
[insert_volume] 07410xM12… …5.943 s.
[insert_volume] 07410xM13… …1.911 s.
[insert_volume] 07410xM14… …0.015 s.
[insert_titre] 146144 (1778) <u>Eloy</u>
[insert_volume] 146144x01… …2.942 s.
[insert_volume] 146144x02… …2.357 s.
[insert_volume] 146144x03… …3.097 s.
[insert_volume] 146144x04… …3.567 s.
[insert_titre] extalfobuchoz (1775) <u>Buc’hoz</u> Vétérinaire
[insert_volume] extalfobuchozx01… …0.466 s.
[insert_volume] extalfobuchozx02… …0.770 s.
[insert_volume] extalfobuchozx03… …0.334 s.
[insert_volume] extalfobuchozx04… …0.432 s.
[insert_volume] extalfobuchozx05… …0.603 s.
[insert_volume] extalfobuchozx06… …0.058 s.
[insert_titre] pharma_013686 (1772) <u>Buc’hoz</u> Minér. hydrol.
[insert_volume] pharma_013686x01… …0.472 s.
[insert_volume] pharma_013686x02… …1.201 s.
[insert_volume] pharma_013686x03… …0.547 s.
[insert_volume] pharma_013686x04… …0.692 s.
[insert_titre] 32546 (1771) <u>Hélian</u>
[insert_volume] 32546… …0.220 s.
[insert_titre] pharma_019127 (1770) <u>Buc’hoz</u> Plantes
[insert_volume] pharma_019127x01… …0.402 s.
[insert_volume] pharma_019127x02… …0.300 s.
[insert_volume] pharma_019127x03… …0.304 s.
[insert_volume] pharma_019127x04… …1.956 s.
[insert_titre] 30944 (1767) <u>Levacher de La Feutrie</u> & al.
[insert_volume] 30944x01… …1.766 s.
[insert_volume] 30944x02… …2.303 s.
[insert_titre] 31873 (1766) <u>Dufieu</u>
[insert_volume] 31873x01… …3.351 s.
[insert_volume] 31873x02… …3.933 s.
[insert_titre] pharma_019428 (1750) <u>Gissey</u>
[insert_volume] pharma_019428x01… …0.392 s.
[insert_volume] pharma_019428x02… …0.393 s.
[insert_volume] pharma_019428x03… …0.390 s.
[insert_titre] 00216 (1746) <u>James</u> <u>Diderot</u> & al.
[insert_volume] 00216x01… …6.369 s.
[insert_volume] 00216x02… …10.059 s.
[insert_volume] 00216x03… …14.275 s.
[insert_volume] 00216x04… …11.883 s.
[insert_volume] 00216x05… …12.358 s.
[insert_volume] 00216x06… …5.996 s.
[insert_titre] 07399 (1746) <u>Castelli</u> <u>Bruno</u>
[insert_volume] 07399… …36.920 s.
[insert_titre] 07399~hex (1746) <u>Castelli</u> <u>Bruno</u> Nom. hexaglott.
[insert_volume] Fichier introuvable : C:\code\medict-sql/data_events/07399~hex.tsv
[insert_titre] 00216~tab (1746) <u>James</u> <u>Diderot</u> & al. Table fr.
[insert_volume] Fichier introuvable : C:\code\medict-sql/data_events/00216~tab.tsv
[insert_titre] 01686 (1743) <u>James</u>
[insert_volume] 01686x01… …3.022 s.
[insert_volume] 01686x02… …5.177 s.
[insert_volume] 01686x03… …5.030 s.
[insert_titre] 01686~ind (1743) <u>James</u> Index engl.
[insert_volume] Fichier introuvable : C:\code\medict-sql/data_events/01686~ind.tsv
[insert_titre] pharma_000103 (1741) <u>Chomel</u> 4e éd.
[insert_volume] pharma_000103x01… …2.532 s.
[insert_volume] pharma_000103x02… …1.855 s.
[insert_volume] pharma_000103x03… …8.044 s.
[insert_volume] pharma_000103x04… …5.487 s.
[insert_titre] 01208 (1709) <u>Chomel</u>
[insert_volume] 01208… …1.585 s.
[insert_titre] 08757 (1658) <u>Thévenin</u>
[insert_volume] Fichier introuvable : C:\code\medict-sql/data_events/08757.tsv
[insert_titre] 08746 (1622) <u>de Gorris</u>
[insert_volume] Fichier introuvable : C:\code\medict-sql/data_events/08746.tsv
[insert_titre] 00152 (1601) <u>de Gorris</u> Index lat.-gr.
[insert_volume] 00152… …13.076 s.
Optimize… …optimize OKmysqldump: [Warning] Using a password on the command line interface can be insecure.
C:\code\medict-sql/data_sql/medict_dico_entree.zip <- medict_dico_entree.sql
mysqldump: [Warning] Using a password on the command line interface can be insecure.
C:\code\medict-sql/data_sql/medict_dico_rel.zip <- medict_dico_rel.sql
mysqldump: [Warning] Using a password on the command line interface can be insecure.
C:\code\medict-sql/data_sql/medict_dico_terme.zip <- medict_dico_terme.sql
mysqldump: [Warning] Using a password on the command line interface can be insecure.
C:\code\medict-sql/data_sql/medict_dico_titre.zip <- medict_dico_titre.sql
mysqldump: [Warning] Using a password on the command line interface can be insecure.
C:\code\medict-sql/data_sql/medict_dico_volume.zip <- medict_dico_volume.sql
00:22:44
~~
