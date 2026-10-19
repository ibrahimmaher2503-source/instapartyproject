<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Models;

use App\Modules\Identity\Domain\Enums\DocumentStatus;
use App\Modules\Identity\Domain\Enums\DocumentType;
use App\Modules\Shared\Domain\Concerns\HasPublicId;
use Database\Factories\VendorDocumentFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property string $public_id
 * @property int $vendor_profile_id
 * @property DocumentType|string $doc_type
 * @property-read string $doc_type_key
 * @property string $file_path
 * @property string $file_name
 * @property DocumentStatus $status
 * @property Carbon|null $reviewed_at
 * @property int|null $reviewed_by
 * @property array<string, string>|null $review_notes
 * @property Carbon|null $expires_at
 * @property bool $is_critical
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class VendorDocument extends Model
{
    /** @use HasFactory<VendorDocumentFactory> */
    use HasFactory, HasPublicId, HasTranslations;

    /** @var array<int, string> */
    public $translatable = ['review_notes'];

    protected $fillable = [
        'public_id',
        'vendor_profile_id',
        'doc_type',
        'file_path',
        'file_name',
        'status',
        'reviewed_at',
        'reviewed_by',
        'review_notes',
        'expires_at',
        'is_critical',
        'last_reminder_sent_at',
    ];

    /** Never serialize private storage paths into Filament/Livewire payloads. */
    protected $hidden = [
        'file_path',
    ];

    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class);
    }

    protected static function newFactory(): VendorDocumentFactory
    {
        return VendorDocumentFactory::new();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'reviewed_at' => 'datetime',
            'expires_at' => 'date',
            'is_critical' => 'bool',
            'last_reminder_sent_at' => 'date',
        ];
    }

    /** @return Attribute<DocumentType|string, DocumentType|string> */
    protected function docType(): Attribute
    {
        return Attribute::make(
            get: fn (string $value): DocumentType|string => DocumentType::tryFrom($value) ?? $value,
            set: fn (DocumentType|string $value): string => $value instanceof DocumentType ? $value->value : $value,
        );
    }

    /** @return Attribute<string, never> */
    protected function docTypeKey(): Attribute
    {
        return Attribute::make(get: fn (): string => $this->doc_type instanceof DocumentType ? $this->doc_type->value : $this->doc_type);
    }

    public function scopeWithExpiry($query)
    {
        return $query->whereNotNull('expires_at');
    }

    public function scopeExpiringWithinDays($query, int $days)
    {
        $expiryDate = today()->addDays($days)->toDateString();

        return $query
            ->whereDate('expires_at', '>=', today()->toDateString())
            ->whereDate('expires_at', '<=', $expiryDate);
    }

    public function scopeLatestPerType($query)
    {
        return $query->whereIn('id', static::query()->selectRaw('MAX(id)')->groupBy('vendor_profile_id', 'doc_type'));
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now()->toDateString());
    }
}
