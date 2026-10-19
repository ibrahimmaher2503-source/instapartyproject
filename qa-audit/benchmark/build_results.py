"""Reconcile five benchmark cases from exact outcomes, not green/red test counts."""
import csv
import json
import xml.etree.ElementTree as ET
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
OUT = Path(__file__).resolve().parent
runtime = ROOT / '.e2e/system-audit/benchmark'
evidence = OUT / 'evidence'
evidence.mkdir(exist_ok=True)
data = {case: json.loads((runtime / f'{case}.json').read_text()) for case in ['B01', 'B02', 'B03', 'B04', 'B05']}
assert data['B01']['status'] == 201 and data['B01']['response']['data']['is_available'] is False
assert data['B01']['persisted']['wishlist_count'] == 1 and data['B01']['persisted']['saved_service']['status'] == 'published'
assert data['B02'] == {'status': 500, 'report_count_before': 0, 'report_count_after': 0}
assert data['B03']['status'] == 404
for field in ['foreign_service', 'change_status', 'foreign_change_items', 'attack_idempotency_count']:
    assert data['B03']['before'][field] == data['B03']['after'][field]
assert data['B03']['after']['foreign_change_items'] == ['pending'] and data['B03']['after']['attack_idempotency_count'] == 0
assert data['B05']['input'] == 'EG00••••001' and data['B05']['raw_value_present_in_html'] is False and data['B05']['bank_unchanged'] is True
failure = next(ET.parse(runtime / 'integration.xml').iter('failure')).text
assert 'ttl_seconds' in failure and 'syntax error' in failure
assert data['B04'][0]['http_status'] == 500 and data['B04'][0]['processing_error'] is None
safe = {
    'B01': {'http_status': 201, 'is_available': False, 'saved_service': data['B01']['persisted']['saved_service'], 'saved_item_count': 1},
    'B02': data['B02'],
    'B03': {'status': 404, **{phase: {field: data['B03'][phase][field] for field in ['foreign_service', 'change_status', 'foreign_change_items', 'attack_idempotency_count']} for phase in ['before', 'after']}},
    'B04': {'attempts': data['B04'], 'blocker': 'SQLite rejects MySQL interval expression in IdempotencyService::hasBeenUsed before amount/currency comparison; the USD variant was not reached.'},
    'B05': data['B05'],
}
for case, item in safe.items():
    (evidence / f'{case}.json').write_text(json.dumps(item, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')

case_rows = [
    ('B01', 'TA-SYS-001', 'DETECTED', 'PERSISTS', 'NOT_DETECTED by passing normal suite', 'DETECTED by separate failing repro', 'Weak assertions: count/status/ownership without is_available; browser title alone does not validate API availability.', 'docs/qa/tester-army-system/confirmed-findings.md', 'tests/tester-army/benchmark/pilot.e2e.ts', 'SC-Discovery-01fcf4441a', 'Sale fixture only; no blanket wishlist/type coverage.'),
    ('B02', 'TA-SYS-002', 'DETECTED', 'PERSISTS', 'NOT_DETECTED by passing normal suite', 'DETECTED by separate failing repro', 'Missing accepted-type branch: normal suite POSTs an empty report, stops at 422 and never tests chat_thread mapping.', 'docs/qa/tester-army-system/confirmed-findings.md', 'tests/tester-army/benchmark/pilot.e2e.ts', 'SC-TrustSafety-778af0c3d0 (adjacent vendor report flow; chat branch missing)', 'Nonexistent valid-format chat ID; valid participant reporting not tested.'),
    ('B03', 'VTSE-001', 'NOT_REPRODUCIBLE', 'NOT_REPRODUCED_IN_TESTED_BRANCH', 'NOT_COVERED by normal e2e suite', 'Native ownership regression already covered; historical focused fix reported', 'Missing adversarial browser/API coverage in normal suite; current actor/ownership guard rejects with 404 and preserves rows.', 'docs/qa/vendor-to-settlement-e2e-audit-2026-09-12.md:65', 'tests/tester-army/benchmark/pilot.e2e.ts', 'SC-Catalog-63ee5055a4;SC-Catalog-f7413ff5b6', 'Cross-vendor attempt only; owner allowlist variants and all product types are not proven.'),
    ('B04', 'VTSE-007', 'BLOCKED', 'UNKNOWN', 'NOT_COVERED; fake initiation never reaches capture', 'Native amount test mocks IdempotencyService and cannot establish real HTTP/database path', 'Missing workflow steps; wrong testing layer and environment differences: real HTTP stops at MySQL-only interval SQL on SQLite.', 'docs/qa/vendor-to-settlement-e2e-audit-2026-09-12.md:130', 'qa-audit/benchmark/tests/WebhookMismatchTest.php', 'SC-Payments-3436fcba08;SC-Payments-929e770a5e', 'Gateway signature/parser simulated, capture/ledger real; currency variant not reached; MySQL and real HMAC unverified.'),
    ('B05', 'BUG-VENDOR-BANK-DATA-001', 'NOT_REPRODUCIBLE', 'NOT_REPRODUCED_IN_TESTED_BRANCH', 'NOT_COVERED; indexes only', 'Native audit masking coverage is a different layer', 'Missing edit workflow and controlled bank fixture; prior admin fixture granted view_any rather than explicit record update permission.', 'docs/qa/instaparty-issues-2026-09-12.md:952', 'tests/tester-army/benchmark/pilot.e2e.ts', 'SC-Identity-e02e2e0de3 (adjacent profile update; no exact admin masking scenario)', 'Admin edit default IBAN only; review queue/reveal/export/encryption/other bank fields not tested.'),
]
headers = ['case_id', 'bug_id', 'classification', 'current_persistence', 'previous_normal_suite', 'previous_campaign_overall', 'evidence_supported_cause', 'original_report', 'repro_test', 'related_scenario_ids', 'limitations', 'evidence']
with (OUT / 'DETECTION_RESULTS.csv').open('w', encoding='utf-8-sig', newline='') as stream:
    writer = csv.writer(stream)
    writer.writerow(headers)
    for row in case_rows:
        writer.writerow([*row, f'qa-audit/benchmark/evidence/{row[0]}.json'])
assert len(case_rows) == 5
print('Five cases reconciled: 2 DETECTED, 2 NOT_REPRODUCIBLE, 1 BLOCKED. No application correctness is inferred from a test status alone.')
