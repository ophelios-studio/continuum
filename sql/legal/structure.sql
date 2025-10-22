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

CREATE TABLE IF NOT EXISTS legal.case_participant
(
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    case_id UUID NOT NULL REFERENCES legal.case(id) ON DELETE CASCADE,
    address TEXT NOT NULL, -- wallet
    role TEXT NOT NULL, -- OWNER / EDITOR / VIEWER
    org_id INTEGER NULL,
    invited_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    accepted_at TIMESTAMPTZ NULL,
    CONSTRAINT case_participant_role_ck CHECK (role IN ('OWNER','EDITOR','VIEWER')),
    CONSTRAINT case_participant_unique UNIQUE (case_id, address)
);

CREATE INDEX IF NOT EXISTS idx_case_participant_role ON legal.case_participant (role);

CREATE TABLE IF NOT EXISTS legal.case_label
(
    id BIGSERIAL PRIMARY KEY,
    case_id UUID NOT NULL REFERENCES legal.case(id) ON DELETE CASCADE,
    label TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_case_label_case ON legal.case_label (case_id);
CREATE INDEX IF NOT EXISTS idx_case_label_tag ON legal.case_label (label);

CREATE TABLE IF NOT EXISTS legal.case_event
(
    id BIGSERIAL PRIMARY KEY,
    case_id BIGINT NOT NULL REFERENCES legal.case(id) ON DELETE CASCADE,
    actor TEXT NOT NULL, -- wallet
    kind TEXT NOT NULL, -- e.g. CASE_CREATED / CASE_UPDATED / MEMBER_ADDED / STATUS_CHANGED / NOTE
    data JSONB NOT NULL DEFAULT '{}'::jsonb, -- additional payload
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_case_event_case ON legal.case_event (case_id);
CREATE INDEX IF NOT EXISTS idx_case_event_kind ON legal.case_event (kind);
CREATE INDEX IF NOT EXISTS idx_case_event_actor ON legal.case_event (actor);