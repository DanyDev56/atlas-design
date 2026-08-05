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
- Payment

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
