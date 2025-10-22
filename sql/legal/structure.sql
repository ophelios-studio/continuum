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

CREATE TABLE IF NOT EXISTS legal.case_event
(
    id BIGSERIAL PRIMARY KEY,
    case_id UUID NOT NULL REFERENCES legal.case(id) ON DELETE CASCADE,
    actor TEXT NOT NULL, -- wallet
    kind TEXT NOT NULL, -- e.g. CASE_CREATED / CASE_UPDATED / MEMBER_ADDED / STATUS_CHANGED / NOTE
    data JSONB NOT NULL DEFAULT '{}'::jsonb, -- additional payload
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_case_event_case ON legal.case_event (case_id);
CREATE INDEX IF NOT EXISTS idx_case_event_kind ON legal.case_event (kind);
CREATE INDEX IF NOT EXISTS idx_case_event_actor ON legal.case_event (actor);

CREATE TABLE IF NOT EXISTS legal.evidence
(
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    case_id UUID NOT NULL REFERENCES legal.case(id) ON DELETE CASCADE,
    title TEXT NOT NULL,
    kind TEXT NOT NULL, -- DIGITAL_DUMP / FILESET / PHOTO / VIDEO / AUDIO / PHYSICAL_ITEM / OTHER
    description TEXT,
    jurisdiction TEXT NOT NULL,
    external_uri TEXT,
    physical_tag TEXT,
    serial TEXT,
    content_hash TEXT, -- 0x... bytes32 hex
    media_uri TEXT, -- ipfs://…
    evidence_id_hex TEXT UNIQUE, -- 0x... bytes32 hex
    anchor_tx TEXT, -- 0x... tx hash
    anchored_at TIMESTAMPTZ,
    submitter_address TEXT NOT NULL,
    current_custodian TEXT NOT NULL,
    pending_custodian TEXT,
    status TEXT NOT NULL DEFAULT 'DRAFT',  -- DRAFT / READY / ANCHORED
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS evidence_case_idx ON legal.evidence (case_id);
CREATE INDEX IF NOT EXISTS evidence_kind_idx ON legal.evidence (kind);
CREATE INDEX IF NOT EXISTS evidence_status_idx ON legal.evidence (status);
CREATE INDEX IF NOT EXISTS evidence_submitter_idx ON legal.evidence (LOWER(submitter_address));
CREATE INDEX IF NOT EXISTS evidence_current_custodian_idx ON legal.evidence (LOWER(current_custodian));
CREATE INDEX IF NOT EXISTS evidence_created_desc_idx ON legal.evidence (created_at DESC);

CREATE TABLE IF NOT EXISTS legal.evidence_revision
(
    id BIGSERIAL PRIMARY KEY,
    evidence_id UUID NOT NULL REFERENCES legal.evidence(id) ON DELETE CASCADE,
    rev_no INTEGER NOT NULL, -- 1, 2, 3...
    content_hash TEXT NOT NULL, -- bytes32 0x...
    media_uri TEXT, -- ipfs://manifest.json (encrypted)
    anchor_tx TEXT,
    anchored_at TIMESTAMPTZ,
    created_by TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (evidence_id, rev_no)
);

CREATE TABLE IF NOT EXISTS legal.evidence_file
(
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    evidence_id UUID NOT NULL REFERENCES legal.evidence(id) ON DELETE CASCADE,
    revision_id BIGINT NOT NULL REFERENCES legal.evidence_revision(id) ON DELETE CASCADE,
    filename TEXT NOT NULL,
    mime_type TEXT,
    byte_size BIGINT,
    sha256_hex TEXT,
    keccak256_hex TEXT,
    storage_provider TEXT, -- 'local' | 'lighthouse' | ...
    storage_cid TEXT,
    storage_uri TEXT, -- ipfs://…
    encrypted BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS evidence_file_eid_idx ON legal.evidence_file (evidence_id);
CREATE INDEX IF NOT EXISTS evidence_file_created_idx ON legal.evidence_file (created_at DESC);

CREATE TABLE IF NOT EXISTS legal.evidence_event
(
    id BIGSERIAL PRIMARY KEY,
    evidence_id UUID NOT NULL REFERENCES legal.evidence(id) ON DELETE CASCADE,
    actor TEXT NOT NULL,              -- wallet (lowercase) who did action in app
    kind TEXT NOT NULL,              -- CREATED | UPDATED | FILE_ATTACHED | FILE_REMOVED | ANCHORED | NOTE | ...
    data JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS evidence_event_eid_created_idx ON legal.evidence_event (evidence_id, created_at DESC);