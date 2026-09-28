import { useState, useEffect } from "react";
import { Head } from "@inertiajs/react";
import { use_M_Store } from "@/Stores/0_M_Store";

export default function M_TargetSelector() {
    const [base, setBase] = useState("");
    const [projects, setProjects] = useState([]);

    const active_Target_App = use_M_Store((state) => state.active_Target_App);
    const set_active_Target_App = use_M_Store.getState().set_active_Target_App;

    const [error_TargetSelector, set_error_TargetSelector] = useState("");
    const [busy, setBusy] = useState(false);

    const scan = async (customBase = "") => {
        setBusy(true);
        set_error_TargetSelector("");
        try {
            const url = customBase
                ? `/api/target/scan?base=${encodeURIComponent(customBase)}`
                : "/api/target/scan";
            const res = await fetch(url);
            const data = await res.json();
            if (data.success) {
                setProjects(data.projects);
                setBase(data.base);
            } else {
                set_error_TargetSelector(data.message || "Scan failed");
            }
        } catch (err) {
            set_error_TargetSelector("Network error during scan");
        } finally {
            setBusy(false);
        }
    };

    useEffect(() => {
        scan();
    }, []);

    return (
        <div className="target-selector-container">
            <Head title="Target Application Manager" />

            <div className="target-selector-card">
                <h1 className="target-selector-title">
                    Target Application Manager
                </h1>
                <p className="target-selector-subtitle">
                    Choose a target laravel project to start M-Project
                </p>

                <div className="target-selector-path-info">
                    ⚠️ If the Laravel project is not listed, please move it to
                    the same directory level shown here: <br />
                    <strong>{base}</strong>
                </div>

                <div className="target-selector-scan-box">
                    <input
                        type="text"
                        value={base}
                        onChange={(e) => setBase(e.target.value)}
                        className="target-selector-input"
                        placeholder="Enter base path..."
                    />
                    <button
                        onClick={() => scan(base)}
                        disabled={busy}
                        className="target-selector-btn target-selector-btn-scan"
                    >
                        {busy ? "Scanning..." : "Scan"}
                    </button>
                </div>

                {error_TargetSelector && (
                    <div className="error-text">{error_TargetSelector}</div>
                )}

                <div className="target-selector-grid">
                    {projects.map((p) => {
                        // const isSelected = active_Target_App === p.root_path;
                        return (
                            <div
                                key={p.name}
                                onClick={() =>
                                    save(p.root_path, set_error_TargetSelector)
                                }
                                // className={`target-selector-item ${isSelected ? "is-active" : ""}`}
                                className="target-selector-btn target-selector-btn-confirm"
                            >
                                <div className="target-selector-name">
                                    {p.name}
                                </div>
                                <div className="target-selector-path">
                                    {p.root_path}
                                </div>
                            </div>
                        );
                    })}
                </div>

                {/* <div className="target-selector-action">
                    <button
                        onClick={save}
                        disabled={!active_Target_App || busy}
                        className="target-selector-btn target-selector-btn-confirm"
                    >
                        {busy ? "Processing..." : "Confirm this Target"}
                    </button>
                </div> */}
            </div>
        </div>
    );
}

export const save = async (root_path, setError) => {
    // ตัดบรรทัด if (!active_Target_App) ออกไปเลย เพราะเรามี root_path จากพารามิเตอร์อยู่แล้ว!
    // setBusy(true);
    setError("");

    try {
        // อัปเดตสเตทใน Store เพื่อให้ UI รู้ทันที
        use_M_Store.getState().set_active_Target_App(root_path);

        const res = await fetch("/api/target/save", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN":
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute("content") || "",
            },
            body: JSON.stringify({ path: root_path }), // ส่ง root_path เข้าไปตรงๆ ทันที
        });

        const data = await res.json();
        if (data.success) {
            window.location.href = "/dashboard";
        } else {
            setError(data.message || "Failed to save target");
            // setBusy(false);
        }
    } catch (err) {
        setError("Network error during save");
        // setBusy(false);
    }
};
