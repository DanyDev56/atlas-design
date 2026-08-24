---
id: BPT-014
title: Back-office Metrics Catalogue
status: In Review
owner: Product, Engineering and Operations
version: 0.5.0
last_updated: 2026-08-24

references:
  - backoffice.md
  - backoffice-implementation-plan.md
  - ../governance/metrics.md
  - ../governance/pricing-validation.md
  - ../../fondation/domains/analytics/metric-catalog.md
  - ../../implementation/runbooks/beta-research-plan.md
  - ../../implementation/runbooks/observability.md
  - ../../implementation/runbooks/outbox-incident.md
---

# Catalogue de métriques du back-office

## Objectif

Le back-office réunit les métriques nécessaires à Product, Support et
Operations sans confondre quatre familles de signaux : activité métier d'un
Workspace, usage du produit, revenu SaaS Atlas et santé technique.

Une carte de métrique affiche toujours : définition, valeur ou état d'absence,
période, population, source, date de calcul, fraîcheur et niveau d'accès. Un
nombre sans dénominateur ou une valeur périmée n'est pas présenté comme fiable.

## États communs

| État | Sens |
|---|---|
| `Available` | valeur calculée avec les sources attendues |
| `NoData` | aucune donnée admissible pour la période |
| `InsufficientData` | données présentes mais seuil de publication non atteint |
| `NotCollected` | instrumentation non livrée ou volontairement désactivée |
| `Stale` | dernière valeur plus ancienne que la fraîcheur contractuelle |
| `Unavailable` | source attendue en erreur au moment de la lecture |

`NoData`, `NotCollected` et `Unavailable` ne deviennent jamais `0`.

## Vue d'ensemble opérateur

| Métrique | Définition | Source cible | Fraîcheur | Accès |
|---|---|---|---|---|
| Participants actifs | participants entre début et fin, non sortis | Operations | 5 min | Product, Support |
| Participants bloqués | dernière étape inchangée au-delà du seuil ou blocage ouvert | Operations projection | 15 min | Product, Support |
| Incidents `P0/P1` ouverts | dossiers incident non clos par gravité | Operations | temps réel | Operator, Security |
| API disponible | checks réussis / checks exécutés sur la fenêtre | observabilité | 1 min | Operator |
| Worker et scheduler sains | heartbeat dans la fenêtre attendue | observabilité | 1 min | Operator |
| Outbox pending/dead-letter | messages non dispatchés par état | Platform messaging | 1 min | Operator |
| Emails failed/retrying | livraisons par état normalisé | registres email | 5 min | Operator, Support |
| Sauvegarde récente | âge et résultat de la dernière sauvegarde vérifiée | Operations | après chaque job | Operator, Security |
| Essais arrivant à échéance | Trials actifs finissant sous 7 jours | Subscriptions | 15 min | Product, Support |
| Demandes support en retard | dossiers ouverts au-delà de la cible interne | Operations | 5 min | Support |
| Demandes de données ouvertes | demandes non closes par type et échéance | Operations | 5 min | Legal, Security |

## Activation beta

Les étapes `E0` à `E6` restent définies par le plan de recherche. Le back-office
calcule pour chaque étape : nombre atteint, nombre éligible, taux, médiane du
délai depuis l'étape précédente et liste pseudonymisée des blocages.

| Métrique | Numérateur | Dénominateur | Source |
|---|---|---|---|
| Invitation vérifiée | participants `E1+` | participants invités `E0+` | Identity + Operations |
| Workspace prêt | participants `E2+` | participants vérifiés `E1+` | Workspace + Operations |
| Données prêtes | participants `E3+` | Workspaces prêts `E2+` | CRM/Billing + Operations |
| Première valeur | participants `E4+` | données prêtes `E3+` | Analytics/Billing + Operations |
| Réutilisation | participants `E5+` | première valeur `E4+` | événements métier + Operations |
| Décision recueillie | participants `E6` | participants admissibles | registre pricing + Operations |

Sur une cohorte de cinq, le back-office affiche toujours les nombres absolus à
côté des pourcentages. Une personne non encore observable n'est ni un succès ni
un échec.

## Usage et valeur produit

| Métrique | Définition | Source cible | Publication |
|---|---|---|---|
| Temps vers Workspace prêt | `WorkspaceReadyAt - VerifiedAt` | projection Operations | cohorte et médiane |
| Temps vers première donnée | `DataReadyAt - WorkspaceReadyAt` | projection Operations | cohorte et médiane |
| Temps vers première valeur | `FirstValueAt - WorkspaceReadyAt` | projection Operations | cohorte, chemin et médiane |
| Temps vers premier devis envoyé | première émission réelle moins activation | Billing | agrégé, jamais contenu du devis |
| Temps vers premier encaissement déclaré | premier Payment reçu moins facture émise | Billing | agrégé |
| Workspaces réutilisés | action métier un jour distinct après première valeur | projection Operations | nombre / observables |
| Recommendations générées | par `RuleKey` et priorité | Advisor | agrégé |
| Recommendations terminées | terminales `Completed` / générées observables | Advisor | avec période |
| Recommendations écartées | terminales `Dismissed` par raison / observables | Advisor | avec période |
| Charge support | minutes de traitement / Workspace actif | Operations | médiane et distribution |
| CSAT beta | réponses à la question standard / répondants | Operations | effectif obligatoire |

Le temps gagné grâce aux automatisations ne sera pas déduit de clics. Il exige
une méthode de comparaison ou une déclaration structurée documentée avant
publication.

## Métriques Analytics d'un Workspace

Les treize clés canoniques restent possédées par Analytics. Le back-office peut
afficher leur statut et leur valeur pour diagnostiquer un Workspace autorisé,
mais ne redéfinit ni leur période ni leur calcul :

1. `analytics.pipeline.open-amount` ;
2. `analytics.quotes.pending-amount` ;
3. `analytics.quotes.acceptance-rate` ;
4. `analytics.quotes.average-response-time` ;
5. `analytics.billing.net-invoiced-amount` ;
6. `analytics.billing.collected-amount` ;
7. `analytics.receivables.outstanding-amount` ;
8. `analytics.receivables.overdue-amount` ;
9. `analytics.receivables.overdue-count` ;
10. `analytics.receivables.due-soon-amount` ;
11. `analytics.payments.average-time-to-payment` ;
12. `analytics.payments.on-time-rate` ;
13. `analytics.clients.top-collection-share`.

La vue globale ne somme pas aveuglément des montants de devis ou factures entre
Workspaces. Les agrégats monétaires ne sont publiés que pour une finalité
définie, une devise homogène, une population autorisée et une période commune.

## Subscriptions et revenu Atlas

| Métrique | Définition | Source | Accès |
|---|---|---|---|
| Trials actifs | Trials `Active` à la date de calcul | Subscriptions | Product, Support |
| Activation d'essai | Workspaces activés / Trials démarrés observables | Operations + Subscriptions | Product |
| Conversion activé → payant | premier paiement autorisé / activés arrivés à décision | Subscriptions | Product, Financial |
| MRR | revenu récurrent mensuel normalisé, net des remises, hors taxes | Subscriptions | Financial |
| ARPA | revenu net / Workspaces payants moyens | Subscriptions | Financial |
| Churn volontaire/involontaire | définitions gouvernance, populations séparées | Subscriptions | Product, Financial |
| Paiements échoués | événements normalisés par période et état final | Subscriptions | Financial, Support |
| Grâce active | abonnements `PastDue` encore dans la grâce | Subscriptions | Support, Financial |
| Marge contributive | revenu net moins coûts directs attribuables | registre Finance | Financial |

Les événements Stripe sandbox et live sont séparés par environnement et
étiquetés visiblement. Une donnée sandbox n'entre jamais dans MRR ou conversion
réels.

## Santé technique

| Famille | Signaux minimums | Source cible |
|---|---|---|
| HTTP RED | débit, erreurs 5xx, durée moyenne/maximale par route normalisée | `operations.http_red_minute_buckets` |
| Runtime | disponibilité et redémarrages API/worker/scheduler | orchestrateur + heartbeat |
| PostgreSQL | disponibilité, connexions, saturation, taille, latence | exporter PostgreSQL |
| Outbox | pending, dead-letter, âge du plus ancien, débit traité, retries | Platform messaging |
| Emails | accepted, retrying, failed, délai de remise, rejets | registres email |
| Scheduler | dernière exécution et résultat par job critique | Operations heartbeat |
| Backups | dernier succès, âge, taille, vérification et restauration canary | jobs backup |
| Webhooks | reçus, invalides, doublons, échecs et âge du dernier succès | Subscriptions inbox |
| Frontend | erreurs non gérées et échecs de chargement essentiels | collecte first-party minimisée |

Jaeger fournit des traces et reste le diagnostic détaillé. Le socle RED durable
est une projection PostgreSQL bornée à 30 jours : buckets minute, méthode,
gabarit Laravel, classe HTTP, volume et durées. Il ne stocke ni URL appelée, ni
query string, ni UUID, ni utilisateur, ni Workspace. Cette projection suffit à
la vue opérateur et à l'alerte beta ; un backend de métriques dédié reste requis
avant une exploitation à plus grande échelle ou des percentiles fiables.

## Support et conformité

| Métrique | Définition | Données interdites dans l'agrégat |
|---|---|---|
| Dossiers ouverts | dossiers non clos par gravité et âge | contenu et identité brute |
| Première réponse | médiane `FirstResponseAt - OpenedAt` par gravité | texte des échanges |
| Résolution | médiane `ClosedAt - OpenedAt` par gravité | pièces jointes |
| Réouverture | dossiers rouverts / dossiers clos observables | motif nominatif |
| Demandes de droits | ouvertes, échues et closes par type | copie des justificatifs |
| Consentements | participants par version de textes et état | preuve nominative |
| Exports | demandés, remis, expirés et échoués | contenu des paquets |
| Fermetures | demandées, approuvées, exécutées et backups résiduels | données supprimées |
| Accès sensibles | révélations et actions par permission | valeur révélée |

## Fraîcheur cible

| Classe | Cible | Affichage périmé après |
|---|---:|---:|
| incident, disponibilité, outbox | 1 min | 5 min |
| email, support, conformité | 5 min | 15 min |
| abonnement et cohorte | 15 min | 1 h |
| produit et activation | 1 h | 24 h |
| finance et marge | quotidien | 48 h |
| sauvegarde | après le job | prochaine fenêtre + 1 h |

## État actuel

| Capacité | Disponible aujourd'hui | Manque pour le back-office |
|---|---|---|
| 13 métriques Analytics Workspace | API snapshot et endpoint unitaire ; plusieurs clés restent `NoData` | vue opérateur et calculs manquants |
| Dashboard et Business Health | interface par Workspace | agrégation et diagnostic opérateur |
| Traces et HTTP RED | Jaeger via OTLP + buckets minute Operations | percentiles et backend longue durée |
| Backlog outbox | compteurs à la demande et registre opérateur paginé/filtré | série temporelle et alertes durables |
| Emails | compteurs à la demande et registre opérateur paginé/filtré, sans destinataire ni contenu | délai de remise, rejets et alertes durables |
| Stripe | dashboard fournisseur et état local | vue corrélée, séparation sandbox/live |
| Activation beta | projection E0–E6 à la demande, entonnoir avec dénominateurs, cellules pricing et vues pseudonymisées | série historique et sign-off Product/Security sur la cohorte réelle |
| Support/conformité | runbooks et documents | stockage, workflow et audit opérateur |

Les cartes HTTP, runtime et sauvegarde sont désormais raccordées aux projections
Operations. HTTP vaut `NoData` lorsque la fenêtre observée est réellement vide,
et jamais zéro par défaut ; un heartbeat absent reste `NotCollected`. Support et
demandes de données restent explicitement `NotCollected`. Une panne de lecture
devient `Unavailable` pour toutes ces sources.
