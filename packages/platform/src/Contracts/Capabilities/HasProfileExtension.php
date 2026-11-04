<?php

namespace Nreljed\Platform\Contracts\Capabilities;

/**
 * The feature extends the account profile page.
 */
interface HasProfileExtension
{
    /**
     * Additional profile fields this feature contributes to the shared profile
     * form.
     *
     * @return array<string, mixed>
     */
    public function getProfileFieldsConfig(): array;

    /**
     * Profile section keys this feature opts into on
     * `/dashboard/account/profile`.
     *
     * The core profile page renders a slot that resolves each returned key to a
     * core React section component. This lets features extend the account area
     * (e.g. `['address']` for shop billing/shipping) WITHOUT overriding the
     * whole profile page component.
     *
     * @return list<string>
     */
    public function getProfileSections(): array;
}
