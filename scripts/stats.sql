SELECT 
	id AS identifiant,
	cote,
    nom,
    annee,
    pages,
	(SELECT COUNT(*) FROM dico_entree WHERE dico_titre = identifiant) AS entrees,
	(SELECT COUNT(*) FROM dico_rel WHERE reltype = 1 AND dico_titre = identifiant) AS vedettes,
	(SELECT COUNT(*) FROM dico_rel WHERE reltype = 2 AND dico_titre = identifiant) AS 'sous-vedettes',
	(SELECT COUNT(*) FROM dico_rel WHERE reltype = 3 AND dico_titre = identifiant) AS traductions,
	(SELECT COUNT(*) FROM dico_rel WHERE reltype = 3 AND langue = 1 AND dico_titre = identifiant) AS fra,
	(SELECT COUNT(*) FROM dico_rel WHERE reltype = 3 AND langue = 2 AND dico_titre = identifiant) AS lat,
	(SELECT COUNT(*) FROM dico_rel WHERE reltype = 3 AND langue = 3 AND dico_titre = identifiant) AS grc,
	(SELECT COUNT(*) FROM dico_rel WHERE reltype = 3 AND langue = 4 AND dico_titre = identifiant) AS eng,
	(SELECT COUNT(*) FROM dico_rel WHERE reltype = 3 AND langue = 5 AND dico_titre = identifiant) AS deu,
	(SELECT COUNT(*) FROM dico_rel WHERE reltype = 3 AND langue = 6 AND dico_titre = identifiant) AS spa,
	(SELECT COUNT(*) FROM dico_rel WHERE reltype = 3 AND langue = 7 AND dico_titre = identifiant) AS ita
FROM dico_titre 
ORDER BY annee;