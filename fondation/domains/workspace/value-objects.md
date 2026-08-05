---
id: WSP-VALUE-OBJECTS
title: Workspace Value Objects
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - model.md
  - entities.md
  - invariants.md
---

# Value Objects

Tous les Value Objects sont immuables, validés à la construction et comparés
par valeur normalisée.

## Identifiants et versions

| Valeur | Rôle |
|---|---|
| `WorkspaceId` | identifie uniquement un Workspace |
| `WorkspaceRequestId` | racine des clés d'idempotence spécialisées |
| `Revision` | protège la concurrence optimiste |
| `GovernanceVersion` | versionne l'accès public |
| `ProfileVersion` | versionne le profil commercial |
| `BillingIdentityVersion` | versionne l'identité de facturation |
| `PreferencesVersion` | versionne les préférences |

Une version est strictement croissante dans son périmètre et n'est jamais
réutilisée.

---

## WorkspaceProfile

```text
WorkspaceProfile
├── DisplayName
├── TradingName?
├── ActivityDescription?
├── BusinessAddress?
└── ContactDetails?
```

`DisplayName` est obligatoire, normalisé pour les espaces superflus et borné en
longueur. Il n'a pas besoin d'être globalement unique.

Le profil sert à l'affichage et au contexte commercial. Il ne constitue pas une
preuve légale.

---

## BillingIdentity

```text
BillingIdentity
├── LegalName?
├── BillingAddress?
├── AdministrativeEmail?
├── RegistrationIdentifiers[]
└── TaxIdentifiers[]
```

Un identifiant déclaré contient :

- un type canonique ;
- une valeur normalisée ;
- un pays émetteur lorsque pertinent.

Workspace valide la forme supportée, l'unicité du type et l'absence de donnée
manifestement invalide. Billing ou une intégration réglementaire reste
responsable de la validité nécessaire à une opération financière.

---

## WorkspacePreferences

```text
WorkspacePreferences
├── Locale
├── TimeZone
├── DefaultCurrency
└── EstablishmentCountry
```

- `Locale` utilise une balise canonique compatible BCP 47 ;
- `TimeZone` utilise un identifiant IANA ;
- `DefaultCurrency` utilise un code ISO 4217 supporté ;
- `EstablishmentCountry` utilise un code ISO 3166-1 alpha-2 supporté.

Une nouvelle préférence ne modifie jamais rétroactivement un fait ou snapshot.

---

## WorkspaceAccessContext

Contrat public immuable :

```text
WorkspaceAccessContext
├── WorkspaceId
├── AccessState: Active | Restricted | Closed
├── GovernanceVersion
└── RequiresActiveOwner: true
```

Il ne révèle ni la raison détaillée d'une restriction, ni les données de profil.

---

## OwnerReadinessProof

Preuve bornée émise par le contrat public d'Identity :

```text
OwnerReadinessProof
├── WorkspaceId
├── HasActiveOwner
├── IdentityGovernanceVersion
├── AssessedAt
└── ValidUntil
```

La preuve est refusée si sa portée ne correspond pas au Workspace ou si elle est
expirée.

---

## ClosureReadinessProof

Attestation bornée produite par l'orchestrateur de fermeture :

```text
ClosureReadinessProof
├── WorkspaceId
├── AssessmentId
├── RequiredDomains[]
├── BlockingCategories[]
├── AssessedAt
└── ValidUntil
```

Une fermeture volontaire exige une liste de blocages vide. Une autorité de
conformité peut fournir une dérogation explicite et auditée sans masquer les
blocages constatés. La preuve ne contient aucune donnée métier appartenant aux
domaines consultés.

---

## RestrictionContext et ClosureContext

Ces valeurs contiennent une catégorie de raison stable, l'acteur ou workflow
auditable et l'instant de décision. Les notes sensibles restent dans le journal
d'audit restreint, pas dans les événements publics.
