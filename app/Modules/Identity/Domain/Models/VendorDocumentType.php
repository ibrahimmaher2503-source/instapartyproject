<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Models;

use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property string $key
 * @property array<string, string> $name
 * @property list<string> $required_for
 * @property bool $is_active
 */
class VendorDocumentType extends Model
{
    use HasPublicId, HasTranslations;

    protected $fillable = ['key', 'name', 'required_for', 'is_active'];

    /** @var array<int, string> */
    public $translatable = ['name'];

    protected function casts(): array
    {
        return ['required_for' => 'array', 'is_active' => 'bool'];
    }
}
