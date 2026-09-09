# OD-001 — Benchmark de détection des décisions ouvertes

> Nature de preuve : `Synthetic Evidence`
> Statut : `Draft`

## Hypothèse

Atlas peut créer une valeur différenciante non pas en produisant un conseil plus original qu'un substitut, mais en détectant à temps une décision encore modifiable, en rapprochant automatiquement des faits historiques d'un événement courant, puis en choisissant correctement entre :

- poser une question de clarification ;
- proposer une option conditionnelle ;
- s'abstenir avec `No Recommendation`.

L'expérience cherche à falsifier cette hypothèse avant toute refonte de Business Health ou d'Advisor.

## Question centrale

À informations comparables, Atlas fait-il mieux qu'un substitut simple pour identifier une occasion décisionnelle utile avant sa fermeture, tout en évitant les interventions superflues et les conseils incompatibles avec le contexte ?

## Ce que l'expérience ne teste pas

OD-001 ne mesure pas :

- la volonté de payer ;
- l'adoption réelle ;
- la rétention ;
- un effet statistique sur une population ;
- la qualité commerciale réelle d'une négociation ;
- un paiement futur ;
- la supériorité d'une architecture logicielle ;
- la fréquence réelle de ces situations chez des indépendants.

Un succès synthétique autorise seulement une étape de validation supplémentaire.

## Origine

OD-001 prolonge BH-001 / run-002.

BH-001 a suggéré que :

- le rapprochement entre faits connus et décision courante peut ouvrir un arbitrage non prévu ;
- une situation grave n'apporte pas forcément de valeur nouvelle si l'utilisateur a déjà décidé quoi faire ;
- une souplesse volontaire ou une protection déjà prévue doivent pouvoir conduire au statu quo ;
- la recommandation elle-même peut être facilement substituable ;
- le mécanisme potentiellement différenciant reste `détection + rapprochement + timing + abstention`.

Ces éléments restent des résultats synthétiques et des inférences, pas des validations marché.

## Design expérimental

OD-001 utilise 12 dossiers temporels inédits répartis en trois familles de décisions de service.

Chaque famille contient quatre variantes contrôlées :

- `V1 — Open` : décision encore ouverte avec rapprochement pertinent ;
- `V2 — Protected` : protection ou action adéquate déjà prévue ;
- `V3 — Accepted` : risque ou compromis consciemment accepté ;
- `V4 — Closed/Irrelevant` : décision déjà close ou historique non pertinent.

Les 12 dossiers sont définis dans `OD-001-cases.md`.

## Familles

### A — Nouveau devis après historique de paiement lent

Décision testée : faut-il revoir les modalités de paiement avant acceptation du nouveau devis ?

Fenêtre de modification : jusqu'à l'acceptation du devis.

### B — Renouvellement après dépassements répétés de charge

Décision testée : faut-il modifier le périmètre, le prix, la capacité engagée ou les modalités avant renouvellement ?

Fenêtre de modification : avant validation du renouvellement.

### C — Engagement de capacité face à plusieurs opportunités

Décision testée : faut-il accepter, différer ou prioriser une nouvelle opportunité compte tenu des engagements et probabilités déjà observables ?

Fenêtre de modification : avant engagement ferme de capacité.

## Bras comparés

Les bras sont décrits dans `OD-001-arms-and-ablations.md`.

Le benchmark compare au minimum :

1. `ATLAS` — mécanisme manuel simulant la proposition Atlas ;
2. `TABLE` — tableur / règles simples / rappels préenregistrés ;
3. `GENERAL_AI` — IA généraliste appelée aux mêmes points avec les mêmes informations accessibles ;
4. `ABLATION_LINK` — même intervention qu'Atlas mais sans rapprochement explicite entre les faits ;
5. `ABLATION_TIMING` — même contenu que le bras Atlas, mais après fermeture de la décision.

## Principe d'égalité d'information

Pour un même instant du scénario :

- chaque bras reçoit les mêmes faits observables ;
- aucun bras ne reçoit l'étiquette `Open Decision` ;
- aucun bras ne reçoit de faits futurs ;
- les informations privées ne sont révélées qu'en réponse à une question explicitement autorisée ;
- chaque question a un coût d'interaction enregistré ;
- aucune sortie ne peut inventer une réponse client ou un résultat économique futur.

Si un bras obtient une information supplémentaire, le journal doit montrer précisément pourquoi et à quel coût.

## Unité d'analyse

L'unité n'est pas une préférence de persona ni un score de satisfaction.

Chaque exécution produit des observations comportementales du mécanisme :

```text
Case
→ Timeline events
→ Intervention point
→ Silent / Ask / Recommend
→ Clarifications
→ Action proposed
→ Closure
→ Evaluation
```

Les métriques et gates sont définis dans `OD-001-evaluation.md`.

## Règle de décision

Pour chaque événement, un bras peut uniquement choisir :

- `SILENT` — aucune intervention ;
- `ASK` — une question de clarification ciblée ;
- `RECOMMEND` — une option conditionnelle ou une action ;
- `NO_RECOMMENDATION` — situation analysée mais intervention volontairement refusée faute de valeur ou de contexte.

`ASK` n'est pas automatiquement préférable à `SILENT`. Une question inutile compte comme friction.

## Conditions strictes d'une recommandation Atlas

Une sortie `RECOMMEND` doit satisfaire les principes suivants :

- décision encore modifiable ;
- fenêtre temporelle identifiable ;
- faits traçables ;
- alternative crédible incluant le statu quo lorsque pertinent ;
- condition d'invalidation explicite ;
- résultat futur observable, sans attribution causale inventée ;
- `No Recommendation` possible ;
- question préalable si une inconnue peut raisonnablement inverser le conseil.

Une précision de montant, pourcentage, probabilité ou impact non soutenue par les données est interdite.

## Ordre d'exécution

1. Figer les 12 dossiers et leurs réponses autorisées.
2. Figer les bras, prompts et règles.
3. Exécuter les dossiers événement par événement sans accès au futur.
4. Journaliser chaque intervention et chaque clarification.
5. Évaluer les sorties sans marque du bras lorsque possible.
6. Comparer les bras sur les métriques préenregistrées.
7. Exécuter le Devil's Advocate.
8. Exécuter le Review Committee.
9. Conclure `Keep`, `Change`, `Kill` ou `Field Test`.

## Interdictions

Ne jamais :

- compter les 12 cas comme un échantillon de marché ;
- ajuster un cas après avoir vu un résultat ;
- révéler les Ground Truth au bras testé ;
- donner à Atlas une information refusée au contrôle ;
- changer le timing d'un bras sans le documenter ;
- considérer une recommandation plus longue comme meilleure ;
- conclure qu'une action serait acceptée ou rentable sans observation réelle ;
- promouvoir automatiquement un succès synthétique en refonte produit.

## Critère stratégique

OD-001 ne doit pas répondre :

> « Atlas sait-il produire de bons conseils ? »

Il doit répondre :

> « Le mécanisme open decision apporte-t-il une différence observable sur la détection, la prudence ou l'effort face à des substituts plus simples ? »

Si la réponse est non, la différenciation proposée doit être remise en cause avant toute sophistication supplémentaire.
