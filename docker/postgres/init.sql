-- PostgreSQL initialization script for BLBGenSix AI
-- Creates database extensions and base schema

CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- Create default admin user UUID extension helper
CREATE OR REPLACE FUNCTION generate_ulid()
RETURNS uuid AS $$
    SELECT encode(
        substring(
            encode(sha256(random()::text::bytea || clock_timestamp()::text::bytea), 'hex')
        from 1 for 32
    ), 'hex')::uuid;
$$ LANGUAGE sql;
