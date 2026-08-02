<?php

namespace Nreljed\DashboardCore\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nreljed\DashboardCore\Services\MediaService;

class MediaController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        $service = app(MediaService::class);
        $validated = $request->validate($service->getValidationRules());

        $result = $service->upload($validated['file'], 'media');

        return response()->json($result);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['path' => 'required|string']);

        app(MediaService::class)->delete($request->path);

        return response()->json(['success' => true]);
    }
}
