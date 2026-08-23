---
title: Mesure d'activation et recherche — Beta fermée
status: In Review
owner: Product
last_updated: 2026-08-23
references:
  - beta-program.md
  - ../../evolution/governance/metrics.md
  - ../../evolution/governance/pricing-validation.md
  - ../../fondation/product/personnas/persona-primary.md
---

# Mesure et recherche beta

Le plan mesure l'activation à partir des états métier qu'Atlas doit déjà
conserver. Il n'ajoute ni pixel publicitaire, ni session replay, ni SDK de
tracking tiers. Les notes nominatives et enregistrements restent hors du dépôt.

## Entonnoir d'activation

| Étape | Définition observable | Source | Cible exploratoire |
|---|---|---|---:|
| `E0 Invited` | invitation envoyée au participant qualifié | registre restreint | 5/5 |
| `E1 Verified` | adresse vérifiée et première session ouverte | Identity | >= 80 % sous 48 h |
| `E2 WorkspaceReady` | Workspace créé et identité minimale complétée | Workspace | >= 80 % sous 72 h |
| `E3 DataReady` | import confirmé ou au moins un client et un document métier réels | CRM + Billing | >= 60 % sous 7 j |
| `E4 FirstValue` | une analyse exploitable est publiée ou un devis/facture est réellement envoyé | Analytics + Business Health + Billing | >= 60 % sous 10 j |
| `E5 Reused` | nouvelle action métier lors d'un jour distinct après `FirstValue` | événements métier datés | observation |
| `E6 Decision` | décision pricing codée selon le protocole | registre pricing | 5/5 avant J30 |

Une étape est binaire par Workspace. Un clic ou une page ouverte ne suffit pas.
`FirstValue` conserve la date et le chemin (`analyse` ou `document`) afin de ne
pas confondre activité et valeur.

## Revue de cohorte

À J2, J7, J14, J21 et J30, Product renseigne uniquement : identifiant beta,
dernière étape, date de l'étape, blocage principal, temps de support cumulé et
prochaine action. Les agrégats publiables comprennent effectif et dénominateur ;
aucun pourcentage n'est présenté seul sur une cohorte de cinq.

| Participant | Étape | Date | Chemin FirstValue | Blocage | Support cumulé | Prochaine action |
|---|---|---|---|---|---:|---|
| `BETA-001` | — | — | — | — | 0 min | qualifier |
| `BETA-002` | — | — | — | — | 0 min | qualifier |
| `BETA-003` | — | — | — | — | 0 min | qualifier |
| `BETA-004` | — | — | — | — | 0 min | qualifier |
| `BETA-005` | — | — | — | — | 0 min | qualifier |

## Script d'entretien

### Introduction

- rappeler la durée, la nature facultative de l'entretien et l'usage des notes ;
- demander séparément l'autorisation avant tout enregistrement ;
- préciser qu'une critique n'affecte ni l'accès ni le support ;
- ne pas annoncer la cellule de prix avant l'observation du produit.

### Comprendre la situation actuelle

1. Comment suivez-vous aujourd'hui clients, devis, factures et encaissements ?
2. Racontez la dernière fois où une information manquante a retardé une décision.
3. Qu'est-ce qui vous prend le plus de temps ou vous fait le plus douter ?
4. Qui choisit et paie les logiciels utilisés pour cette activité ?

### Observer Atlas

5. Montrez comment vous retrouveriez la prochaine action commerciale utile.
6. Que comprenez-vous de la santé de l'activité et de la fiabilité affichée ?
7. Quelle recommandation vous paraît crédible, inutile ou difficile à appliquer ?
8. À quel moment Atlas vous a-t-il apporté une information que vous n'aviez pas ?
9. Qu'est-ce qui vous empêcherait d'utiliser Atlas avec vos données réelles ?

### Tester le packaging et le prix

Product montre uniquement la cellule attribuée (`P19`, `P24` ou `P29`) avec le
packaging identique, sans remise ni prix barré.

10. Reformulez ce qui est inclus et ce qui ne l'est pas.
11. Que manque-t-il pour prendre une décision aujourd'hui ?
12. Comment comparez-vous ce coût à vos outils ou à votre temps actuel ?
13. Préférez-vous mensuel, annuel ou aucun des deux, et pourquoi ?
14. Quelle décision prenez-vous maintenant : poursuivre vers un achat,
    convenir d'une date de décision, ou refuser ?
15. Quelle est la raison principale de cette décision ?

La réponse est codée avec la taxonomie `PAID`, `PREORDERED`,
`TRIAL_COMMITTED`, `DECLINED_PRICE`, `DECLINED_VALUE`, `DECLINED_SCOPE`,
`DECLINED_TRUST` ou `INELIGIBLE`. Pendant la beta sans paiement réel,
`PAID` n'est pas utilisable et une intention vague n'est pas une précommande.

## Minimisation et consentement

- aucune donnée nominative dans les tableaux Git ;
- aucune adresse client, facture ou capture brute dans les notes de recherche ;
- notes de recherche restreintes et supprimées selon la durée validée ;
- enregistrement audio/vidéo désactivé par défaut et consentement révocable ;
- citations externes soumises à un accord distinct, avec texte et contexte ;
- métriques dérivées des événements métier existants, sans profilage publicitaire ;
- toute nouvelle télémétrie exige finalité, données, durée, accès et information
  documentés avant activation.

## Compte-rendu final

La synthèse contient la taille de cohorte, les abandons, l'entonnoir en nombres
absolus, les temps jusqu'à la valeur, les blocages, la charge support, les
résultats par cellule de prix et les biais. Elle sépare constat, interprétation
et décision. Les cinq participants ne constituent pas une preuve statistique de
marché ; ils servent à détecter les défauts majeurs et préparer la validation
pricing plus large.

