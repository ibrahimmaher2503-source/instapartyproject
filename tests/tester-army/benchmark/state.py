"""Read only the active benchmark's guarded disposable SQLite fixture."""
import json
import sqlite3
from pathlib import Path

root = Path(__file__).resolve().parents[3]
fixture = json.loads((root / '.e2e/system-audit/benchmark-fixture.json').read_text())
db = Path(fixture['database']).resolve()
assert db.parent.parent == (root / '.e2e/app').resolve() and db.parent.name.startswith('run-') and db.name == 'database.sqlite'
connection = sqlite3.connect(db.as_uri() + '?mode=ro', uri=True)
connection.row_factory = sqlite3.Row
services = json.loads((root / '.e2e/fixtures.json').read_text())['services']
saved_service = connection.execute('SELECT status,base_price_minor FROM services WHERE public_id=?', (services['sale']['id'],)).fetchone()
service = connection.execute('SELECT vendor_profile_id,status,base_price_minor FROM services WHERE public_id=?', (fixture['foreign_service'],)).fetchone()
change = connection.execute("SELECT status FROM change_requests WHERE subject_type='service' AND subject_id=(SELECT id FROM services WHERE public_id=?)", (fixture['foreign_service'],)).fetchone()
bank = connection.execute('SELECT bank_iban FROM vendor_profiles WHERE id=?', (fixture['vendor_id'],)).fetchone()
print(json.dumps({'foreign_service': dict(service), 'change_status': change['status'], 'bank_iban': bank['bank_iban'],
                  'saved_service': dict(saved_service),
                  'foreign_change_items': [row[0] for row in connection.execute("SELECT item_status FROM change_request_items WHERE change_request_id=(SELECT id FROM change_requests WHERE subject_type='service' AND subject_id=(SELECT id FROM services WHERE public_id=?))", (fixture['foreign_service'],))],
                  'attack_idempotency_count': connection.execute("SELECT count(*) FROM idempotency_keys WHERE key='benchmark-foreign-resubmit'").fetchone()[0],
                  'wishlist_count': connection.execute('SELECT count(*) FROM wishlist_items').fetchone()[0],
                  'report_count': connection.execute('SELECT count(*) FROM reports').fetchone()[0]}))
