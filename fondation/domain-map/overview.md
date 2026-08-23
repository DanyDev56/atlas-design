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

       +-----------------------------------------------+
       | Subscriptions : catalogue, Trial, Entitlement |
       +-----------------------------------------------+
```

Identity établit qui agit. Workspace établit dans quelle activité et sous quel
contexte l'action se déroule. Les domaines métier enrichissent ensuite
progressivement la compréhension de cette activité.

Notifications remet la priorité déjà décidée par Advisor à l'audience autorisée
par Identity, selon l'état et la locale fournis par Workspace. Il ne complète
pas la chaîne de décision par une nouvelle décision métier.

Les domaines ne se remplacent pas.

Subscriptions reste transversal à la chaîne métier : il détermine l'accès
commercial sans posséder ni redéfinir les décisions des huit contextes du MVP.

Ils s'alimentent mutuellement.
