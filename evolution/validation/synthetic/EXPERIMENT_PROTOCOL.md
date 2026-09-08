# Protocole des expériences synthétiques

Statut : `Draft`

## Objectif

Tester une hypothèse produit avant de décider de la développer, de la complexifier ou de la présenter comme différenciante.

L'unité d'analyse n'est pas « le persona aime-t-il Atlas ? », mais :

> **Atlas modifie-t-il une décision plausible pour une raison que les données permettent réellement de défendre ?**

## Déroulement obligatoire

### Phase 0 — Pré-enregistrement

Avant toute simulation, écrire :
- identifiant de l'expérience ;
- hypothèse ;
- recommandation candidate ;
- données accessibles à Atlas ;
- données volontairement inaccessibles ;
- personas retenus ;
- critères de succès ;
- critères d'échec ;
- substituts à comparer.

Les critères ne doivent pas être réécrits après lecture des résultats.

### Phase 1 — État initial

Fournir au persona son profil, l'état métier et son état caché.

Demander sa compréhension et sa décision initiale. Sauvegarder la réponse avant de générer la recommandation.

### Phase 2 — Analyse Atlas

Un rôle séparé reçoit uniquement les données auxquelles Atlas aurait accès.

Il doit produire :
- constat ;
- preuves ;
- information manquante susceptible d'inverser le conseil ;
- décision encore ouverte ;
- recommandation ;
- raison d'agir maintenant ;
- prochaine étape ;
- condition d'expiration ou de réévaluation.

Il peut conclure `NO_RECOMMENDATION`.

### Phase 3 — Réaction

Présenter au persona uniquement la sortie qu'un utilisateur pourrait réellement voir.

Mesurer :
- compréhension ;
- nouveauté ;
- confiance ;
- objection ;
- décision après Atlas ;
- différence avec la décision initiale ;
- raison exacte du changement ou du non-changement.

### Phase 4 — Substitution

Pour les expériences stratégiques, comparer la même situation avec au moins un substitut pertinent :
- logiciel de facturation ;
- tableur ;
- calendrier / tâches ;
- IA généraliste disposant des mêmes informations.

Ne pas donner moins d'informations au substitut pour favoriser Atlas.

### Phase 5 — Revue contradictoire

Le contradicteur recherche :
- biais du scénario ;
- information injectée artificiellement ;
- recommandation évidente ;
- changement de décision non crédible ;
- avantage qui disparaît à informations égales ;
- besoin d'information qu'Atlas ne possède pas ;
- fausse précision ;
- recommandation qui aurait dû être suspendue.

### Phase 6 — Classification

Pour chaque run :
- score de réaction `0..4` ;
- `useful`: oui/non ;
- `decision_changed`: oui/non ;
- `already_known`: oui/non ;
- `missing_context`: oui/non ;
- `substitute_equivalent`: oui/non/inconnu ;
- `atlas_should_abstain`: oui/non ;
- justification courte.

## Répétitions

Une seule réponse d'un modèle n'est pas un résultat robuste.

Pour une hypothèse importante :
- plusieurs personas ;
- variantes de l'état métier ;
- variantes d'état caché ;
- ordre de comparaison alterné lorsque pertinent ;
- plusieurs runs si le coût le permet.

Les répétitions servent à chercher les cas d'échec, pas à fabriquer un pourcentage de marché.

## Sortie compacte

Une synthèse doit contenir :
- hypothèse ;
- nombre de runs ;
- distribution des scores 0..4 ;
- principaux motifs de changement ;
- principaux motifs de rejet ;
- cas où Atlas aurait dû s'abstenir ;
- comparaison avec substituts ;
- données manquantes ;
- verdict synthétique : `REJECT`, `REFINE`, `FIELD_CANDIDATE`.

`FIELD_CANDIDATE` signifie uniquement que l'hypothèse mérite un test avec de vraies personnes.