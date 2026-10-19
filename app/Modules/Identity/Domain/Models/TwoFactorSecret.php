<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $user_id
 * @property string $secret_encrypted
 * @property array<int, string> $recovery_codes_encrypted
 * @property \Illuminate\Support\Carbon|null $confirmed_at
 * @property int|null $last_used_timestep
 */
final class TwoFactorSecret extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['secret_encrypted', 'recovery_codes_encrypted'];

    protected function casts(): array
    {
        return [
            'secret_encrypted' => 'encrypted',
            'recovery_codes_encrypted' => 'encrypted:array',
            'confirmed_at' => 'datetime',
            'last_used_timestep' => 'integer',
        ];
    }
}
