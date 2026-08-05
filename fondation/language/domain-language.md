---
id: LANG-006
title: Domain Language
status: Stable
owner: Product
version: 1.2
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

Contact

Opportunity

Activity

Pipeline

---

## Billing

Quote

Invoice

Payment

Credit Note

Deposit

Due Date

---

## Business Health

Business Health

Factor

Trend

Risk

Projection

---

## Advisor

Recommendation

Signal

Confidence

Priority

Action

---

## Notifications

Notification

Channel

Delivery

Read Status
