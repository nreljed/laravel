<?php

namespace Nreljed\Platform\Contracts\Capabilities;

/**
 * The feature contributes entries to the dashboard sidebar.
 *
 * Declare either an inline `groupLabel` on each item or a single group heading
 * via getNavGroupLabel(); the registry normalises the two so the sidebar always
 * renders one heading per module.
 */
interface HasNavigation
{
    /**
     * The heading the sidebar groups this feature's items under, or null when
     * every item carries its own `groupLabel`.
     */
    public function getNavGroupLabel(): ?string;

    /**
     * Sidebar entries. Each item is an array with `title`, `url`, an optional
     * `icon` (lucide name), an optional `permission` slug and, for a
     * collapsible entry, a nested `items` array. A missing icon degrades to the
     * registry default rather than rendering a blank row.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getNavItems(): array;
}
