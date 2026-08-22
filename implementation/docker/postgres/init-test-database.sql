-- Base séparée pour les tests Laravel utilisant RefreshDatabase.
-- Elle empêche la suppression des schémas de la base de développement `atlas`.

CREATE DATABASE atlas_test OWNER atlas;
