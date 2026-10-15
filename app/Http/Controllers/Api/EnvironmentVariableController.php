<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EnvironmentVariable;
use App\Services\EnvironmentVariable\EnvironmentVariableService;

class EnvironmentVariableController extends Controller
{
    protected EnvironmentVariableService $service;

    public function __construct(EnvironmentVariableService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $variables = $this->service->getAll()->map(function ($env) {
            if ($env->type === 'secret') {
                $env->value = null;
            }
            return $env;
        });
        return response()->json($variables);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'key' => 'required|string|max:255|unique:environment_variables',
            'value' => 'required|string',
            'type' => 'required|string|in:secret,config',
            'environments' => 'nullable|string',
            'note' => 'nullable|string'
        ]);

        $env = $this->service->create($validated, auth()->id());
        $env->load('user');

        if ($env->type === 'secret') {
            $env->value = null;
        }

        return response()->json($env);
    }

    public function show(EnvironmentVariable $environmentVariable)
    {
        if ($environmentVariable->type === 'secret') {
            $environmentVariable->value = null;
        }
        return response()->json($environmentVariable);
    }

    public function update(Request $request, EnvironmentVariable $environmentVariable)
    {
        $validated = $request->validate([
            'key' => 'required|string|max:255|unique:environment_variables,key,' . $environmentVariable->id,
            'value' => 'nullable|string',
            'type' => 'required|string|in:secret,config',
            'environments' => 'nullable|string',
            'note' => 'nullable|string',
        ]);

        // If secret and value is empty/not provided, preserve existing value
        if ($environmentVariable->type === 'secret' && ($validated['value'] === null || $validated['value'] === '')) {
            unset($validated['value']);
        } elseif (!isset($validated['value'])) {
            $validated['value'] = '';
        }

        $env = $this->service->update($environmentVariable, $validated);
        $env->load('user');

        if ($env->type === 'secret') {
            $env->value = null;
        }

        return response()->json($env);
    }

    public function destroy(EnvironmentVariable $environmentVariable)
    {
        $this->service->delete($environmentVariable);
        return response()->json(['success' => true]);
    }
}
