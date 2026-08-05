---
id: NTF-SCOPE
title: Notifications Scope
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - mission.md
  - notification-policy.md
  - lifecycle.md
  - integrations.md
  - future.md
---

# Périmètre

## Inclus dans Notifications 1.0

### Planification

- consommation idempotente de `RecommendationEvaluationCompleted` ;
- traitement des Recommendation Completed, Dismissed et Expired ;
- lecture de l'AdvisorOverview exact stabilisé ;
- sérialisation monotone d'AdvisorOverviewVersion par topic ;
- résolution d'une audience Identity autorisée ;
- application des préférences et de la politique de canal ;
- déduplication, remplacement et résolution par thread.

### Inbox

- Notification personnelle dans un Workspace ;
- liste paginée et compteur unread ;
- états unread et read ;
- historique resolved, superseded et expired ;
- action Advisor copiée sous forme de destination allowlistée.

### E-mail important

- opt-in explicite par utilisateur et Workspace ;
- seulement pour une PrimaryRecommendation High ou Critical ;
- contenu minimal sans montant, Client ou document ;
- référence opaque à un endpoint e-mail vérifié ;
- limite de fréquence et suppression auditée ;
- tentatives, retries, accepted, delivered et failed distincts.

## Hors périmètre

| Responsabilité | Propriétaire ou horizon |
|---|---|
| choix de l'action et RecommendationPriority | `Advisor` |
| User, Membership, permission et e-mail vérifié | `Identity` |
| e-mails de vérification, invitation, récupération et sécurité | port Communication Identity |
| envoi d'une Quote, Invoice ou relance Client | `Billing` / Communication |
| modification CRM ou Billing depuis le message | domaine source |
| SMS, push mobile, webhook et messagerie instantanée | futur |
| marketing, campagnes et newsletters | système dédié futur |
| automatisation d'une action | `Automation`, futur |
| impressions, clics et conversion | Product Analytics |
| digest, quiet hours et fuseau par utilisateur | futur |

## Limites 1.0

- Advisor est l'unique source métier ;
- `InApp` et `Email` sont les seuls canaux ;
- l'e-mail est désactivé par défaut ;
- un seul topic `AdvisorPrimaryRecommendation` ;
- une seule notification active par recipient/thread ;
- aucun contenu libre ou génératif ;
- aucun secret, adresse brute ou donnée financière détaillée ;
- aucune garantie de livraison lorsqu'un fournisseur accepte un message.
