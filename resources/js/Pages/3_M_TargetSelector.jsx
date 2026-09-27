import React, { useState, useEffect } from "react";
import { Head, router } from "@inertiajs/react";

export default function M_TargetSelector() {
    const [base, setBase] = useState("");
    const [projects, setProjects] = useState([]);
    const [selected, setSelected] = useState("");
    const [error, setError] = useState("");
    const [busy, setBusy] = useState(false);

    const scan = async (customBase = "") => {
        setBusy(true);
        setError("");
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
                setError(data.message || "Scan failed");
            }
        } catch (err) {
            setError("Network error during scan");
        } finally {
            setBusy(false);
        }
    };

    useEffect(() => {
        scan();
    }, []);

    const save = async () => {
        if (!selected) return;
        setBusy(true);
        setError("");
        try {
            const res = await fetch("/api/target/save", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN":
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute("content") || "",
                },
                body: JSON.stringify({ path: selected }),
            });
            const data = await res.json();
            if (data.success) {
                router.visit("/dashboard");
            } else {
                setError(data.message || "Failed to save target");
                setBusy(false);
            }
        } catch (err) {
            setError("Network error during save");
            setBusy(false);
        }
    };

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

                {error && <div className="target-selector-error">{error}</div>}

                <div className="target-selector-grid">
                    {projects.map((p) => {
                        const isSelected = selected === p.root_path;
                        return (
                            <div
                                key={p.name}
                                onClick={() => setSelected(p.root_path)}
                                className={`target-selector-item ${isSelected ? "is-active" : ""}`}
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

                <div className="target-selector-action">
                    <button
                        onClick={save}
                        disabled={!selected || busy}
                        className="target-selector-btn target-selector-btn-confirm"
                    >
                        {busy ? "Processing..." : "Confirm this Target"}
                    </button>
                </div>
            </div>
        </div>
    );
}
