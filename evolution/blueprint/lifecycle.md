# Cycle commercial vers décision

```text
Opportunity Open
   │
   ▼
Opportunity Qualified
   │
   ▼
Quote Accepted
   ├──► Opportunity Won
   │
   ▼
Invoice
   │
Payment
   │
Analytics
   │
Business Health
   │
Recommendation
   │
Notification
```

La sémantique et les modes de dégradation de la boucle de décision sont définis
dans [`decision-loop.md`](decision-loop.md). `Automation` reste hors MVP et ne
doit pas être insérée implicitement dans cette chaîne.
