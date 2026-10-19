# GitHub #15/#16 — FAQ create/edit

FaqItemResource's RichEditor lacked its Filament import. A real authorized create route failed before the fix. With the import, both route/form regressions pass: creation persists English/Arabic questions and answers; editing one locale preserves the other. No schema, package, policy or translation behavior changed.

Evidence: tests/Feature/GitHubFaqAdminFormsTest.php, `.e2e/risk-program/remediation/github-faq-red.log`, `github-faq.log`, `github-faq-junit.xml`. Scoped Pint passes. Local only; no deployed-browser acceptance is claimed. Both issues are ready for the authorized GitHub closure.
