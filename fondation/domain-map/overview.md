# Vue d'ensemble

Atlas est organisé autour de plusieurs domaines indépendants.

```text
           +------------------+     +------------------+
           |     Identity     |<--->|    Workspace     |
           +------------------+     +------------------+
                    |                       |
                    +-----------+-----------+
                                |
                                v
                 +-----------------------------+
                 |        CRM -> Billing       |
                 +-----------------------------+
                                |
                                v
                 +-----------------------------+
                 |          Analytics          |
                 +-----------------------------+
                                |
                                v
                 +-----------------------------+
                 | Business Health -> Advisor  |
                 +-----------------------------+
                                |
                                v
                 +-----------------------------+
                 |        Notifications        |
                 +-----------------------------+
```

Identity établit qui agit. Workspace établit dans quelle activité et sous quel
contexte l'action se déroule. Les domaines métier enrichissent ensuite
progressivement la compréhension de cette activité.

Les domaines ne se remplacent pas.

Ils s'alimentent mutuellement.
