import React, { useState } from "react";
import { Head, router } from "@inertiajs/react";

export default function M_TargetSelector() {
    const [path, setPath] = useState("");
    const [error, setError] = useState("");
    const [busy, setBusy] = useState(false);

    // ฟังก์ชันเปิดหน้าต่างเลือกโฟลเดอร์ของระบบ (Native Directory Picker)
    const handleBrowse = async () => {
        try {
            if (!window.showDirectoryPicker) {
                alert(
                    "Your browser does not support directory picker. Please type the path manually.",
                );
                return;
            }
            const handle = await window.showDirectoryPicker();
            // หมายเหตุ: เบราว์เซอร์จะคืนค่าเป็นชื่อโฟลเดอร์หรือ Handle
            // แต่เนื่องจาก Security ของ Browser จะไม่ได้เปิดเผย Full Absolute Path ตรงๆ ทันที
            // เราจึงต้องใช้เทคนิคพิมพ์ต่อ หรือถ้าระบบรันบน Localhost บางเคสอาจจะได้ชื่อมา
            // เดี๋ยวเรามาดูเทคนิคเสริมข้างล่างครับ
            setPath(handle.name);
        } catch (err) {
            // User cancel or permission denied
            console.log("User cancelled folder selection");
        }
    };

    const save = async () => {
        if (!path.trim()) {
            setError("Please select or enter a target project path.");
            return;
        }

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
                body: JSON.stringify({ path: path.trim() }),
            });

            const data = await res.json();

            if (data.success) {
                router.visit("/dashboard");
            } else {
                setError(
                    data.message || "Failed to save target configuration.",
                );
                setBusy(false);
            }
        } catch (err) {
            setError("Network error during save process.");
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
                    Select your target Laravel project folder
                </p>

                <div className="target-selector-scan-box">
                    <input
                        type="text"
                        value={path}
                        onChange={(e) => setPath(e.target.value)}
                        className="target-selector-input"
                        placeholder="C:/Users/o/.vscode/react/ecommerce"
                    />
                    <button
                        onClick={handleBrowse}
                        type="button"
                        className="target-selector-btn target-selector-btn-scan"
                    >
                        📁 Browse...
                    </button>
                </div>

                {error && <div className="target-selector-error">{error}</div>}

                <div className="target-selector-action">
                    <button
                        onClick={save}
                        disabled={!path.trim() || busy}
                        className="target-selector-btn target-selector-btn-confirm"
                    >
                        {busy ? "Processing..." : "Confirm this Target"}
                    </button>
                </div>
            </div>
        </div>
    );
}
