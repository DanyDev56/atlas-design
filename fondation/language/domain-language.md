---
id: LANG-006
title: Domain Language
status: Stable
owner: Product
version: 1.7
last_updated: 2026-08-05
---

# Vocabulaire par domaine

## Identity

User

Membership

Role

Permission

Invitation

Session

`Membership` est le concept métier officiel représentant l'appartenance d'un
`User` à un `Workspace`.

Le mot « membre » peut être utilisé comme libellé naturel dans l'interface pour
désigner un utilisateur disposant d'un `Membership`. Il ne constitue ni une
entité distincte, ni un synonyme utilisable dans le code, les API ou les
événements.

---

## Workspace

Workspace

WorkspaceProfile

BillingIdentity

WorkspacePreferences

WorkspaceAccessContext

`Workspace` représente une activité professionnelle utilisant Atlas. Il ne doit
jamais être remplacé dans le modèle par Company, Organisation, Tenant ou Account.

---

## CRM

Client

ClientProfile

ClientBillingProfile

Contact

Opportunity

Activity

Pipeline

`Client` désigne la contrepartie stable d'une relation commerciale potentielle
ou établie. `Opportunity` porte la vente potentielle ; Atlas ne crée pas
d'entité Lead ou Prospect séparée.

`Activity` désigne une interaction commerciale passée. `Pipeline` est une
projection des Opportunity, pas une entité métier indépendante.

---

## Billing

Quote

Invoice

Payment

CreditNote

DocumentLine

DocumentTotals

DocumentNumber

DocumentNumberSequence

ClientSnapshot

IssuerSnapshot

OpportunitySnapshot

DepositInvoice

FinalInvoice

DueDate

OutstandingBalance

`Quote`, `Invoice` et `CreditNote` sont trois documents distincts. Un `Payment`
est enregistré dans une Invoice. `DepositInvoice` et `FinalInvoice` sont des
kinds d'Invoice, pas de nouveaux agrégats.

Les libellés naturels « devis », « facture », « avoir », « acompte », « date
d'échéance » et « solde restant dû » sont autorisés dans l'interface française,
mais le code et les contrats emploient les noms canoniques ci-dessus.

---

## Analytics

AnalyticsFact

MetricDefinition

MetricSeries

MetricObservation

ProjectionGeneration

AnalyticsSnapshot

DataFreshness

DataCompleteness

ObservationPeriod

ReportingCalendar

SourceWatermark

Analytics transforme des faits versionnés en mesures. Il ne possède ni KPI
libre, ni BusinessHealth, ni Recommendation. Une valeur sans définition,
période, fraîcheur et complétude n'est pas une MetricObservation valide.

---

## Business Health

BusinessHealthAssessment

HealthPolicy

ActiveHealthPolicyVersion

OverallScore

HealthBand

HealthFactor

FactorScore

AssessmentReliability

HealthTrend

HealthRisk

RiskSeverity

PrimaryAttention

AssessmentEvidence

CurrentBusinessHealth

Business Health interprète un AnalyticsSnapshot selon une HealthPolicyVersion.
PrimaryAttention désigne le facteur au déficit dominant, jamais une Action ou
une RecommendationPriority. OverallScore est le seul nom du score global ;
HealthScore est ambigu et interdit.

---

## Advisor

Recommendation

RecommendationPolicy

ActiveRecommendationPolicyVersion

RecommendationEvaluation

RecommendationStatus

RecommendationPriority

RecommendationRankScore

PrimaryRecommendation

RecommendationAction

ExpectedImpact

RecommendationConfidence

EstimatedEffort

RecommendationEvidenceRevision

AdvisorOverview

Advisor transforme une BusinessHealthAssessment en zéro à trois Recommendation.
PrimaryRecommendation est l'action la mieux classée ; elle ne doit pas être
confondue avec PrimaryAttention. Completed confirme l'action humaine sans
signifier Executed ni prouver un outcome.

---

## Notifications

Notification

Channel

Delivery

Read Status
