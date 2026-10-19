# GitHub #7 — approval checklist labels

Neutral EN/AR labels are now stored under `approval_eligibility.requirements`, and the approval queue uses them independently of the computed status. The existing failure-message keys remain unchanged for approval validation.

Verified real authorized detail routes for EN/AR and complete/incomplete identity: `GitHubIssue7ChecklistLabelsTest` passes four cases. Existing `VendorApprovalLifecycleTest` passes 8 tests / 34 assertions. Evidence: `.e2e/risk-program/remediation/github7-labels.log`, `github7-junit.xml`, and `github7.log`. Local only, no deployment proof.
