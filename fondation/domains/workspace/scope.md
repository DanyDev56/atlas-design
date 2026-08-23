---
id: WSP-SCOPE
title: Workspace Scope
status: In Review
owner: Product
version: 1.0.1
last_updated: 2026-08-23

references:
  - README.md
  - mission.md
  - model.md
  - integrations.md
  - future.md
  - ../../product/pricing-strategy.md
---

# Périmètre

## Inclus dans Workspace 1.0

### Identité de l'activité

- `WorkspaceId` stable ;
- nom d'affichage et nom commercial ;
- description courte de l'activité ;
- coordonnées professionnelles ;
- adresse commerciale.

### Identité de facturation

- nom légal de l'émetteur ;
- adresse de facturation ;
- identifiants d'enregistrement déclarés ;
- identifiants fiscaux déclarés ;
- adresse e-mail administrative.

Workspace garantit la structure et la version de ces données. Il ne détermine
pas leur validité fiscale ou juridique dans chaque juridiction.

### Préférences principales

- locale ;
- fuseau horaire ;
- devise par défaut ;
- pays d'établissement déclaré.

Ces préférences fournissent des valeurs par défaut. Elles ne remplacent jamais
les règles d'un document métier ni les données historisées par un autre domaine.

### Cycle de vie

- création en cours de provisioning ;
- activation après bootstrap ;
- restriction et restauration de l'accès ;
- fermeture logique terminale ;
- contexte d'accès public versionné.

### Contrats transverses

- lecture du contexte d'accès par `Identity` ;
- lecture de snapshots de profil par `Billing` et les domaines autorisés ;
- publication des faits de cycle de vie et de changement de profil.

---

## Hors périmètre

| Responsabilité | Domaine propriétaire |
|---|---|
| User, Membership, Role, Permission, Invitation, Session | `Identity` |
| Client, Contact, Opportunity | `CRM` |
| Quote, Invoice, Payment, taxes, numérotation, échéances | `Billing` |
| abonnement, plan et consommation commerciale d'Atlas | contexte commercial de plateforme à définir dans une décision dédiée ; voir la [stratégie tarifaire](../../product/pricing-strategy.md) |
| recommandations | `Advisor` |
| indicateurs et projections | `Analytics` / `Business Health` |

Workspace ne devient ni un conteneur générique de toutes les données, ni un
agrégat racine de la plateforme.

---

## Frontière des « paramètres de facturation »

Le libellé produit peut regrouper plusieurs écrans, mais la propriété métier
reste séparée :

```text
Workspace                              Billing
-----------------------------------    ----------------------------------
identité de l'émetteur                 règles de numérotation
adresse et identifiants déclarés       taxes applicables
devise par défaut                      conditions et échéances
coordonnée administrative              modèles et cycle des documents
```

Billing copie un snapshot versionné de l'identité nécessaire lorsqu'un document
devient juridiquement significatif. Une mise à jour dans Workspace ne réécrit
jamais ce snapshot.

---

## Limites produit 1.0

- un Workspace représente une seule activité professionnelle ;
- la première expérience crée un seul Workspace, mais cette limite d'interface
  n'est pas un invariant du domaine ;
- aucune hiérarchie de filiales, branches ou sous-workspaces ;
- aucune suppression physique par une commande métier ;
- aucune réouverture après `Closed` ;
- aucune configuration fiscale avancée dans Workspace.

Les extensions sont classées dans [`future.md`](future.md).
