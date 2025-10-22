CREATE TABLE IF NOT EXISTS legal.case
(
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ref_code TEXT UNIQUE NOT NULL, -- e.g. "C-2025-000012"
    title TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    jurisdiction TEXT NOT NULL, -- e.g. "CA-QC"
    status TEXT NOT NULL DEFAULT 'OPEN',  -- OPEN / CLOSED / ARCHIVED
    visibility TEXT NOT NULL DEFAULT 'PRIVATE', -- PRIVATE / ORG / PUBLIC
    sensitivity TEXT NOT NULL DEFAULT 'NORMAL',  -- NORMAL / SEALED
    created_by TEXT NOT NULL, -- wallet
    organization_id  INTEGER NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT cases_status_ck CHECK (status IN ('OPEN','CLOSED','ARCHIVED')),
    CONSTRAINT cases_visibility_ck  CHECK (visibility IN ('PRIVATE','ORG','PUBLIC')),
    CONSTRAINT cases_sensitivity_ck CHECK (sensitivity IN ('NORMAL','SEALED'))
);

CREATE INDEX IF NOT EXISTS idx_cases_created_by ON legal.case (created_by);
CREATE INDEX IF NOT EXISTS idx_cases_status ON legal.case (status);
CREATE INDEX IF NOT EXISTS idx_cases_visibility ON legal.case (visibility);
CREATE INDEX IF NOT EXISTS idx_cases_org ON legal.case (organization_id);
