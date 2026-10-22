-- Database integrity: immutable evidence (changes are appended as new rows/versions).
CREATE FUNCTION rt_prevent_mutation() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'immutable evidence table: %', TG_TABLE_NAME; END $$;
CREATE TRIGGER rt_price_snapshot_immutable BEFORE UPDATE OR DELETE ON rt_booking_price_snapshots FOR EACH ROW EXECUTE FUNCTION rt_prevent_mutation();
CREATE TRIGGER rt_fx_snapshot_immutable BEFORE UPDATE OR DELETE ON rt_booking_fx_snapshots FOR EACH ROW EXECUTE FUNCTION rt_prevent_mutation();
CREATE TRIGGER rt_financial_movements_immutable BEFORE UPDATE OR DELETE ON rt_financial_movements FOR EACH ROW EXECUTE FUNCTION rt_prevent_mutation();
CREATE TRIGGER rt_audit_events_immutable BEFORE UPDATE OR DELETE ON rt_audit_events FOR EACH ROW EXECUTE FUNCTION rt_prevent_mutation();
-- A Booking must have exactly one matching service-specific detail at transaction commit.
CREATE FUNCTION rt_assert_booking_detail(p_booking uuid) RETURNS void LANGUAGE plpgsql AS $$
DECLARE s varchar(32); h integer; t integer; o integer; detail_type varchar(32);
BEGIN
 SELECT service_type INTO s FROM rt_bookings WHERE id=p_booking;
 IF NOT FOUND THEN RETURN; END IF;
 SELECT count(*) INTO h FROM rt_hotel_booking_details WHERE booking_id=p_booking;
 SELECT count(*) INTO t FROM rt_transport_booking_details WHERE booking_id=p_booking;
 SELECT count(*), max(service_type) INTO o, detail_type FROM rt_service_booking_details WHERE booking_id=p_booking;
 IF NOT ((s='hotel' AND h=1 AND t=0 AND o=0) OR (s='transport' AND t=1 AND h=0 AND o=0) OR (s NOT IN ('hotel','transport') AND o=1 AND detail_type=s AND h=0 AND t=0)) THEN
  RAISE EXCEPTION 'Booking % must have exactly one matching service detail (type=%)',p_booking,s;
 END IF;
END $$;
CREATE FUNCTION rt_check_booking_detail() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF TG_TABLE_NAME='rt_bookings' THEN PERFORM rt_assert_booking_detail(CASE WHEN TG_OP = 'DELETE' THEN OLD.id ELSE NEW.id END);
 ELSE PERFORM rt_assert_booking_detail(CASE WHEN TG_OP = 'DELETE' THEN OLD.booking_id ELSE NEW.booking_id END); END IF;
 RETURN NULL;
END $$;
CREATE CONSTRAINT TRIGGER rt_booking_detail_parent_check AFTER INSERT OR UPDATE ON rt_bookings DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION rt_check_booking_detail();
CREATE CONSTRAINT TRIGGER rt_booking_hotel_detail_check AFTER INSERT OR UPDATE OR DELETE ON rt_hotel_booking_details DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION rt_check_booking_detail();
CREATE CONSTRAINT TRIGGER rt_booking_transport_detail_check AFTER INSERT OR UPDATE OR DELETE ON rt_transport_booking_details DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION rt_check_booking_detail();
CREATE CONSTRAINT TRIGGER rt_booking_other_detail_check AFTER INSERT OR UPDATE OR DELETE ON rt_service_booking_details DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION rt_check_booking_detail();
-- Validate company/channel/customer ownership against parent order.
CREATE FUNCTION rt_validate_booking_order() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE o rt_orders%ROWTYPE;
BEGIN
 SELECT * INTO o FROM rt_orders WHERE id=NEW.order_id;
 IF NEW.channel<>o.channel OR NEW.company_id IS DISTINCT FROM o.company_id OR NEW.customer_user_id<>o.customer_user_id THEN
  RAISE EXCEPTION 'Booking tenant/channel/customer must match parent Order';
 END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER rt_booking_order_guard BEFORE INSERT OR UPDATE OF order_id,company_id,channel,customer_user_id ON rt_bookings FOR EACH ROW EXECUTE FUNCTION rt_validate_booking_order();
-- Payment allocation validation; lock payment intent to serialize concurrent allocations.
CREATE FUNCTION rt_guard_payment_allocation() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE p rt_payment_intents%ROWTYPE; b rt_bookings%ROWTYPE; allocated numeric(20,6); n integer;
BEGIN
 SELECT * INTO p FROM rt_payment_intents WHERE id=NEW.payment_intent_id FOR UPDATE;
 SELECT * INTO b FROM rt_bookings WHERE id=NEW.booking_id;
 IF p.order_id<>b.order_id OR p.currency<>NEW.currency OR b.currency<>NEW.currency THEN
  RAISE EXCEPTION 'Payment allocation must match Order and currency';
 END IF;
 SELECT COALESCE(sum(amount),0) INTO allocated FROM rt_payment_allocations WHERE payment_intent_id=NEW.payment_intent_id AND id<>NEW.id;
 IF allocated+NEW.amount>p.requested_amount THEN RAISE EXCEPTION 'Payment allocations exceed intent amount'; END IF;
 IF p.mode='separate' THEN
  SELECT count(*) INTO n FROM rt_payment_allocations WHERE payment_intent_id=NEW.payment_intent_id AND id<>NEW.id;
  IF n>0 THEN RAISE EXCEPTION 'Separate payment intent may fund only one Booking'; END IF;
 END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER rt_payment_allocation_guard BEFORE INSERT OR UPDATE ON rt_payment_allocations FOR EACH ROW EXECUTE FUNCTION rt_guard_payment_allocation();
-- No supplier entitlement is implicitly granted: queries must require allowed+verified+valid time+scope.

-- Allocation identity and amount cannot change once created; corrections require reversal workflow.
CREATE FUNCTION rt_allocation_immutable_guard() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE p rt_payment_intents%ROWTYPE;
BEGIN
 SELECT * INTO p FROM rt_payment_intents WHERE id=OLD.payment_intent_id FOR UPDATE;
 IF TG_OP='DELETE' THEN
   IF p.captured_amount > 0 OR p.refunded_amount > 0 THEN
     RAISE EXCEPTION 'Cannot delete allocation for captured/refunded payment';
   END IF;
   RETURN OLD;
 END IF;
 IF NEW.payment_intent_id IS DISTINCT FROM OLD.payment_intent_id OR
    NEW.booking_id IS DISTINCT FROM OLD.booking_id OR
    NEW.amount IS DISTINCT FROM OLD.amount OR
    NEW.currency IS DISTINCT FROM OLD.currency THEN
    RAISE EXCEPTION 'Allocation financial identity is immutable';
 END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER rt_allocation_immutable BEFORE UPDATE OR DELETE ON rt_payment_allocations
 FOR EACH ROW EXECUTE FUNCTION rt_allocation_immutable_guard();
-- Protect approved booking price from drifting away from approved snapshot.
CREATE FUNCTION rt_validate_approved_booking_price() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF NEW.approved_total IS NOT NULL AND NOT EXISTS (
  SELECT 1 FROM rt_booking_price_snapshots s WHERE s.booking_id=NEW.id
    AND s.approved_at IS NOT NULL AND s.currency=NEW.currency AND s.customer_total=NEW.approved_total
 ) THEN RAISE EXCEPTION 'Booking approved total must match an approved price snapshot'; END IF;
 RETURN NEW;
END $$;
CREATE CONSTRAINT TRIGGER rt_booking_price_consistency AFTER INSERT OR UPDATE OF approved_total,currency ON rt_bookings
 DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION rt_validate_approved_booking_price();
CREATE FUNCTION rt_validate_price_snapshot_parent() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE b rt_bookings%ROWTYPE;
BEGIN
 SELECT * INTO b FROM rt_bookings WHERE id=NEW.booking_id;
 IF b.currency <> NEW.currency THEN RAISE EXCEPTION 'Snapshot currency must match booking'; END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER rt_price_snapshot_parent BEFORE INSERT ON rt_booking_price_snapshots
 FOR EACH ROW EXECUTE FUNCTION rt_validate_price_snapshot_parent();
