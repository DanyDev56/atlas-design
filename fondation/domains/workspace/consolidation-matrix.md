---
id: WSP-CONSOLIDATION
title: Workspace Consolidation Matrix
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - mission.md
  - scope.md
  - model.md
  - entities.md
  - aggregates.md
  - value-objects.md
  - relationships.md
  - invariants.md
  - decision-record.md
  - permissions.md
  - events.md
  - api.md
  - integrations.md
  - workflows.md
  - future.md
  - glossary.md
  - commands/README.md
---

# Matrice de consolidation

## Sources canoniques

| Source | Présente | Normalisée | Cohérente | Complète 1.0 |
|---|---:|---:|---:|---:|
| Mission et scope | Oui | Oui | Oui | Oui |
| Modèle, entités et agrégats | Oui | Oui | Oui | Oui |
| Value Objects et relations | Oui | Oui | Oui | Oui |
| Invariants | Oui | Oui | Oui | Oui |
| Décisions | Oui | Oui | Oui | Oui |
| Permissions | Oui | Oui | Oui | Oui |
| Commandes | Oui | Oui | Oui | Oui |
| Domain Events | Oui | Oui | Oui | Oui |
| API publique | Oui | Oui | Oui | Oui |
| Intégrations et workflows | Oui | Oui | Oui | Oui |
| Glossaire et futur | Oui | Oui | Oui | Oui |

---

## Traçabilité des commandes

| Commande | Autorité | Permission | Invariants principaux | Domain Events | Idempotence |
|---|---|---|---|---|---|
| `CreateWorkspace` | User éligible ou onboarding | — | 001, 003, 005, 009, 014, 015, 018 | `WorkspaceCreated` | `CreateWorkspaceRequestId` |
| `ActivateWorkspace` | Bootstrap de confiance | `workspace.lifecycle.activate` | 002–005, 010, 013–015, 018 | `WorkspaceActivated`, `WorkspaceAccessStateChanged` | `ActivateWorkspaceRequestId` |
| `UpdateWorkspaceProfile` | Membre autorisé | `workspace.profile.update` | 003, 006, 008, 011, 013–015, 017 | `WorkspaceProfileUpdated` | `UpdateWorkspaceProfileRequestId` |
| `UpdateWorkspaceBillingIdentity` | Membre autorisé avec step-up | `workspace.billing-identity.update` | 006–008, 011, 013–015, 017 | `WorkspaceBillingIdentityUpdated` | `UpdateBillingIdentityRequestId` |
| `ChangeWorkspacePreferences` | Membre autorisé | `workspace.preferences.change` | 003, 006, 008, 009, 011, 013–015, 017 | `WorkspacePreferencesChanged` | `ChangePreferencesRequestId` |
| `RestrictWorkspace` | Sécurité ou conformité | `workspace.lifecycle.restrict` | 002, 005, 010, 012–017 | `WorkspaceRestricted`, `WorkspaceAccessStateChanged` | `RestrictWorkspaceRequestId` |
| `RestoreWorkspaceAccess` | Sécurité ou conformité | `workspace.lifecycle.restore` | 002–005, 010, 012–015, 017 | `WorkspaceAccessRestored`, `WorkspaceAccessStateChanged` | `RestoreWorkspaceRequestId` |
| `CloseWorkspace` | Owner avec step-up ou conformité | `workspace.lifecycle.close`, `workspace.lifecycle.close-for-compliance` | 001, 002, 005, 010, 012–017 | `WorkspaceClosed`, `WorkspaceAccessStateChanged` | `CloseWorkspaceRequestId` |

Les numéros abrégés dans la matrice désignent `WSP-INV-nnn`. Les fiches de
commande contiennent les références complètes et normatives.

---

## Décisions 1.0

| Sujet | Décision |
|---|---|
| Concept | une activité professionnelle, sans hypothèse de forme juridique |
| Agrégats | un unique agrégat `Workspace` |
| Statuts | `Provisioning`, `Active`, `Restricted`, `Closed` |
| Accès public | `Active`, `Restricted`, `Closed` |
| Owner | requis ; propriété et preuve fournies par Identity |
| Profil commercial | propriété de Workspace |
| Identité de facturation | propriété de Workspace, snapshot par le consommateur |
| Règles financières | propriété de Billing |
| Fermeture | logique, terminale, non destructive |
| MVP mono-workspace | limite d'expérience, pas invariant du domaine |
| Historique | aucune mise à jour rétroactive des snapshots |

---

## Quality gates Workspace 1.0

- [x] Toutes les sources canoniques sont présentes.
- [x] Chaque responsabilité possède un domaine propriétaire explicite.
- [x] Toutes les transitions de cycle de vie sont fermées.
- [x] Toutes les commandes figurent dans le catalogue.
- [x] Chaque commande référence des invariants et une règle d'idempotence.
- [x] Tous les événements possèdent un producteur tracé.
- [x] Toutes les permissions utilisées figurent dans le catalogue.
- [x] Le contrat `getWorkspaceAccessContext` confirme celui d'Identity.
- [x] La preuve d'owner ne divulgue aucun modèle Identity.
- [x] La frontière Workspace/Billing empêche la duplication des règles financières.
- [x] Les snapshots historiques sont non rétroactifs.
- [x] La fermeture conserve l'historique.
- [x] Les questions futures sont hors du contrat 1.0.
- [x] Les références et liens locaux sont valides.
- [x] Les contrôles documentaires automatisés passent.

Commande de vérification :

```bash
scripts/check-workspace-docs.sh
```

Workspace 1.0 est `In Review` depuis le 5 août 2026. Le statut `Stable` exige
une confrontation avec l'implémentation et les tests de domaine.
