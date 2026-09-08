# Validation synthétique

> Statut : `Draft`

## Objet

Ce dossier définit un laboratoire de validation synthétique pour Atlas. Il sert à challenger des hypothèses produit avant de mobiliser du développement ou une validation terrain.

La validation synthétique ne constitue jamais une preuve de marché.

Elle peut :
- révéler une recommandation évidente, incohérente ou fragile ;
- comparer des réactions plausibles entre profils ;
- identifier les informations manquantes qui peuvent inverser une décision ;
- préparer des expériences terrain ;
- réduire le nombre d'hypothèses coûteuses à tester réellement.

Elle ne peut pas démontrer :
- une volonté réelle de payer ;
- une adoption durable ;
- une rétention ;
- un coût d'acquisition ;
- un comportement réel sous contrainte ;
- une validation de marché.

## Principe de séparation des preuves

Toute observation issue d'un agent synthétique porte explicitement la nature `Synthetic Evidence`.

Elle ne doit jamais être agrégée avec :
- un entretien utilisateur réel ;
- une observation d'usage réelle ;
- un paiement ;
- une précommande ;
- une donnée de rétention ;
- une autre `Market Evidence`.

Une hypothèse qui échoue de manière robuste en simulation peut être simplifiée, reformulée ou abandonnée avant développement.

Une hypothèse qui réussit en simulation devient seulement **candidate à une validation terrain**.

## Architecture du laboratoire

```text
Synthetic Customer Panel
        │
        ├── Personas
        │     ├── profil observable
        │     ├── Business State
        │     └── Hidden State
        │
        ├── Experiment
        │     ├── hypothèse
        │     ├── scénario figé
        │     ├── décision avant Atlas
        │     ├── recommandation Atlas
        │     └── décision après Atlas
        │
        └── Review Committee
              ├── analyse les résultats
              ├── recherche les biais
              ├── compare les substituts
              └── recommande Keep / Change / Kill / Field Test
```

## Règles anti-complaisance

1. Un persona n'est jamais chargé de confirmer la vision Atlas.
2. Il répond d'abord selon son propre intérêt, ses habitudes et ses contraintes.
3. Il peut considérer Atlas inutile, intrusif, trop coûteux ou redondant.
4. Il distingue ce qu'il savait déjà de ce que la recommandation lui apprend.
5. Il formule sa décision avant de voir la recommandation Atlas.
6. Une réaction positive verbale n'est pas assimilée à un changement de décision.
7. Le `Hidden State` peut contenir des faits que les données Atlas ne permettent pas de connaître.
8. Atlas doit être pénalisé lorsqu'il conseille avec assurance malgré une information manquante susceptible d'inverser la décision.
9. Les alternatives doivent être considérées sérieusement : logiciel existant, tableur, calendrier, procédure personnelle et IA généraliste.
10. Une simulation peut conclure `No Recommendation` si aucune intervention Atlas n'apporte assez de valeur.

## Échelle de valeur

| Niveau | Qualification | Interprétation |
|---|---|---|
| 0 | Inutile | L'utilisateur savait déjà quoi faire ou la recommandation n'apporte rien. |
| 1 | Informatif | Un fait est mieux compris, sans effet sur la décision. |
| 2 | Pertinent | La recommandation mérite réflexion mais ne change pas encore l'action prévue. |
| 3 | Décision modifiée | L'utilisateur modifie une action, un calendrier ou un arbitrage qu'il avait prévu. |
| 4 | Forte valeur | La décision change et l'utilisateur estime qu'il n'aurait probablement pas identifié ou traité la situation de cette manière sans Atlas. |

Les niveaux 3 et 4 sont les principaux signaux synthétiques recherchés. Ils ne constituent toujours pas une preuve de comportement réel.

## Protocole minimal

Pour chaque expérience :

1. figer le scénario et les données accessibles à Atlas ;
2. demander au persona sa compréhension et sa décision initiale ;
3. enregistrer cette réponse avant toute recommandation ;
4. produire la recommandation Atlas uniquement à partir des données autorisées ;
5. exposer la recommandation au persona ;
6. recueillir sa réaction, ses objections et sa nouvelle décision ;
7. attribuer un niveau de valeur ;
8. faire analyser le résultat par le Review Committee ;
9. consigner les biais et informations manquantes ;
10. conclure `Keep`, `Change`, `Kill` ou `Field Test`.

## Panel initial

Le panel initial est défini dans `personas.md`.

Il vise volontairement des profils dont les besoins et les comportements divergent malgré un cycle de service compatible avec Atlas.

## Première expérience

La première expérience candidate est définie dans `experiments/BH-001-slow-payer-new-mission.md`.

Elle teste une hypothèse issue de l'audit Business Health / Advisor : l'historique de paiement d'un client récurrent peut-il modifier utilement les conditions d'une nouvelle mission ?
