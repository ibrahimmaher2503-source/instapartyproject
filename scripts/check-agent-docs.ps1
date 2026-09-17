$ErrorActionPreference = 'Stop'
$repoRoot = Split-Path $PSScriptRoot -Parent
$documents = @('AGENTS.md', 'CLAUDE.md', 'README.md') + @(
    Get-ChildItem (Join-Path $repoRoot 'docs/ai') -Filter '*.md' -File |
        ForEach-Object { 'docs/ai/' + $_.Name }
)
$failures = @()
$linkCount = 0

foreach ($document in $documents) {
    $documentPath = Join-Path $repoRoot $document
    $content = Get-Content -LiteralPath $documentPath -Raw
    foreach ($link in [regex]::Matches($content, '\[[^\]]+\]\(([^)]+)\)')) {
        $target = $link.Groups[1].Value
        if ($target -match '^(https?://|#)') { continue }
        # ponytail: simple local Markdown paths only; use a Markdown parser if titles/escaped parentheses are introduced.
        $target = ($target -split '#', 2)[0]
        $resolved = Join-Path (Split-Path $documentPath -Parent) $target
        $linkCount++
        if (-not (Test-Path -LiteralPath $resolved)) {
            $failures += "${document}: missing link target $target"
        }
    }
}

$map = Get-Content -LiteralPath (Join-Path $repoRoot 'docs/ai/code-map.md') -Raw
$modules = @(Get-ChildItem (Join-Path $repoRoot 'app/Modules') -Directory)
foreach ($module in $modules) {
    if (-not $map.Contains("[$($module.Name)](../../app/Modules/$($module.Name)/)")) {
        $failures += "code-map.md: missing module $($module.Name)"
    }
}

if ($failures.Count) {
    $failures | ForEach-Object { Write-Output "FAIL: $_" }
    exit 1
}
Write-Output "PASS: $($documents.Count) documents, $linkCount local links, $($modules.Count) modules. Historical references excluded; application behavior not tested."
