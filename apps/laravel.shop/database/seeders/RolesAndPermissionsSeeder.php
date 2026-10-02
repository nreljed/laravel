<?php

namespace Database\Seeders;

use Nreljed\Platform\Database\Seeders\RolesAndPermissionsSeeder as BaseRolesAndPermissionsSeeder;
use Nreljed\Platform\Models\Role;

class RolesAndPermissionsSeeder extends BaseRolesAndPermissionsSeeder
{
    public function run(): void
    {
        $this->migrateLegacyCrmUserRole();

        parent::run();
    }

    /**
     * Migrate old crm_user role → customer (for existing installs).
     */
    protected function migrateLegacyCrmUserRole(): void
    {
        $oldCrmUser = Role::where('slug', 'crm_user')->first();

        if (! $oldCrmUser) {
            return;
        }

        $customerRole = Role::where('slug', 'customer')->first();

        if ($customerRole) {
            foreach ($oldCrmUser->users as $user) {
                if (! $user->hasRole('customer')) {
                    $user->roles()->attach($customerRole->id);
                }
            }

            $oldCrmUser->users()->detach();
        }

        $oldCrmUser->permissions()->detach();
        $oldCrmUser->delete();
    }
}
