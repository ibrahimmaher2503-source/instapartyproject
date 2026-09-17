# InstaParty — agent entry point

Laravel 12 modular marketplace: rental, sale, digital; Arabic RTL + English LTR.
This checkout includes a Blade storefront, Filament admin/vendor panels, and Firebase chat functions.

## Start here

1. Read this file, then [code map](docs/ai/code-map.md) for the affected surface only.
2. Check `git status --short`; preserve unrelated edits. If Git is unavailable, report it and track only your changed files. Never reinitialize or repair Git implicitly.
3. Read the relevant task/spec if present; check [checkout gaps](docs/ai/checkout-gaps.md) before relying on old phase, tool, or coverage claims.
4. Trace route/panel → request/policy → action → contract/binding → persistence/event → response/view → tests. Use CodeGraph first for architecture, flow, callers and impact when available; verify current files before editing or claiming coverage.
5. Make the smallest verified change. Reuse existing helpers and patterns; do not add speculative abstractions or reformat unrelated code.

## Authority and missing context

- System/developer instructions and explicit user scope take precedence over repository guidance.
- Project requirements: PRD → constitution → this shared policy → technical/type/bilingual/schema/phase/package specs → accepted ADRs → feature spec/plan/tasks. Name conflicts with file evidence; do not silently amend product decisions.
- `CLAUDE.md` points here; it is not a competing constitution. [Engineering policy](docs/ai/engineering-policy.md) holds detailed constraints, read by topic before code changes.
- `docs/specs/`, constitution, ADRs and `specs/*/tasks.md` are absent in this checkout. Do not claim to have read them, invent requirements, or treat `.specify/` alone as installed spec-kit.
- Missing feature tasks: use `/speckit.tasks` only if actually available; otherwise identify the missing input for feature implementation. Scoped documentation, investigation and fixes can proceed from explicit user scope and code evidence; do not fabricate a completed phase.
- Existing code proves implementation, not approval or correctness. Missing specs do not authorize new packages, schema redesign, Phase 2 scope or stack replacements.

## Non-negotiable defaults

- Features belong to `app/Modules/{Name}`. Cross-module communication uses contracts/events, never direct Eloquent model imports. Bind implementations in providers.
- Controllers delegate to single-purpose Actions (`execute()`); models contain relationships/casts/scopes, not business logic. Mutations use transactions; domain events fire after commit.
- Use canonical `ProductType` and `match`; type-aware changes cover rental, sale and digital.
- Money uses Brick Money, integer minor units + currency, no floats or raw arithmetic. Preserve append-only ledger/history rules; read engineering policy §§6.4–6.7 before persistence edits.
- API URLs expose ULIDs, never internal IDs. Preserve ownership/role checks, idempotency and the `{ data, meta, errors }` envelope.
- Require non-empty EN + AR translations; keep RTL/LTR and UTC storage with Resource-layer timezone conversion.
- New API routes require Request field PHPDocs, Resource response examples, [API registry](.specify/memory/api-registry.md) and [Bruno](docs/api/collections/) in the same change. Update real task checkboxes immediately when completed.
- Dependencies remain locked. Unlisted packages require explicit approval and an update to the package spec. See engineering policy §§3–5 for stack and scope restrictions.
- Do not expand existing subscription/tax/dispute code based on its presence; resolve scope against approved requirements first.
- Preserve secrets, `.env`, user data and unrelated files. Do not dump credentials, runtime logs or database exports into agent context. No production changes, destructive migrations or service resets implied by a documentation task.

## Validation and delivery

Read [workflow](docs/ai/workflow.md) for verified command locations and limitations.
Run relevant checks; before any commit run Pint + PHPStan, and Pest `--bail` when `app/` changes. Do not bypass failed/unavailable gates or claim checks ran.
Keep conventional commits by layer with a why body and agent co-author trailer; never amend published commits or use `--no-verify`. Never push without explicit user authorization.
Report changes, exact validation and remaining blockers. Browser acceptance requires actual browser interaction; a static check is not UI proof.

## Keep context small

Read one relevant document section and module at a time. Prefer `rg --files` then scoped `rg -n -F`; expand only along actual dependencies.
Use direct `rg`/reads for exact edits, config/text/generated files, stale-index warnings and final verification. If CodeGraph is unavailable, unindexed or incomplete, fall back to the code map and scoped `rg` without blocking the task.
Exclude vendor, node_modules, runtime storage, generated assets, lockfiles and old QA dumps from general searches. Read them only for a specific dependency/runtime question.
Do not load `docs/ai/reference/` by default: it preserves the original instructions for decision archaeology only.
Update the code map when entry points move. Keep task progress in its real tasks file; do not paste session history into this file.

<!-- CODEGRAPH_START -->
## CodeGraph

In repositories indexed by CodeGraph (a `.codegraph/` directory exists at the repo root), reach for it BEFORE grep/find or reading files when you need to understand or locate code:

- **MCP tool** (when available): `codegraph_explore` answers most code questions in one call — the relevant symbols' verbatim source plus the call paths between them, including dynamic-dispatch hops grep can't follow. Name a file or symbol in the query to read its current line-numbered source. If it's listed but deferred, load it by name via tool search.
- **Shell** (always works): `codegraph explore "<symbol names or question>"` prints the same output.

If there is no `.codegraph/` directory, skip CodeGraph entirely — indexing is the user's decision.
<!-- CODEGRAPH_END -->
