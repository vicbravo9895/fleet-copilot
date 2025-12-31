<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'samsara_id',
        'name',
        'parent_tag_id',
        'addresses',
        'assets',
        'drivers',
        'machines',
        'sensors',
        'vehicles',
        'external_ids',
        'data_hash',
    ];

    protected function casts(): array
    {
        return [
            'addresses' => 'array',
            'assets' => 'array',
            'drivers' => 'array',
            'machines' => 'array',
            'sensors' => 'array',
            'vehicles' => 'array',
            'external_ids' => 'array',
        ];
    }

    /**
     * Get the parent tag.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Tag::class, 'parent_tag_id', 'samsara_id');
    }

    /**
     * Get the child tags.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Tag::class, 'parent_tag_id', 'samsara_id');
    }

    /**
     * Generate a hash from the tag data for change detection.
     */
    public static function generateDataHash(array $data): string
    {
        // Sort the array to ensure consistent hashing
        ksort($data);
        return md5(json_encode($data));
    }

    /**
     * Check if the tag data has changed based on hash comparison.
     */
    public function hasDataChanged(array $newData): bool
    {
        $newHash = self::generateDataHash($newData);
        return $this->data_hash !== $newHash;
    }

    /**
     * Create or update a tag from Samsara API data.
     */
    public static function syncFromSamsara(array $samsaraData): self
    {
        $dataHash = self::generateDataHash($samsaraData);
        
        $tag = self::where('samsara_id', $samsaraData['id'])->first();
        
        // If tag exists and hash hasn't changed, skip update
        if ($tag && $tag->data_hash === $dataHash) {
            return $tag;
        }
        
        $mappedData = self::mapSamsaraData($samsaraData);
        $mappedData['data_hash'] = $dataHash;
        
        return self::updateOrCreate(
            ['samsara_id' => $samsaraData['id']],
            $mappedData
        );
    }

    /**
     * Map Samsara API data to model attributes.
     */
    protected static function mapSamsaraData(array $data): array
    {
        return [
            'samsara_id' => $data['id'] ?? null,
            'name' => $data['name'] ?? null,
            'parent_tag_id' => $data['parentTagId'] ?? null,
            'addresses' => $data['addresses'] ?? null,
            'assets' => $data['assets'] ?? null,
            'drivers' => $data['drivers'] ?? null,
            'machines' => $data['machines'] ?? null,
            'sensors' => $data['sensors'] ?? null,
            'vehicles' => $data['vehicles'] ?? null,
            'external_ids' => $data['externalIds'] ?? null,
        ];
    }

    /**
     * Get the count of associated vehicles.
     */
    public function getVehicleCountAttribute(): int
    {
        return is_array($this->vehicles) ? count($this->vehicles) : 0;
    }

    /**
     * Get the count of associated drivers.
     */
    public function getDriverCountAttribute(): int
    {
        return is_array($this->drivers) ? count($this->drivers) : 0;
    }

    /**
     * Get the count of associated assets.
     */
    public function getAssetCountAttribute(): int
    {
        return is_array($this->assets) ? count($this->assets) : 0;
    }

    /**
     * Check if this is a root tag (no parent).
     */
    public function isRoot(): bool
    {
        return empty($this->parent_tag_id);
    }

    /**
     * Get the full hierarchy path of this tag.
     */
    public function getHierarchyPath(): array
    {
        $path = [$this->name];
        $current = $this;
        
        while ($current->parent) {
            $current = $current->parent;
            array_unshift($path, $current->name);
        }
        
        return $path;
    }
}

