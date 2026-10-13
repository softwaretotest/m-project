import { useState, useEffect } from "react";
import { createPortal } from "react-dom";

export function Example_Selector_App_Data({
    open_Example,
    set_open_Example,
    handleCopyData,
    tab,
}) {
    const [examples, setExamples] = useState([]);

    useEffect(() => {
        if (open_Example) {
            fetch(`/api/m-get-examples?tab=${tab}`)
                .then((res) => res.json())
                .then((data) => {
                    if (data.status === "success") {
                        setExamples(data.data);
                    }
                })
                .catch((err) => console.error("Failed to load examples:", err));
        }
    }, [open_Example]);

    if (!open_Example) return null;

    return createPortal(
        <div
            className="example-selector-backdrop"
            onClick={(e) => {
                e.stopPropagation();
                set_open_Example(false);
            }}
        >
            <div
                className="example-selector-modal"
                onClick={(e) => e.stopPropagation()}
            >
                <h3>Choose Example Data</h3>
                <div className="example-options-list">
                    {examples.length > 0 ? (
                        examples.map((item, index) => (
                            <div
                                key={index}
                                className="example-item"
                                onClick={() => {
                                    handleCopyData("app_data", item.filename);
                                    set_open_Example(false);
                                }}
                            >
                                <span className="example-name">
                                    {item.name}
                                </span>
                                <span
                                    className="example-desc"
                                    title={item.preview}
                                >
                                    {item.preview}
                                </span>
                            </div>
                        ))
                    ) : (
                        <div className="example-loading">
                            Loading examples...
                        </div>
                    )}
                </div>
            </div>
        </div>,
        document.body,
    );
}
