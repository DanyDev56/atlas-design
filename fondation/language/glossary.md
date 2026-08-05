---
id: LANG-001
title: Glossary
status: Stable
owner: Product
version: 1.5
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

Personne ou structure identifiée comme contrepartie d'une relation commerciale
potentielle ou établie.

**Domaine**

CRM

---

# ClientProfile

Profil commercial courant d'un Client.

**Domaine**

CRM

---

# ClientBillingProfile

Données administratives courantes d'un Client, fournies à Billing comme source
d'un snapshot sans contenir les règles du document financier.

**Domaine**

CRM

---

# Contact

Personne rattachée à un Client dans le cadre de la relation commerciale.

**Domaine**

CRM

---

# Opportunity

Possibilité réelle de conclure une vente.

Ne devient jamais un "Lead".

**Domaine**

CRM

---

# Activity

Interaction commerciale passée enregistrée dans CRM, par exemple une note, un
appel, une réunion ou un e-mail.

**Domaine**

CRM

---

# Pipeline

Projection des Opportunity selon leur statut courant.

**Domaine**

CRM

---

# Quote

Proposition commerciale versionnée adressée à un Client, dont le contenu est
figé avant envoi et dont la réponse est terminale.

**Domaine**

Billing

---

# Invoice

Document financier émis représentant une créance immuable, dont le règlement,
le retard et la livraison sont suivis séparément.

**Domaine**

Billing

---

# Payment

Encaissement enregistré dans une Invoice et appliqué, en tout ou partie, à son
solde. Un Payment erroné est inversé, jamais édité.

**Domaine**

Billing

---

# CreditNote

Document financier correctif émis contre une seule Invoice et applicable à son
solde sans le rendre négatif.

**Domaine**

Billing

---

# DocumentLine

Description canonique d'une prestation ou correction avec quantité, prix,
remise et règle de taxe.

**Domaine**

Billing

---

# DocumentNumber

Numéro lisible et non réutilisable alloué à un document finalisé ou émis.

**Domaine**

Billing

---

# ClientSnapshot

Copie historique des données Client nécessaires à un document Billing.

**Domaine**

Billing

---

# IssuerSnapshot

Copie historique de l'identité Workspace émettrice nécessaire à un document.

**Domaine**

Billing

---

# DepositInvoice

Invoice d'acompte issue d'une Quote acceptée. Billing 1.0 en autorise au plus
une par Quote.

**Domaine**

Billing

---

# FinalInvoice

Invoice facturant le reliquat d'une Quote acceptée après l'éventuel acompte.

**Domaine**

Billing

---

# DueDate

Date après laquelle une Invoice émise avec un solde positif devient en retard.

**Domaine**

Billing

---

# OutstandingBalance

Montant restant dû après application des Payments actifs et CreditNotes.

**Domaine**

Billing

---

# AnalyticsFact

Observation normalisée, minimale et immuable d'une révision CRM ou Billing
utilisée pour construire une mesure.

**Domaine**

Analytics

---

# MetricDefinition

Contrat versionné décrivant la formule, l'unité, les sources, périodes,
dimensions et règles de complétude d'une métrique.

**Domaine**

Analytics

---

# MetricSeries

Suite d'observations partageant une définition, une génération, une devise et
un ensemble de dimensions cohérents.

**Domaine**

Analytics

---

# MetricObservation

Valeur calculée pour une période ou un instant, accompagnée de sa fraîcheur, sa
complétude, son échantillon et son explication.

**Domaine**

Analytics

---

# ProjectionGeneration

Ensemble isolé de projections Analytics construit avec les mêmes définitions,
calendrier et checkpoints sources.

**Domaine**

Analytics

---

# AnalyticsSnapshot

Ensemble immuable de MetricObservations cohérentes au même instant, publié à un
consommateur comme Business Health.

**Domaine**

Analytics

---

# DataFreshness

État mesurant le retard d'une projection au moyen de ses instants et watermarks
sources.

**Domaine**

Analytics

---

# DataCompleteness

État décrivant la couverture des données requises par une MetricDefinition.

**Domaine**

Analytics

---

# ObservationPeriod

Période ou instant non ambigu auquel une MetricObservation s'applique.

**Domaine**

Analytics

---

# ReportingCalendar

Fuseau et règles civiles versionnés utilisés pour construire les périodes d'un
Workspace.

**Domaine**

Analytics

---

# SourceWatermark

Dernière position d'une source durablement intégrée dans une projection.

**Domaine**

Analytics

---

# Recommendation

Action proposée par Atlas après analyse.

---

# Signal

Événement ou situation détectée automatiquement.

---

# Business Health

Évaluation globale de la santé récente de l'activité.
