import React, { useState, useEffect } from "react";
import { use_M_Store } from "@/Stores/0_M_Store";

export function Input_Group_JS({
    uf_name,
    initial_code = "",
    onClose,
    onSave,
}) {
    const [code, setCode] = useState(initial_code);
    const [initialCode] = useState(initial_code);

    const isDirty = code !== initialCode;

    const handleSave = async () => {
        if (!uf_name) return;
        try {
            if (typeof onSave === "function") {
                await onSave(uf_name, code);
            }
            if (typeof onClose === "function") {
                onClose();
            }
        } catch (error) {
            console.error("Failed to save JS formatter:", error);
        }
    };

    const handleCancel = () => {
        if (isDirty) {
            const confirmLeave = window.confirm(
                "You have unsaved changes. Do you want to close without saving?",
            );
            if (!confirmLeave) return;
        }
        if (typeof onClose === "function") {
            onClose();
        }
    };

    useEffect(() => {
        const handleKeyDown = (e) => {
            if (e.key === "Escape") {
                handleCancel();
            }
        };
        window.addEventListener("keydown", handleKeyDown);
        return () => window.removeEventListener("keydown", handleKeyDown);
    }, [isDirty, code]);

    if (!uf_name) return null;

    return (
        <div className="Input_Grup_JS-container">
            <div className="Input_Group_JS-header">
                {/* <span className="Input_Group_JS-title"> */}
                <label className="Input_Group_JS-label">JS Formatter :</label>
                <label className="Input_Group_JS-filename">{uf_name}.js</label>
                {/* </span> */}
                <button
                    className="Input_Group_JS-close-btn"
                    onClick={handleCancel}
                >
                    ❌
                </button>
            </div>

            <textarea
                className="Input_Grup_JS-textarea"
                value={code}
                onChange={(e) => setCode(e.target.value)}
                rows={25}
                placeholder="// Write your JavaScript code here..."
            />

            <div className="Input_Group_JS-footer">
                <button className="save-button" onClick={handleSave}>
                    💾 Save
                </button>
                <button className="cancel-button" onClick={handleCancel}>
                    ↩️ Cancel
                </button>
            </div>
        </div>
    );
}
