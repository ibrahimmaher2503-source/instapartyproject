"""Preserve source-defined records without treating remediation claims as current proof."""
import json
import re
from pathlib import Path
from collections import Counter

ROOT = Path(__file__).resolve().parents[2]
OUT = Path(__file__).resolve().parent
catalog = json.loads((ROOT / 'docs/qa-discovery/catalog_data.json').read_text(encoding='utf-8'))
scenarios = next(s[2] for s in catalog['sheetDefs'] if s[0].startswith('05 '))
records = {}

def add(id_, path, line, title, body, kind):
    if id_ not in records:
        records[id_] = dict(id=id_, source=path.as_posix(), line=line, title=title, body=body, kind=kind)

def sections(path, pattern, kind):
    lines = (ROOT / path).read_text(encoding='utf-8').splitlines()
    starts = [(i, re.match(pattern, line)) for i, line in enumerate(lines) if re.match(pattern, line)]
    for j, (i, match) in enumerate(starts):
        end = starts[j + 1][0] if j + 1 < len(starts) else len(lines)
        block = '\n'.join(lines[i + 1:end])
        if '\n## ' in block:
            block = block.split('\n## ', 1)[0]
        add(match.group(1), path, i + 1, lines[i].lstrip('# ').strip(), block, kind)

sections(Path('docs/qa/instaparty-issues-2026-09-12.md'), r'^((?:BUG|UX|WEB|VEN|FR|BLOCK)-[A-Z0-9-]*\d+)\s+—', 'original QA record')
sections(Path('docs/qa/vendor-to-settlement-e2e-audit-2026-09-12.md'), r'^### (VTSE-(?:D\d+|\d+))\s+—', 'forensic finding / decision')
path = Path('docs/qa/instaparty-developer-handoff-backlog-2026-10-04.md')
for n, line in enumerate((ROOT / path).read_text().splitlines(), 1):
    match = re.match(r'^- \[.\] \*\*([A-Z]+(?:-[A-Z]+)*-\d+) — (.*?)\*\*\s*(.*)', line)
    if match:
        add(match[1], path, n, match[2], match[3], 'handoff change / reported defect / decision')
path = Path('docs/qa/admin-audit-closure-2026-09-10.md')
for n, line in enumerate((ROOT / path).read_text().splitlines(), 1):
    match = re.match(r'^\| (IP-QA-\d+) \| (.*?) \| (.*?) \|', line)
    if match:
        add(match[1], path, n, match[3], 'Historical closure: ' + match[2], 'closure-only finding; original incorrect behavior unavailable')
for path in sorted(Path('docs/qa/public-ui-ux-audit').glob('*.md')):
    active = False
    ordinal = 0
    for n, line in enumerate((ROOT / path).read_text().splitlines(), 1):
        if line == '## Closed defects':
            active = True
        elif active and line.startswith('- '):
            ordinal += 1
            add(f'LOCAL-UI-{path.stem}-{ordinal:02}', path, n, line[2:], 'Historical closed-defect bullet; no original bug ID or full reproduction supplied.', 'local locator ID, not an original bug ID')
sections(Path('docs/qa/tester-army-system/confirmed-findings.md'), r'^## (TA-SYS-\d+)\s+—', 'confirmed in prior campaign')
path = Path('docs/qa/client-seven-requirements-2026-10-12.md')
sections(path, r'^### (\d{2})\s+—', 'client requirement / presentation change')
for key in list(records):
    if key.isdigit():
        value = records.pop(key)
        value['id'] = 'LOCAL-CLIENT-' + key
        records[value['id']] = value

all_reports = sorted(Path('docs/qa').rglob('*.md'))
id_pattern = re.compile(r'\b(?:BUG-[A-Z0-9-]*\d+|UX-[A-Z0-9-]*\d+|VTSE-(?:D\d+|\d{3})|IP-QA-\d{3}|(?:FR|WEB|VEN)-\d+|TA-SYS-\d+)\b')
for path in all_reports:
    # Include referenced-only IDs rather than silently lose an orphan record.
    for n, line in enumerate((ROOT / path).read_text().splitlines(), 1):
        for id_ in id_pattern.findall(line):
            if id_ not in records:
                add(id_, path, n, 'Referenced-only identifier', line, 'original defect description unavailable')

module_words = {
    'Booking': ['booking', 'حجز', 'تفاوض', 'modification', 'تعديل الحجز'],
    'Payments': ['payment', 'paymob', 'refund', 'webhook', 'دفع', 'استرداد'],
    'Settlement': ['settlement', 'withdrawal', 'ledger', 'wallet', 'سحب', 'تسوية', 'مالي'],
    'Identity': ['vendor profile', 'registration', 'onboarding', 'bank', 'iban', 'مورد', 'بنكي', 'تسجيل'],
    'Catalog': ['service', 'category', 'inventory', 'import', 'خدمة', 'خدمات', 'مخزون', 'استيراد'],
    'Discovery': ['search', 'browse', 'wizard', 'wishlist', 'بحث', 'معالج', 'اكتشاف'],
    'Communication': ['chat', 'notification', 'inbox', 'محادثة', 'إشعار', 'وارد'],
    'TrustSafety': ['report', 'بلاغ', 'moderation', 'إشراف'],
    'Support': ['support', 'دعم'], 'Subscriptions': ['subscription', 'اشتراك'],
    'Loyalty': ['loyalty', 'ولاء'], 'Shared': ['cms', 'translation', 'تعريب', 'ترجمة', 'activity', 'نشاط'],
}

def clean(text):
    text = re.sub(r'<[^>]+>', '', text)
    return re.sub(r'\s+', ' ', text).strip()

def observed(r):
    m = re.search(r'المشكلة/نتيجة الفحص:\s*(.*?)(?:\n\n|$)', r['body'], re.S)
    if m:
        return clean(m[1])
    return clean(r['body'].split('**Required regression:**')[0]) or 'Not supplied in original source.'

def expected(r):
    m = re.search(r'\*\*Required regression:\*\*\s*(.*?)(?:\n\n|$)', r['body'], re.S)
    if m:
        return clean(m[1])
    m = re.search(r'<summary>.*?</summary>\s*(.*?)\s*</details>', r['body'], re.S)
    if m:
        return clean(m[1])
    if r['kind'].startswith(('handoff', 'client', 'local locator')):
        return r['title'] + ' (reported change target; not independent business approval)'
    if r['id'].startswith('TA-SYS-001'):
        return 'Published displayable service: wishlist is_available=true; correct service ID and persisted saved item.'
    if r['id'].startswith('TA-SYS-002'):
        return 'Unsupported/missing report target: client error, never an unhandled 500; no report persisted.'
    return 'Full intended business oracle is not specified here; recover original report/approved requirement before execution.'

lines = ['# Source-bound defect inventory and independent reproduction designs', '',
         'This appendix lists source-defined tracking records, not independent currently persisting bugs. Do not merge aliases without evidence. IDs beginning LOCAL are stable benchmark source locators for records without an original ID. Closed historical reports are not current execution proof. Entries outside the five-case pilot are NOT EXECUTED.', '',
         'The 145 discovery gaps are a separate static ambiguity/concurrency/question backlog, explicitly not a bug register. Source: [Discovery_Gaps](../../docs/qa-discovery/Discovery_Gaps.md). Forensic VTSE originals and current discovery finance/booking outputs are included as planning evidence, not runtime acceptance.', '',
         'For non-pilot records, source omissions are recorded explicitly. A source-bound design is not a claim that exact selectors, column values, or every business clause have been resolved. Such records cannot be executed until these gaps are resolved.', '']
pilot = {'TA-SYS-001': 'B01', 'TA-SYS-002': 'B02', 'VTSE-001': 'B03', 'VTSE-007': 'B04', 'BUG-VENDOR-BANK-DATA-001': 'B05'}
summary = Counter()
for r in records.values():
    corpus = (r['title'] + ' ' + r['body']).lower()
    modules = [m for m, words in module_words.items() if any(w in corpus for w in words)]
    roles = [role for role, words in {'admin': ['admin', 'أدمن', 'مشرف', 'إدارة'], 'vendor': ['vendor', 'مورد'], 'customer': ['customer', 'عميل'], 'system/gateway': ['webhook', 'queue', 'scheduler']}.items() if any(w in corpus for w in words)]
    refs = sorted(set(re.findall(r'(?:app|tests|resources)/[A-Za-z0-9_./-]+\.(?:php|ts|js)', r['body'])))
    related = [s[0] for s in scenarios if any(ref in str(s[23]) for ref in refs)]
    summary[r['kind']] += 1
    source = f'../../{r["source"]}#L{r["line"]}'
    links = [p.as_posix() for p in all_reports if r['id'] in (ROOT / p).read_text() and p.as_posix() != r['source']]
    trigger = re.search(r'\*\*Trigger:\*\*\s*(.*?)(?:\n\n|$)', r['body'], re.S)
    trigger = clean(trigger[1]) if trigger else observed(r)
    lines += [f'## {r["id"]} — {r["title"]}', '',
              f'- Original report: [{r["source"]}:{r["line"]}]({source}); type: {r["kind"]}.',
              f'- Reproduction conditions reported: {trigger}',
              f'- Intended correct behavior / source oracle: {expected(r)}',
              f'- Previously observed incorrect behavior / closure text: {observed(r)}',
              f'- Impacted roles: unresolved until entry-route verification; wording-derived candidates: {", ".join(roles) or "Not enumerated by source"}; candidate modules: {", ".join(modules) or "Unresolved"}. Mentioning a vendor/customer does not establish that they are the executing actor.',
              '- Required fixture data: disposable records matching the reported trigger above, including its lifecycle, age, ownership, localization, and monetary values; fields omitted by the report remain unresolved. Do not substitute empty fixtures for populated workflow records.',
              f'- Related scenario IDs: {", ".join(related) or "No exact source-file link established; unresolved, not invented"}. Matching source references are candidate traceability only.',
              f'- Current persistence: {"See pilot " + pilot[r["id"]] + " in KNOWN_DEFECT_BENCHMARK.md; limited to tested branch" if r["id"] in pilot else "UNKNOWN — not executed in this pilot; historical remediation is not current proof"}.',
              '- Related later report references: ' + ('; '.join(f'[{p}](../../{p})' for p in links) or 'None found.'), '',
              'Independent reproduction design (source-bound, unexecuted unless selected in the pilot):', '',
              f'1. Preconditions: reproduce the reported trigger: {trigger}',
              '2. Initial database: isolated disposable database; establish the named records in the reported states and snapshot affected rows. Exact omitted columns/values must be recovered before running.',
              '3. User role: unresolved; recover the original executing actor and verify the entry route/policy rather than treating mentioned subjects as actors. Assert authorization with an allowed control before testing a denied operation.',
              '4. Browser actions: visit the original affected surface, select the specific fixture record, perform the reported operation through tester.army. Exact route/selector: ' + (', '.join(refs) if refs else 'not supplied by the source; must be resolved rather than guessed') + '.',
              '5. API requests: use the original request/operation if one is documented; preserve original actor, payload and retry/ordering. Do not bypass HTTP authorization by calling an Action and describe it as route proof.',
              f'6. Expected result: {expected(r)}',
              f'7. Incorrect result to detect: {observed(r)}',
              '8. Assertions: pin the intended outcome above; add exact ownership/status/amount/content assertions relevant to that record. A 200, visible heading or table alone cannot establish correctness.',
              '9. Persistence verification: compare the affected records against the snapshot; rejected mutations must change nothing, successful mutations must write the intended result exactly once. Read only the disposable database.',
              '10. Side effects: inspect applicable audit/events/notifications/inventory/ledger/jobs and cross-role visibility. The original source does not enumerate all side effects; unresolved expectations remain explicit gates, never fabricated passes.', '']

assert len([r for r in records.values() if r['kind'] == 'original QA record']) == 73
assert len([r for r in records.values() if r['id'].startswith('VTSE-')]) == 27
(OUT / 'DEFECT_INVENTORY_AND_PLANS.md').write_text('\n'.join(lines), encoding='utf-8')
(OUT / 'inventory-data.json').write_text(json.dumps({'counts': dict(summary), 'total_tracking_records': len(records), 'records': list(records.values())}, ensure_ascii=False, indent=2), encoding='utf-8')
print(json.dumps({'records': len(records), 'types': dict(summary), 'handoff_named_tickets': sum(r['kind'].startswith('handoff') for r in records.values())}, indent=2))
