---
id: WSP-PUBLIC-CONTRACT
title: Workspace Public Contract
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - scope.md
  - invariants.md
  - events.md
  - permissions.md
  - ../identity/api.md
---

# Contrat public de Workspace

Ce contrat décrit des intentions et lectures indépendantes du transport.

## Commandes

```text
createWorkspace(initialProfile, initialPreferences, initialOwnerUserId,
                createWorkspaceRequestId)

activateWorkspace(workspaceId, ownerReadinessProof, expectedRevision,
                  activateWorkspaceRequestId)

updateWorkspaceProfile(workspaceId, profileChanges, expectedRevision,
                       updateWorkspaceProfileRequestId)

updateWorkspaceBillingIdentity(workspaceId, billingIdentity, expectedRevision,
                               updateBillingIdentityRequestId)

changeWorkspacePreferences(workspaceId, preferences, expectedRevision,
                           changePreferencesRequestId)

restrictWorkspace(workspaceId, restrictionContext, expectedRevision,
                  restrictWorkspaceRequestId)

restoreWorkspaceAccess(workspaceId, ownerReadinessProof, expectedRevision,
                       restoreWorkspaceRequestId)

closeWorkspace(workspaceId, closureReadinessProof, confirmation,
               expectedRevision, closeWorkspaceRequestId)
```

Chaque commande applique l'autorité et l'idempotence définies dans sa fiche.

---

## Lectures

### Contexte d'accès

Contrat consommé par Identity :

```text
getWorkspaceAccessContext(workspaceId)
→
WorkspaceId
AccessState: Active | Restricted | Closed
GovernanceVersion
RequiresActiveOwner
```

Une ressource absente produit `NotFound`. Un Workspace en provisioning retourne
`Restricted`; il n'est jamais traité comme actif.

### Résumé

```text
getWorkspaceSummary(workspaceId)
→
WorkspaceId
DisplayName
AccessState
ProfileVersion
```

La réponse exige `workspace.profile.read` ou une capacité de domaine bornée. Elle
ne contient aucune donnée administrative sensible.

### Profil

```text
getWorkspaceProfile(workspaceId)
→ WorkspaceProfile, ProfileVersion
```

La lecture exige `workspace.profile.read` ou une capacité de domaine bornée.

### Identité de facturation

```text
getWorkspaceBillingIdentity(workspaceId)
→ BillingIdentity, BillingIdentityVersion
```

Ce contrat exige `workspace.billing-identity.read` ou une capacité de domaine
bornée. Billing persiste un snapshot lorsqu'une règle historique l'exige.

### Préférences

```text
getWorkspacePreferences(workspaceId)
→ WorkspacePreferences, PreferencesVersion
```

La lecture exige `workspace.preferences.read` ou une capacité de domaine bornée.

---

## Fait public d'accès

```text
WorkspaceAccessStateChanged
```

Son schéma canonique est défini dans [`events.md`](events.md). Il confirme le
contrat attendu par Identity 1.0.

---

## Erreurs publiques

| Catégorie | Sens |
|---|---|
| `InvalidInput` | valeur syntaxiquement invalide |
| `Unauthenticated` | principal absent ou invalide |
| `Unauthorized` | autorité insuffisante |
| `NotFound` | Workspace absent ou volontairement masqué |
| `InvalidState` | transition interdite |
| `InvariantViolation` | règle absolue menacée |
| `Conflict` | révision ou idempotence incompatible |
| `UnsupportedPreference` | locale, fuseau, devise ou pays non supporté |
| `ReadinessRequired` | preuve inter-domaine absente ou invalide |
| `ClosureBlocked` | readiness de fermeture incomplète |
| `TemporarilyUnavailable` | dépendance requise indisponible |

---

## Versioning

- ajout compatible de champs optionnels ;
- nouvelle version pour tout changement de sens ;
- aucune réutilisation d'un nom d'événement ou d'une valeur enum dépréciée ;
- consommateurs tolérants aux champs inconnus ;
- réduction de privilège effective sans attendre une migration de cache.
