<?php

namespace Nreljed\DashboardCore\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;
use Nreljed\DashboardCore\Http\Controllers\Controller;
use Nreljed\DashboardCore\Models\IpAccessRule;

class IpAccessRuleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('dashboard/admin/settings/ip-access', [
            'ipRules' => IpAccessRule::orderByDesc('is_active')->orderByDesc('created_at')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ip_address' => ['required', 'string', 'max:45'],
            'type' => ['required', 'in:allow,block'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $exists = IpAccessRule::where('ip_address', $request->ip_address)
            ->where('type', $request->type)
            ->exists();

        if ($exists) {
            return response()->json(['errors' => ['ip_address' => ['This IP rule already exists.']]], 422);
        }

        $rule = IpAccessRule::create($request->only('ip_address', 'type', 'description'));

        return response()->json([
            'success' => true,
            'message' => 'IP rule created.',
            'rule' => $rule,
        ]);
    }

    public function update(Request $request, IpAccessRule $ipAccessRule): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $ipAccessRule->update($request->only('description', 'is_active'));

        return response()->json([
            'success' => true,
            'message' => 'IP rule updated.',
            'rule' => $ipAccessRule,
        ]);
    }

    public function destroy(IpAccessRule $ipAccessRule): JsonResponse
    {
        $ipAccessRule->delete();

        return response()->json([
            'success' => true,
            'message' => 'IP rule deleted.',
        ]);
    }
}
