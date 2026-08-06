---
id: NTF-INTEGRATIONS
title: Notifications Integrations
status: In Review
owner: Product
version: 1.1.0
last_updated: 2026-08-06

references:
  - api.md
  - events.md
  - workflows.md
  - ../advisor/api.md
  - ../advisor/events.md
  - ../identity/api.md
  - ../identity/permissions.md
  - ../workspace/api.md
---

# Intégrations

## Advisor

Notifications consomme :

```text
AdvisorOverviewChanged

getAdvisorOverviewForNotification(workspaceId, advisorOverviewVersion)
→ NotificationAdvisorOverview

getRecommendationForNotification(workspaceId, recommendationId)
→ NotificationRecommendationView
```

La lecture exacte exige `advisor.recommendations.consume`. Le contrat fournit
AdvisorOverviewVersion, OverviewConvergenceKind, SourceOrder, RecommendationId, status, Priority,
template versionné, données minimales, ActionDescriptor allowlisté et
ValidUntil. Notifications ne consomme aucun événement Recommendation ou
RecommendationEvaluationCompleted et ne recalcule jamais le classement.

## Identity

Notifications consomme :

```text
resolveWorkspaceNotificationAudience(workspaceId, requiredPermissionKeys[],
                                     audiencePurpose)
→ AudienceSnapshot

revalidateNotificationRecipient(workspaceId, userId, requiredPermissionKeys[],
                                audienceVersion?, endpointReference?)
→ NotificationRecipientValidation
```

La résolution exige `identity.notification-audience.read`. AudienceSnapshot
contient AudienceVersion et, par destinataire, UserId, MembershipId et une
DeliveryEndpointReference optionnelle. Aucune adresse brute ne quitte
Identity.

La revalidation juste avant dispatch confirme état, capacités et validité de
l'endpoint opaque. Identity conserve entièrement vérification d'adresse,
invitations, récupération de compte et communications de sécurité.

## Workspace

Notifications consomme :

```text
getWorkspaceAccessContext(workspaceId)
getWorkspacePreferences(workspaceId)
```

Le premier contrat vérifie frontière et état Active. Le second fournit la
locale supportée nécessaire au template. Notifications ne copie ni profil
commercial, ni identité de facturation, ni préférences métier.

## Fournisseur Email

Un port sortant minimal accepte :

```text
submitImportantNotification(endpointReference, notificationTemplateKey,
                            notificationTemplateVersion,
                            locale, minimalTemplateData,
                            providerIdempotencyKey)
→ Accepted(providerMessageReference, acceptedAt)
  | TransientFailure(reason)
  | PermanentFailure(reason)
```

Les callbacks authentifiés produisent Delivered, Bounced, Complained ou
PermanentlyFailed. Le fournisseur n'est jamais source de NotificationStatus ni
de NotificationReadState.

L'adaptateur Atlas résout DeliveryEndpointReference par le port confidentiel
Identity uniquement au dernier moment, transmet l'adresse au fournisseur puis
l'oublie. Le domaine Notifications, son outbox et ses logs ne voient jamais
cette valeur brute.

## Billing et communications Identity

Les Quotes, Invoices, reminders et preuves de remise financières restent dans
le flux Communication de Billing. Les e-mails de vérification, invitation,
récupération et sécurité restent dans Identity. Notifications 1.0 n'est pas un
transport générique pour ces messages.

## CRM, Analytics, Business Health et Product Analytics

Notifications ne lit aucun stockage ou contrat CRM, Billing, Analytics ou
Business Health. Product Analytics reçoit séparément la télémétrie d'affichage,
ouverture et clic ; cette instrumentation ne change aucun agrégat Notifications.

## Garanties

- aucune base de données partagée ;
- signal minimal puis lecture exacte ;
- endpoint opaque, contenu allowlisté et logs expurgés ;
- retries avec backoff, clé fournisseur stable, déduplication et dead-letter
  observable ;
- indisponibilité d'une source ou d'Identity ne produit ni audience supposée ni
  message ancien ;
- aucune capacité ou action Advisor n'est relayée vers un domaine cible.
