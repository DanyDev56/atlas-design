-- Schémas PostgreSQL par module (ADR-001 / ADR-002).
-- Chaque module possède son namespace ; aucune FK transversale.

CREATE SCHEMA IF NOT EXISTS identity;
CREATE SCHEMA IF NOT EXISTS workspace;
CREATE SCHEMA IF NOT EXISTS crm;
CREATE SCHEMA IF NOT EXISTS billing;
CREATE SCHEMA IF NOT EXISTS analytics;
CREATE SCHEMA IF NOT EXISTS "business_health";
CREATE SCHEMA IF NOT EXISTS advisor;
CREATE SCHEMA IF NOT EXISTS notifications;
CREATE SCHEMA IF NOT EXISTS subscriptions;
CREATE SCHEMA IF NOT EXISTS platform;

-- Rôles bornés par module (dev local — identité commune pour simplifier le bootstrap).
-- En validation/production : une identité distincte par module (condition ADR-002 #6).

GRANT USAGE ON SCHEMA identity TO atlas;
GRANT USAGE ON SCHEMA workspace TO atlas;
GRANT USAGE ON SCHEMA crm TO atlas;
GRANT USAGE ON SCHEMA billing TO atlas;
GRANT USAGE ON SCHEMA analytics TO atlas;
GRANT USAGE ON SCHEMA "business_health" TO atlas;
GRANT USAGE ON SCHEMA advisor TO atlas;
GRANT USAGE ON SCHEMA notifications TO atlas;
GRANT USAGE ON SCHEMA subscriptions TO atlas;
GRANT USAGE ON SCHEMA platform TO atlas;

GRANT CREATE ON SCHEMA identity TO atlas;
GRANT CREATE ON SCHEMA workspace TO atlas;
GRANT CREATE ON SCHEMA crm TO atlas;
GRANT CREATE ON SCHEMA billing TO atlas;
GRANT CREATE ON SCHEMA analytics TO atlas;
GRANT CREATE ON SCHEMA "business_health" TO atlas;
GRANT CREATE ON SCHEMA advisor TO atlas;
GRANT CREATE ON SCHEMA notifications TO atlas;
GRANT CREATE ON SCHEMA subscriptions TO atlas;
GRANT CREATE ON SCHEMA platform TO atlas;
