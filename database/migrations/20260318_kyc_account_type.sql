-- Add account_type to kyc_profiles
-- Stores the seller's account/business category chosen during KYC wizard Step 2
ALTER TABLE kyc_profiles
    ADD COLUMN IF NOT EXISTS account_type VARCHAR(60) DEFAULT NULL AFTER status;
