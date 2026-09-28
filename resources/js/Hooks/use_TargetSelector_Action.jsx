import { useState } from "react";

export function use_TargetSelector_Action() {
    const [error, setError] = useState("");

    const handleSaveTarget = async (root_path) => {
        setError("");

        try {
            set_active_Target_App(root_path);
            const res = await fetch("/api/target/save", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN":
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute("content") || "",
                },
                body: JSON.stringify({ path: root_path }),
            });

            const data = await res.json();
            if (data.success) {
                window.location.href = "/dashboard";
            } else {
                setError(data.message || "Failed to save target");
                setBusy(false);
            }
        } catch (err) {
            setError("Network error during save");
            setBusy(false);
        }
    };

    const ErrorDisplay = () =>
        error ? <div className="target-selector-error">{error}</div> : null;

    return {
        handleSaveTarget,
        ErrorDisplay,
    };
}
