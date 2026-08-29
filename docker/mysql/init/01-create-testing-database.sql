-- Pest runs with RefreshDatabase, which truncates every table it touches.
-- Pointing the suite at the development database would wipe seeded demo data
-- on every test run, so tests get their own schema.
CREATE DATABASE IF NOT EXISTS hookscope_testing;
