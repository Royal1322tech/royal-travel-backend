SELECT
    table_name,
    column_name,
    data_type,
    is_nullable,
    column_default
FROM information_schema.columns
WHERE table_schema = 'public'
  AND table_name IN (
    'rt_orders',
    'rt_bookings',
    'rt_booking_guests',
    'rt_hotel_booking_details',
    'rt_booking_price_snapshots',
    'rt_idempotency_keys'
  )
ORDER BY table_name, ordinal_position;
