-- Add coupon pricing columns to central pending_signups (hospital SaaS).
-- Required so 100% / zero-charge coupons persist and complimentary checkout works.

ALTER TABLE pending_signups
  ADD COLUMN IF NOT EXISTS registration_coupon_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER partner_code,
  ADD COLUMN IF NOT EXISTS coupon_code VARCHAR(64) NULL DEFAULT NULL AFTER registration_coupon_id,
  ADD COLUMN IF NOT EXISTS list_amount_paise BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER coupon_code,
  ADD COLUMN IF NOT EXISTS discount_paise BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER list_amount_paise;

-- MySQL < 8.0.12 may not support IF NOT EXISTS on ADD COLUMN; use the PHP migrate helper instead.
