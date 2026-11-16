<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentCategory extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'color', 'retention_days', 'is_active', 'created_by'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function documents(): HasMany { return $this->hasMany(Document::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    /** Catégories proposées par défaut (créées si la base n'en contient pas). */
    public const DEFAULTS = [
        ['name' => 'Constitution et gouvernance', 'color' => 'gold', 'retention_days' => null],
        ['name' => 'Procès-verbaux', 'color' => 'navy', 'retention_days' => 3650],
        ['name' => 'Finances', 'color' => 'green', 'retention_days' => 3650],
        ['name' => 'Juridique', 'color' => 'purple', 'retention_days' => 3650],
        ['name' => 'Ressources membres', 'color' => 'blue', 'retention_days' => 1825],
        ['name' => 'Autres documents', 'color' => 'gray', 'retention_days' => 1825],
    ];

    public static function ensureDefaults(): void
    {
        if (static::query()->where('is_active', true)->exists()) {
            return;
        }
        foreach (self::DEFAULTS as $category) {
            static::query()->firstOrCreate(['slug' => \Illuminate\Support\Str::slug($category['name'])], [...$category, 'is_active' => true]);
        }
    }

    public static function activeOptions(): \Illuminate\Support\Collection
    {
        static::ensureDefaults();

        return static::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    /** Catégorie utilisée quand aucune n'est choisie : « Autres documents ». */
    public static function fallbackId(): int
    {
        return static::query()->firstOrCreate(
            ['slug' => 'autres-documents'],
            ['name' => 'Autres documents', 'color' => 'gray', 'retention_days' => 1825, 'is_active' => true],
        )->id;
    }
}
