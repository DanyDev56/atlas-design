---
id: ADV-INTEGRATIONS
title: Advisor Integrations
status: In Review
owner: Product
version: 1.2.0
last_updated: 2026-08-06

references:
  - api.md
  - events.md
  - workflows.md
  - actions.md
  - ../business-health/api.md
  - ../business-health/events.md
  - ../workspace/api.md
  - ../identity/api.md
  - ../crm/permissions.md
  - ../billing/permissions.md
---

# Intégrations

## Identity

Identity résout les capacités Advisor de lecture, completion et dismissal. Les
workloads utilisent exclusivement les capacités SystemActorOnly. Advisor ne lit
ni Membership, ni Role, ni Session dans le stockage Identity.

La capacité requise par RecommendationAction reste une exigence du domaine
cible. Advisor ne l'accorde et ne la délègue jamais.

## Workspace

Advisor consomme :

```text
getWorkspaceAccessContext(workspaceId)
```

Le contrat vérifie frontière et état d'accès. Advisor ne copie ni profil
commercial, ni identité de facturation, ni préférences métier.

## Business Health

Advisor consomme :

```text
BusinessHealthAssessed

getBusinessHealthAssessment(workspaceId, businessHealthAssessmentId)
→ BusinessHealthAssessment

getLatestBusinessHealthAssessment(workspaceId, minimumAsOf?,
                                  acceptedPolicyVersions?)
→ BusinessHealthAssessment | AssessmentUnavailable
```

La lecture exacte exige `business-health.assessments.consume`. L'événement est
un signal ; le contrat relu fournit la valeur. Advisor vérifie identité, statut,
fiabilité, politique, AsOf et caractère courant.

## Analytics

Advisor 1.0 ne possède pas `analytics.snapshots.consume` et ne relit aucun
AnalyticsSnapshot. Les EvidenceReferences transitives restent des références
d'audit issues de Business Health, pas une autorisation de lecture.

## CRM et Billing

Advisor ne consomme aucune API CRM ou Billing en 1.0. Il reconnaît uniquement
les capacités publiques suivantes dans ses ActionDescriptor :

```text
crm.opportunities.read
crm.opportunities.create
billing.invoices.read
business-health.assessments.read
```

Le client Atlas ouvre la RouteKey. Toute commande ultérieure est initiée par
l'utilisateur, directement auprès du domaine propriétaire.

## Notifications

Notifications consomme exclusivement `AdvisorOverviewChanged`, que la cause soit
une évaluation, une source invalidante, une completion, un dismissal ou une
expiration. Il relit la version exacte par
`getAdvisorOverviewForNotification` avec
`advisor.recommendations.consume`. `RecommendationEvaluationCompleted` et les
événements Recommendation restent auditables mais ne planifient aucune
diffusion. Advisor ne décide ni canal, ni cadence, ni destinataire. La vue
fournie contient uniquement status, priorité, template et données minimales
allowlistés, ActionDescriptor et ValidUntil ; elle inclut
AdvisorOverviewVersion, ConvergenceKind et SourceOrder, puis exclut preuves
détaillées, montants, Clients et documents.

## Product Analytics et Automation

Product Analytics reçoit l'instrumentation d'affichage, ouverture et clic hors
du modèle Advisor. Automation ne consomme aucune RecommendationAction en 1.0 ;
une future exécution exigera consentement et contrat propres.

## Garanties

- aucune base de données partagée ;
- lecture exacte après signal minimal ;
- retries avec backoff, déduplication et dead-letter observable ;
- aucune source indisponible ne devient une recommandation ancienne ou inventée ;
- aucune autorité utilisateur relayée vers un domaine cible.
