<?php

namespace Nreljed\Platform\Support;

/**
 * Immutable value object describing a module that a feature provider exposes
 * to the account "Data" area (backup/restore of the user's own data).
 *
 * The capability is purely declarative — the actual export/import behaviour
 * is implemented by the provider via exportDataBackup() / importDataBackup().
 * The account Data page aggregates every capability from every enabled
 * feature so users see exactly which modules support backup and which support
 * restore ("what can be backed up & restored").
 */
class DataBackupCapability
{
    /**
     * @param  bool  $supportsExport  Whether users can download this module's data.
     * @param  bool  $supportsRestore  Whether users can restore this module's data from a previous export.
     */
    public function __construct(
        protected readonly string $key,
        protected readonly string $label,
        protected readonly ?string $description = null,
        protected readonly bool $supportsExport = true,
        protected readonly bool $supportsRestore = true,
    ) {}

    public function getKey(): string
    {
        return $this->key;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function supportsExport(): bool
    {
        return $this->supportsExport;
    }

    public function supportsRestore(): bool
    {
        return $this->supportsRestore;
    }

    /**
     * The actions this module supports: 'export' and/or 'restore'.
     *
     * @return list<string>
     */
    public function getActions(): array
    {
        $actions = [];

        if ($this->supportsExport) {
            $actions[] = 'export';
        }

        if ($this->supportsRestore) {
            $actions[] = 'restore';
        }

        return $actions;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'description' => $this->description,
            'supports_export' => $this->supportsExport,
            'supports_restore' => $this->supportsRestore,
            'actions' => $this->getActions(),
        ];
    }
}
