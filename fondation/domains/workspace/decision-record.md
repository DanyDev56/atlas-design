---
id: WSP-DECISIONS
title: Workspace Decision Record
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - scope.md
  - model.md
  - invariants.md
  - integrations.md
  - future.md
---

# Registre de décisions

## WSP-ADR-001 — Workspace est un bounded context distinct

**Statut :** Accepted

Workspace possède l'identité et le cycle de vie d'une activité. Il ne fait pas
partie d'Identity et n'est pas un simple champ partagé par tous les domaines.

Conséquence : les autres contextes conservent un `WorkspaceId` et utilisent un
contrat public.

---

## WSP-ADR-002 — Un Workspace représente une activité professionnelle

**Statut :** Accepted

Le modèle ne présume ni une société commerciale, ni une forme juridique, ni une
équipe. Cette neutralité couvre l'indépendant comme la petite structure de
services sans transformer Workspace en structure organisationnelle complexe.

---

## WSP-ADR-003 — Un seul agrégat en 1.0

**Statut :** Accepted

Le profil, l'identité de facturation, les préférences et le cycle de vie sont
contenus dans l'agrégat `Workspace`. Leur taille et leur fréquence de mutation ne
justifient pas une cohérence distribuée.

---

## WSP-ADR-004 — Provisioning est distinct d'Active

**Statut :** Accepted

`CreateWorkspace` crée un espace en `Provisioning`. Seul le workflow de
bootstrap peut l'activer après confirmation du socle Identity.

Conséquence : un échec partiel ne rend jamais visible un Workspace sans owner.

---

## WSP-ADR-005 — Le contrat d'accès possède trois états publics

**Statut :** Accepted

Identity consomme uniquement `Active`, `Restricted` ou `Closed`.
`Provisioning` est projeté en `Restricted`.

Ce contrat confirme le contrat provisoire fermé lors de la consolidation
d'Identity 1.0.

---

## WSP-ADR-006 — Identity possède memberships, rôles et autorisation

**Statut :** Accepted

Workspace ne stocke ni owner, ni membre, ni rôle. Il consomme une preuve de
readiness et délègue toute décision d'autorisation contextualisée à Identity.

`RequiresActiveOwner = true` reste une exigence publique de Workspace 1.0 ; la
réalisation de cette exigence appartient à Identity.

---

## WSP-ADR-007 — Séparer identité de facturation et règles Billing

**Statut :** Accepted

Workspace possède les coordonnées déclarées de l'émetteur. Billing possède les
taxes, numéros, échéances, documents et validations nécessaires à une opération
financière.

Le regroupement éventuel dans une même interface n'altère pas cette frontière.

---

## WSP-ADR-008 — Les consommateurs figent leurs snapshots

**Statut :** Accepted

Workspace expose la valeur courante et sa version. Le consommateur qui doit
préserver l'historique copie un snapshot dans son propre agrégat.

Un événement de mise à jour annonce une nouvelle valeur disponible ; il ne
demande jamais de réécrire l'historique.

---

## WSP-ADR-009 — Closed est terminal et logique

**Statut :** Accepted

La fermeture désactive l'usage ordinaire sans suppression physique. Une
réouverture ambiguë pourrait invalider des décisions de sécurité et de
conformité ; elle est exclue de la version 1.0.

---

## WSP-ADR-010 — Le bootstrap est une saga idempotente

**Statut :** Accepted

Le workflow coordonne les commandes publiques Workspace et Identity. Il utilise
des clés déterministes, reprend chaque étape et ferme un provisioning abandonné
au lieu de supprimer ses traces.

---

## WSP-ADR-011 — Le mono-workspace MVP n'est pas un invariant métier

**Statut :** Accepted

L'expérience MVP crée et présente un seul Workspace. Le modèle n'interdit pas à
un `User` d'en référencer plusieurs, car Identity supporte déjà cette relation et
la vision prévoit les petites équipes.

Une limite d'offre ou d'interface reste une politique externe.

---

## WSP-ADR-012 — Les préférences sont des défauts non rétroactifs

**Statut :** Accepted

Locale, fuseau, devise et pays facilitent la saisie et la présentation. Un
domaine consommateur applique ses propres règles, conserve la valeur effective
et ne modifie pas rétroactivement ses faits lors d'un changement de préférence.
