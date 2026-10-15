@props(['code'])
<svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($code)
        @case('birthday')
            <path d="M5 27h22M7 25V15h18v10M7 19c3-4 5 4 9 0s5 4 9 0M10 14V8m6 6V6m6 8V8M9 5h2m4-2h2m4 2h2" />
            @break
        @case('graduation')
            <path d="m3 11 13-5 13 5-13 5-13-5Zm7 4v7c3 4 9 4 12 0v-7M5 13v11" />
            @break
        @case('wedding')
            <circle cx="12" cy="20" r="8" /><circle cx="21" cy="20" r="8" /><path d="m9 7 3-4 3 4-3 4-3-4Zm9 0 3-4 3 4-3 4-3-4Z" />
            @break
        @case('baby-shower')
            <path d="M5 14h22l-3 9H9l-4-9Zm0 0V6h8v8M4 6H2m25 8 3-5" /><circle cx="11" cy="27" r="2" /><circle cx="23" cy="27" r="2" />
            @break
        @case('engagement')
            <path d="M16 27 5 16C-1 9 8 2 13 9l3 3 3-3c5-7 14 0 8 7L16 27Z" /><path d="m9 10-2 3m16-3 2 3" />
            @break
        @case('family-gathering')
            <circle cx="16" cy="9" r="4"/><circle cx="6" cy="12" r="3"/><circle cx="26" cy="12" r="3"/><path d="M9 28v-6a7 7 0 0 1 14 0v6H9ZM6 19a5 5 0 0 0-5 5v3h5m20-8a5 5 0 0 1 5 5v3h-5"/>
            @break
        @case('corporate')
            <rect x="4" y="10" width="24" height="18" rx="2"/><path d="M11 10V5h10v5M4 17h24M10 15v5m12-5v5M13 5h6"/>
            @break
        @default
            <path d="m6 25 4-17 14 14-18 3ZM18 5l2-2m4 6h4m-4 6 3 2M9 5 7 2" />
    @endswitch
</svg>
