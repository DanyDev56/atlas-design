-- Rôles bornés par module (spike ADR-002 condition #6).
-- atlas conserve l'accès complet pour le dev ; les rôles module prouvent l'isolation.

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'atlas_workspace') THEN
        CREATE ROLE atlas_workspace LOGIN PASSWORD 'atlas_workspace_dev';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'atlas_platform') THEN
        CREATE ROLE atlas_platform LOGIN PASSWORD 'atlas_platform_dev';
    END IF;
END
$$;

REVOKE ALL ON SCHEMA platform FROM atlas_workspace;
REVOKE ALL ON SCHEMA workspace FROM atlas_platform;

GRANT USAGE ON SCHEMA workspace TO atlas_workspace;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA workspace TO atlas_workspace;
ALTER DEFAULT PRIVILEGES IN SCHEMA workspace GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO atlas_workspace;

GRANT USAGE ON SCHEMA platform TO atlas_platform;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA platform TO atlas_platform;
ALTER DEFAULT PRIVILEGES IN SCHEMA platform GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO atlas_platform;
