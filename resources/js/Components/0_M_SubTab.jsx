// resources/js/Components/0_M_SubTab.jsx
import React, { useState, useEffect, useRef } from "react";
import { useScrollIntoView } from "@/hooks/useScrollIntoView";

import { use_M_Store } from "@/Stores/0_M_Store";

import Sidebar from "@/Components/0_M_Sidebar";
import TabContent from "@/Components/0_M_TabContent";
import JSON_Content from "@/Components/0_M_JSON_Content";

/**
 * @param {*} data
 * if SubTab = APP DATA , then data = content of 1_App-Data.json
 * ,e.g. * f:: = Field
 *
 * if SubTab = M DATA , then data = content of 1_M-Data.json
 * ,e.g. * Tabs of Classes S CD D U CU CUD
 *
 * if SubTab = ENTITIES , then data = content of 1_Entities.json
 * ,e.g. * t:: = Tablename
 */
export default function SubTab({ data }) {
    /**
     * ONLY 1 state to focus 3 Components with same click
     */
    const set_M_value = use_M_Store((state) => state.set_M_value);

    const activeField = use_M_Store((state) => state.activeField);
    const setActiveField = use_M_Store.getState().setActiveField;
    const set_Error_FIELDNAME = use_M_Store.getState().set_Error_FIELDNAME;

    /**
     * SET SubTab
     */
    const activeSubTab = use_M_Store((state) => state.activeSubTab);
    const setActiveSubTab = use_M_Store.getState().setActiveSubTab;
    const set_FIELDNAME_to_add = use_M_Store.getState().set_FIELDNAME_to_add;

    const scrollRefs = useRef({});
    useScrollIntoView(activeField, scrollRefs);

    /**
     * first M_value on render/refresh
     */
    const activeTab = use_M_Store.getState().activeTab;

    /**
     * 1. subTab set_M_value , when user klick to change SubTab
     * 2. SET default Subtab , if undefined
     * 3. SET default Subtab , if wrong Subtab
     * 4. determin if can_render_TabContent
     */
    const [can_render_TabContent, set_can_render_TabContent] = useState(false);
    useEffect(() => {
        if (!activeTab) return; //this was set in M_Store on refresh

        const Default_SubTab = get_Default_SubTab(activeTab);

        // 2. SET default Subtab , if undefined
        if (!activeSubTab) {
            setActiveSubTab(Default_SubTab);
            if (data[Default_SubTab]) {
                set_M_value(data[Default_SubTab]);
            }
            set_can_render_TabContent(false);
            return;
        }

        /**
         * * 3. SET default Subtab , if wrong Subtab
         * * Object.keys(data) = e.g.
         * * for app_data : f , t
         * * for m_data : d , u , cd , cu , cud , s
         * * for entities : entities
         * *
         * * this failure can happen e.g. on Tab Change
         * * if activeTab and activeSubTab correct
         * * activeTab : app_data
         * * activeSubTab from store: d  (wrong , must be f for app_data)
         */
        if (activeSubTab && !Object.keys(data).includes(activeSubTab)) {
            setActiveSubTab(Default_SubTab);
            if (data[Default_SubTab]) {
                set_M_value(data[Default_SubTab]);
            }
            set_can_render_TabContent(false);
            return;
        }

        /**
         * * 1. subTab set_M_value, sync data when activeSubTab changes
         * * and activeSubTab exists in data
         */
        if (data[activeSubTab]) {
            set_M_value(data[activeSubTab]);
        }

        /**
         * * if activeTab and activeSubTab correct) e.g.
         * * activeTab : m_data
         * * activeSubTab from store: d
         */
        set_can_render_TabContent(true);
        set_FIELDNAME_to_add("");
    }, [activeSubTab, data]); // Reason for Dependency : user klicks tab , data changes

    /**
     * * call when user klicks change tab , to set default subTab
     * @returns default subTab
     */
    function get_Default_SubTab() {
        if (activeTab === "entities") {
            return "entities";
        }

        if (activeTab === "app_data") {
            return "f";
        }

        if (activeTab === "m_data") {
            return "d";
        }
    }

    return (
        <>
            {/* Tab        SubTab */}
            {/* M-DATA     S CD D U UF CU CUD */}
            {/* APP-DATA   F T */}
            {/* ENTITIES   ENTITIES*/}
            <div className="subtab-container">
                {Object.keys(data)
                    .filter(
                        (subtab) =>
                            typeof data[subtab] === "object" &&
                            data[subtab] !== null &&
                            /**
                             * * don't need to show SubTab 't' of app_data
                             * * Because, redandant to SubTab entities
                             */
                            !(
                                activeTab === "app_data" &&
                                subtab.toLowerCase() === "t"
                            ),
                    )
                    .map((subtab) => (
                        <button
                            key={subtab}
                            className={`subtab-button ${activeSubTab === subtab ? "active" : ""}`}
                            onClick={() => {
                                setActiveSubTab(subtab);
                                if (data[subtab]) {
                                    set_M_value(data[subtab]);
                                }
                                //clear activeField on subTab changed
                                setActiveField(null);
                                scrollRefs.current = {};
                                set_Error_FIELDNAME("");
                            }}
                        >
                            {subtab.toUpperCase()}
                        </button>
                    ))}
            </div>

            {/* SIDEBAR , TabContent , JSON_Content */}
            <div className="content-box content-grid">
                {/* SIDEBAR */}
                <Sidebar />

                {/* left column = Input Boxes */}
                <div className="column-flex-form">
                    {can_render_TabContent && <TabContent />}
                </div>

                {/* right column = JSON */}
                <div className="column-flex json-preview-column">
                    <JSON_Content />
                </div>
            </div>
        </>
    );
}
