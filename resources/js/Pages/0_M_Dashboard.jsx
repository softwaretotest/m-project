//resources/js/Pages/0_M_Dashboard.jsx

import { useState, useEffect } from "react";

import { use_M_Data } from "@/Providers/0_M_DataProvider";
import { use_M_Store } from "@/Stores/0_M_Store.jsx";

import {
    add_field_ENTITIES,
    merge_M_value_F,
    merge_M_value_Entities,
} from "@/Services/0_M_value_Service";

import SubTab from "@/Components/0_M_SubTab.jsx";

import M_Sync from "@/Components/3_M_Sync.jsx";

import { save_Target_App } from "@/Pages/3_M_TargetSelector";

import { Example_Selector_App_Data } from "@/Components/3_M_Example_Selector";
import { validate_imported_json } from "@/Components/3_M_value_Validator";

const TABS = [
    { id: "m_data", label: "M_DATA" },
    { id: "app_data", label: "APP_DATA" },
    { id: "entities", label: "ENTITIES" },
];

/**
 * * count item in object/array
 * * do not count key beginning with "_" e.g. _comment
 * @returns 0 if no data sent in here
 */
function count_Items(obj) {
    if (Array.isArray(obj)) return obj.length;
    if (!obj || typeof obj !== "object") return 0;
    return Object.keys(obj).filter((key) => !key.startsWith("_")).length;
}

/**
 * * get item of each Tab
 * * null = not show number (do not count m_data)
 */
function get_Tab_Counts(data) {
    return {
        m_data: null,
        app_data: count_Items(data?.app_data?.f),
        entities: count_Items(data?.entities?.entities),
    };
}

export default function M_Dashboard({ activeTarget }) {
    const data = use_M_Data();

    // ---- all Hooks must be always early return ----
    const [error_Dashboard, set_error_Dashboard] = useState("");

    const [open_Sync_Service, set_open_Sync_Service] = useState(false);

    const [open_Example, set_open_Example] = useState(false);

    const active_Target_App = use_M_Store((state) => state.active_Target_App);
    const activeTab = use_M_Store((state) => state.activeTab);
    const setActiveTab = use_M_Store((state) => state.setActiveTab);
    const selected_F_S = use_M_Store((state) => state.selected_F_S);

    useEffect(() => {
        if (activeTarget && !active_Target_App) {
            use_M_Store.getState().set_active_Target_App(activeTarget);
        }
    }, [activeTarget, active_Target_App]);

    // ---- Early return after Hooks ----
    if (!data)
        return <div>Dashboard Loading... waiting for data from Backend</div>;

    const tabCounts = get_Tab_Counts(data);
    const show_add_USERS = !selected_F_S?.["USERS"] && activeTab === "entities";

    function handle_Tab_Click(tabId) {
        const store = use_M_Store.getState();
        setActiveTab(tabId);
        store.setActiveField(null); // clear activeField on tab changed
        store.set_Error_FIELDNAME("");
        store.set_FIELDNAME_to_add("");
    }

    /**
     * * we need to reset before show error
     * * to show the blink-red effect
     * * Timeout : reset 50 and show 100 ,
     * * because React re-render immediatly
     * * so we need delay to show error again
     * @param {*} error_text
     */
    function show_error_Dashboard(error_text, stop = true) {
        setTimeout(() => {
            set_error_Dashboard("");
        }, 50);
        setTimeout(() => {
            set_error_Dashboard(error_text);
        }, 100);
        if (stop) {
            throw new Error(error_text);
        }
    }

    /**
     * * DESCRIPTON : Handle example data copying workflow with frontend duplication validation.
     * * xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
     * * get example json from backend for validate
     * * tell backend to copy json , but validate duplication first
     * * tell DataProvider to reload JSON
     * * force SubTab to restart to re-render Component immediatly
     * @param {String} tab - The target type identifier ('app_data' or 'entities').
     * @return {Promise<void>}
     */
    async function handleCopyData(tab, filename) {
        const fetch_response = await fetch(
            `/api/m-get-example-json?type=${tab}&filename=${filename}`,
        );
        if (!fetch_response.ok) {
            show_error_Dashboard(
                "Failed to fetch example JSON data ! Maybe wrong Route-Config ?",
            );
        }

        const imported_json_data = await fetch_response.json();
        const validation_result = validate_imported_json(imported_json_data);

        console.log("validation_result = ", validation_result);

        if (validation_result.has_error) {
            show_error_Dashboard(
                `VALIDATION ERROR ON COPY - '${tab}' <br />` +
                    `duplicate field ___ or ___ M_value_KEY <> fielname_UPPERCASE <br />` +
                    validation_result.error_message,
                /* stop */ false, //if stop = false , UI show error but continues codes below
            );
        }

        set_error_Dashboard("");

        if (imported_json_data.f) {
            merge_M_value_F(validation_result.new_M_value_f);
        }

        if (imported_json_data.entities) {
            // const M_value_Entities_imported = imported_json_data.entities;
            // merge_M_value_Entities(M_value_Entities_imported);
            console.log(
                "JKFDÖASJFDLKÖ --- DASHBOARD -handleCopyData validation_result.new_M_value_entities = ",
                validation_result.new_M_value_entities,
            );
            merge_M_value_Entities(validation_result.new_M_value_entities);
        }

        // change tab and subTab after import success
        const subTab = tab === "app_data" ? "f" : "entities";
        use_M_Store.getState().setActiveTab(tab);
        use_M_Store.getState().setActiveSubTab(subTab);
        use_M_Store.getState().setActiveField(null);
    }

    const [mode, set_mode] = useState(false);

    useEffect(() => {
        fetchModeFromBackend();
    }, []);

    function fetchModeFromBackend() {
        fetch("/api/target/get-target-mode")
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (data && data.success) {
                    set_mode(data.mode);
                }
            })
            .catch(function (error) {
                console.error("Error fetching target mode:", error);
            });
    }

    function switch_mode() {
        if (false === mode || mode === "" || !mode) {
            set_mode("DEV");
            saveModeToBackend("DEV");
            return;
        }

        if ("DEV" === mode) {
            var isConfirmed = window.confirm(
                "You cannot change back to DEV again in UI. Are you sure to set mode to PROD ?",
            );

            if (!isConfirmed) {
                return;
            }

            set_mode("PROD");
            saveModeToBackend("PROD");
            return;
        }

        if ("PROD" === mode) {
            return;
        }
    }

    function saveModeToBackend(targetMode) {
        fetch("/api/target/save-target-mode", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
            },
            body: JSON.stringify({ mode: targetMode }),
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                console.log("Mode saved successfully:", data);
            })
            .catch(function (error) {
                console.error("Error saving mode:", error);
            });
    }

    return (
        <>
            {error_Dashboard && (
                <div
                    className="error-text"
                    dangerouslySetInnerHTML={{ __html: error_Dashboard }} // <br /> to show newline
                />
            )}

            {open_Sync_Service && (
                <M_Sync
                    open_Sync_Service={open_Sync_Service}
                    set_open_Sync_Service={set_open_Sync_Service}
                />
            )}

            {open_Example && (
                <Example_Selector_App_Data
                    open_Example={open_Example}
                    set_open_Example={set_open_Example}
                    handleCopyData={handleCopyData}
                    tab={use_M_Store.getState().activeTab}
                />
            )}

            <div className="dashboard-wrapper">
                <div className="header">
                    <button
                        className="header-target"
                        onClick={() => save_Target_App("", set_error_Dashboard)}
                    >
                        {active_Target_App} Dashboard
                        <br />
                        <span className="button-info">click to change App</span>
                    </button>

                    {show_add_USERS && (
                        <button
                            className="header-button"
                            onClick={() => add_field_ENTITIES({ isUser: true })}
                        >
                            ADD USERS TABLE
                        </button>
                    )}

                    <div className="btn-copy-container">
                        <button
                            className="btn-copy-app btn-setting"
                            onClick={() => {
                                use_M_Store.getState().setActiveTab("app_data");
                                set_open_Example(true);
                            }}
                        >
                            copy
                            <br />
                            <span>APP_Data</span>
                        </button>
                        <button
                            className="btn-copy-entities btn-setting"
                            onClick={() => {
                                use_M_Store.getState().setActiveTab("entities");
                                set_open_Example(true);
                            }}
                        >
                            copy
                            <br />
                            <span>Entities</span>
                        </button>
                    </div>

                    <div className="header-title">
                        <span>Project M</span>
                        <button
                            className={`btn-switch-mode 
                                ${
                                    false === mode || mode === "PROD"
                                        ? "blink-red"
                                        : "bg-green"
                                }`}
                            onClick={() => switch_mode()}
                        >
                            {mode === false ? "DEV / PROD" : mode}
                            <br />
                            <span>
                                {mode === "PROD"
                                    ? "LOCKED (PROD)"
                                    : "click to switch"}
                            </span>
                        </button>
                    </div>

                    <button
                        className="btn-setting"
                        onClick={() => set_open_Sync_Service(true)}
                    >
                        SYNC
                    </button>
                </div>

                <div className="tab-switcher-container">
                    {TABS.map((tab) => {
                        const count = tabCounts[tab.id];
                        const isActive = activeTab === tab.id;
                        const isEmpty = count === 0;

                        return (
                            <button
                                key={tab.id}
                                onClick={() => handle_Tab_Click(tab.id)}
                                className={`nav-button ${isActive ? "active" : ""} ${isEmpty ? "blink-red" : ""}`}
                            >
                                {tab.label}
                                {count !== null && (
                                    <span className="tab-count">{count}</span>
                                )}
                            </button>
                        );
                    })}
                </div>

                <div className="dashboard-main-box">
                    {activeTab === "m_data" && <SubTab data={data.m_data} />}
                    {activeTab === "app_data" && (
                        <SubTab data={data.app_data} />
                    )}
                    {activeTab === "entities" && (
                        <SubTab data={data.entities} />
                    )}
                </div>
            </div>
        </>
    );
}
