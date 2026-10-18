-- Helpful indexes for common portal queries.
-- Run after schema.sql. These statements are safe to run repeatedly.

CREATE INDEX IF NOT EXISTS idx_reports_type_status
    ON reports (report_type, status);

CREATE INDEX IF NOT EXISTS idx_reports_category
    ON reports (category);

CREATE INDEX IF NOT EXISTS idx_reports_location
    ON reports (location);

CREATE INDEX IF NOT EXISTS idx_reports_item_date
    ON reports (item_date DESC);

CREATE INDEX IF NOT EXISTS idx_reports_user_created
    ON reports (user_id, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_claims_claimant_created
    ON claims (claimant_id, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_claims_status_created
    ON claims (status, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_claims_report_status
    ON claims (report_id, status);
