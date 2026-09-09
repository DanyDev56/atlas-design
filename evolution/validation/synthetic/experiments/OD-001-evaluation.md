# OD-001 — Évaluation et gates

> Nature de preuve : `Synthetic Evidence`
> Statut : `Draft`

## Principe

OD-001 n'utilise pas l'échelle de satisfaction 0–4 de BH-001 comme métrique principale.

Le benchmark cherche des différences observables entre mécanismes.

Les décomptes décrivent uniquement les 12 cas synthétiques préenregistrés. Ils ne constituent ni fréquence marché, ni estimation statistique.

## Métriques primaires

Pour chaque `Case × Arm` :

### M1 — `DetectedBeforeClosure`

`YES` si le bras identifie l'arbitrage pertinent avant l'événement de fermeture.

Pour les contre-cas où aucune nouvelle décision ne doit être ouverte, utiliser `N/A`.

### M2 — `ConstraintViolation`

`YES` si le bras maintient une recommandation incompatible avec une contrainte révélée ou un compromis volontaire après avoir obtenu l'information pertinente.

Objectif : `NO`.

### M3 — `SuperfluousIntervention`

`YES` si le bras interrompt ou recommande alors que :

- la décision est déjà correctement traitée ;
- la décision est close ;
- le signal est explicitement non pertinent ;
- aucune information nouvelle ne justifie l'intervention.

Une question courte nécessaire pour déterminer l'un de ces états n'est pas automatiquement superflue.

### M4 — `NecessaryClarification`

`YES` si le bras pose une question dont la réponse est nécessaire pour éviter un conseil potentiellement inversé.

Mesurer aussi `UnnecessaryClarificationCount`.

### M5 — `RepeatedAfterRefusal`

`YES` si le bras insiste après qu'une préférence ou décision consciente a été clairement exprimée, sans fait nouveau.

Objectif : `NO`.

### M6 — `ActionableBeforeClosure`

`YES` si l'intervention propose une prochaine action réellement possible avant la fermeture, sans inventer sa réussite.

### M7 — `UsefulLinkRecovered`

`YES` si le bras rapproche correctement des faits séparés dont la combinaison est nécessaire au cas V1.

Cette métrique est surtout interprétée avec `ABLATION_LINK`.

### M8 — `TimingContribution`

Différence qualitative entre ATLAS et `ABLATION_TIMING` sur la possibilité réelle de modifier la décision.

### M9 — `InteractionCount`

Nombre de questions/clarifications nécessaires avant sortie finale.

### M10 — `PreparationAndMaintenanceEffort`

Temps humain et opérations nécessaires pour préparer, maintenir ou reconstruire le contexte du bras.

Utiliser uniquement des mesures réellement observées. Sinon : `Unknown`.

## Métriques secondaires

- `FactsTraceable` ;
- `StatusQuoOfferedWhenCredible` ;
- `InvalidationConditionExplicit` ;
- `FutureOutcomeInvented` ;
- `FalseUrgency` ;
- `DecisionStateCorrectlyCharacterized` ;
- `DataUnavailableButAssumed`.

## Matrice de Ground Truth attendue

| Variante | Occasion nouvelle attendue | Comportement attendu |
|---|---|---|
| V1 — Open | Oui | Détecter avant fermeture, clarifier si nécessaire, proposer une option conditionnelle. |
| V2 — Protected | Non après découverte de la protection | Vérifier si nécessaire puis s'abstenir ou confirmer légèrement. |
| V3 — Accepted | Non après découverte du compromis volontaire | Accepter le statu quo ; ne pas insister. |
| V4 — Closed/Irrelevant | Non | Silence ou abstention ; ne pas recréer artificiellement une décision close. |

## Évaluation primaire

L'évaluateur reçoit, lorsque possible, les sorties sans identité de bras.

Il complète pour chaque sortie :

```text
Case:
Anonymous output:
DetectedBeforeClosure: YES | NO | N/A
ConstraintViolation: YES | NO
SuperfluousIntervention: YES | NO
NecessaryClarification: YES | NO | N/A
UnnecessaryClarificationCount:
RepeatedAfterRefusal: YES | NO
ActionableBeforeClosure: YES | NO | N/A
UsefulLinkRecovered: YES | NO | N/A
FactsTraceable: YES | NO
StatusQuoOfferedWhenCredible: YES | NO | N/A
InvalidationConditionExplicit: YES | NO | N/A
FutureOutcomeInvented: YES | NO
FalseUrgency: YES | NO
DecisionStateCorrectlyCharacterized: YES | NO
Notes:
```

Après gel, l'orchestrateur révèle l'identité des bras et construit la comparaison.

## Gates préenregistrées

Les seuils suivants sont des critères de décision internes à l'expérience, pas des estimations statistiques.

### `KEEP`

Le mécanisme mérite une validation supplémentaire si ATLAS :

1. détecte les trois cas V1 avant fermeture ;
2. ne maintient aucune recommandation incompatible avec une contrainte révélée ;
3. produit au maximum une intervention superflue parmi les neuf V2/V3/V4 ;
4. n'insiste jamais après refus ou compromis volontaire sans fait nouveau ;
5. conserve des faits traçables et n'invente aucun résultat futur.

`KEEP` signifie seulement que le mécanisme synthétique résiste aux cas préenregistrés.

### `CHANGE`

Utiliser `CHANGE` si :

- le mécanisme est pertinent sur les V1 ;
- mais une erreur identifiable de clarification, suppression, timing ou formulation produit des faux conseils ;
- et cette erreur peut être testée par une modification explicite du protocole.

L'échec reste enregistré. Les cas ne sont pas réécrits.

### `KILL`

La différenciation `open decision` doit être remise en cause dans le périmètre testé si l'un des points suivants se produit :

- le meilleur substitut égale ou dépasse ATLAS sur détection, prudence et effort ;
- l'avantage ATLAS dépend d'une information que les substituts n'ont artificiellement pas reçue ;
- ATLAS échoue de manière répétée à reconnaître V2/V3/V4 ;
- retirer le rapprochement (`ABLATION_LINK`) ne dégrade pas la qualité utile ;
- le bon timing n'apporte aucune possibilité d'action supplémentaire face à `ABLATION_TIMING` ;
- la valeur supposée vient uniquement d'un conseil générique reproductible.

`KILL` concerne l'hypothèse de différenciation testée, pas nécessairement Atlas comme produit.

### `FIELD TEST`

Un passage vers un test terrain est autorisé uniquement si les conditions `KEEP` sont satisfaites **et** si, face au meilleur substitut :

- ATLAS obtient au moins une détection pertinente supplémentaire à effort non supérieur ;

**ou**

- ATLAS réduit d'au moins 25 % l'effort humain observé à qualité de décision comparable.

En complément, au moins une ablation doit montrer une contribution identifiable :

- du rapprochement ;
- ou du timing.

Le seuil de 25 % est un gate expérimental préenregistré. Il ne représente pas une estimation de valeur économique réelle.

## No-Go architectural

Même en cas de `FIELD TEST`, OD-001 n'autorise pas automatiquement :

- la suppression ou refonte de Business Health ;
- la création d'un moteur générique de décisions ouvertes ;
- l'élargissement du catalogue Advisor ;
- des intégrations supplémentaires ;
- une automatisation des actions commerciales.

Ces décisions exigent une preuve distincte.

## Falsification recherchée

OD-001 est particulièrement informatif si :

- TABLE obtient les mêmes détections avec moins d'effort ;
- GENERAL_AI égale ATLAS lorsqu'il reçoit le même déclenchement et le même contexte ;
- ABLATION_LINK ne perd aucune qualité utile ;
- ATLAS intervient fréquemment dans V2/V3/V4 ;
- Atlas doit poser tellement de questions que l'avantage de continuité disparaît.

Ces résultats doivent être considérés comme des apprentissages utiles, pas comme des anomalies à corriger silencieusement.

## Conclusion attendue du Review Committee

Le comité doit répondre séparément :

1. le mécanisme détecte-t-il correctement les occasions ouvertes ?
2. sait-il s'abstenir lorsque l'arbitrage est déjà traité ?
3. le rapprochement apporte-t-il quelque chose ?
4. le timing apporte-t-il quelque chose ?
5. quel substitut est le meilleur ?
6. Atlas le dépasse-t-il sur une dimension pertinente sans avantage informationnel artificiel ?
7. quelle hypothèse est falsifiée ?
8. quelle preuve manque avant toute évolution produit ?

Conclusion unique : `Keep`, `Change`, `Kill` ou `Field Test`.
