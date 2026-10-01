<?php

namespace App\Http\Controllers;

use App\Constant\TargetManager;
use App\Services\SyncManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SyncManagerController extends Controller
{
    /**
     * @param SyncManagerService $sync_Manager_Service Manages runs, process state, and run logs.
     */
    public function __construct(private SyncManagerService $sync_Manager_Service)
    {
    }

    /**
     * Validate selected script identifiers and start a run when the target is available.
     *
     * @param Request $request Selected script IDs from the Sync Manager UI.
     * @return JsonResponse e.g.
     * * {
     * * * "success": true,
     * * * "run": {
     * * * * "run_id": "7a3f...",
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
            'scripts' => 'required|array|min:1',
            'scripts.*' => ['required', 'string', Rule::in(SyncManagerService::SCRIPT_IDS)],
        ]);

        if (TargetManager::get_activeTarget() === '') {
            return response()->json([
                'success' => false,
                'message' => 'Select an active target before starting Sync Manager.',
            ], 422);
        }

        $result = $this->sync_Manager_Service->startRun($validated['scripts']);

        if (!$result['accepted']) {
            $run_Status = $result['run']['status'] ?? '';
            $http_Status = $run_Status === 'failed' ? 500 : 409;

            return response()->json([
                'success' => false,
                'message' => $result['run']['message'] ?? $this->get_Blocking_Run_Message($result['run']),
                'run' => $result['run'],
            ], $http_Status);
        }

        return response()->json([
            'success' => true,
            'run' => $result['run'],
        ], 202);
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
     * * * * "run_id": "7a3f...",
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
            'run_id' => 'nullable|uuid',
            'cursor' => 'nullable|integer|min:0',
        ]);

        if (TargetManager::get_activeTarget() === '') {
            return response()->json([
                'success' => false,
                'message' => 'Select an active target before checking Sync Manager status.',
            ], 422);
        }

        $status = $this->sync_Manager_Service->getCurrentStatus(
            $validated['run_id'] ?? null,
            (int) ($validated['cursor'] ?? 0)
        );

        if ($status['status'] === 'not_found') {
            return response()->json([
                'success' => false,
                'message' => 'The requested Sync Manager run is no longer available.',
            ], 404);
        }

        return response()->json(['success' => true] + $status);
    }

    /**
     * Clear a failed target run so Sync Manager can start again, retaining its log file.
     *
     * @return JsonResponse Reset confirmation or the run state that prevented reset.
     */
    public function resetFailedRun(): JsonResponse
    {
        if (TargetManager::get_activeTarget() === '') {
            return response()->json([
                'success' => false,
                'message' => 'Select an active target before resetting Sync Manager status.',
            ], 422);
        }

        $result = $this->sync_Manager_Service->resetFailedRun();
        if (!$result['reset']) {
            return response()->json([
                'success' => false,
                'message' => $result['run'] === null
                    ? 'There is no failed Sync Manager run to reset.'
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
     *
     * @param string $run_ID Identifier of the run waiting for review.
     * @return JsonResponse e.g.
     * * {
     * * * "success": true,
     * * * "run": {
     * * * * "run_id": "7a3f...",
     * * * * "status": "starting",
     * * * * "script_statuses":
     * * * * {
     * * * * * "migration": "pending"
     * * * * }
     * * * }
     * * }
     */
    public function continueRun(string $run_ID): JsonResponse
    {
        if (TargetManager::get_activeTarget() === '') {
            return response()->json([
                'success' => false,
                'message' => 'Select an active target before continuing Sync Manager.',
            ], 422);
        }

        $result = $this->sync_Manager_Service->continueRun($run_ID);

        if (!$result['accepted']) {
            $status = $result['run']['status'] ?? 'unknown';
            $http_Status = match ($status) {
                'not_found' => 404,
                'failed' => 500,
                default => 409,
            };

            return response()->json([
                'success' => false,
                'message' => $result['run']['message'] ?? $this->get_Blocking_Run_Message($result['run']),
                'run' => $result['run'],
            ], $http_Status);
        }

        return response()->json([
            'success' => true,
            'run' => $result['run'],
        ], 202);
    }

    /**
     * Explain why a prior run prevents starting or continuing another run.
     *
     * @param array<string, mixed> $run_Record Existing persisted run state.
     * @return string User-readable reason.
     */
    private function get_Blocking_Run_Message(array $run_Record): string
    {
        if (($run_Record['status'] ?? '') === 'not_found') {
            return 'The requested Sync Manager run is no longer available.';
        }

        if (($run_Record['status'] ?? '') === 'awaiting_review') {
            return 'A previous run is waiting for the Entities.json review. Continue that run before starting another.';
        }

        return 'A previous Sync Manager run is still active for this target. Check its status and backend log before starting another.';
    }
}
