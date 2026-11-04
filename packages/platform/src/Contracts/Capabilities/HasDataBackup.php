<?php

namespace Nreljed\Platform\Contracts\Capabilities;

use Nreljed\Platform\Models\User;
use Nreljed\Platform\Support\DataBackupCapability;

/**
 * The feature lets users export and restore their own data from the account
 * "Data" area.
 *
 * A data-owning feature is the exception, not the rule: implement this only
 * when you can scope every read and write strictly to the authenticated user.
 */
interface HasDataBackup
{
    /**
     * The backup capabilities this feature exposes. Each capability declares
     * one module the user can back up and/or restore, e.g.
     * `new DataBackupCapability('posts', 'Posts, categories and tags')`.
     *
     * Keys must be globally unique across enabled features.
     *
     * @return array<int, DataBackupCapability>
     */
    public function getDataBackupCapabilities(): array;

    /**
     * Build the user-owned data payload for the given capability key, ready to
     * be serialized into a downloadable backup file.
     *
     * Implementations MUST scope strictly to the authenticated user's own
     * records so a backup only ever contains that user's data.
     *
     * @return array<string, mixed> data payload (usually a list of records)
     */
    public function exportDataBackup(string $key, User $user): array;

    /**
     * Replace this user's data for the given capability key with the contents
     * of a previously exported backup file. The feature decides what "replace"
     * means for its records (e.g. restore owned rows, delete owned rows absent
     * from the file). Shared/non-owned resources must not be deleted.
     *
     * @return int number of records restored/imported
     */
    public function importDataBackup(string $key, User $user, array $data): int;

    /**
     * Count of the user's own records in the given capability's module, shown
     * on the account Data page ("N records").
     */
    public function dataBackupSummary(string $key, User $user): int;
}
