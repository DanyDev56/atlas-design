---
id: NTF-CMD-CHANGE-NOTIFICATION-PREFERENCES
title: ChangeNotificationPreferences
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - ../notification-policy.md
  - ../invariants.md
  - ../events.md
  - ../permissions.md
---

# ChangeNotificationPreferences

## Objectif

Modifier les canaux personnels utilisés pour les notifications produit.

## Agrégat concerné

`NotificationPreference`.

## Acteur et autorité

User actif disposant de `notifications.preferences.change`, pour lui-même dans
le même Workspace.

## Données d'entrée

```text
WorkspaceId
UserId
InAppPreference: Enabled | Disabled
EmailPreference: Disabled | ImportantOnly
EmailConsentConfirmation?
ExpectedRevision
ChangeNotificationPreferencesRequestId
ActorContext
```

ExpectedRevision vaut zéro si aucun agrégat n'existe encore. Les valeurs par
défaut virtuelles sont InApp Enabled et Email Disabled.

## Préconditions

- acteur identique à UserId et actif dans le Workspace ;
- préférences supportées par NotificationPolicy courante ;
- passage d'Email à ImportantOnly accompagné d'un consentement explicite ;
- ExpectedRevision égale à zéro ou à la révision courante.

L'absence momentanée de DeliveryEndpointReference n'empêche pas
l'enregistrement du choix ; elle supprime toute livraison Email jusqu'à ce
qu'Identity fournisse une référence vérifiée.

## Invariants concernés

`NTF-INV-001`–`NTF-INV-003`, `NTF-INV-011`–`NTF-INV-020`,
`NTF-INV-049`–`NTF-INV-058`.

## Événement produit

- `NotificationPreferenceChanged`.

## Erreurs métier

`InvalidInput`, `Unauthenticated`, `Unauthorized`, `UnsupportedChannel`,
`ConsentRequired`, `Conflict`.

## Idempotence

ChangeNotificationPreferencesRequestId est obligatoire. Le rejeu identique
retourne la préférence initialement écrite ; toute réutilisation avec d'autres
valeurs échoue avec `Conflict`.
