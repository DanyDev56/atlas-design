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

Notifications remet la priorité déjà décidée par Advisor à l'audience autorisée
par Identity, selon l'état et la locale fournis par Workspace. Il ne complète
pas la chaîne de décision par une nouvelle décision métier.

Les domaines ne se remplacent pas.

Ils s'alimentent mutuellement.
