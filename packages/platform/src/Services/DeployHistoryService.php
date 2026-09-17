<?php

namespace Nreljed\Platform\Services;

use Illuminate\Support\Collection;
use Nreljed\Platform\Models\DeployRelease;

class DeployHistoryService
{
    public function all(): Collection
    {
        return DeployRelease::query()
            ->orderByDesc('id')
            ->get()
            ->map(fn (DeployRelease $release) => [
                'id' => $release->id,
                'tag' => $release->tag,
                'status' => $release->status,
                'error' => $release->error,
                'deployed_by' => $release->deployed_by,
                'deployed_at' => $release->deployed_at?->toISOString(),
            ])
            ->values();
    }
}
