---
id: LANG-001
title: Glossary
status: Stable
owner: Product
version: 1.2
last_updated: 2026-08-05
---

# Glossaire

Le glossaire recense tous les concepts métier officiels utilisés par Atlas.

Chaque concept possède :

- une définition unique ;
- un nom officiel ;
- un domaine propriétaire ;
- les synonymes interdits.

---

# Workspace

**Définition**

Entité représentant une activité professionnelle utilisant Atlas.

**Domaine**

Workspace

**Synonymes interdits**

- Organisation
- Company
- Tenant
- Account

---

# WorkspaceProfile

Profil commercial courant d'un `Workspace`, destiné à l'affichage et à la
réutilisation par les domaines autorisés.

**Domaine**

Workspace

---

# BillingIdentity

Coordonnées administratives déclarées d'un `Workspace`, servant de source aux
snapshots créés par Billing sans contenir les règles financières.

**Domaine**

Workspace

---

# WorkspacePreferences

Locale, fuseau horaire, devise et pays utilisés comme valeurs par défaut sans
effet rétroactif sur les faits existants.

**Domaine**

Workspace

---

# User

Identité reconnue par Atlas, indépendante des espaces de travail auxquels elle
peut appartenir.

**Domaine**

Identity

---

# Membership

Relation durable entre un `User` et un `Workspace`, portant son état
d'appartenance et son `Role` courant.

**Domaine**

Identity

Le mot « membre » peut être utilisé dans l'interface pour désigner le `User`
associé. Il ne remplace pas `Membership` dans le modèle métier.

---

# Role

Ensemble de responsabilités et de permissions attribuable à un `Membership`
dans un seul `Workspace`.

**Domaine**

Identity

---

# Permission

Capacité élémentaire reconnue par Atlas et accordée indirectement à un
`Membership` par l'intermédiaire de son `Role`.

**Domaine**

Identity

---

# Invitation

Autorisation temporaire permettant à un destinataire identifié de rejoindre un
`Workspace` avec un `Role` prévu.

**Domaine**

Identity

---

# Session

Continuité d'authentification bornée, rattachée à un `User` et révocable
indépendamment de ses autorisations.

**Domaine**

Identity

---

# Client

Personne ou organisation recevant les prestations de l'utilisateur.

---

# Contact

Personne appartenant à un Client.

---

# Opportunity

Possibilité réelle de conclure une vente.

Ne devient jamais un "Lead".

---

# Quote

Proposition commerciale envoyée à un client.

---

# Invoice

Document comptable représentant une créance.

---

# Payment

Paiement associé à une facture.

---

# Recommendation

Action proposée par Atlas après analyse.

---

# Signal

Événement ou situation détectée automatiquement.

---

# Business Health

Évaluation globale de la santé récente de l'activité.
