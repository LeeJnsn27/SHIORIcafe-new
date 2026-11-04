-- SHIORIcafe PostgreSQL Migration
-- Adds reservation start and end times.
-- Connect to the shiori_cafe database before running.

-- =========================================
-- 1. ADD NEW TIME COLUMNS
-- =========================================

ALTER TABLE reservations
    ADD COLUMN IF NOT EXISTS reservation_start_time TIME,
    ADD COLUMN IF NOT EXISTS reservation_end_time TIME;

-- =========================================
-- 2. COPY EXISTING RESERVATION TIMES
-- =========================================
-- Run this only if the old reservation_time column exists.

UPDATE reservations
SET
    reservation_start_time = reservation_time,
    reservation_end_time = reservation_time + INTERVAL '1 hour'
WHERE reservation_start_time IS NULL
   OR reservation_end_time IS NULL;

-- =========================================
-- 3. APPLY NOT NULL CONSTRAINTS
-- =========================================

ALTER TABLE reservations
    ALTER COLUMN reservation_start_time SET NOT NULL,
    ALTER COLUMN reservation_end_time SET NOT NULL;

-- =========================================
-- 4. UPDATE UNIQUE CONSTRAINT
-- =========================================

ALTER TABLE reservations
    DROP CONSTRAINT IF EXISTS unique_booking;

ALTER TABLE reservations
    ADD CONSTRAINT unique_booking
    UNIQUE (seat_id, reservation_date, reservation_start_time);

-- =========================================
-- 5. REMOVE OLD TIME COLUMN
-- =========================================
-- Run this only if the old reservation_time column exists.

ALTER TABLE reservations
    DROP COLUMN IF EXISTS reservation_time;