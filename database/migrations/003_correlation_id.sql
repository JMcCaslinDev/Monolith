-- Tie events from the same HTTP request (page view + permissions, etc.)
ALTER TABLE events ADD COLUMN IF NOT EXISTS correlation_id VARCHAR(32) NULL AFTER id;
CREATE INDEX IF NOT EXISTS events_correlation_idx ON events (correlation_id);
