# Ownership

## Identity

Possède :

- User
- Membership
- Role
- Permission
- Invitation
- Session

---

## Workspace

Possède :

- Workspace
- WorkspaceProfile
- BillingIdentity
- WorkspacePreferences
- WorkspaceStatus

Ne possède jamais les memberships, clients ou documents financiers rattachés au
Workspace.

---

## CRM

Possède :

- Client
- Contact
- Opportunity
- Activity

`Pipeline` est une projection CRM, pas une entité possédée.

---

## Billing

Possède :

- Quote
- Invoice
- Payment, comme entité de l'agrégat Invoice
- CreditNote
- DocumentNumberSequence

Billing copie les données courantes de CRM et Workspace dans des snapshots,
mais ne devient jamais propriétaire du Client, de l'Opportunity ou de l'identité
courante du Workspace.

---

## Analytics

Possède :

- Metrics
- Aggregates

---

## Business Health

Possède :

- Score
- Factors

---

## Advisor

Possède :

- Recommendation

---

## Notifications

Possède :

- Notification
