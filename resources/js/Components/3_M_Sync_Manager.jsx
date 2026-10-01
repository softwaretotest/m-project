import { useCallback, useEffect, useRef, useState } from "react";
import "./3_M_Sync_Manager.css";

const script_Options = [
    { id: "json_to_php", label: "JSON to PHP" },
    { id: "php_to_json", label: "PHP to JSON" },
    { id: "migration", label: "Migration" },
    { id: "generators", label: "DTOs, Models, Controllers" },
];

const sync_Manager_Api_Path = "/api/sync-manager";
const POLLING_INTERVAL_MS = 1000;
const RUN_STATUS = {
    STARTING: "starting",
    RUNNING: "running",
    AWAITING_REVIEW: "awaiting_review",
    COMPLETED: "completed",
    FAILED: "failed",
    BREAK_BY_USER: "break_by_user",
    CONNECTION_ERROR: "connection_error",
};
const active_Run_Statuses = [RUN_STATUS.STARTING, RUN_STATUS.RUNNING];
const run_Status_Labels = {
    idle: "READY",
    [RUN_STATUS.STARTING]: "STARTING",
    [RUN_STATUS.RUNNING]: "RUNNING",
    [RUN_STATUS.AWAITING_REVIEW]: "REVIEW ENTITIES.JSON",
    [RUN_STATUS.COMPLETED]: "FINISH",
    [RUN_STATUS.FAILED]: "ERROR",
    [RUN_STATUS.BREAK_BY_USER]: "BREAK BY USER",
    [RUN_STATUS.CONNECTION_ERROR]: "BACKEND STATUS UNAVAILABLE",
};
const script_Status_Classes = {
    completed: "pass",
    running: "running",
    warning: "warning",
    failed: "error",
};

/**
 * Map a persisted script result to its visual status class.
 *
 * @param {string|undefined} script_Status Status value stored by the backend.
 * @returns {string} CSS class for the script result, or an empty string while pending.
 */
function get_Script_Status_Class(script_Status) {
    return script_Status_Classes[script_Status] || "";
}

/**
 * Renders the synchronization manager modal with script selection, run controls, and live logs.
 *
 * @param {Object} props Component properties.
 * @param {boolean} props.is_Sync_Modal_Open Whether the modal is visible.
 * @param {(isOpen: boolean) => void} props.set_is_Sync_Modal_Open Closes or opens the modal.
 * @returns {JSX.Element|null} The modal markup or null while closed.
 */
export default function M_Sync_Manager({
    is_Sync_Modal_Open,
    set_is_Sync_Modal_Open,
}) {
    const [selected_Scripts, set_selected_Scripts] = useState([
        "json_to_php",
        "php_to_json",
    ]);
    const [run_ID, set_run_ID] = useState(null);
    const [run_Status, set_run_Status] = useState("idle");
    const [script_Statuses, set_script_Statuses] = useState({});
    const [run_Logs, set_run_Logs] = useState("");
    const [request_Error, set_request_Error] = useState("");
    const [is_Polling, set_is_Polling] = useState(false);
    const [is_Submitting, set_is_Submitting] = useState(false);

    const log_Cursor = useRef(0);
    const logger_Console = useRef(null);

    /**
     * Append newly received backend log text to the visible console.
     *
     * @param {string} new_Logs Complete log lines received since the previous poll.
     * @returns {void}
     */
    const append_Run_Logs = useCallback((new_Logs) => {
        if (new_Logs !== "") {
            set_run_Logs((previous_Logs) => previous_Logs + new_Logs);
        }
    }, []);

    /**
     * Restore the backend's current run when the modal opens.
     *
     * @returns {Promise<void>} Resolves after loading the active target's run state.
     */
    const load_Current_Run = useCallback(async () => {
        set_request_Error("");

        try {
            const response = await fetch(`${sync_Manager_Api_Path}/status`);
            const response_Data = await response.json();

            if (!response.ok) {
                throw new Error(response_Data.message || "Could not load Sync Manager status.");
            }

            if (!response_Data.run) {
                set_run_ID(null);
                set_run_Status("idle");
                return;
            }

            const current_Run = response_Data.run;
            set_run_ID(current_Run.run_id);
            set_run_Status(current_Run.status);
            set_selected_Scripts(current_Run.scripts || []);
            set_script_Statuses(current_Run.script_statuses || {});
            set_run_Logs(response_Data.logs || "");
            if (current_Run.status === RUN_STATUS.FAILED) {
                set_request_Error(current_Run.message || "The previous Sync Manager run failed.");
            }
            log_Cursor.current = response_Data.cursor || 0;

            if (active_Run_Statuses.includes(current_Run.status)) {
                set_is_Polling(true);
            }
        } catch (error) {
            set_request_Error(error.message || "Could not load Sync Manager status.");
        }
    }, []);

    useEffect(() => {
        if (is_Sync_Modal_Open) {
            load_Current_Run();
        }
    }, [is_Sync_Modal_Open, load_Current_Run]);

    useEffect(() => {
        if (!is_Polling || !run_ID) {
            return undefined;
        }

        let is_Effect_Active = true;
        let poll_Timer = null;
        let active_Request = null;

        /**
         * Fetch new log output and schedule the next poll while the backend run is active.
         *
         * @returns {Promise<void>} Resolves after one status request and optional reschedule.
         */
        const poll_Run_Status = async () => {
            active_Request = new AbortController();

            try {
                const query = new URLSearchParams({
                    run_id: run_ID,
                    cursor: String(log_Cursor.current),
                });
                const response = await fetch(
                    `${sync_Manager_Api_Path}/status?${query.toString()}`,
                    { signal: active_Request.signal },
                );
                const response_Data = await response.json();

                if (!response.ok) {
                    throw new Error(response_Data.message || "Could not poll Sync Manager status.");
                }
                if (!is_Effect_Active) {
                    return;
                }

                append_Run_Logs(response_Data.logs || "");
                log_Cursor.current = response_Data.cursor || log_Cursor.current;

                const current_Run = response_Data.run;
                if (current_Run) {
                    set_run_Status(current_Run.status);
                    set_script_Statuses(current_Run.script_statuses || {});
                }

                if (active_Run_Statuses.includes(response_Data.status)) {
                    poll_Timer = window.setTimeout(
                        poll_Run_Status,
                        POLLING_INTERVAL_MS,
                    );
                } else {
                    set_is_Polling(false);
                }
            } catch (error) {
                if (!is_Effect_Active || error.name === "AbortError") {
                    return;
                }

                set_is_Polling(false);
                set_run_Status(RUN_STATUS.CONNECTION_ERROR);
                set_request_Error(error.message || "Sync Manager polling failed.");
            }
        };

        poll_Run_Status();

        return () => {
            is_Effect_Active = false;
            window.clearTimeout(poll_Timer);
            active_Request?.abort();
        };
    }, [append_Run_Logs, is_Polling, run_ID]);

    useEffect(() => {
        const console_Element = logger_Console.current;
        if (console_Element) {
            console_Element.scrollTop = console_Element.scrollHeight;
        }
    }, [run_Logs]);

    /**
     * Start the selected scripts after asking the backend whether this target is available.
     *
     * @returns {Promise<void>} Resolves after the start request has been handled.
     */
    const start_Run = async () => {
        set_request_Error("");
        set_is_Submitting(true);
        set_is_Polling(false);
        set_run_ID(null);
        set_run_Logs("");
        set_script_Statuses({});
        log_Cursor.current = 0;

        try {
            const response = await fetch(`${sync_Manager_Api_Path}/start`, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ scripts: selected_Scripts }),
            });
            const response_Data = await response.json();

            if (response_Data.run) {
                set_run_ID(response_Data.run.run_id);
                set_run_Status(response_Data.run.status);
                set_script_Statuses(response_Data.run.script_statuses || {});
                set_selected_Scripts(response_Data.run.scripts || selected_Scripts);
            }

            if (response.status === 409) {
                set_request_Error(response_Data.message || "An earlier run blocks this target.");
                if (response_Data.run?.run_id) {
                    set_is_Polling(true);
                }
                return;
            }

            if (!response.ok) {
                throw new Error(response_Data.message || "Could not start Sync Manager.");
            }

            set_is_Polling(true);
        } catch (error) {
            set_run_Status("failed");
            set_request_Error(error.message || "Could not start Sync Manager.");
        } finally {
            set_is_Submitting(false);
        }
    };

    /**
     * Continue the current run after the user has reviewed the generated Entities.json.
     *
     * @returns {Promise<void>} Resolves after the continue request has been handled.
     */
    const continue_Run = async () => {
        if (!run_ID) {
            return;
        }

        set_request_Error("");
        set_is_Submitting(true);

        try {
            const response = await fetch(
                `${sync_Manager_Api_Path}/${encodeURIComponent(run_ID)}/continue`,
                { method: "POST" },
            );
            const response_Data = await response.json();

            if (response_Data.run) {
                set_run_Status(response_Data.run.status);
                set_script_Statuses(response_Data.run.script_statuses || {});
            }

            if (!response.ok) {
                if (active_Run_Statuses.includes(response_Data.run?.status)) {
                    set_is_Polling(true);
                }
                throw new Error(response_Data.message || "Could not continue Sync Manager.");
            }

            set_is_Polling(true);
        } catch (error) {
            set_request_Error(error.message || "Could not continue Sync Manager.");
        } finally {
            set_is_Submitting(false);
        }
    };

    /**
     * Stop the UI polling loop without sending a stop command to the backend worker.
     *
     * @returns {void}
     */
    const cancel_Polling = () => {
        set_is_Polling(false);
        set_run_Status(RUN_STATUS.BREAK_BY_USER);
    };

    /**
     * Clear a failed backend run record without deleting its saved log file.
     *
     * @returns {Promise<void>} Resolves after the reset request is handled.
     */
    const reset_Failed_Run = async () => {
        set_request_Error("");
        set_is_Submitting(true);

        try {
            const response = await fetch(`${sync_Manager_Api_Path}/reset`, {
                method: "POST",
            });
            const response_Data = await response.json();

            if (!response.ok) {
                throw new Error(response_Data.message || "Could not reset the failed run.");
            }

            set_run_ID(null);
            set_run_Status("idle");
            set_script_Statuses({});
            set_run_Logs("");
            log_Cursor.current = 0;
        } catch (error) {
            set_request_Error(error.message || "Could not reset the failed run.");
        } finally {
            set_is_Submitting(false);
        }
    };

    /**
     * Toggle one script selection while the backend is not executing a run.
     *
     * @param {string} script_ID Allowlisted script identifier.
     * @returns {void}
     */
    const toggle_Script = (script_ID) => {
        set_selected_Scripts((selected) =>
            selected.includes(script_ID)
                ? selected.filter((selected_ID) => selected_ID !== script_ID)
                : [...selected, script_ID],
        );
    };

    /**
     * Return a user-facing label for the overall backend or local polling state.
     *
     * @returns {string} Status label shown beside the run controls.
     */
    const get_Run_Status_Label = () => {
        return run_Status_Labels[run_Status] || run_Status.toUpperCase();
    };

    /**
     * Close the modal without stopping the backend run.
     *
     * @returns {void}
     */
    const close_Modal = () => {
        set_is_Sync_Modal_Open(false);
    };

    if (!is_Sync_Modal_Open) {
        return null;
    }

    const is_Run_Active = active_Run_Statuses.includes(run_Status);
    const is_Awaiting_Review = run_Status === RUN_STATUS.AWAITING_REVIEW;
    const is_Run_Failed = run_Status === RUN_STATUS.FAILED;
    const can_Reset_Failed_Run = is_Run_Failed && run_ID !== null;
    const are_Scripts_Disabled = is_Run_Active || is_Awaiting_Review || is_Submitting;

    return (
        <>
            <div className="m-sync-backdrop" onClick={close_Modal}>
                <div
                    className="m-sync-modal-container"
                    onClick={(event) => event.stopPropagation()}
                >
                    <div className="m-sync-left-section">
                        <div className="m-sync-control-box">
                            <div className="m-sync-run-controls">
                                {is_Awaiting_Review ? (
                                    <button
                                        className="m-sync-start-btn"
                                        onClick={continue_Run}
                                        disabled={is_Submitting}
                                    >
                                        Continue after review
                                    </button>
                                ) : (
                                    <button
                                        className="m-sync-start-btn"
                                        onClick={start_Run}
                                        disabled={are_Scripts_Disabled}
                                    >
                                        {is_Run_Active ? "Running" : "Start"}
                                        {is_Run_Active && (
                                            <span className="m-sync-spinner" aria-label="Running" />
                                        )}
                                    </button>
                                )}
                                {is_Run_Active && (
                                    <button
                                        className="m-sync-cancel-btn"
                                        onClick={cancel_Polling}
                                        aria-label="Cancel frontend polling"
                                        title="Stop polling only; backend script continues"
                                    >
                                        ■
                                    </button>
                                )}
                                {can_Reset_Failed_Run && (
                                    <button
                                        className="m-sync-reset-btn"
                                        onClick={reset_Failed_Run}
                                        disabled={is_Submitting}
                                        title="Clear failed status; keep the saved run log"
                                    >
                                        Reset
                                    </button>
                                )}
                                <span className={`m-sync-run-status ${run_Status}`}>
                                    {get_Run_Status_Label()}
                                </span>
                            </div>
                            {is_Awaiting_Review && (
                                <p className="m-sync-review-message">
                                    Review and order Entities.json in the target app, then continue.
                                </p>
                            )}
                            {request_Error && (
                                <div className="m-sync-error-message" role="alert">
                                    {request_Error}
                                </div>
                            )}
                            <pre
                                className="m-sync-logger-console"
                                ref={logger_Console}
                                role="log"
                                aria-live="polite"
                            >
                                {run_Logs || "Select scripts and press Start."}
                            </pre>
                        </div>
                    </div>

                    <div className="m-sync-right-section">
                        <h3 className="m-sync-sidebar-title">choose script</h3>
                        {script_Options.map((script) => {
                            const script_Status = script_Statuses[script.id];
                            const status_Class = get_Script_Status_Class(script_Status);

                            return (
                                <label
                                    key={script.id}
                                    className={`m-sync-script-item ${status_Class}`}
                                >
                                    <input
                                        type="checkbox"
                                        checked={selected_Scripts.includes(script.id)}
                                        onChange={() => toggle_Script(script.id)}
                                        disabled={are_Scripts_Disabled}
                                    />
                                    <span>{script.label}</span>
                                    {script_Status && (
                                        <span className={`m-sync-status ${status_Class}`}>
                                            Status = {script_Status === "completed"
                                                ? "PASS"
                                                : script_Status.toUpperCase()}
                                        </span>
                                    )}
                                </label>
                            );
                        })}
                    </div>
                    <button
                        className="m-sync-close-btn"
                        onClick={close_Modal}
                        aria-label="Close Sync Manager"
                    >
                        ❌
                    </button>
                </div>
            </div>
        </>
    );
}
