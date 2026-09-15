<?php

namespace Nreljed\BookingModule\Properties\Services;

use Illuminate\Support\Facades\Cache;
use Nreljed\BookingModule\Properties\Models\Property;

class PropertyUserCountManager
{
    public function getUserCount(int $user_id): int
    {
        return Cache::remember("user_prop_count_{$user_id}", 3600, function () use ($user_id) {
            return $this->queryCount($user_id);
        });
    }

    public function updateCount(int $user_id): void
    {
        $count = $this->queryCount($user_id);
        Cache::put("user_prop_count_{$user_id}", $count, 3600);
    }

    public function clearCache(int $user_id): void
    {
        Cache::forget("user_prop_count_{$user_id}");
    }

    public function rebuildCache(): array
    {
        $results = $this->queryAllUserCounts();
        $counts = [];

        foreach ($results as $row) {
            $user_id = (int) $row['user_id'];
            $count = (int) $row['property_count'];

            $counts[$user_id] = $count;
            Cache::put("user_prop_count_{$user_id}", $count, 3600);
        }

        return $counts;
    }

    private function queryCount(int $user_id): int
    {
        return Property::query()
            ->where('user_id', $user_id)
            ->where('status', '!=', 'trash')
            ->count();
    }

    private function queryAllUserCounts(): array
    {
        return Property::query()
            ->where('status', '!=', 'trash')
            ->selectRaw('user_id, COUNT(*) as property_count')
            ->groupBy('user_id')
            ->get()
            ->toArray();
    }
}
