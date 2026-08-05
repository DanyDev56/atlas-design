# Cycle de vie d'une Recommendation

Le cycle de vie décrit l'état métier d'une `Recommendation`.

Il ne doit pas être confondu avec les interactions de l'utilisateur, comme
l'affichage ou l'ouverture de la recommandation.

Le cycle durable de la `Recommendation` commence à `Generated`. `Draft`
représente le candidat interne évalué par le moteur avant la création de
l'identité métier.

```text
Draft
  │
  ▼
Generated
  ├──► Executed
  ├──► Dismissed
  └──► Expired
```

`Executed`, `Dismissed` et `Expired` sont des états terminaux concurrents.

Une même recommandation ne peut donc jamais être successivement exécutée,
rejetée puis expirée.

---

## Draft

La recommandation est en cours d'évaluation interne.

Elle n'est pas visible par l'utilisateur et ne peut pas encore être exécutée.

Un brouillon qui ne satisfait pas les règles de génération est abandonné sans
devenir une recommandation métier.

---

## Generated

La recommandation a satisfait les règles de génération.

Elle possède notamment :

- une cause ;
- une priorité ;
- une explication ;
- un niveau de confiance ;
- une action principale.

`Generated` est le seul état non terminal exposable à l'utilisateur.

---

## Executed

L'action principale a été exécutée ou confirmée comme accomplie.

Le résultat attendu et le résultat observé peuvent ensuite être mesurés sans
modifier cet état terminal.

---

## Dismissed

L'utilisateur a explicitement choisi de ne pas suivre la recommandation.

Le motif du rejet peut être conservé afin d'améliorer la pertinence future.

---

## Expired

Le contexte qui justifiait la recommandation n'est plus valide ou sa fenêtre
d'action est terminée.

L'expiration est déterminée à partir de faits observables, jamais uniquement parce
que la recommandation n'a pas été ouverte.

---

## Interactions sans changement d'état

Les interactions suivantes ne sont pas des états du cycle de vie :

- `Displayed` indique que la recommandation a été affichée ;
- `Opened` indique que l'utilisateur a consulté son détail.

Elles produisent des événements d'interaction, mais la recommandation reste
`Generated`.

```text
Generated
  ├── RecommendationDisplayed
  ├── RecommendationOpened
  └── Generated
```

---

## Nouvelle recommandation après un état terminal

Une recommandation terminale ne revient jamais à `Generated`.

Si un nouvel événement métier justifie de proposer de nouveau une action
similaire, Atlas crée une nouvelle `Recommendation` possédant sa propre identité.
Elle peut référencer la recommandation précédente pour expliquer sa continuité.

Toutes les transitions et interactions significatives sont historisées.
