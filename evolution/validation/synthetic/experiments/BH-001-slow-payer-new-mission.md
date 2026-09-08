# BH-001 — Client récurrent, paiement lent et nouvelle mission

> Nature de preuve : `Synthetic Evidence`
> Statut : `Draft`

## Hypothèse

L'historique de paiement d'un client récurrent, rapproché d'une nouvelle mission encore négociable, peut conduire un indépendant à modifier utilement ses conditions commerciales.

## Pourquoi cette expérience

Elle teste une différence centrale entre :
- une alerte sur une créance passée ;
- une recommandation qui utilise l'historique pour éclairer une décision future.

Le but n'est pas de démontrer que demander un acompte est toujours préférable. Le but est de vérifier si Atlas peut identifier une exposition non évidente, au bon moment, sans ignorer le contexte utilisateur.

## Critères d'échec

L'hypothèse doit être considérée comme faible si, de manière récurrente :
- les personas avaient déjà prévu la même décision ;
- l'historique n'apporte aucune information susceptible de modifier les conditions ;
- une information qu'Atlas ne possède pas inverse fréquemment le conseil ;
- un simple logiciel de facturation ou rappel produit une valeur équivalente ;
- la collecte des données nécessaires paraît disproportionnée ;
- la recommandation générique « demander un acompte » remplace un véritable arbitrage.

## Panel initial

Utiliser :
- P01 Julien ;
- P02 Thomas ;
- P03 Léa ;
- P04 Sophie ;
- P05 Nicolas ;
- P06 Camille.

Le même scénario observable est fourni à tous. Leur `Hidden State` doit varier de façon contrôlée.

## Données accessibles à Atlas

Client : `Studio North`

Historique récent :

| Mission | Montant HT | Échéance | Paiement réel |
|---|---:|---|---|
| M-01 | 4 800 € | J+30 | J+49 |
| M-02 | 6 200 € | J+30 | J+58 |
| M-03 | 5 500 € | J+30 | J+51 |
| M-04 | 7 100 € | J+30 | J+57 |

Faits supplémentaires :
- toutes les missions ont finalement été intégralement payées ;
- aucune créance de ce client n'est actuellement en souffrance ;
- une nouvelle mission de 8 000 € HT est en négociation ;
- le projet de devis reprend un paiement à 30 jours sans acompte ;
- le client souhaite démarrer rapidement ;
- Atlas ne connaît pas la trésorerie bancaire de l'indépendant ;
- Atlas ne connaît pas encore l'importance relationnelle du client ni sa tolérance personnelle à l'exposition.

## Hidden State — variations contrôlées

### Variante H1 — aucun contexte particulier
Le persona apprécie le client mais n'a pas réfléchi au cumul d'exposition. Il prévoyait d'accepter les mêmes conditions.

### Variante H2 — souplesse volontaire
Le persona sait que le client paie lentement et accepte volontairement cette souplesse car la relation est stratégique et très rentable.

### Variante H3 — trésorerie contrainte
Le persona doit absorber une dépense professionnelle importante prochainement. Atlas ne connaît pas cette information.

### Variante H4 — négociation déjà prévue
Le persona avait déjà décidé de demander un acompte avant toute intervention Atlas.

Chaque run doit recevoir exactement une variante. Le moteur Atlas ne reçoit jamais le contenu du Hidden State.

## Baseline

Avant de montrer Atlas :

1. Accepterais-tu cette nouvelle mission aux conditions proposées ?
2. Modifierais-tu quelque chose au devis ?
3. Quand prendrais-tu cette décision ?
4. Pourquoi ?
5. Qu'est-ce qui pourrait te faire changer d'avis ?

## Recommandation Atlas candidate

Ne pas présenter automatiquement le texte ci-dessous comme vérité. Il constitue le candidat testé.

> Studio North a réglé vos quatre dernières missions entre 19 et 28 jours après l'échéance prévue. La nouvelle mission de 8 000 € reprend les mêmes conditions sans acompte. Si ce comportement se répète, vous pourriez fournir une nouvelle prestation tout en restant exposé plus longtemps que les conditions contractuelles ne le suggèrent. Avant d'accepter le devis, vérifiez si cette exposition vous convient ; sinon, envisagez un acompte ou un paiement par jalons. Atlas ne connaît pas votre trésorerie ni l'importance stratégique de cette relation, qui peuvent modifier cette décision.

### Action candidate

`Revoir les conditions du devis`

Atlas ne doit pas affirmer que l'acompte est obligatoire ni inventer un montant optimal.

## Réaction

Après exposition :

1. Quelle partie de cette analyse était nouvelle ?
2. Avais-tu déjà identifié le comportement de paiement ?
3. Avais-tu déjà relié ce comportement à la nouvelle mission ?
4. Ta décision change-t-elle ?
5. Si oui, précisément comment ?
6. Si non, pourquoi ?
7. Quelle information Atlas aurait dû connaître ?
8. Aurais-tu pu obtenir la même conclusion avec ton système actuel ? Avec quel effort ?
9. Cette recommandation mérite-t-elle d'interrompre ton travail maintenant ?

## Résultat recherché

Le signal synthétique intéressant n'est pas « bonne idée ».

Le signal recherché est une différence entre :

```text
Décision prévue avant Atlas
        ↓
Information / arbitrage Atlas
        ↓
Décision après Atlas
```

Les variantes H2 et H4 sont particulièrement importantes : une bonne conception doit accepter qu'Atlas ne change pas la décision lorsque l'utilisateur possède déjà un contexte supérieur.

## Comparaison avec substituts

Le Review Committee doit répondre :

- un logiciel de facturation standard aurait-il rendu cette situation suffisamment visible ?
- un tableur simple aurait-il suffi ?
- l'utilisateur aurait-il naturellement pensé à poser la question à une IA généraliste ?
- si une IA généraliste reçoit exactement les mêmes données, que reste-t-il comme avantage structurel potentiel à Atlas ?
- cet avantage compense-t-il l'effort de maintenir les données dans Atlas ?

## Critère de passage

Cette expérience ne peut jamais valider le marché.

Elle peut conduire à `Field Test` si :
- plusieurs profils produisent des niveaux 3 ou 4 dans les variantes où l'arbitrage n'était pas déjà prévu ;
- les variantes H2/H4 ne provoquent pas de recommandation artificiellement insistante ;
- les informations manquantes sont explicitement reconnues ;
- l'avantage face aux substituts repose sur la continuité et la détection au bon moment, pas uniquement sur la centralisation.
