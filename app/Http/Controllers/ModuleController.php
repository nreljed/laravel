<?php

namespace App\Http\Controllers;

use App\Http\Requests\Module\ToggleModuleRequest;
use App\Models\Module;
use App\Services\DashboardService;
use App\Services\FormattingService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ModuleController extends Controller
{
    public function __construct(
        protected FormattingService $formattingService,
    ) {}

    public function toggle(Request $request, Module $module)
    {
        $module->update(['enabled' => ! $module->enabled]);

        SettingsService::clearCache();
        $this->formattingService->clearModuleConfigCache();
        app(DashboardService::class)->clearStatsCache();

        return redirect()->back()->with(
            'status',
            "Module '{$module->name}' ".($module->enabled ? 'enabled' : 'disabled').'.',
        );
    }

    public function reorder(ToggleModuleRequest $request)
    {
        if (! Schema::hasTable('modules')) {
            return redirect()->back()->with('status', 'Modules table not available.');
        }

        foreach ($request->modules as $item) {
            Module::where('id', $item['id'])->update(['order' => $item['order']]);
        }

        return redirect()->back()->with('status', 'Module order updated.');
    }
}
