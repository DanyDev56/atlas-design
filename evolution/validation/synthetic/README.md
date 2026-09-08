# Laboratoire de validation synthétique

Statut : `Draft`

Ce dossier définit un dispositif de **Product Discovery synthétique** pour Atlas. Son objectif est d'éliminer tôt des hypothèses faibles, de challenger Business Health et Advisor et de préparer de futurs tests terrain.

## Principe fondamental

Une simulation synthétique n'est **jamais une preuve de marché**.

Elle peut :
- révéler une incohérence ;
- montrer qu'une recommandation est évidente ou mal contextualisée ;
- comparer plusieurs scénarios ;
- identifier les données manquantes ;
- préparer une expérience terrain ;
- réduire le développement inutile.

Elle ne peut pas valider :
- la volonté réelle de payer ;
- la rétention ;
- un canal d'acquisition ;
- un changement de comportement réel ;
- la fréquence réelle d'un problème ;
- le product-market fit.

Aucun résultat de ce dossier ne doit être recopié dans un registre de preuves terrain comme s'il provenait d'un utilisateur réel.

## Architecture du laboratoire

```text
synthetic/
├── README.md
├── PANEL.md
├── EXPERIMENT_PROTOCOL.md
├── EVIDENCE_POLICY.md
├── personas/
│   ├── julien-core.md
│   ├── thomas-long-mission.md
│   ├── lea-fragmented.md
│   ├── sophie-organized.md
│   ├── nicolas-skeptic.md
│   └── camille-growth.md
└── experiments/
    └── BH-001-slow-payer-new-mission.md
```

## Deux rôles distincts

### Synthetic Customer Panel

Les personas jouent uniquement le rôle d'utilisateurs. Ils possèdent des objectifs, habitudes, informations et contraintes qui peuvent contredire Atlas.

Ils ne doivent pas chercher à aider le produit, ni deviner l'intention du concepteur.

### Product Review Committee

Le comité analyse les réactions du panel après l'expérience. Il ne joue pas les utilisateurs.

Composition recommandée :
- Product Strategy ;
- Product Discovery ;
- UX ;
- SaaS / GTM ;
- Decision Intelligence ;
- contradicteur chargé de chercher les explications alternatives et les faux positifs.

## Règle anti-complaisance

Un persona ne doit jamais répondre favorablement parce qu'une fonctionnalité correspond à la vision Atlas.

Avant toute recommandation, il doit produire sa décision initiale sans connaître ce qu'Atlas va proposer. Cette réponse est figée avant la suite de l'expérience.

La recommandation est ensuite présentée séparément. On mesure le **delta de décision**, et non l'enthousiasme déclaré.

## Échelle de réaction

- `0 — Inutile` : information déjà connue, erronée ou sans effet.
- `1 — Informatif` : élément nouveau mais sans conséquence décisionnelle.
- `2 — Pertinent` : mérite réflexion ou vérification.
- `3 — Décision modifiée` : l'utilisateur change une action, son calendrier ou un arbitrage.
- `4 — Forte valeur` : la décision change et l'utilisateur estime de manière argumentée qu'il n'aurait probablement pas identifié le point aussi tôt ou aussi clairement.

Le niveau 4 reste un **signal synthétique**, jamais une preuve économique.

## Usage des modèles

Les simulations répétitives doivent utiliser un modèle raisonnablement économique. Un modèle de raisonnement coûteux peut intervenir pour :
- concevoir une expérience ;
- analyser un lot de résultats compact ;
- challenger les biais ;
- décider quelle hypothèse tester ensuite.

Il ne doit pas être utilisé par défaut pour jouer chaque persona et chaque répétition.

## Règle de décision

Une hypothèse qui échoue de manière robuste en simulation peut être simplifiée, reformulée ou abandonnée avant développement.

Une hypothèse qui réussit en simulation obtient seulement le statut :

> **candidate à validation terrain**

Elle ne devient jamais `Validated` sur la seule base du laboratoire synthétique.