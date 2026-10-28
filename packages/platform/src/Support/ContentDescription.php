<?php

namespace Nreljed\Platform\Support;

/**
 * Immutable value object describing a content type exposed by a feature
 * provider. The shared content pipeline (CRUD, editor, upload, taxonomy,
 * SEO, notifications) consumes these descriptors so each feature gets
 * generic behaviour without post-type-specific hardcoding.
 *
 * Instantiate via the static `fromArray()` factory or the constructor.
 */
class ContentDescription
{
    public function __construct(
        protected readonly string $slug,
        protected readonly string $modelClass,
        protected readonly string $label,
        protected readonly array $features = [],
        protected readonly array $taxonomies = [],
        protected readonly ?array $mediaConfig = null,
        protected readonly ?array $seoMetaConfig = null,
        protected readonly string $capabilityBase = 'post',
        protected readonly array $registrationArgs = [],
    ) {}

    /**
     * Factory from a config array (e.g. from a provider's getContentDescription()).
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            slug: $config['slug'] ?? '',
            modelClass: $config['model_class'] ?? '',
            label: $config['label'] ?? '',
            features: $config['features'] ?? [],
            taxonomies: $config['taxonomies'] ?? [],
            mediaConfig: $config['media_config'] ?? null,
            seoMetaConfig: $config['seo_meta_config'] ?? null,
            capabilityBase: $config['capability_base'] ?? 'post',
            registrationArgs: $config['registration_args'] ?? [],
        );
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getModelClass(): string
    {
        return $this->modelClass;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * Feature flags this content type supports (e.g. 'seo', 'gallery',
     * 'excerpt', 'scheduled', 'revisions').
     *
     * @return list<string>
     */
    public function getFeatures(): array
    {
        return $this->features;
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features, true);
    }

    /**
     * Taxonomy declarations for the shared term manager.
     *
     * @return list<array{key: string, label: string, hierarchical?: bool, slug?: string}>
     */
    public function getTaxonomies(): array
    {
        return $this->taxonomies;
    }

    /**
     * Media upload configuration: upload profile name, subdirectory, etc.
     */
    public function getMediaConfig(): ?array
    {
        return $this->mediaConfig;
    }

    /**
     * SEO meta key configuration: prefix, individual keys, etc.
     */
    public function getSeoMetaConfig(): ?array
    {
        return $this->seoMetaConfig;
    }

    public function getCapabilityBase(): string
    {
        return $this->capabilityBase;
    }

    /**
     * Native registration arguments passed to register_post_type() or
     * equivalent.
     *
     * @return array<string, mixed>
     */
    public function getRegistrationArgs(): array
    {
        return $this->registrationArgs;
    }

    /**
     * Export as a plain array (useful for Inertia shared data or caching).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'model_class' => $this->modelClass,
            'label' => $this->label,
            'features' => $this->features,
            'taxonomies' => $this->taxonomies,
            'media_config' => $this->mediaConfig,
            'seo_meta_config' => $this->seoMetaConfig,
            'capability_base' => $this->capabilityBase,
            'registration_args' => $this->registrationArgs,
        ];
    }
}
