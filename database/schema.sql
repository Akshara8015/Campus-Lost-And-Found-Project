-- Campus Lost-and-Found Portal: PostgreSQL schema
BEGIN;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'user'
        CHECK (role IN ('user', 'admin')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS reports (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    item_name VARCHAR(160) NOT NULL,
    report_type VARCHAR(10) NOT NULL CHECK (report_type IN ('lost', 'found')),
    category VARCHAR(80) NOT NULL,
    location VARCHAR(180) NOT NULL,
    item_date DATE NOT NULL,
    description TEXT NOT NULL,
    image_path TEXT,
    status VARCHAR(24) NOT NULL DEFAULT 'open'
        CHECK (status IN ('open', 'claim_requested', 'resolved', 'closed')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS claims (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    report_id BIGINT NOT NULL REFERENCES reports(id) ON DELETE RESTRICT,
    claimant_id BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    message TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending', 'approved', 'rejected', 'withdrawn')),
    reviewed_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMPTZ,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT claims_review_timestamp_consistency CHECK (
        (status IN ('approved', 'rejected') AND reviewed_at IS NOT NULL)
        OR status NOT IN ('approved', 'rejected')
    ),
    CONSTRAINT claims_unique_user_report UNIQUE (report_id, claimant_id)
);

CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_users_updated_at ON users;
CREATE TRIGGER trg_users_updated_at BEFORE UPDATE ON users
FOR EACH ROW EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS trg_reports_updated_at ON reports;
CREATE TRIGGER trg_reports_updated_at BEFORE UPDATE ON reports
FOR EACH ROW EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS trg_claims_updated_at ON claims;
CREATE TRIGGER trg_claims_updated_at BEFORE UPDATE ON claims
FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE OR REPLACE FUNCTION validate_claim_report()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
DECLARE
    owner_id BIGINT;
BEGIN
    SELECT user_id INTO owner_id
    FROM reports WHERE id = NEW.report_id;
    IF owner_id IS NULL THEN
        RAISE EXCEPTION 'Report % does not exist', NEW.report_id;
    END IF;
    IF owner_id = NEW.claimant_id THEN
        RAISE EXCEPTION 'Users cannot claim or respond to their own reports';
    END IF;
    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_validate_claim_report ON claims;
CREATE TRIGGER trg_validate_claim_report
BEFORE INSERT OR UPDATE OF report_id, claimant_id ON claims
FOR EACH ROW EXECUTE FUNCTION validate_claim_report();

COMMIT;
