---
id: WSP-INVARIANTS
title: Workspace Invariants
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - model.md
  - entities.md
  - aggregates.md
  - value-objects.md
  - events.md
---

# Invariants

## Catalogue

| ID | Règle absolue |
|---|---|
| `WSP-INV-001` | Un `WorkspaceId` identifie durablement un seul Workspace. |
| `WSP-INV-002` | Le cycle de vie suit uniquement les transitions autorisées et `Closed` est terminal. |
| `WSP-INV-003` | Un Workspace actif possède un profil et des préférences valides. |
| `WSP-INV-004` | Un Workspace ne devient utilisable qu'après confirmation d'un owner actif par Identity. |
| `WSP-INV-005` | L'état d'accès public est dérivé sans ambiguïté du statut interne. |
| `WSP-INV-006` | Seul Workspace modifie son profil, ses préférences et son cycle de vie. |
| `WSP-INV-007` | L'identité de facturation respecte la structure canonique supportée. |
| `WSP-INV-008` | Une mutation courante ne réécrit jamais un snapshot historique externe. |
| `WSP-INV-009` | Locale, fuseau, devise et pays utilisent des valeurs canoniques supportées. |
| `WSP-INV-010` | Toute modification affectant l'accès incrémente `GovernanceVersion`. |
| `WSP-INV-011` | Une autorisation ordinaire est évaluée dans le même Workspace actif. |
| `WSP-INV-012` | `Restricted` et `Closed` refusent l'usage ordinaire. |
| `WSP-INV-013` | Toute mutation vérifie la révision attendue. |
| `WSP-INV-014` | Toute commande est idempotente dans sa portée métier. |
| `WSP-INV-015` | État et événements correspondants sont enregistrés atomiquement. |
| `WSP-INV-016` | La fermeture est logique et ne supprime pas l'historique requis. |
| `WSP-INV-017` | Une donnée ou preuve d'un Workspace ne s'applique jamais implicitement à un autre. |
| `WSP-INV-018` | Le provisioning incomplet n'est jamais exposé comme actif. |

---

## WSP-INV-001 — Identité durable

Un `WorkspaceId` n'est ni un nom, ni un slug, ni un identifiant légal. Il reste
stable lors de toute modification du profil.

Un identifiant fermé ne peut pas être recyclé pour une nouvelle activité.

---

## WSP-INV-002 — Cycle de vie

Transitions autorisées :

```text
Provisioning -> Active
Provisioning -> Closed
Active       -> Restricted
Active       -> Closed
Restricted   -> Active
Restricted   -> Closed
```

Toutes les autres transitions sont refusées. `Closed` ne possède aucune
transition sortante en 1.0.

---

## WSP-INV-003 — Readiness locale

Avant `Active`, le Workspace possède au minimum :

- un `DisplayName` valide ;
- une `Locale` supportée ;
- un `TimeZone` supporté ;
- une `DefaultCurrency` supportée ;
- un `EstablishmentCountry` supporté.

L'identité de facturation complète peut être exigée par Billing avant
l'émission d'un document, mais elle ne bloque pas l'activation générale.

---

## WSP-INV-004 — Owner actif

`ActivateWorkspace` et `RestoreWorkspaceAccess` exigent une
`OwnerReadinessProof` :

- émise par le contrat public d'Identity ;
- portant le même `WorkspaceId` ;
- non expirée ;
- indiquant `HasActiveOwner = true`.

Workspace ne déduit jamais cette information en lisant le stockage d'Identity.

---

## WSP-INV-005 — Projection d'accès

```text
Provisioning -> Restricted
Active       -> Active
Restricted   -> Restricted
Closed       -> Closed
```

L'état public ne peut pas être écrit directement.

---

## WSP-INV-006 — Propriété du modèle

Un consommateur peut copier un snapshot ou demander une commande. Il ne peut ni
modifier une valeur Workspace, ni reconstruire un état public concurrent.

---

## WSP-INV-007 — Identité de facturation structurée

- aucune chaîne vide après normalisation ;
- au plus une valeur par type d'identifiant et pays émetteur ;
- formats et longueurs bornés ;
- adresses e-mail et codes pays syntaxiquement valides ;
- aucune donnée secrète ou credential.

La validation réglementaire approfondie appartient au domaine qui réalise
l'acte réglementé.

---

## WSP-INV-008 — Non-rétroactivité

Une modification du profil, de l'identité de facturation ou des préférences :

- produit une nouvelle version ;
- s'applique aux usages futurs ;
- ne modifie jamais une facture, un devis ou un événement historique ;
- ne demande pas à un consommateur d'écraser ses snapshots.

---

## WSP-INV-009 — Valeurs canoniques

Les listes de locales, fuseaux, devises et pays supportés sont versionnées. Une
valeur devenue dépréciée reste interprétable pendant sa migration et n'est pas
réutilisée avec un autre sens.

---

## WSP-INV-010 — Version de gouvernance

`GovernanceVersion` augmente lors de :

- l'activation ;
- la restriction ;
- la restauration ;
- la fermeture ;
- toute future modification de `RequiresActiveOwner`.

Une mise à jour purement descriptive ne l'incrémente pas.

---

## WSP-INV-011 — Autorisation contextualisée

Une commande initiée par un membre exige :

- un principal actif ;
- une décision Identity portant le même `WorkspaceId` ;
- la permission exacte ;
- le niveau d'authentification requis ;
- un Workspace `Active`.

Les workflows système possèdent une autorité distincte, bornée et auditée.

---

## WSP-INV-012 — Accès non ordinaire

`Restricted` permet uniquement les commandes explicitement désignées comme
remédiation ou sécurité. `Closed` refuse toute mutation ordinaire.

La consultation légale ou le règlement d'un document historique peut continuer
dans son domaine propriétaire sans réactiver l'accès ordinaire au Workspace.

---

## WSP-INV-013 — Concurrence

Chaque mutation reçoit `ExpectedRevision`. Une révision différente produit un
`Conflict` sans événement métier.

Les preuves externes sont réévaluées après tout retry susceptible de dépasser
leur durée de validité.

---

## WSP-INV-014 — Idempotence

La portée d'une clé inclut au minimum l'intention, l'acteur et le Workspace.
Même clé et même empreinte d'entrée retournent le même résultat ; même clé et
entrée différente produisent un conflit.

---

## WSP-INV-015 — Atomicité

Une transition validée ne devient visible qu'avec ses Domain Events et son
entrée d'outbox. Aucun événement de réussite n'est publié après un refus.

---

## WSP-INV-016 — Conservation

`CloseWorkspace` conserve l'identifiant, les versions, les faits et les données
requises par les obligations de rétention. L'effacement ou l'anonymisation est un
traitement de conformité distinct et auditable.

---

## WSP-INV-017 — Isolation

Toute commande, preuve, clé d'idempotence, projection et snapshot est borné par
`WorkspaceId`. Une absence de contexte entraîne un refus par défaut.

---

## WSP-INV-018 — Provisioning invisible

`WorkspaceCreated` ne signifie pas que l'espace est utilisable. Le passage à
`Active` intervient uniquement après la fin du workflow de bootstrap et produit
un fait distinct.
