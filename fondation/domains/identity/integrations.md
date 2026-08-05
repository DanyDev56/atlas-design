---
id: IDN-INTEGRATIONS
title: Identity Integrations
status: In Review
owner: Product
version: 1.1.0
last_updated: 2026-08-05

references:
  - events.md
  - workflows.md
  - api.md
  - future.md
---

# Intégrations

Identity 1.0 s'intègre à des composants externes par des ports remplaçables.

---

## Communication

Le port de communication transmet :

- vérifications d'adresse e-mail ;
- invitations ;
- récupérations de compte ;
- notifications de sécurité.

Identity produit une demande via l'outbox. L'adaptateur confirme la prise en
charge sans confondre `Sent` et `Delivered`.

Lorsqu'un message exige un secret à usage unique, l'outbox contient seulement un
`DeliverySecretHandle` opaque et borné. L'adaptateur autorisé échange ce handle
par un canal confidentiel au moment de construire le message. Le token brut
n'est ni un Domain Event ni une donnée durable du message.

---

## Stockage des credentials

Le port de credentials est responsable :

- du hachage et de la comparaison sécurisée ;
- de la rotation ;
- des familles de refresh credentials ;
- de la consommation atomique des preuves à usage unique ;
- de la protection des secrets au repos.

Le modèle métier ne reçoit que des preuves ou références validées.

---

## Workspace

Identity consulte uniquement le contrat public d'accès et de gouvernance défini
dans [`api.md`](api.md), désormais confirmé par le contrat public de Workspace.

Il ne lit jamais le stockage de Workspace et ne modifie jamais son cycle de vie.

Identity fournit en retour `getWorkspaceOwnerReadiness` aux workflows de
bootstrap et de restauration. Cette preuve minimale confirme uniquement
l'existence d'un owner actif ; elle n'expose pas le modèle interne des rôles ou
memberships.

---

## Notifications

Identity fournit `resolveWorkspaceNotificationAudience` et
`revalidateNotificationRecipient` aux seuls workloads autorisés par
`identity.notification-audience.read`.

Le premier contrat résout les Users dont User, Membership et Role sont actifs
et dont toutes les permissions demandées sont effectives. Le second invalide
les réductions de privilèges et l'endpoint immédiatement avant un effet externe.
Les réponses peuvent contenir une `DeliveryEndpointReference` opaque,
jamais une adresse brute.

Seul l'adaptateur de livraison Email autorisé peut échanger temporairement cette
référence contre l'endpoint courant par un port confidentiel Identity. L'adresse
n'est jamais retournée au domaine Notifications, mise en cache durablement ou
écrite dans son outbox et ses logs.

Notifications possède les préférences, canaux, fréquences et messages produit.
Identity conserve la propriété et la vérification de l'adresse ainsi que ses
communications d'invitation, récupération, vérification et sécurité. Aucun
secret ou token Identity ne traverse le bounded context Notifications.

---

## Audit et sécurité

Les événements sensibles alimentent un journal d'audit append-only avec un accès
restreint.

Un moteur de risque peut demander une élévation, un verrouillage temporaire ou une
révocation. Il ne modifie jamais directement un agrégat.

---

## Fournisseurs d'identité

Identity 1.0 peut utiliser un fournisseur pour valider une authentification, mais
le `UserId`, les memberships, rôles et permissions Atlas restent des concepts
internes stables.

La fédération d'entreprise et le provisioning automatique sont reportés dans
[`future.md`](future.md).

---

## Garanties

- outbox transactionnelle pour les effets externes ;
- clé d'idempotence pour chaque demande ;
- retry avec backoff borné ;
- dead-letter queue observable ;
- corrélation de bout en bout ;
- aucun secret dans les messages persistants ;
- compensation ou reprise explicite après un échec partiel.
