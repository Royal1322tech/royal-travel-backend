-- v0.2 constraints; policies and authorization remain enforced in application layer.
ALTER TABLE rt_refunds ADD CONSTRAINT rt_refunds_allocation_fk
 FOREIGN KEY (payment_intent_id, booking_id)
 REFERENCES rt_payment_allocations(payment_intent_id, booking_id) ON DELETE RESTRICT;
ALTER TABLE rt_payment_intents ADD CONSTRAINT rt_payment_amount_bounds_ck
 CHECK (captured_amount <= requested_amount);
ALTER TABLE rt_booking_price_snapshots ADD CONSTRAINT rt_price_approved_complete_ck
 CHECK (approved_at IS NULL OR approved_by IS NOT NULL);
ALTER TABLE rt_booking_fx_snapshots ADD CONSTRAINT rt_fx_snapshot_direction_ck
 CHECK (base_currency <> quote_currency);
CREATE INDEX IF NOT EXISTS rt_refunds_allocation_idx ON rt_refunds(payment_intent_id, booking_id);
CREATE INDEX IF NOT EXISTS rt_bookings_company_status_idx ON rt_bookings(company_id,status,created_at DESC);
CREATE INDEX IF NOT EXISTS rt_supplier_entitlements_scope_idx ON rt_supplier_entitlements(supplier_id,channel,service_type,product_scope,rate_scope);
-- Do not infer supplier eligibility from a missing row: app queries must require explicit verified allow.
