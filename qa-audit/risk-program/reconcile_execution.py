"""Reconcile source-preserved scenario coverage from business assertions and retained runs."""
import csv
import hashlib
import json
import re
import xml.etree.ElementTree as ET
from collections import Counter
from datetime import datetime
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
OUT = Path(__file__).resolve().parent
EVIDENCE = OUT / 'evidence'
EVIDENCE.mkdir(exist_ok=True)
source = json.loads((OUT / 'SOURCE_RECONCILIATION.json').read_text(encoding='utf-8'))
selected = source['selected']
catalog = json.loads((ROOT / 'docs/qa-discovery/catalog_data.json').read_text(encoding='utf-8'))['sheetDefs'][4]
scenarios = {row[0]: dict(zip(catalog[1], row)) for row in catalog[2]}
with (ROOT / 'docs/qa/tester-army-system/scenario-execution.csv').open(encoding='utf-8-sig', newline='') as stream:
    old = {row['scenario_id']: row for row in csv.DictReader(stream)}

for entry in source['sources']:
    assert hashlib.sha256((ROOT / entry['file']).read_bytes()).hexdigest() == entry['sha256'], f'Original source changed: {entry["file"]}'

def load(path):
    return json.loads(path.read_text(encoding='utf-8'))

def link(path):
    return Path(path).relative_to(ROOT).as_posix()

def save_evidence(key, content):
    path = EVIDENCE / f'{key}.json'
    path.write_text(json.dumps(content, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    return link(path)

php_runs = []
for directory in sorted((ROOT / '.e2e/risk-program/mysql').glob('run-*')):
    if (directory / 'junit.xml').exists(): php_runs.append((directory, ET.parse(directory / 'junit.xml')))
records = []
policy_gates = {'SC-Payments-785d9ad7b6', 'SC-Payments-ff1b150250'}
partial_limits = {
    'SC-Booking-d23fdddb0e': 'Draft, note, currency and real authorized HTTP creation verified; BookingDraftCreated event delivery and every optional field not instrumented.',
    'SC-Booking-159b4bcd53': 'Sale identity/name/price/quantity snapshot and held inventory verified; rental/digital, commission/transport snapshots and BookingItemAdded delivery not all asserted.',
    'SC-Booking-96dc5a0c0e': 'Single-vendor lifecycle/VAT/deadline/transition verified; multiple vendors and all notification/event effects not established.',
    'SC-Booking-ff6a2c0c84': 'Sequential replay preserves booking, items, holds, deadlines, transitions and notification/key counts; event fanout and simultaneous retry not instrumented.',
    'SC-Booking-1806e15c31': 'Final single vendor acceptance confirms parent with unchanged amount; BookingConfirmed event itself and multi-vendor prerequisite gate not instrumented.',
    'SC-Payments-db31a76816': 'Capture ledger, paid projection, amount and after-commit events verified on MariaDB; scope-coercion defect means a written idempotency row is not valid replay recognition. Real Paymob signature not tested.',
}
for key in selected:
    scenario = scenarios[key]
    if key.startswith('SC-Booking-') and key != 'SC-Booking-320197067b':
        directory = ROOT / '.e2e/risk-program/browser-connected' if key in selected[:5] else ROOT / '.e2e/risk-program/browser-initial'
        report = load(directory / 'report.json')['run']
        title = 'connected sale booking' if key in selected[:5] else key
        result = next(row for row in report['results'] if row['selected'] and title in ' '.join(row['titlePath']))
        assert result['status'] == 'passed'
        evidence = load(ROOT / '.e2e/risk-program/browser-evidence' / f'{key}.json')
        retained = save_evidence(key, evidence)
        trace = next((directory / 'results').glob('booking-connected*/trace.md')) if key in selected[:5] else next((directory / 'results').glob('*' + key.lower().replace('sc-booking-', 'sc-booking-')[:21] + '*/trace.md'), None)
        if trace is None:
            # Resolve through actual trace text; never invent a wildcard link.
            trace = next(path for path in (directory / 'results').glob('*/trace.md') if key in path.read_text(encoding='utf-8'))
        status = 'PARTIALLY_TESTED' if key in partial_limits else 'VERIFIED_PASS'
        observed = 'Executed API actions and exact SQLite persistence assertions; connected browser shows Confirmed, correct saved service and event note.' if key in selected[:5] else f'Exact {404 if key.endswith("32b824ae4d") else 422} with identical before/after booking/item/vendor/hold/transition/notification/key snapshot.'
        test_file = 'tests/tester-army/risk/booking.e2e.ts'
        test_name = ' › '.join(result['titlePath'])
        assertion_path = retained
        duration = result['attempts'][-1]['durationMs'] / 1000
        run_evidence = link(trace)
        limits = partial_limits.get(key, 'Specified HTTP guard and disposable fixture only; not all roles, locales or subsequent workflow branches.')
    else:
        candidates = [(directory, tree) for directory, tree in php_runs if (directory / f'{key}.json').exists()]
        assert candidates, key
        directory, tree = candidates[-1]
        evidence = load(directory / f'{key}.json')
        retained = save_evidence(key, evidence)
        target = next((case for case in tree.iter('testcase') if key.split('-')[-1] in case.attrib['name']), None)
        if target is None:
            label = 'r q a 001' if key.endswith('db566d3009') else 'r q a 002'
            target = next(case for case in tree.iter('testcase') if label.replace(' ', '') in case.attrib['name'].lower().replace(' ', '') or ('RQA_001' if key.endswith('db566d3009') else 'RQA_002') in case.attrib['name'])
        failures = [entry for entry in evidence['assertions'] if entry['actual'] != entry['expected']]
        status = 'VERIFIED_FAIL' if failures else 'PARTIALLY_TESTED' if key in partial_limits else 'VERIFIED_PASS'
        assert (target.find('failure') is not None) == bool(failures), key
        observed = '; '.join(f'{entry["assertion"]}: expected={entry["expected"]!r}, actual={entry["actual"]!r}' for entry in failures) if failures else '; '.join(entry['assertion'] for entry in evidence['assertions'])
        test_file = 'qa-audit/risk-program/tests/NewDefectReproTest.php' if 'independent repro' in target.attrib['name'].lower() else 'qa-audit/risk-program/tests/FinancialPilotTest.php'
        test_name = target.attrib['class'] + '::' + target.attrib['name']
        assertion_path = retained
        duration = float(target.attrib['time'])
        run_evidence = link(directory / 'junit.xml')
        limits = partial_limits.get(key, 'Real MariaDB 11.8.6 via Laravel mysql driver, file-cache locks, simulated external gateway boundary. Not Oracle MySQL migration acceptance, production Redis or real gateway transport/signatures.')
        if key.endswith('320197067b'): limits += ' Two independent processes at REPEATABLE-READ with forced service-lock contention; not a general load benchmark.'
        if key.endswith('db566d3009'): limits += ' Ledger/event counts stayed unchanged due to the capture guard; no duplicate charge is established.'
    records.append({'scenario_id': key, 'module': scenario['Module'], 'title': scenario['Scenario Title'], 'original_priority': scenario['Priority'], 'status': status, 'test_file': test_file, 'test_name': test_name, 'assertion_evidence': assertion_path, 'execution_evidence': run_evidence, 'duration_seconds': round(duration, 3), 'observed': observed, 'limitations': limits, 'source_outcome': scenario['Expected Business Outcome']})

assert len(records) == len({r['scenario_id'] for r in records}) == 20
counts = Counter(row['status'] for row in records)
assert counts == {'VERIFIED_PASS': 12, 'PARTIALLY_TESTED': 6, 'VERIFIED_FAIL': 2}, counts
by_id = {r['scenario_id']: r for r in records}
baseline_status = {'not-run': 'NOT_TESTED', 'needs-business-confirmation': 'NEEDS_BUSINESS_DECISION', 'partially-tested': 'PARTIALLY_TESTED'}
coverage = []
for key, scenario in scenarios.items():
    result = by_id.get(key)
    coverage.append({'scenario_id': key, 'module': scenario['Module'], 'original_priority': scenario['Priority'], 'before_status': baseline_status[old[key]['status']], 'after_status': result['status'] if result else 'NEEDS_BUSINESS_DECISION' if key in policy_gates else baseline_status[old[key]['status']], 'selected_pilot': bool(result), 'current_execution': 'EXECUTED' if result else 'NOT_EXECUTED_THIS_PILOT', 'test_file': result['test_file'] if result else '', 'test_name': result['test_name'] if result else '', 'assertion_evidence': result['assertion_evidence'] if result else '', 'execution_evidence': result['execution_evidence'] if result else '', 'coverage_delta': 'Exact new business/persistence evidence; see bounded status' if result else 'Additional policy gate; no execution coverage' if key in policy_gates else 'No new exact scenario coverage; historical partial/decision labels preserved', 'limitations': result['limitations'] if result else 'Current unsafe-looking observation is not an approved correct outcome; terminal callback/partial-refund policy unresolved' if key in policy_gates else old[key]['limit']})
with (OUT / 'COVERAGE_DELTA.csv').open('w', encoding='utf-8-sig', newline='') as stream:
    writer = csv.DictWriter(stream, fieldnames=list(coverage[0])); writer.writeheader(); writer.writerows(coverage)
(OUT / 'PILOT_RESULTS.json').write_text(json.dumps(records, ensure_ascii=False, indent=2), encoding='utf-8')

run_rows = []
for directory in [ROOT / '.e2e/risk-program/browser-initial', ROOT / '.e2e/risk-program/browser-connected', ROOT / '.e2/risk-program/known-controls', ROOT / '.e2e/risk-program/explore-stale-context', ROOT / '.e2e/risk-program/explore-profile-validation']:
    run = load(directory / 'report.json')['run']
    summary = (directory / 'summary.md').read_text(encoding='utf-8')
    found = re.search(r'(\d+) model calls?', summary)
    calls = int(found[1]) if found else 0 if run['usage']['modelTokens'] == 0 else 'not exposed'
    duration = (datetime.fromisoformat(run['finishedAt'].replace('Z', '+00:00')) - datetime.fromisoformat(run['startedAt'].replace('Z', '+00:00'))).total_seconds()
    run_rows.append({'run': run['id'], 'kind': directory.name, 'status': run['status'], 'duration_seconds': round(duration, 3), 'model_calls': calls, 'model_tokens': run['usage']['modelTokens'], 'retries': 0, 'report': link(directory / 'report.json')})
for directory, tree in php_runs:
    suite = next(tree.iter('testsuite'))
    measured = re.search(r'Duration:\s*([0-9.]+)s', (directory / 'run.log').read_text(encoding='utf-8'))
    duration = float(measured[1]) if measured else float(suite.attrib.get('time', 0)) or None
    run_rows.append({'run': directory.name, 'kind': 'PHP isolated database attempt', 'status': 'failed' if list(tree.iter('failure')) or list(tree.iter('error')) else 'passed', 'duration_seconds': duration, 'model_calls': 0, 'model_tokens': 0, 'retries': 0, 'report': link(directory / 'junit.xml')})
(OUT / 'EXECUTION_METRICS.json').write_text(json.dumps(run_rows, indent=2), encoding='utf-8')
schema_repro = load(ROOT / '.e2e/risk-program/mysql/run-1791835287-379/RQA-003.json')
assert schema_repro['actual_exit'] == 1 and 'DROP CONSTRAINT IF EXISTS' in schema_repro['exception']
save_evidence('RQA-003', {key: value for key, value in schema_repro.items() if key != 'output'})

lines = ['# Pilot execution results — 20 original scenarios', '', '**12 VERIFIED_PASS, 6 PARTIALLY_TESTED, 2 VERIFIED_FAIL.** These are outcome classifications, not the test runner pass count. The two failures independently reproduce new runtime defects; MySQL schema bootstrap separately reproduces an infrastructure defect. No product code was changed.', '', 'The pilot covers 12 original P0 and 8 P1 scenarios. 49 other P0 scenarios were not selected. The catalog still contains 1,292 original IDs. The selected pilot is 20/1,292 investigated definitions, not a system correctness percentage. Global coverage statuses retain 31 unresolved business decisions and two unrefreshed historical partial records.', '', '## Per-scenario evidence', '', '| Scenario / original priority | Defensible status | Test and exact evidence | Outcome / limits |', '| --- | --- | --- | --- |']
for row in records:
    lines += [f'| {row["scenario_id"]} / {row["original_priority"]} | {row["status"]} | [{row["test_file"]}](../../{row["test_file"]}) → `{row["test_name"]}`; [assertions](../../{row["assertion_evidence"]}); [execution](../../{row["execution_evidence"]}); {row["duration_seconds"]}s | {row["observed"].replace("|", "/")} **Limit:** {row["limitations"].replace("|", "/")} |']
lines += ['', 'Shared connected-test duration is listed against each participating scenario for traceability; do not sum those five identical durations as five independent executions.', '', '## What was connected', '', 'One real HTTP booking lifecycle created a future customer draft with note/address, added a sale item and hold, submitted it to vendor review, retried the identical submit without persisted effects, and accepted the final vendor contribution. tester.army signed in and verified the resulting account page showed Confirmed, the exact service and note, and the total. These were API mutations followed by browser readback, not a complete UI checkout.', '', 'Financial fixtures separately followed real HTTP capture → committed paid booking/ledger → real full refund processor → refunded booking/ledger, with gateway boundary substitution. They were not the same booking as the browser lifecycle; no single storefront-to-gateway journey is claimed. All exact amount/currency/missing-amount mismatch branches preserved pending/unpaid rows and emitted no capture event on MariaDB.', '', '## Known-defect controls', '', 'TA-SYS-001 and TA-SYS-002 still fail unchanged availability/client-error assertions in [the fresh control run](../../.e2/risk-program/known-controls/junit.xml). They remain known findings, not new defects or extra original catalog IDs. The wishlist entry scenario describes saved-row reuse; availability is an additional contract oracle. The chat-type branch is not the catalog vendor-profile-report scenario. Do not rewrite either original expected outcome or inflate exact scenario execution.', '', 'B04/VTSE-007 now reaches the real mismatch guard on MariaDB; amount-minus-one, wrong currency and absent captured amount all pass exact state/ledger/log/event assertions. Oracle MySQL 8.4 remains blocked by its migration syntax. The previous benchmark reports remain historical and unchanged.', '', '## Exploratory sessions', '', 'Stale event context: three charter steps completed, 45 model calls, 383,274 tokens (110,592 cached), 174.09s including 35.57s startup. Invalid event ranges were rejected; no cart/date contradiction was independently established. Its generic-error warning is unconfirmed: the cited end-before-start input is correctly invalid, and the agent inferred a wrong current-date explanation. It is not filed as a new bug. Rental/sale cart switching was not fully reached.', '', 'Profile validation: provider usage limit prevented all charter steps; 1 attempted model call, zero returned tokens, 114.35s including 42.14s startup. Status BLOCKED, not a clean exploratory pass. No profile-validation coverage is awarded. See [BLOCKERS](BLOCKERS.md).', '', '## Run ledger', '', '| Run | Kind / outcome | Seconds | Retries | Model calls / tokens | Evidence |', '| --- | --- | ---: | ---: | --- | --- |']
for row in run_rows:
    lines += [f'| {row["run"]} | {row["kind"]}: {row["status"]} | {row["duration_seconds"]} | {row["retries"]} | {row["model_calls"]} / {row["model_tokens"]} | [report](../../{row["report"]}) |']
lines += ['', 'Manual corrections/reruns are distinct run records; automatic retries were zero. Initial runtime helper/selection/source-copy issues and migration rollback cleanup failures are retained as harness/setup evidence, never counted as detected business failures. PHP duration is suite execution from JUnit and excludes unmeasured archive/database startup; no total campaign elapsed time is invented.', '', '## Coverage boundaries and handoff', '', 'See [QA_EXECUTION_PLAN](QA_EXECUTION_PLAN.md), [RISK_PRIORITY_MATRIX](RISK_PRIORITY_MATRIX.csv), [TEST_LAYER_MAPPING](TEST_LAYER_MAPPING.csv), [COVERAGE_DELTA](COVERAGE_DELTA.csv), [NEW_DEFECTS](NEW_DEFECTS.md) and [BLOCKERS](BLOCKERS.md). Every original ID has an after-status and explicit current execution marker. Every selected result has a test name, persisted actual/expected evidence, execution artifact and limit.', '', 'Failing reproductions and PHP pilot classes are outside ordinary Unit/Feature suite directories; browser risk/benchmark directories are excluded by the normal e2e glob. No blanket CI-green, full-system, production, real-gateway, real-Redis, Arabic parity or full-concurrency acceptance is claimed. **Stopped after 20 distinct selected catalog scenarios.**', '']
lines += ['## Delivery verification', '', 'All seven requested outputs exist. The workbook/JSON/CSV scenario definitions reconcile on all 1,292 original IDs and every saved scenario field. Forty-six original source hashes remain unchanged. Matrix, layer mapping and coverage CSV each contain exactly 1,292 distinct IDs; twenty are selected, and all twenty have real test/assertion/execution files.', '', 'Normal tester.army collection selects 77 desktop pairs and no risk/benchmark repros. Risk collection selects the three browser/API test titles covering seven selected IDs. These were collection checks only, not extra executions. Four PHP files passed syntax checks, and the three infrastructure shell scripts passed shell syntax validation. Evidence links resolve; test daemons are stopped. No broader suite was rerun.', '', 'Coverage retains 31 original business-decision flags and adds two explicit policy gates for late failed callbacks and partial persisted refunds, yielding 33 NEEDS_BUSINESS_DECISION rows. This changes review status only, not the original expected results or execution coverage.', '']
(OUT / 'PILOT_EXECUTION_RESULTS.md').write_text('\n'.join(lines), encoding='utf-8')
print(json.dumps({'pilot': dict(counts), 'catalog_after': dict(Counter(row['after_status'] for row in coverage)), 'exact_ids': len(coverage), 'source_hashes_preserved': len(source['sources'])}))
