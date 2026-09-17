<?php

declare(strict_types=1);

use App\Modules\Identity\Application\Support\MaskBankData;
use App\Modules\Identity\Domain\Models\VendorProfile;

it('masks every vendor banking value before display or audit storage', function (): void {
    $attributes = [
        'bank_name' => 'Banque Misr',
        'bank_account_holder' => 'QA Eligible Vendor',
        'bank_iban' => 'EG380019000500000000263180002',
        'bank_swift_bic' => 'BMISEGCX',
        'bank_branch' => 'Cairo Main',
    ];

    $masked = MaskBankData::snapshot($attributes);

    expect($masked)
        ->toBe([
            'bank_name' => 'B***',
            'bank_account_holder' => 'Q***',
            'bank_iban' => 'EG38••••002',
            'bank_swift_bic' => 'B***',
            'bank_branch' => 'C***',
        ])
        ->and(implode('|', $masked))->not->toContain('Banque Misr')
        ->and(implode('|', $masked))->not->toContain('EG380019000500000000263180002');
});

it('hides vendor banking attributes from model serialization', function (): void {
    $profile = new VendorProfile([
        'bank_name' => 'Banque Misr',
        'bank_account_holder' => 'QA Eligible Vendor',
        'bank_iban' => 'EG380019000500000000263180002',
        'bank_swift_bic' => 'BMISEGCX',
        'bank_branch' => 'Cairo Main',
    ]);

    expect($profile->attributesToArray())->not->toHaveKeys(MaskBankData::FIELDS);
});
