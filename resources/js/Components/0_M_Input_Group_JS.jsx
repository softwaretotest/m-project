import { useState, useEffect } from "react";
import { createPortal } from "react-dom";

import Editor from "@monaco-editor/react";

export function Input_Group_JS({
    uf_name,
    initial_code = "",
    onClose,
    onSave,
}) {
    const [code, setCode] = useState(initial_code);
    const [initialCode, setInitialCode] = useState(initial_code);
    const isDirty = code !== initialCode;

    useEffect(() => {
        if (!uf_name) return;
        fetch(`/api/uf-js/${uf_name}`)
            .then((res) => res.json())
            .then((data) => {
                if (data.ok && typeof data.code === "string") {
                    setCode(data.code);
                    setInitialCode(data.code);
                }
            })
            .catch((err) => console.error("Failed to load JS:", err));
    }, [uf_name]);

    const handleEditorChange = (value) => {
        setCode(value || "");
    };

    const handleSave = async () => {
        if (!uf_name) return;
        if (typeof onSave === "function") {
            const ok = await onSave(uf_name, code);
            if (ok === false) return;
        }
        if (typeof onClose === "function") {
            onClose();
        }
    };

    const handleCancel = () => {
        if (isDirty) {
            const confirmLeave = window.confirm(
                "You have unsaved change. Do you want to quit without change ?",
            );
            if (!confirmLeave) return;
        }
        if (typeof onClose === "function") {
            onClose();
        }
    };

    const handleEditorMount = (editor, monaco) => {
        editor.focus();
    };

    if (!uf_name) return null;

    return createPortal(
        //make React Modal backdrop
        <div className="Input_Group_JS-backdrop" onClick={handleCancel}>
            <div
                // stopPropagation = do not let event goes to parent element
                onClick={(e) => e.stopPropagation()} // get click event on backdrop (to close Modal)
                onKeyDown={(e) => e.stopPropagation()} // fix push Enter in JS Editor
            >
                <div className="Input_Group_JS-container">
                    <div className="Input_Group_JS-header">
                        <span className="Input_Group_JS-label">
                            JS Formatter :
                        </span>
                        <span className="Input_Group_JS-filename">
                            {uf_name}.js
                        </span>
                        <button
                            className="Input_Group_JS-close-btn"
                            onClick={handleCancel}
                        >
                            ❌
                        </button>
                    </div>

                    <div className="Input_Group_JS-editor-wrapper">
                        {!code && (
                            <div className="Input_Group_JS-editor">
                                // Write your JavaScript code here...
                            </div>
                        )}
                        <Editor
                            height="100%"
                            defaultLanguage="javascript"
                            theme="vs-dark"
                            value={code}
                            onChange={handleEditorChange}
                            onMount={handleEditorMount}
                            options={{
                                minimap: { enabled: true }, // left map colum of small codes like in vscode
                                fontSize: 14,
                                lineHeight: 22,
                                scrollBeyondLastLine: false, // prevent not to scroll over last line
                                automaticLayout: true, // reponsive resize of screen
                                wordWrap: "on",
                                mouseWheelZoom: true,
                                cursorBlinking: "smooth",
                                scrollbar: {
                                    verticalScrollbarSize: 10,
                                    horizontalScrollbarSize: 10,
                                    useShadows: true,
                                },
                            }}
                        />
                    </div>
                </div>
                <div className="Input_Group_JS-footer">
                    <button className="save-button" onClick={handleSave}>
                        💾 Save
                    </button>
                    <button className="cancel-button" onClick={handleCancel}>
                        ↩️ Cancel
                    </button>
                </div>
            </div>
        </div>,
        document.body,
    );
}
