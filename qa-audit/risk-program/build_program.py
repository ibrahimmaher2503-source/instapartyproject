"""Source-preserving risk/layer analysis; coverage advances only on assertion evidence."""
import csv
import hashlib
import json
import re
import xml.etree.ElementTree as ET
import zipfile
from collections import Counter
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
OUT = Path(__file__).resolve().parent
CATALOG = ROOT / 'docs/qa-discovery/catalog_data.json'
data = json.loads(CATALOG.read_text(encoding='utf-8'))
name, headers, raw_rows = data['sheetDefs'][4]
scenarios = {row[0]: dict(zip(headers, row)) for row in raw_rows}
assert len(scenarios) == len(raw_rows) == 1292
with (ROOT / 'docs/qa/tester-army-system/scenario-execution.csv').open(encoding='utf-8-sig', newline='') as stream:
    old = {row['scenario_id']: row for row in csv.DictReader(stream)}
assert old.keys() == scenarios.keys()

# Read saved workbook cells, not just its companion JSON; never modify the original.
ns = {'s': 'http://schemas.openxmlformats.org/spreadsheetml/2006/main'}
workbook = ROOT / 'docs/qa-discovery/Master_Scenario_Flow_Catalog.xlsx'
with zipfile.ZipFile(workbook) as archive:
    strings = [''.join(node.itertext()) for node in ET.fromstring(archive.read('xl/sharedStrings.xml')).findall('s:si', ns)] if 'xl/sharedStrings.xml' in archive.namelist() else []
    sheet = ET.fromstring(archive.read('xl/worksheets/sheet5.xml'))
    workbook_ids = []
    workbook_scenarios = []
    for row in sheet.findall('s:sheetData/s:row', ns):
        cell = next((c for c in row.findall('s:c', ns) if re.fullmatch(r'A\d+', c.attrib['r'])), None)
        if cell is None: continue
        value = cell.find('s:v', ns)
        text = strings[int(value.text)] if cell.attrib.get('t') == 's' else ''.join(cell.find('s:is', ns).itertext()) if cell.attrib.get('t') == 'inlineStr' else value.text if value is not None else ''
        if text.startswith('SC-'):
            workbook_ids.append(text)
            values = {}
            for field in row.findall('s:c', ns):
                ref = re.match(r'[A-Z]+', field.attrib['r'])[0]
                index = 0
                for character in ref: index = index * 26 + ord(character) - ord('A') + 1
                value = field.find('s:v', ns)
                values[index - 1] = strings[int(value.text)] if field.attrib.get('t') == 's' else ''.join(field.find('s:is', ns).itertext()) if field.attrib.get('t') == 'inlineStr' else value.text if value is not None else ''
            workbook_scenarios.append([values.get(index, '') for index in range(len(headers))])
assert len(workbook_ids) == 1292 and set(workbook_ids) == scenarios.keys()
assert workbook_scenarios == raw_rows, 'Saved workbook scenario definitions must match all companion JSON fields'
assert {p.name for p in (ROOT / 'app/Modules').iterdir() if p.is_dir()} == set(data['stats']['moduleCounts']) - {'Firebase'}

browser_ids = ['SC-Booking-d23fdddb0e', 'SC-Booking-159b4bcd53', 'SC-Booking-96dc5a0c0e', 'SC-Booking-ff6a2c0c84', 'SC-Booking-1806e15c31', 'SC-Booking-32b824ae4d', 'SC-Booking-e8e7c96608']
php_file = 'qa-audit/risk-program/tests/FinancialPilotTest.php'
php_text = (ROOT / php_file).read_text(encoding='utf-8')
methods = dict((f'SC-{module}-{key}', method) for method, module, key in re.findall(r'function (test_SC_(Payments|Settlement|Booking)_([a-z0-9]+)_[a-z0-9_]+)\(', php_text))
selected = browser_ids + list(methods)
policy_gates = {'SC-Payments-785d9ad7b6', 'SC-Payments-ff1b150250'}
assert len(selected) == len(set(selected)) == 20 and set(selected) <= scenarios.keys()
sources = [workbook, CATALOG, ROOT / 'docs/qa/tester-army-system/scenario-execution.csv']
sources += list((ROOT / 'docs/qa-discovery').glob('*.md')) + list((ROOT / 'docs/qa-discovery').glob('*.json'))
sources += list((ROOT / 'qa-audit/benchmark').glob('*.md')) + [ROOT / 'qa-audit/benchmark/inventory-data.json']
sources += list((ROOT / 'docs/qa').glob('*.md')) + list((ROOT / 'docs/qa/tester-army-system').glob('*.md'))
manifest = []
for source in dict.fromkeys(sources):
    content = source.read_bytes()
    if source.suffix == '.json': json.loads(content)
    manifest.append({'file': source.relative_to(ROOT).as_posix(), 'sha256': hashlib.sha256(content).hexdigest(), 'bytes': len(content)})
(OUT / 'SOURCE_RECONCILIATION.json').write_text(json.dumps({'sources': manifest, 'workbook_scenario_ids_verified': len(workbook_ids), 'modules': data['stats']['moduleCounts'], 'previous_statuses': dict(Counter(row['status'] for row in old.values())), 'priorities': dict(Counter(row['Priority'] for row in scenarios.values())), 'blank_preconditions': sum(not row['Preconditions'] for row in scenarios.values()), 'selected': selected}, indent=2), encoding='utf-8')

def layers(row):
    text = ' '.join(str(v) for v in row.values()).lower()
    layer = []
    if row['Module'] == 'Firebase': layer += ['External integration testing', 'Security testing']
    if any(t in text for t in ['filament', 'storefront', 'browser', 'rtl', 'wizard']): layer += ['Browser E2E using tester.army']
    if any(t in text for t in ['webhook', 'gateway', 'firebase', 'sms', 'fcm']): layer += ['External integration testing']
    if row['Scenario Category'] == 'Concurrency' or any(t in text for t in ['concurrent', 'race', 'deadlock']): layer += ['Concurrency testing']
    if row['Scenario Category'] == 'Role-Based' or any(t in text for t in ['ownership', 'unauthorized', 'permission', 'authentication', 'signature', 'another customer', 'another vendor']): layer += ['Security testing']
    if row['Module'] in ['Payments', 'Settlement', 'Booking', 'Loyalty', 'Tax', 'Promotions']: layer += ['Business invariant testing']
    layer += ['API contract/integration' if any(t in text for t in ['routes/', 'http/controllers', 'post /', 'get /', 'request']) else 'Business invariant testing']
    layer += ['Database and persistence verification']
    return list(dict.fromkeys(layer))

def score(row):
    text = ' '.join(str(v) for v in row.values()).lower()
    reasons = [row['Priority'] + ' original priority']
    value = {'P0': 1000, 'P1': 600, 'P2': 200}[row['Priority']]
    for applies, points, reason in [
        (row['Module'] in ['Payments', 'Settlement'], 120, 'capture/refund/ledger exposure'),
        (row['Module'] == 'Booking', 100, 'booking/inventory lifecycle'),
        (any(t in text for t in ['authorization', 'ownership', 'permission', 'signature', 'authentication']), 90, 'trust boundary'),
        (any(t in text for t in ['concurrent', 'idempot', 'duplicate', 'race']), 80, 'retry/concurrency'),
        (';' in row['Affected Modules'], 40, 'cross-module propagation'),
    ]:
        if applies: value += points; reasons.append(reason)
    return value, '; '.join(reasons)

def csv_file(name, fieldnames, rows):
    with (OUT / name).open('w', encoding='utf-8-sig', newline='') as stream:
        writer = csv.DictWriter(stream, fieldnames=fieldnames); writer.writeheader(); writer.writerows(rows)

risk_rows = []
layer_rows = []
for key, row in scenarios.items():
    value, reason = score(row)
    risk_rows.append({'scenario_id': key, 'module': row['Module'], 'title': row['Scenario Title'], 'original_priority': row['Priority'], 'execution_risk_score': value, 'risk_reason': reason, 'selected_pilot': key in selected, 'prior_status': old[key]['status'], 'business_decision_gate': key in policy_gates or old[key]['status'] == 'needs-business-confirmation', 'business_authority': row['PRD Reference'], 'assertion_gap': 'Prior navigation/transport/static links do not verify the complete stated outcome; executable oracle must be linked.', 'fixture_gap': row['Preconditions'] or 'Missing exact source preconditions; design required', 'source': row['Code References']})
    mapping = layers(row)
    if key in browser_ids: mapping = ['Browser E2E using tester.army', 'API contract/integration', 'Database and persistence verification', 'Security testing' if key.endswith('32b824ae4d') else 'Business invariant testing']
    if key == 'SC-Booking-320197067b': mapping = ['Concurrency testing', 'Business invariant testing', 'Database and persistence verification']
    layer_rows.append({'scenario_id': key, 'module': row['Module'], 'primary_layer': mapping[0], 'supporting_layers': '; '.join(mapping[1:]), 'source_outcome_unchanged': row['Expected Business Outcome'], 'roles': row['Actor'], 'preconditions': row['Preconditions'] or 'Exact fixture not supplied by catalog; see pilot plan or retain unresolved gate', 'required_oracle': row['Expected Business Outcome'], 'persistence_evidence_required': row['Affected Entities'] or 'Resolve affected rows from source before execution', 'cross_module_effects': row['Affected Modules'], 'executable_test': 'tests/tester-army/risk/booking.e2e.ts' if key in browser_ids else php_file if key in methods else '', 'status': 'SELECTED_FOR_EXECUTION' if key in selected else 'NEEDS_BUSINESS_DECISION' if key in policy_gates or old[key]['status'] == 'needs-business-confirmation' else 'NOT_TESTED; design mapping only', 'limitations': 'Static implementation evidence is not approved PRD; no live external integration or all-variant coverage implied'})
risk_rows.sort(key=lambda r: (-r['execution_risk_score'], r['scenario_id']))
for rank, row in enumerate(risk_rows, 1): row['rank'] = rank
csv_file('RISK_PRIORITY_MATRIX.csv', ['rank', *[k for k in risk_rows[0] if k != 'rank']], risk_rows)
csv_file('TEST_LAYER_MAPPING.csv', list(layer_rows[0]), layer_rows)

plan = ['# Risk-based QA execution plan', '', 'Scope: exactly 20 original catalog IDs selected; separate known-defect controls and exploratory candidates do not inflate that count. Product source, original catalog and previous reports remain unchanged.', '', '## Source reconciliation and authority', '', '17 real Laravel modules plus Firebase external integration explain the 18 CSV classifications. The saved workbook has exactly the same 1,292 scenario IDs as the JSON and prior execution CSV. There is no missing eighteenth Laravel module.', '', 'Original priorities: 61 P0, 639 P1, 592 P2. Prior execution: 1,258 not-run, 31 needs-business-confirmation, 3 partially-tested. None is a verified full-scenario pass. There are 690 blank preconditions and 368 blank affected-entity fields; all related-scenario fields are blank. A derived shared-flow link is not an original relationship.', '', 'All discovered code-derived scenarios retain their original expected outcomes and lack an authoritative PRD. AGENTS/engineering policy provides ownership, UTC, integer-money, atomic/append-only financial history and idempotency invariants. Source behavior is verified only within that stated boundary; uncertain policy is NEEDS_BUSINESS_DECISION. FR-2 mandatory end versus WIZ-01 optional end remains unresolved.', '', 'The matrix ranks every scenario by original severity first, then financial/lifecycle/security/retry/propagation exposure. This is a transparent ordinal prioritization, not a calibrated probability or safety score. Unselected P0 areas remain NOT_TESTED. Layer mapping is a design proposal, not executable coverage. No assumption that all scenarios require a browser.', '', '## Execution and isolation', '', 'Browser mutations use real HTTP against the existing guarded disposable SQLite launcher and tester.army browser readback. Financial integration uses an isolated socket-only MySQL/MariaDB instance, real routing, idempotency, capture/refund processor, ledger, committed rows and real after-commit listeners. Only the external gateway boundary is simulated. No production credentials or gateway calls. File-cache wallet locks are real, but do not prove production Redis behavior.', '', 'The first MySQL run exposes a real migration incompatibility; preserve its failed trace. MariaDB is a separately labeled compatibility attempt, not MySQL acceptance. DatabaseTruncation isolates committed test data without reversing migrations against populated append-only history. Shutdown only the private database daemon; retain guarded artifacts for review.', '', 'No automatic retries. Correct locator/data/harness errors only; keep original business oracles. Re-run only affected tests after those corrections. Confirmed failing repros stay outside ordinary CI. Stop after the selected pilot; do not rerun the full suites.', '', '## Selected test designs', '']
for key in selected:
    row = scenarios[key]
    browser = key in browser_ids
    title = methods.get(key, 'connected sale booking' if key in browser_ids[:5] else key)
    pre = 'Verified isolated customer, approved vendor, published sale service 12500 EGP minor units, stock20, same-currency future event and valid local address; each standalone guard gets its own draft.' if browser else 'Socket-only disposable MySQL-compatible database; fresh truncated data, approved-role factories. Capture/refund fixture: confirmed unpaid booking and pending 10000 EGP payment; ledger fixture: equal 10000 debit/credit. Concurrency: stock1, two different owned drafts and two independent PHP processes held behind a real service row lock.'
    plan += [f'### {key} — {row["Scenario Title"]}', '', f'- Original priority: {row["Priority"]}; roles: {row["Actor"]}; source: `{row["Code References"]}`.', f'- Test: `{php_file if not browser else "tests/tester-army/risk/booking.e2e.ts"}` → `{title}`.', f'- Preconditions/data: {pre}', f'- Actions: {row["Scenario Description"]} See exact request bodies/process barrier in the linked test; no invented endpoint for Action-only invariants.', f'- Expected outcome (unchanged catalog): {row["Expected Business Outcome"]}', '- Exact assertions: the test pins HTTP/error class, lifecycle/ownership and integer amount/group/count as relevant; named assertions and actual/expected values are retained in JSON and JUnit/trace evidence. Browser navigation alone never advances full coverage.', f'- Persistence: real read-only SQLite snapshots for browser/API flow; real committed MySQL-compatible rows, ledger sums, immutable row snapshots and wallet projections for financial cases. Entities: {row["Affected Entities"] or "source-resolved in test"}.', f'- Cross-module effects: {row["Affected Modules"]}. Financial tests observe real PaymentCaptured, RefundCompleted and LedgerTransactionPosted events and booking projections; booking replay compares persisted transitions/deadlines/notification counts. Uninstrumented downstream systems remain a limitation.', '- Failure/cleanup: retain evidence before decisive assertion; failed business assertion stays failing. Disposable database/app shuts down; no shared data repair. Setup/SQL/tool errors are BLOCKED or PARTIALLY_TESTED, not business passes.', '- Limits: this is a specified branch/fixture; variants, all roles/locales and real provider signatures are not implied. Final coverage classification is reconciled from executed evidence.', '']
plan += ['## Separate exploratory work', '', 'Focused tester.army charters investigate stale planner/cart state and wishlist/account ownership/localization edges not exercised by deterministic booking/financial tests. Each uses its own disposable app, sequentially because global fixture manifests are shared. Findings are candidates until an independent deterministic test asserts approved behavior and fails for that reason. Preserve warnings and rejected/environment findings separately.', '', '## Status policy and review', '', 'VERIFIED_PASS requires every decisive stated business assertion in the tested scope and reproducible evidence. VERIFIED_FAIL requires a demonstrated intended-outcome contradiction. PARTIALLY_TESTED records useful branch evidence with unproven portions/tool interruptions. BLOCKED means prerequisites prevent reaching the oracle. NEEDS_BUSINESS_DECISION retains genuine policy ambiguity. NOT_TESTED means no exact current execution.', '', 'Coverage is tracked per original ID, not per passing test title, page visited or scenario name embedded in a test. The original CSV is preserved. Known chat report and wishlist availability controls are reported separately where the original scenario outcome does not contain their exact contract oracle.', '']
(OUT / 'QA_EXECUTION_PLAN.md').write_text('\n'.join(plan), encoding='utf-8')
print(json.dumps({'scenarios': len(scenarios), 'selected': len(selected), 'modules': 17, 'integration_surfaces': 1, 'workbook_verified': True, 'prior_statuses': dict(Counter(r['status'] for r in old.values()))}))
