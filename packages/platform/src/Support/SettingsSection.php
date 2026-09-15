<?php

namespace Nreljed\Platform\Support;

/**
 * Immutable value object describing a feature's settings section for the
 * unified admin settings page. Consumed by the settings page renderer so
 * features can contribute settings tabs without a dedicated route.
 */
class SettingsSection
{
    public function __construct(
        protected readonly string $tab,
        protected readonly string $label,
        protected readonly string $icon = 'Settings',
        protected readonly ?string $component = null,
        protected readonly ?string $submitUrl = null,
        protected readonly ?string $nonceKey = null,
        protected readonly array $defaults = [],
    ) {}

    public static function fromArray(array $config): self
    {
        return new self(
            tab: $config['tab'] ?? '',
            label: $config['label'] ?? '',
            icon: $config['icon'] ?? 'Settings',
            component: $config['component'] ?? null,
            submitUrl: $config['submit_url'] ?? null,
            nonceKey: $config['nonce_key'] ?? null,
            defaults: $config['defaults'] ?? [],
        );
    }

    public function getTab(): string
    {
        return $this->tab;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getComponent(): ?string
    {
        return $this->component;
    }

    public function getSubmitUrl(): ?string
    {
        return $this->submitUrl;
    }

    public function getNonceKey(): ?string
    {
        return $this->nonceKey;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaults(): array
    {
        return $this->defaults;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tab' => $this->tab,
            'label' => $this->label,
            'icon' => $this->icon,
            'component' => $this->component,
            'submit_url' => $this->submitUrl,
            'nonce_key' => $this->nonceKey,
            'defaults' => $this->defaults,
        ];
    }
}
