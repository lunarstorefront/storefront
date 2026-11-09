<?php

namespace Lunar\Storefront\Data;

use Illuminate\Support\Collection;
use Lunar\Core\Models\Attribute;

/**
 * The display name each attribute handle is shown under, per model type. Bound as a
 * scoped instance, so a list of models looks the names up once, and the container
 * starts a fresh one for each request, queued job or Octane request.
 */
class AttributeNames
{
    /** @var array<string, Collection<string, string>> */
    private array $names = [];

    /**
     * @return Collection<string, string>
     */
    public function for(string $morphClass): Collection
    {
        return $this->names[$morphClass] ??= Attribute::query()
            ->whereHas('models', fn ($query) => $query->where('model_type', $morphClass))
            ->pluck('name', 'handle');
    }
}
