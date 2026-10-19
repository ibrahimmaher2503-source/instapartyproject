"""Read-only persistence evidence from the guarded active disposable browser app."""
import json
import sqlite3
import sys
from pathlib import Path

root = Path(__file__).resolve().parents[3]
fixture = json.loads((root / '.e2e/system-audit/benchmark-fixture.json').read_text())
database = Path(fixture['database']).resolve()
assert database.parent.parent == (root / '.e2e/app').resolve() and database.parent.name.startswith('run-') and database.name == 'database.sqlite'
connection = sqlite3.connect(database.as_uri() + '?mode=ro', uri=True)
connection.row_factory = sqlite3.Row
booking = connection.execute('SELECT id,public_id,customer_id,lifecycle_status,payment_status,total_minor,total_currency,total_vat_minor FROM bookings WHERE public_id=?', (sys.argv[1],)).fetchone()
assert booking is not None
items = connection.execute('SELECT i.public_id,i.service_id,s.public_id AS service_public_id,i.quantity,i.unit_price_minor,i.line_total_minor,i.vat_rate_bps,i.vat_amount_minor,i.name_snapshot,i.type_snapshot FROM booking_items i JOIN booking_vendors v ON v.id=i.booking_vendor_id JOIN services s ON s.id=i.service_id WHERE v.booking_id=?', (booking['id'],)).fetchall()
print(json.dumps({
    'booking': dict(booking),
    'items': [dict(item) for item in items],
    'vendors': [dict(row) for row in connection.execute('SELECT public_id,vendor_profile_id,sub_status,response_deadline FROM booking_vendors WHERE booking_id=?', (booking['id'],))],
    'holds': [dict(row) for row in connection.execute('SELECT r.quantity,r.status,r.expires_at FROM service_inventory_reservations r JOIN booking_items i ON i.id=r.booking_item_id JOIN booking_vendors v ON v.id=i.booking_vendor_id WHERE v.booking_id=?', (booking['id'],))],
    'transitions': [dict(row) for row in connection.execute("SELECT from_state,to_state,trigger_kind FROM state_transitions WHERE transitionable_type LIKE '%Booking' AND transitionable_id=? ORDER BY id", (booking['id'],))],
    'notes': [dict(row) for row in connection.execute('SELECT body FROM booking_customer_notes WHERE booking_id=?', (booking['id'],))],
    'notifications_count': connection.execute('SELECT count(*) FROM notifications').fetchone()[0],
    'idempotency_count': connection.execute('SELECT count(*) FROM idempotency_keys').fetchone()[0],
}))
