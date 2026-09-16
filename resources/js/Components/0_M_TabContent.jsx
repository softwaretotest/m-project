// resources/js/Components/0_M_TabContent.jsx

import { React, useState, useRef, useEffect } from "react";
import { useScrollIntoView } from "@/hooks/useScrollIntoView";
import { useError } from "@/Hooks/useError";

import { use_M_Store } from "@/Stores/0_M_Store.jsx";
import {
    delete_field,
    M_value_Service,
    add_field_ENTITIES,
} from "@/Services/0_M_value_Service";

import SpecialField from "@/Components/0_M_SpecialField";
import Field from "@/Components/0_M_Field";
import EntityField from "@/Components/0_M_EntityField";
import DB_Tablename from "@/Components/0_M_DB_Tablename";
import { set_selected_D_U_UF_FOREIGN } from "@/Components/0_M_Data_Helper";
import { Render_fieldname_input } from "@/Components/0_M_Input_Group.jsx";

export default function TabContent() {
    const { Error_FIELDNAME, handle_Fieldname_Change } = useError();

    const { FIELDNAME_to_add, set_FIELDNAME_to_add } = use_M_Store();

    const M_value = use_M_Store((state) => state.M_value);
    // const M_value = use_M_Store.getState().M_value;
    const activeTab = use_M_Store((state) => state.activeTab);
    const activeSubTab = use_M_Store((state) => state.activeSubTab);
    const is_ENTITIES = activeTab === "entities" && activeSubTab === "entities";

    const activeField = use_M_Store((state) => state.activeField);
    const is_Editing = use_M_Store((state) => state.is_Editing);

    const setActiveField = use_M_Store.getState().setActiveField;
    const set_NEW_added_fieldname =
        use_M_Store.getState().set_NEW_added_fieldname;
    const set_selected_F_S_by_M_value_T =
        use_M_Store.getState().set_selected_F_S_by_M_value_T;

    /**
     * State to open / close Backdrop (lock UI during editig)
     */
    const { set_is_Editing } = use_M_Store();

    if (!M_value)
        return <div className="ui-placeholder">No UI for {activeSubTab}</div>;

    const scrollRefs = useRef({});
    useScrollIntoView(activeField, scrollRefs);

    // define Javascript GLOBAL Variable window.D_HEAL if not exist
    if (typeof window !== "undefined" && !window.D_HEAL) {
        window.D_HEAL = { isLastField: false, total: 0, collected: {} };
    }

    /**
     * * useEffect for:
     * * void infinit loop on refresh
     * * set all table at once
     */
    useEffect(() => {
        if (is_ENTITIES && M_value && Object.keys(M_value).length > 0) {
            set_selected_F_S_by_M_value_T(M_value);
        }
    }, [activeSubTab]); // set only when user click SubTab = "entities"

    function render_TabContent_DOM(fieldname, field_data) {
        return (
            <div
                key={fieldname}
                ref={(DOM_Node) => (scrollRefs.current[fieldname] = DOM_Node)}
                className={`form-subtab-content-row ${activeField === fieldname ? "is-focused" : ""}`}
                onClick={() => {
                    // fieldname = null , when field deleted
                    if (fieldname) setActiveField(fieldname);
                }}
                // for update fielname , user must select a field first
                disabled={!activeField}
            >
                {["d", "u", "uf", "cd", "cu", "cud"].includes(activeSubTab) && (
                    <div className="field-header-container">
                        <Render_fieldname_input
                            fieldname={fieldname}
                            className="M_value_KEY"
                        />

                        <span className="field-separator-colon">:</span>

                        <Render_fieldname_input
                            fieldname={fieldname}
                            className="fieldname"
                        />

                        <button
                            className="delete-button"
                            onClick={() => {
                                delete_field(fieldname);
                                set_is_Editing(false);
                            }}
                        >
                            DELETE
                        </button>
                    </div>
                )}

                {activeSubTab === "s" && Array.isArray(field_data) && (
                    <SpecialField field_data={field_data} />
                )}
                {activeSubTab === "f" && Array.isArray(field_data) && (
                    <Field field_data={field_data} />
                )}
                {activeSubTab === "t" && (
                    <DB_Tablename field_data={field_data} />
                )}
                {activeSubTab === "entities" && (
                    <EntityField
                        f_s_Class_Array={field_data}
                        TABLENAME={fieldname.toUpperCase()}
                    />
                )}
            </div>
        );
    }

    /**
     * * get new fieldname from UI
     * * and save to JSON Backend
     * * BE CAREFULL to save convention : always like this
     * * fieldname = lowercase
     * * M_value [KEY] , KEY = UPPERCASE
     * @returns
     */
    async function add_field_APP_DATA() {
        const input_box_fieldname = document.querySelector(".new_field_name");
        const raw_name = input_box_fieldname ? input_box_fieldname.value : "";

        const trimmed_name = raw_name.trim();
        if (!trimmed_name) return;

        await set_NEW_added_fieldname(trimmed_name.toLowerCase()); //set flag for useEffect in Dropdown_D.jsx

        const new_field_data = [trimmed_name.toLowerCase(), ["d::STRING", 255]];
        const fieldname = new_field_data[0];
        const M_value_KEY = new_field_data[0].toUpperCase();

        const new_M_value = {
            [M_value_KEY]: new_field_data,
            ...M_value,
        };

        await M_value_Service.update(new_M_value);

        if (fieldname) await setActiveField(fieldname); // for auto scroll, not work

        // this make auto scroll for JSON_Content works if new field added
        use_M_Store.getState().set_is_new_field_added(true);

        //clear input box , after finish
        if (input_box_fieldname) input_box_fieldname.value = "";
        set_FIELDNAME_to_add("");

        set_selected_D_U_UF_FOREIGN(new_field_data);
    }

    /**
     * * M_DATA = Simple Data (KEY: value)
     * * value = lowercase string
     */
    async function add_field_M_DATA() {
        const input_box_fieldname = document.querySelector(".new_field_name");
        const raw_name = input_box_fieldname ? input_box_fieldname.value : "";

        const trimmed_name = raw_name.trim();
        if (!trimmed_name) return;

        await set_NEW_added_fieldname(trimmed_name.toLowerCase());

        // เปลี่ยนจาก Array Complex เป็น String ธรรมดา
        const new_field_data = trimmed_name.toLowerCase();

        // Key ต้องเป็น UPPERCASE ตาม Convention ของคุณ
        const M_value_KEY = trimmed_name.toUpperCase();

        const new_M_value = {
            [M_value_KEY]: new_field_data,
            ...M_value,
        };

        await M_value_Service.update(new_M_value);

        if (trimmed_name) await setActiveField(trimmed_name.toLowerCase());

        use_M_Store.getState().set_is_new_field_added(true);

        if (input_box_fieldname) input_box_fieldname.value = "";
        set_FIELDNAME_to_add("");

        // ไม่ต้องเรียก set_selected_D_U_UF_FOREIGN เพราะ M_DATA ไม่มีฟิลด์พวกนี้
    }

    function add_field() {
        if (
            (activeTab === "app_data" && activeSubTab === "f") ||
            (activeTab === "m_data" && activeSubTab === "s")
        ) {
            add_field_APP_DATA();
        }
        if (is_ENTITIES) {
            add_field_ENTITIES();
        }
        if (
            activeTab === "m_data" &&
            ["d", "u", "uf", "cd", "cu", "cud"].includes(activeSubTab)
        ) {
            add_field_M_DATA();
        }
    }
    /**
     * remove scroll to lock UI during editing
     */
    useEffect(() => {
        if (is_Editing) {
            document.body.classList.add("overflow-hidden");
        } else {
            document.body.classList.remove("overflow-hidden");
        }
    }, [is_Editing]);

    return (
        <>
            {Error_FIELDNAME}

            <div className="tab-content-header">
                <label className="tch-label">
                    activeSubTab = {activeSubTab}
                </label>
                <input
                    type="text"
                    className={`new_field_name ${FIELDNAME_to_add ? "justify-items-center" : ""}`}
                    placeholder={
                        is_ENTITIES ? "new table name" : "new field name"
                    }
                    value={FIELDNAME_to_add}
                    onFocus={() => set_FIELDNAME_to_add("")}
                    onChange={(event) => handle_Fieldname_Change(event)}
                />

                <button
                    className="add-button"
                    onClick={() => {
                        add_field();
                    }}
                    //no continue, if no data , or data invalide
                    disabled={!FIELDNAME_to_add}
                >
                    ADD FIELD
                </button>
            </div>

            <div
                className={`input-engine-container ${use_M_Store.getState().is_Editing ? "lock-scroll" : ""}`}
            >
                {use_M_Store.getState().is_Editing && (
                    <div className="backdrop" />
                )}
                {Object.entries(M_value).map(
                    ([M_value_KEY, field_data], index) => {
                        // SAVE TO Javascript GLOBAL Variable
                        // Dropdown_D run D_HEAL only
                        // after refresh and activeTab = app_data
                        if (
                            !window.D_HEAL.isLastField &&
                            activeTab === "app_data"
                        ) {
                            let isLastField =
                                index === Object.entries(M_value).length - 1;
                            window.D_HEAL.isLastField = isLastField;
                        }
                        // !!! MUST HAVE return to show DOM !!!
                        return render_TabContent_DOM(
                            M_value_KEY.toLowerCase(),
                            field_data,
                        );
                    },
                )}
            </div>
        </>
    );
}
