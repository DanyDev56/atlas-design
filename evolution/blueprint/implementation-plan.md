---
id: BPT-010
title: MVP Implementation Plan
status: In Review
owner: Product and Engineering
version: 1.0.0
last_updated: 2026-08-06

references:
  - README.md
  - dashboard.md
  - lifecycle.md
  - ../roadmap/mvp-scope.md
  - ../roadmap/mvp-acceptance.md
  - ../../fondation/domain-map/context-map.md
  - ../../fondation/domain-map/dependencies.md
  - ../../fondation/domain-map/ownership.md
---

# Plan d'implémentation du MVP

## Principe directeur

Le logiciel est construit en tranches verticales démontrables, pas en couches
techniques terminées séparément. Chaque incrément livre une intention, son
autorisation, son stockage, ses événements, ses lectures, ses états UX et son
observabilité.

Les bounded contexts sont des frontières logiques obligatoires. Pour le MVP,
une architecture de type modular monolith est le point de départ recommandé
tant qu'aucune contrainte mesurée n'exige un déploiement séparé. Chaque module
conserve néanmoins :

- son modèle et son schéma possédés ;
- ses contrats publics et adaptateurs ;
- ses migrations ;
- sa transaction locale et son outbox ;
- ses permissions, journaux et tests de contrat.

Cette hypothèse de déploiement doit être confirmée dans un ADR avant la création
du squelette applicatif. Elle ne permet jamais un accès direct au stockage d'un
autre module.

---

## Ordre d'exécution

### Incrément 0 — Socle exécutable

Livrer :

- structure modulaire et règles automatiques de dépendance ;
- identité des messages : `EventId`, `CorrelationId`, `CausationId`, version et
  instant métier ;
- transaction locale avec outbox, inbox de déduplication et checkpoints ;
- catalogue d'erreurs stable et mapping des adaptateurs ;
- résolution de configuration, secrets et horloge injectables ;
- logs structurés, traces, métriques RED et audit des décisions sensibles ;
- harness de tests de contrats et fixtures déterministes.

Gate de sortie : un module exemple commit une mutation et son événement de
manière atomique, un consumer rejoue le message sans double effet et la trace
complète est consultable.

### Incrément 1 — Identité et premier Workspace

Implémenter `MVP-J1` jusqu'à `WorkspaceActivated` : inscription, vérification,
authentification, session, saga de bootstrap, rôles initiaux, membership owner
et autorisation contextualisée.

Gate de sortie : le parcours nominal, chaque point de reprise et les refus
d'accès avant activation passent automatiquement.

### Incrément 2 — Client et opportunité

Livrer les clients, contacts utiles, opportunités simples, qualification,
pipeline et historique minimal. Brancher les permissions CRM et les contextes
versionnés destinés à Billing et Analytics.

Gate de sortie : un membre autorisé crée et retrouve le contexte commercial ;
un autre Workspace et un membre non autorisé ne le peuvent pas.

### Incrément 3 — Devis, facture et paiement

Terminer `MVP-J2` : snapshots CRM/Workspace, drafts, rendu, remise publique,
acceptation/refus, orchestration `QuoteAccepted -> OpportunityWon`, factures,
acomptes, échéances, relance manuelle et paiements manuels.

Gate de sortie : un devis accepté mène à une facture soldée sans doublon après
retry ; paiements partiels, inversions, conflits et échecs de fournisseur sont
testés.

### Incrément 4 — Faits et snapshots Analytics

Ingest les faits CRM/Billing supportés, matérialise les treize métriques du
catalogue 1.0, leur fraîcheur et leur complétude, puis publie les snapshots
requis par Business Health.

Gate de sortie : une reconstruction à partir des mêmes faits produit les mêmes
observations et le même snapshot ; duplication, retard, `NoData` et source
manquante sont couverts.

### Incrément 5 — Business Health

Implémenter la politique de santé versionnée, ses quatre facteurs, sa couverture
et ses explications à partir d'un `AnalyticsSnapshot` exact.

Gate de sortie : les jeux de référence produisent scores, bandes, risques et
`InsufficientData` attendus sans recalcul Analytics.

### Incrément 6 — Advisor

Implémenter l'évaluation déterministe, le classement, l'AdvisorOverview,
l'explication, l'accomplissement, le rejet et l'expiration. Les actions sont des
routes allowlistées vers le module propriétaire.

Gate de sortie : une même évaluation rejouée converge ; zéro, une et trois
recommandations sont des résultats testés ; aucune action CRM/Billing n'est
exécutée par Advisor.

### Incrément 7 — Notifications et Dashboard

Terminer `MVP-J3` avec la planification Notifications, l'inbox, l'état lu/non
lu, les préférences et l'email important après consentement. Composer les vues
publiques dans le Dashboard selon [`dashboard.md`](dashboard.md).

Gate de sortie : la priorité stabilisée est visible et, si éligible, notifiée
une seule fois ; les vues Dashboard exposent séparément données, fraîcheur,
absence et indisponibilité.

### Incrément 8 — Durcissement et release candidate

Valider les trois parcours avec :

- sécurité, isolation Workspace et step-up des actions critiques ;
- migrations aller et retour, sauvegarde et restauration ;
- accessibilité, responsive et performance sur les volumes MVP ;
- pannes d'adaptateurs, files mortes, reprise et reconstruction ;
- politique de rétention et suppression logique ;
- feature flags, runbooks, alertes et support ;
- données de référence versionnées et démonstration reproductible.

Gate de sortie : tous les critères de
[`mvp-acceptance.md`](../roadmap/mvp-acceptance.md) sont prouvés et les risques
restants sont explicitement acceptés.

---

## Matrice des tests de frontière

| Frontière | Contrat à prouver | Risque principal |
|---|---|---|
| Identity ↔ Workspace | contexte d'accès et readiness owner | Workspace actif sans autorité valide. |
| CRM → Billing | contextes Client et Opportunity versionnés | document bâti sur une donnée mutable ou étrangère. |
| Billing → CRM | `QuoteAccepted` vers gain idempotent | Opportunity gagnée deux fois ou sans preuve. |
| CRM/Billing → Analytics | événement, puis relecture de la révision exacte | mesure incohérente ou double comptage. |
| Analytics → Business Health | snapshot exact et versionné | score calculé sur des périodes incompatibles. |
| Business Health → Advisor | assessment exact | recommandation fondée sur une vue différente. |
| Advisor → Notifications | overview stabilisé et monotone | priorité obsolète ou notification dupliquée. |
| Identity/Workspace → Notifications | audience et accès revalidés | effet externe après révocation. |
| Modules → Dashboard | lectures publiques uniquement | duplication de règles et vérité concurrente. |

Chaque frontière possède au minimum un test compatible, un test de version
incompatible, un retry, une indisponibilité et une preuve d'isolation.

---

## Données de référence

Maintenir des fixtures versionnées couvrant au moins :

1. activité vide ;
2. opportunité sans devis ;
3. devis accepté sans paiement ;
4. paiement partiel ;
5. facture soldée ;
6. client dominant ;
7. données trop anciennes ;
8. données suffisantes sans Recommendation ;
9. Recommendation `High` éligible à l'email ;
10. destinataire révoqué avant dispatch.

Ces fixtures alimentent les tests Analytics, Business Health, Advisor,
Notifications, Dashboard et la démonstration produit. Un changement attendu de
résultat doit modifier la version de politique concernée et la fixture, jamais
seulement le snapshot de test.

---

## Non-objectifs de ce plan

Ce plan ne lance pas `Automation`, `Projects`, une marketplace, une API publique
externe ou des connecteurs produit. Toute dépendance du MVP à l'un de ces
éléments signale un écart de périmètre à résoudre avant développement.
