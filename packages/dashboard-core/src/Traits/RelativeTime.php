<?php

namespace Nreljed\DashboardCore\Traits;

trait RelativeTime
{
    protected static function relativeTime(\DateTimeInterface $datetime): string
    {
        $diff = time() - $datetime->getTimestamp();

        if ($diff < 60) {
            return 'just now';
        }
        if ($diff < 3600) {
            return floor($diff / 60).'m ago';
        }
        if ($diff < 86400) {
            return floor($diff / 3600).'h ago';
        }
        if ($diff < 604800) {
            return floor($diff / 86400).'d ago';
        }

        return $datetime->format('M j');
    }
}
