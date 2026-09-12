<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Nreljed\Platform\Support\FoundationCheck;
use Tests\TestCase;

class FoundationCheckTest extends TestCase
{
    public function test_foundation_check_is_clean_on_current_tree(): void
    {
        $result = new FoundationCheck(base_path())->run();

        $this->assertTrue($result['clean'], implode("\n", $result['issues']));
    }

    public function test_foundation_check_detects_drift_in_isolated_root(): void
    {
        $root = sys_get_temp_dir().'/foundation-check-'.uniqid();
        File::ensureDirectoryExists($root.'/vendor/nreljed/platform');

        $manifest = [
            'schema' => 1,
            'app_owned_files' => [
                'bootstrap/app.php' => ['marker' => 'core-managed'],
                'routes/web.php' => ['marker' => 'core-managed'],
            ],
            'forbidden_project_paths' => [
                'app/Http/Controllers/DeployController.php',
            ],
            'overridable_config' => [
                'config/core.php',
            ],
        ];
        File::put($root.'/vendor/nreljed/platform/core-manifest.json', json_encode($manifest));
        File::ensureDirectoryExists($root.'/bootstrap');
        File::put($root.'/bootstrap/app.php', "<?php\n// drift: core marker missing\n");
        File::ensureDirectoryExists($root.'/app/Http/Controllers');
        File::put($root.'/app/Http/Controllers/DeployController.php', "<?php\n");

        try {
            $result = new FoundationCheck($root)->run();

            $this->assertFalse($result['clean']);
            $this->assertContains('missing app-owned file: routes/web.php', $result['issues']);
            $this->assertContains('app-owned file drifted from core: bootstrap/app.php', $result['issues']);
            $this->assertContains('forbidden project path exists: app/Http/Controllers/DeployController.php (move into platform)', $result['issues']);
        } finally {
            File::deleteDirectory($root);
        }
    }
}
