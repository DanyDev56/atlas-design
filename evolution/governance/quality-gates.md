---
id: GOV-002
title: Product Quality Gates
status: In Review
owner: Product and Engineering
version: 1.0.0
last_updated: 2026-08-06

references:
  - README.md
  - release-policy.md
  - consolidation-matrix.md
  - ../roadmap/mvp-acceptance.md
  - ../blueprint/implementation-plan.md
---

# Quality Gates

Une tranche produit ne peut être publiée que si les preuves suivantes sont
attachées à sa version. Une case déclarative sans test, démonstration ou décision
référencée ne constitue pas une validation.

## Métier et contrats

- règles, invariants, commandes, événements, lectures et erreurs sont
  documentés par leur domaine propriétaire ;
- permissions exactes et autorités système sont testées ;
- contrats entre domaines sont versionnés et testés des deux côtés ;
- idempotence, concurrence, causalité et états d'absence sont couverts ;
- aucun accès au stockage ou au modèle privé d'un autre domaine n'existe.

## Expérience

- parcours nominal, onboarding, états vides et erreurs sont validés ;
- les textes respectent Product Language et n'exposent pas le jargon technique
  sans utilité ;
- navigation clavier, focus, labels, contrastes et annonces d'état sont testés ;
- `NoData`, `InsufficientData`, résultat vide et indisponibilité sont
  distingués ;
- la fraîcheur et la provenance d'une décision dérivée sont visibles.

## Sécurité et données

- modèle de menace et revue d'isolation Workspace à jour ;
- refus par défaut, step-up et réduction immédiate des privilèges démontrés ;
- secrets, preuves publiques et données personnelles absents des événements et
  logs ordinaires ;
- rétention, suppression logique, export et restauration définis ;
- effets externes revalidés, idempotents, auditables et bornés.

## Engineering et exploitation

- tests unitaires, d'intégration, de contrat et de bout en bout verts ;
- migration testée, stratégie de rollback ou de forward-fix documentée ;
- logs structurés, traces, métriques, alertes et runbook disponibles ;
- retry, checkpoint, file morte, reconstruction et panne fournisseur testés ;
- objectifs de performance et volumes représentatifs vérifiés ;
- feature flags supprimables et comportement désactivé testés.

## Produit

- la tranche respecte la Constitution, les anti-objectifs et le périmètre ;
- elle renforce une Capability et améliore une décision utilisateur identifiée ;
- outcome, garde-fous et instrumentation de succès sont définis ;
- le coût opérationnel et le support sont acceptés ;
- toute extension de périmètre est explicitement décidée.

## Gate de release MVP

En plus des sections précédentes :

1. `MVP-J1`, `MVP-J2` et `MVP-J3` passent avec les scénarios transverses de
   [`mvp-acceptance.md`](../roadmap/mvp-acceptance.md) ;
2. `scripts/check-mvp-blueprint-docs.sh` passe ;
3. les huit checkers de bounded context passent ;
4. les fixtures de référence produisent les résultats attendus ;
5. les risques résiduels possèdent un owner et une décision d'acceptation.
