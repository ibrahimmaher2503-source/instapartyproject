"""Reconcile the discovery catalog with current e2e JUnit evidence, conservatively."""
import csv
import json
import re
import sys
import xml.etree.ElementTree as ET
from collections import Counter
from pathlib import Path

root = Path(__file__).resolve().parents[2]
catalog = json.loads((root / 'docs/qa-discovery/catalog_data.json').read_text(encoding='utf-8'))
_, headers, scenarios = next(sheet for sheet in catalog['sheetDefs'] if sheet[0].startswith('05 '))
assert len(scenarios) == catalog['stats']['scenarios'] == 1292
assert len({row[0] for row in scenarios}) == len(scenarios)
evidence = {}
runs = []
for argument in sys.argv[1:]:
    report = root / argument
    suite = ET.parse(report).getroot()
    tests = []
    for case in suite.iter('testcase'):
        status = 'failed' if case.find('failure') is not None or case.find('error') is not None else 'skipped' if case.find('skipped') is not None else 'passed'
        item = {'test': case.attrib['name'], 'file': case.attrib.get('classname'), 'status': status, 'report': report.relative_to(root).as_posix()}
        tests.append(item)
        for scenario_id in re.findall(r'SC-[A-Za-z]+-[a-f0-9]+', item['test']):
            evidence[scenario_id] = item
    runs.append({'report': report.relative_to(root).as_posix(), 'counts': dict(Counter(t['status'] for t in tests)), 'tests': tests})

rows = []
for source in scenarios:
    item = dict(zip(headers, source))
    found = evidence.get(source[0])
    proposed = source[25].startswith('Inferred')
    # An ID in a passing test proves its assertions, not every catalog clause.
    status = 'needs-business-confirmation' if proposed else 'not-run'
    if found:
        status = 'partially-tested' if found['status'] == 'passed' else found['status']
    rows.append({'scenario_id': source[0], 'module': source[3], 'title': source[6], 'category': source[8],
                 'priority': source[19], 'status': status, 'test_file': found['file'] if found else '',
                 'report': found['report'] if found else '', 'source': source[23],
                 'limit': 'Only the assertions in the linked test were executed.' if found else 'No current exact scenario execution evidence linked.'})
assert sum(row['status'] == 'needs-business-confirmation' for row in rows) == catalog['stats']['proposed']
out = root / 'docs/qa/tester-army-system'
out.mkdir(exist_ok=True)
with (out / 'scenario-execution.csv').open('w', encoding='utf-8-sig', newline='') as stream:
    writer = csv.DictWriter(stream, fieldnames=list(rows[0]))
    writer.writeheader()
    writer.writerows(rows)
result = {'discovery_scenarios': len(rows), 'scenario_statuses': dict(Counter(row['status'] for row in rows)), 'runs': runs}
(out / 'execution-evidence.json').write_text(json.dumps(result, indent=2, ensure_ascii=False) + '\n', encoding='utf-8')
print(json.dumps({'scenario_statuses': result['scenario_statuses'], 'runs': [{k: v for k, v in r.items() if k != 'tests'} for r in runs]}, indent=2))
