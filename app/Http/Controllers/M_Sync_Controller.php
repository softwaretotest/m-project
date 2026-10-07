<?php

namespace App\Http\Controllers;

use App\Constant\M_Sync_Service;
use App\Constant\TargetManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class M_Sync_Controller extends Controller
{
    /**
     * @param M_Sync_Service $sync_Service Runs scripts and returns run status/logs.
     */
    public function __construct(private M_Sync_Service $sync_Service)
    {
    }

    /**
     * Validate selected script identifiers and start a run when the target is available.
     *
     * @param Request $request Selected script IDs from the Sync UI.
     * @return JsonResponse e.g.
     * * {
     * * * "success": true,
     * * * "run": {
     * * * * "target": "ecommerce",
     * * * * "status": "starting",
     * * * * "scripts":
     * * * * [
     * * * * * "json_to_php"
     * * * * ],
     * * * * "script_statuses":
     * * * * {
     * * * * * "json_to_php": "pending"
     * * * * }
     * * * }
     * * }
     */
    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'selected_scripts' => 'nullable|array',
            'selected_scripts.*' => 'string',
        ]);

        $validated = $validated['selected_scripts'] ?? [];

        $result = $this->sync_Service->startRun($validated);

        return response()->json($result);
    }

    /**
     * Return the active target's current run and appended log text.
     *
     * @param Request $request Optional run_id and byte cursor query parameters.
     * @return JsonResponse e.g.
     * * {
     * * * "success": true,
     * * * "target": "ecommerce",
     * * * "status": "running",
     * * * "run": {
     * * * * "script_statuses":
     * * * * {
     * * * * * "json_to_php": "running"
     * * * * }
     * * * },
     * * * "logs": "[ 2026-10-01 12:00:00 ] [ SUCCESS ] ...",
     * * * "cursor": 94
     * * }
     */
    public function status(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cursor' => 'nullable|integer|min:0',
        ]);

        if (TargetManager::get_activeTarget() === '') {
            return response()->json([
                'success' => false,
                'message' => 'Select an active target before checking Sync status.',
            ], 422);
        }

        $cursor = (int) ($validated['cursor'] ?? 0);
        $status = $this->sync_Service->getCurrentStatus($cursor);

        if (($status['status'] ?? '') === 'not_found') {
            return response()->json([
                'success' => false,
                'message' => 'The requested Sync run is no longer available.',
            ], 404);
        }

        return response()->json($status);
    }

    /**
     * Clear a failed target run so Sync can start again.
     *
     * @return JsonResponse Reset confirmation or the run state that prevented reset.
     */
    public function resetFailedRun(): JsonResponse
    {
        if (TargetManager::get_activeTarget() === '') {
            return response()->json([
                'success' => false,
                'message' => 'Select an active target before resetting Sync status.',
            ], 422);
        }

        $result = $this->sync_Service->resetFailedRun();
        if (!$result['reset']) {
            return response()->json([
                'success' => false,
                'message' => $result['run'] === null
                    ? 'There is no failed Sync run to reset.'
                    : 'Only a failed run can be reset. Active or review-pending runs are preserved.',
                'run' => $result['run'],
            ], $result['run'] === null ? 404 : 409);
        }

        return response()->json([
            'success' => true,
            'status' => 'idle',
            'run' => null,
        ]);
    }

    /**
     * Start the selected generation scripts after the user confirms the Entities.json review.
     * @return JsonResponse e.g.
     * * {
     * * * "success": true,
     * * * "run": {
     * * * * "status": "starting",
     * * * * "script_statuses":
     * * * * {
     * * * * * "migration": "pending"
     * * * * }
     * * * }
     * * }
     */
    public function continueRun(): JsonResponse
    {
        $result = $this->sync_Service->continueRun();

        if (!($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Could not continue Sync run.  CLOSE and Check this   :   Maby no Entities.json found or empty eintities [] ',
            ], 422);
        }

        $current_Status = $this->sync_Service->getCurrentStatus();

        return response()->json([
            'success' => true,
            'message' => 'Sync run continued successfully.',
            'run' => $current_Status['run'] ?? null,
        ], 200);
    }
}
