---
id: NTF-PERMISSIONS
title: Notifications Permissions
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - scope.md
  - invariants.md
  - api.md
  - commands/README.md
  - processors/README.md
  - ../identity/permissions.md
---

# Permissions

Notifications définit ses capacités. Identity les enregistre, les affecte aux
rôles et résout leur efficacité dans un Workspace.

## Capacités attribuables à un rôle

| Clé | Sensibilité | Intention |
|---|---|---|
| `notifications.inbox.read` | Standard | Consulter ses propres notifications et son compteur non lu. |
| `notifications.inbox.mark-read` | Standard | Modifier l'état lu/non lu de ses propres notifications. |
| `notifications.preferences.read` | Standard | Consulter ses propres préférences de notification. |
| `notifications.preferences.change` | Standard | Modifier ses propres préférences et consentements de canal. |

Mark-read implique inbox-read ; preference-change implique preference-read. Le
rôle owner actif conserve ces capacités selon la politique produit du
Workspace.

Ces capacités restent personnelles : un administrateur ne lit ni ne modifie les
notifications d'un autre User par simple élévation de rôle.

## Capacités SystemActorOnly

| Clé | Usage borné |
|---|---|
| `notifications.signals.process` | Transformer un signal Advisor supporté en NotificationPlan. |
| `notifications.deliveries.dispatch` | Soumettre une NotificationDelivery à son fournisseur. |
| `notifications.deliveries.record-outcome` | Enregistrer une preuve de livraison ou d'échec du fournisseur. |
| `notifications.items.expire` | Matérialiser une échéance prouvée par l'horloge. |

Elles ne peuvent pas être accordées à un rôle humain. Elles exigent identité de
workload, portée Workspace, causalité, RequestId et audit.

## Refus par défaut

- un Workspace différent est toujours refusé ;
- le RecipientUserId doit être l'utilisateur effectif pour toute lecture ou
  mutation humaine ;
- un Workspace restreint bloque les intentions humaines ordinaires et toute
  nouvelle livraison ;
- une capacité Notifications n'accorde aucune capacité Advisor ni droit sur la
  RecommendationAction ;
- une capacité système ne peut pas être remplacée par une capacité humaine ;
- aucune adresse email brute n'est retournée dans une décision d'autorisation.
