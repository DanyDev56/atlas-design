---
id: IDN-CMD-EXPIRE-SESSION-ELEVATION
title: ExpireSessionElevation
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

aggregate: Session

invariants:
  - IDN-INV-010
  - IDN-INV-012
  - IDN-INV-022

references:
  - README.md
  - ../entities.md
  - ../value-objects.md
  - ../invariants.md
  - ../events.md
  - ElevateSession.md
  - TerminateSessionElevation.md
  - ExpireSession.md
---

# ExpireSessionElevation

## Objectif

`ExpireSessionElevation` matérialise la fin temporelle d'une élévation devenue
inefficace.

```text
Active Elevation -> Expired Elevation
```

La validité temporelle est vérifiée à chaque autorisation : une élévation
échue est déjà inefficace même avant l'exécution de cette commande.

---

## Agrégat

`Session`.

---

## Acteur

Un ordonnanceur ou `SystemActor` temporel de confiance. Aucun rôle de workspace
ne peut déclencher une expiration anticipée.

---

## Données d'entrée

- `SessionId` ;
- `ElevationVersion` ;
- `ExpiredAt` ;
- `ExpireSessionElevationRequestId` ;
- `ExpectedSessionVersion` ;
- `CorrelationId`.

---

## Préconditions

- la `Session` existe ;
- une élévation courante existe ;
- `ExpiredAt >= Elevation.ExpiresAt` ;
- la version d'élévation correspond ;
- la session n'a pas déjà matérialisé cette fin.

---

## Traitement métier

1. Recharger la session et l'élévation sous concurrence optimiste.
2. Vérifier l'échéance à partir d'une horloge de confiance.
3. Définir `SessionElevationStatus = Expired` et la rendre inutilisable.
4. Incrémenter `SessionSecurityVersion` et la version d'agrégat.
5. Enregistrer `SessionElevationExpired` dans le même commit.

La commande ne modifie ni les permissions ni le statut principal de la session.

---

## Événement produit

`SessionElevationExpired`.

L'événement ne contient aucune preuve, credential ou donnée d'authentification
brute.

---

## Erreurs métier

- `SessionNotFound` ;
- `SessionElevationNotFound` ;
- `SessionElevationNotYetExpired` ;
- `SessionElevationVersionConflict` ;
- `SessionVersionConflict` ;
- `IdempotencyConflict`.

---

## Idempotence

La clé est `ExpireSessionElevationRequestId`. Une élévation déjà expirée retourne
son résultat terminal sans nouvel événement.
