<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Models;

use App\Modules\Communication\Domain\Enums\ProviderName;
use Illuminate\Database\Eloquent\Model;

final class CommunicationProviderSetting extends Model
{
    protected $table = 'communication_provider_settings';

    protected $fillable = [
        'provider',
        'enabled',
        'project_id',
        'credentials',
        'username',
        'password',
        'sender_id',
        'environment',
        'last_connection_status',
        'last_connection_error',
        'last_checked_at',
        'updated_by',
    ];

    protected $hidden = [
        'credentials',
        'password',
    ];

    protected function casts(): array
    {
        return [
            'provider' => ProviderName::class,
            'enabled' => 'boolean',
            'credentials' => 'encrypted:array',
            'password' => 'encrypted',
            'environment' => 'integer',
            'last_checked_at' => 'datetime',
        ];
    }
}
