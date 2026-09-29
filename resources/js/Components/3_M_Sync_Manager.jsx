import "./3_M_Sync_Manager.css";

/**
 * Renders the synchronization manager modal dialog with a backdrop.
 * Prevents interaction with the background elements while active.
 *
 * @param {Object} props - Component properties.
 * @param {boolean} props.is_Modal_Open - Flag indicating whether the modal is visible.
 * @param {Function} props.on_Close_Modal - Callback function triggered to close the modal.
 * @returns {JSX.Element|null} The modal component markup or null if closed.
 */
// export default function M_Sync_Manager({ is_Modal_Open, on_Close_Modal }) {
export default function M_Sync_Manager({
    is_Sync_Modal_Open,
    set_is_Sync_Modal_Open,
}) {
    if (!is_Sync_Modal_Open) {
        return null;
    }
    return (
        <>
            <div
                className="m-sync-backdrop"
                onClick={() => set_is_Sync_Modal_Open(false)}
            >
                <div
                    className="m-sync-modal-container"
                    onClick={(event) => event.stopPropagation()}
                >
                    {/* Left Side: Control Button & Logger Console */}
                    <div className="m-sync-left-section">
                        <div className="m-sync-control-box">
                            <button className="m-sync-start-btn">
                                Start / Running
                            </button>
                            <div className="m-sync-logger-console">
                                [ 2026-09-29 12:00:15 ] [ SUCCESS ] Initializing
                                sync manager...
                            </div>
                        </div>
                    </div>

                    {/* Right Side: Checkboxes and Status */}
                    <div className="m-sync-right-section">
                        <h3 className="m-sync-sidebar-title">choose script</h3>
                        <label className="m-sync-script-item pass">
                            <input type="checkbox" defaultChecked />
                            <span>JSON to PHP</span>
                            <span className="m-sync-status pass">
                                Status = PASS
                            </span>
                        </label>
                        <label className="m-sync-script-item running">
                            <input type="checkbox" defaultChecked />
                            <span>PHP to JSON</span>
                            <span className="m-sync-status running">
                                Status = RUNNING
                            </span>
                        </label>
                        <label className="m-sync-script-item">
                            <input type="checkbox" />
                            <span>Migration</span>
                        </label>
                        <label className="m-sync-script-item">
                            <input type="checkbox" />
                            <span>DTOs, Models, Controllers</span>
                        </label>
                    </div>
                </div>
                <button className="m-sync-close-btn">❌</button>
            </div>
        </>
    );
}
