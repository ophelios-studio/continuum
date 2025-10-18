CREATE TABLE IF NOT EXISTS account.actor
(
    address TEXT PRIMARY KEY, -- 0x... in lowercase
    level TEXT NOT NULL DEFAULT 'NONE',  -- NONE / DECLARED / VERIFIED_L1 / VERIFIED_L2 / REVOKED
    firstname TEXT NOT NULL, -- e.g. "Bruce"
    lastname TEXT NOT NULL, -- e.g. "Wayne"
    email TEXT NOT NULL,
    primary_role TEXT NOT NULL, -- e.g. LAWYER / OFFICER / LAB_TECH / CLERK / OTHER
    jurisdiction TEXT NOT NULL, -- e.g. "CA-QC"
    profile_hash TEXT NOT NULL, -- keccak256 of canonical profile JSON
    organization_id INTEGER NULL DEFAULT NULL,
    verification_token TEXT NULL DEFAULT NULL, -- Email verification token, NULL means verified
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS actor_level_idx ON account.actor(level);
CREATE INDEX IF NOT EXISTS actor_org_idx ON account.actor(organization_id);

CREATE TABLE IF NOT EXISTS account.actor_history
(
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY, -- Prevent id override
    address TEXT NOT NULL,
    profile_json TEXT NOT NULL, -- the exact canonical JSON you hashed
    profile_hash TEXT NOT NULL,
    saved_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS sph_addr_idx ON account.actor_history(address);







