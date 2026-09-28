//resources/js/Pages/0_M_Dashboard.jsx

import { useEffect } from "react";

import { use_M_Data } from "@/Providers/0_M_DataProvider";
import { use_M_Store } from "@/Stores/0_M_Store.jsx";

import { add_field_ENTITIES } from "@/Services/0_M_value_Service";

import SubTab from "@/Components/0_M_SubTab.jsx";

export default function M_Dashboard({ activeTarget }) {
    const data = use_M_Data();
    if (!data)
        return <div>Dashboard Loading... waiting for data from Backend</div>;

    const active_Target_App = use_M_Store((state) => state.active_Target_App);
    const set_active_Target_App = use_M_Store.getState().set_active_Target_App;

    useEffect(() => {
        if (activeTarget && !active_Target_App) {
            set_active_Target_App(activeTarget);
        }
    }, [activeTarget, active_Target_App]);

    const activeTab = use_M_Store((state) => state.activeTab);
    const setActiveTab = use_M_Store((state) => state.setActiveTab);
    const setActiveField = use_M_Store.getState().setActiveField;
    const set_Error_FIELDNAME = use_M_Store.getState().set_Error_FIELDNAME;
    const set_FIELDNAME_to_add = use_M_Store.getState().set_FIELDNAME_to_add;
    const selected_F_S = use_M_Store((state) => state.selected_F_S);
    const show_add_USERS = !selected_F_S["USERS"] && activeTab === "entities";

    const tabs = [
        { id: "m_data", label: "M_DATA", key: "m_data" },
        { id: "app_data", label: "APP_DATA", key: "app_data" },
        { id: "entities", label: "ENTITIES", key: "entities" },
    ];

    return (
        <>
            <div className="dashboard-wrapper">
                <h1 className="dashboard-header">
                    <button className="dashboard-header-button">
                        {active_Target_App} Dashboard
                    </button>
                    {show_add_USERS && (
                        <button
                            className="dashboard-header-button"
                            onClick={() => {
                                add_field_ENTITIES({ isUser: true });
                            }}
                        >
                            ADD USERS TABLE
                        </button>
                    )}
                    <p className="dashboard-header-title">Project M</p>
                </h1>
                <div className="tab-switcher-container">
                    {tabs.map((tab) => (
                        <button
                            key={tab.id}
                            onClick={() => {
                                setActiveTab(tab.id);
                                //clear activeField on subTab changed
                                setActiveField(null);
                                set_Error_FIELDNAME("");
                                set_FIELDNAME_to_add("");
                            }}
                            className={`nav-button ${activeTab === tab.id ? "active" : ""}`}
                        >
                            {tab.label}
                        </button>
                    ))}
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
