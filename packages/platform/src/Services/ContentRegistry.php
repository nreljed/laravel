<?php

namespace Nreljed\Platform\Services;

use Nreljed\Platform\Support\ContentDescription;

/**
 * Collects ContentDescription value objects from all enabled feature
 * providers and exposes aggregate lookups for the shared content pipeline.
 */
class ContentRegistry
{
    /** @var array<string, ContentDescription> */
    protected array $descriptions = [];

    public function __construct(
        protected readonly FeatureRegistry $registry,
    ) {
        $this->build();
    }

    protected function build(): void
    {
        foreach ($this->registry->enabledProviders() as $provider) {
            $description = $provider->getContentDescription();

            if ($description instanceof ContentDescription && $description->getSlug() !== '') {
                $this->descriptions[$description->getSlug()] = $description;
            }
        }
    }

    /**
     * All content descriptions from enabled providers.
     *
     * @return array<string, ContentDescription>
     */
    public function all(): array
    {
        return $this->descriptions;
    }

    public function getBySlug(string $slug): ?ContentDescription
    {
        return $this->descriptions[$slug] ?? null;
    }

    /**
     * Look up a content description by its Eloquent model class.
     */
    public function getByModel(string $modelClass): ?ContentDescription
    {
        foreach ($this->descriptions as $description) {
            if ($description->getModelClass() === $modelClass) {
                return $description;
            }
        }

        return null;
    }

    /**
     * Whether the given feature flag is supported by any registered content
     * type.
     */
    public function anyHasFeature(string $feature): bool
    {
        foreach ($this->descriptions as $description) {
            if ($description->hasFeature($feature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * All taxonomies declared across every content type, keyed by taxonomy key.
     *
     * @return array<string, array{key: string, label: string, hierarchical?: bool, slug?: string, content_type?: string}>
     */
    public function allTaxonomies(): array
    {
        $taxonomies = [];

        foreach ($this->descriptions as $description) {
            foreach ($description->getTaxonomies() as $taxonomy) {
                $taxonomy['content_type'] = $description->getSlug();
                $taxonomies[$taxonomy['key']] = $taxonomy;
            }
        }

        return $taxonomies;
    }
}
