// /Resources/Services/0_M_value_Service.jsx
import { use_M_Store } from "@/Stores/0_M_Store.jsx";

import {
    change_fieldname_in_field_data,
    is_DATA_simple,
    is_T_tablename,
} from "@/Components/0_M_Data_Helper";
import { GLOBAL_METADATA } from "@/Providers/0_M_DataProvider";
import { findout_F_or_S } from "@/Providers/0_M_DataProvider";

/**
 * * EXPLAIN M_value = { Object }
 * * M_value has same format like JSON to simplify convertion
 * * ------------------------------------------
 * * CONVENTION
 * *{ M_value_KEY: [ fieldname , ...field_definition] }
 * * field_data  = [ fieldname , ...field_definition]
 * * M_value_KEY <=> fieldname
 * * UPPPERCASE  <=> lowercase
 * * e.g.
 * * {"IMAGE": [ "image", ["d::STRING" , 255] , "u::FILE"]}
 * * ------------------------------------------
 * * function of M_value_Service
 * 0. save M_value to Global States M_Store
 * 1. POST to Backend
 * 2. notify use_M_Data to notify JSON_Content to update it self with Backend
 *
 * @param {*} new_M_value
 * * activeTab  subTab
 * * M-DATA     CD D U CU CUD S
 * * APP-DATA   F T
 * * ENTITIES   ENTITIES
 */
export const M_value_Service = {
    update: async (new_M_value, cascade = null) => {
        const set_M_value = use_M_Store.getState().set_M_value;
        const set_has_M_value_Change =
            use_M_Store.getState().set_has_M_value_Change;

        /**
         * 0. save M_value to Global States M_Store
         * * and get corrected_M_value after save
         */
        let corrected_M_value = null;
        if (!cascade) {
            corrected_M_value = await set_M_value(new_M_value);
        } else {
            corrected_M_value = new_M_value;
        }

        try {
            /**
             *  1. POST to Backend
             * * need await for try-catch
             * * and send_POST must 100% finish
             * * before set_has_M_value_Change (to tell JSON_Conten to get Backend data)
             * * we must get snapshot after set_M_value (corrected d:: to 2nd positon)
             */
            await send_POST(corrected_M_value, cascade);

            // 2. notify use_M_Data to notify JSON_Content to update it self with Backend
            set_has_M_value_Change(true);
        } catch (error) {
            console.error("[SERVICE] Error saving:", error);
        }
    },
};

async function send_POST(corrected_M_value, cascade = null) {
    const activeTab = use_M_Store.getState().activeTab;
    const activeSubTab = use_M_Store.getState().activeSubTab;

    const response = await fetch("/api/m-value", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-XSRF-TOKEN": getCookie("XSRF-TOKEN"),
        },
        body: JSON.stringify({
            tab: cascade ? cascade.activeTab : activeTab,
            subTab: cascade ? cascade.activeSubTab : activeSubTab,
            data: corrected_M_value,
        }),
    });

    if (!response.ok) throw new Error("Server error");

    console.log(
        `[SERVICE] Saved ${activeTab}/${activeSubTab} successfully ✅!`,
    );
}

/**
 * * Merge fields (f) from "copy APP_DATA" to existing f on Target App
 * 1. Set target tab and subTab synchronously in store
 * 2. prepare new_M_value_f
 * 3. update GLOBAL_METADATA to prepare new app_data.f
 * 4. Send updated M_value via service
 * @param {} M_value_f_imported
 */
export async function merge_M_value_F(M_value_f_imported) {
    // 1. Set target tab and subTab synchronously in store
    use_M_Store.getState().setActiveTab("app_data");
    use_M_Store.getState().setActiveSubTab("f");

    // 2. prepare new_M_value_f
    const new_M_value_f = {
        ...GLOBAL_METADATA.app_data.f,
        ...M_value_f_imported,
    };

    // 3. update GLOBAL_METADATA to prepare new app_data.f to show in TabContent ,
    // using M_value_Service functions :
    //  * move_d_to_2nd_position()
    //  * sanitize_field_data()
    // to run find_D_Params_in_GLOBAL_METADATA without error field_data = undefined
    GLOBAL_METADATA.app_data.f = new_M_value_f;

    // 4. Send updated M_value via service
    await M_value_Service.update(new_M_value_f);
}

/**
 * * Merge entities from "copy Entities" to existing entities on Target App
 * 1. Set target tab and subTab synchronously in store
 * 2. prepare new_M_value_entities
 * 3. update GLOBAL_METADATA to prepare new entities.entities to show in TabContent
 * 4. Send updated M_value via service
 * @param {} M_value_Entities_imported
 */
export async function merge_M_value_Entities(M_value_Entities_imported) {
    // 1. Set target tab and subTab synchronously in store
    use_M_Store.getState().setActiveTab("entities");
    use_M_Store.getState().setActiveSubTab("entities");

    // 2. prepare new_M_value_f
    const new_M_value_entities = {
        ...GLOBAL_METADATA.entities.entities,
        ...M_value_Entities_imported,
    };

    // 3. update GLOBAL_METADATA to prepare new entities.entities to show in TabContent
    GLOBAL_METADATA.entities.entities = M_value_Entities_imported;

    // 4. Send updated M_value via service
    await M_value_Service.update(new_M_value_entities);
}
/**
 * * Merge entities T by pushing button "copy Entities"
 * 1. prepare new_M_value_t
 * 2. Send updated M_value via service
 * @param {} M_value_Entities_imported
 */
export async function merge_M_value_App_Data_T(new_tablename_list) {
    // 1. prepare new_M_value_t
    const M_value_t_imported = new_tablename_list.reduce((acc, item) => {
        acc[item.key] = item.value;
        return acc;
    }, {});

    const new_M_value_t = {
        ...GLOBAL_METADATA.app_data.t,
        ...M_value_t_imported,
    };

    // 2. Send updated M_value via service / param tab and subTab = update without changing tab
    // We have to drill params to send_Post() ,
    // because some where during this function , tab and stubTab changed to entities
    const cascade = {
        activeTab: "app_data",
        activeSubTab: "t",
    };
    await M_value_Service.update(new_M_value_t, cascade);
}

/**
 * this to solve problem , content = null
 * * headers: {
 * *   "Content-Type": "application/json",
 * *    "X-CSRF-TOKEN": document.querySelector(
 * *        'meta[name="csrf-token"]',
 * *    ).content, // important!
 */
const getCookie = (name) => {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(";").shift();
};

/**
 * * CALLED by onClick DELETE-Button of activeField
 * * ----------------------------------------
 * 1. setActiveField(fieldname) if activeField !== fieldname
 * * and return to let hightlightk first for user
 * * to see, that this field is active
 * * ----------------------------------------
 * 2.remove the active field from M_value
 * * and update JSON Backend
 */
export async function delete_field(fieldname) {
    //guard for Class t (Tablename) , on subTab t prevent DELETE
    if (is_T_tablename()) return;

    const debug = false;

    const activeTab = use_M_Store.getState().activeTab;
    const activeSubTab = use_M_Store.getState().activeSubTab;
    const activeField = use_M_Store.getState().activeField;
    const setActiveField = use_M_Store.getState().setActiveField;
    const set_FIELDNAME_to_update =
        use_M_Store.getState().set_FIELDNAME_to_update;

    if (activeField !== fieldname) {
        console.log(`${activeField} === ${fieldname}`);
        setActiveField(fieldname);
        return;
    }

    const FIELDNAME = activeField.toUpperCase();

    const isConfirmed = window.confirm(`Are you sure to delete ${FIELDNAME}?`);
    if (!isConfirmed) {
        set_FIELDNAME_to_update(fieldname, activeField.toUpperCase());
        return;
    }
    if (isConfirmed) {
        if (
            (activeTab === "app_data" && activeSubTab === "f") || // Field
            (activeTab === "m_data" && activeSubTab === "s") // SpecialField
        ) {
            const new_M_value = { ...use_M_Store.getState().M_value };
            delete new_M_value[FIELDNAME];
            await M_value_Service.update(new_M_value);

            await delete_cascade_fieldname_in_entities(activeField, debug);
        }

        // DB Table
        if (activeTab === "entities" && activeSubTab === "entities") {
            //delete tablename from selected_F_S
            delete use_M_Store.getState().selected_F_S[FIELDNAME];

            const new_M_value = make_M_value_by_selected_F_S();
            await M_value_Service.update(new_M_value);

            await delete_cascade_tablename_in_app_data_t(activeField, debug);
        }
    }
    if (
        activeTab === "m_data" &&
        ["d", "u", "uf", "cd", "cu", "cud"].includes(activeSubTab)
    ) {
        const new_M_value = { ...use_M_Store.getState().M_value };
        delete new_M_value[FIELDNAME];
        await M_value_Service.update(new_M_value);
    }
}

/**
 * * CALLED after app_data t::CLASS tablename in ENTITIES added
 * * to add cascade tablename in Entities.json
 * @param {*} activeField e.g. image123
 */
export async function add_cascade_tablename_in_app_data_t(activeField) {
    const M_value_T = GLOBAL_METADATA?.app_data?.t;

    if (!M_value_T) return;

    const new_M_value_T = { ...M_value_T };
    new_M_value_T[activeField.toUpperCase()] = activeField;

    // console.log(`[SERVICE] -- new_M_value_T:`, new_M_value_T);

    const cascade = {
        activeTab: "app_data",
        activeSubTab: "t",
    };

    await M_value_Service.update(new_M_value_T, cascade);
}

/**
 * * CALLED after app_data f::CLASS fieldname deleted
 * * to delete cascade fieldname in Entities.json, if f:: usage exists there
 * @param {*} activeField e.g. image123
 */
async function delete_cascade_tablename_in_app_data_t(activeField, debug) {
    const M_value_T = GLOBAL_METADATA?.app_data?.t;

    if (!M_value_T) return;

    const new_M_value_T = { ...M_value_T };
    delete new_M_value_T[activeField.toUpperCase()];

    if (debug) console.log(`[5][SERVICE] -- new_M_value_T:`, new_M_value_T);

    const cascade = {
        activeTab: "app_data",
        activeSubTab: "t",
    };

    await M_value_Service.update(new_M_value_T, cascade);
}

/**
 * * CALLED after app_data f::CLASS fieldname deleted
 * * to delete cascade fieldname in Entities.json, if f:: usage exists there
 * @param {*} activeField e.g. IMAGE123
 */
async function delete_cascade_fieldname_in_entities(activeField, debug) {
    const entities = GLOBAL_METADATA?.entities?.entities;
    const FIELDNAME = activeField.toUpperCase();
    const f_s_CLASS = findout_F_or_S(FIELDNAME); // ได้ค่าตรงฟอร์แมต เช่น "f::IMAGE123" มาทันที

    if (debug)
        console.log(
            `[1][SERVICE -- delete_cascade] -- activeField: ${activeField}`,
        );
    if (debug)
        console.log(`[2][SERVICE -- delete_cascade] -- entities:`, entities);

    if (!entities) return;

    const new_M_value_Entities = {};

    // LOOP TO find f_CLASS in all entities[TABLENAME]
    for (const table in entities) {
        const fields = entities[table];
        if (debug)
            console.log(`[2.0][SERVICE -- delete_cascade] -- table: ${table}`);

        // กรองเอา field ที่ตรงกับ f_s_CLASS ออกตรงๆ ได้เลย
        const new_fields = fields.filter((field) => {
            const isMatch = field === f_s_CLASS;
            if (isMatch && debug) {
                console.log(
                    `[3.2][SERVICE -- delete_cascade] -- Removed matched field: ${field} from table: ${table}`,
                );
            }
            return !isMatch;
        });

        if (debug)
            console.log(
                `[4][SERVICE -- delete_cascade] -- New fields for ${table}:`,
                new_fields,
            );
        new_M_value_Entities[table] = new_fields;
    }

    if (debug)
        console.log(
            `[5][SERVICE -- delete_cascade] -- new_M_value_Entities:`,
            new_M_value_Entities,
        );

    const cascade = {
        activeTab: "entities",
        activeSubTab: "entities",
    };

    await M_value_Service.update(new_M_value_Entities, cascade);
}

/**
 * * to rename M_value_KEY and fieldname
 * * and keep same sort-order ,
 * @param {*} old_M_value
 * * e.g. { IMAGE : ['image' , ['d::STRING',255] , 'u::FILE ] ... }
 * @param {*} OLD_KEY e.g. IMAGE
 * @param {*} NEW_KEY e.go PRODUCT_IMAGE
 * @returns new_M_value
 * * e.g. { PRODUCT_IMAGE : ['product_image' , ['d::STRING',255] , 'u::FILE ] ... }
 * * ---------------------------------------
 * * MY OPINION:
 * * we have to make new_M_value und loop all field from old_M_value
 * * This effort is insane !!!
 * * Because, we auto. save M_value to backend onChange of fieldname
 * * that means, we loops all fields every time we type a letter
 * * we have 2026 , but GEMINI said :
 * * " JS still has no better solution to change object key "
 */
export async function rename_M_value_KEY_and_fieldname(fieldname) {
    const debug = false;

    const activeTab = use_M_Store.getState().activeTab;
    const activeSubTab = use_M_Store.getState().activeSubTab;
    const activeField = use_M_Store.getState().activeField;
    const setActiveField = use_M_Store.getState().setActiveField;
    const set_FIELDNAME_to_update =
        use_M_Store.getState().set_FIELDNAME_to_update;
    const remove_FIELDNAME_to_update =
        use_M_Store.getState().remove_FIELDNAME_to_update;

    const is_F_or_S =
        (activeTab === "app_data" && activeSubTab === "f") ||
        (activeTab === "m_data" && activeSubTab === "s");

    const is_ENTITIES = activeTab === "entities" && activeSubTab === "entities";

    let old_M_value = {};
    if (is_F_or_S || is_DATA_simple())
        old_M_value = use_M_Store.getState().M_value;
    if (is_ENTITIES) {
        /**
         * * WORKAROUND : BUG M_value[M_VALUE_KEY] = undefined
         * * we Loop to build new_M_value by copy content from selected_F_S ,
         * * because if we use clone ...M_value ,
         * * M_value[M_value_KEY] it has all empty content ,
         * * BUG = empty content of all table
         * * -------------------------------
         * * SOLUTION : seleted_F_S = Entities on UI
         * * we keep seleted_F_S correct on the whole CRUD - Flow
         * * so seleted_F_S can be save backend as M_value anytime
         */
        for (const TABLENAME of Object.keys(
            use_M_Store.getState().selected_F_S,
        )) {
            // get current data before save
            old_M_value[TABLENAME] =
                use_M_Store.getState().selected_F_S[TABLENAME];
        }
    }

    const OLD_KEY = activeField.toUpperCase();
    const NEW_KEY = fieldname.toUpperCase();

    const set_NEW_added_fieldname =
        use_M_Store.getState().set_NEW_added_fieldname;
    const set_has_Fieldname_Change =
        use_M_Store.getState().set_has_Fieldname_Change;

    let new_M_value;

    if (is_DATA_simple()) {
        new_M_value = {};
        for (const KEY of Object.keys(old_M_value)) {
            if (KEY === OLD_KEY) {
                new_M_value[NEW_KEY] = NEW_KEY.toLowerCase();
            } else {
                new_M_value[KEY] = old_M_value[KEY];
            }
        }
    } else {
        new_M_value = prepare_M_value_for_update_f_s_entities(
            old_M_value,
            OLD_KEY,
            NEW_KEY,
            debug,
        );
    }

    setActiveField(NEW_KEY.toLowerCase());

    // to fix bug BOOLEAN123 , must be BOOLEAN after deleted 1 from BOOLEAN1
    set_FIELDNAME_to_update(NEW_KEY.toLowerCase(), NEW_KEY);
    // clean map
    remove_FIELDNAME_to_update(OLD_KEY.toLowerCase());

    set_NEW_added_fieldname(NEW_KEY.toLowerCase());
    set_has_Fieldname_Change(true);
    await M_value_Service.update(new_M_value);

    if (is_F_or_S) {
        await update_cascade_fieldname_in_entities(OLD_KEY, NEW_KEY, debug);
    }

    if (is_ENTITIES) {
        await update_cascade_tablename_in_app_data_t(OLD_KEY, NEW_KEY, debug);
    }

    if (is_T_tablename()) {
        await update_cascade_entities_KEY(OLD_KEY, NEW_KEY);
    }
}

/**
 * * CALLED after app_data.t TABLENAME changed
 * * to update cascade table key in Entities (Entities.json)
 */
async function update_cascade_entities_KEY(OLD_KEY, NEW_KEY) {
    const entities = GLOBAL_METADATA?.entities?.entities;
    if (!entities) return;

    const new_entities = {};

    for (const tableKey of Object.keys(entities)) {
        const fields = entities[tableKey];
        if (tableKey === OLD_KEY) {
            new_entities[NEW_KEY] = fields;
        } else {
            new_entities[tableKey] = fields;
        }
    }

    const cascade = {
        activeTab: "entities",
        activeSubTab: "entities",
    };

    await M_value_Service.update(new_entities, cascade);
}

/**
 * * CALLED after entities TABLENAME changed
 * * to update cascade tablename in App-Data.json (under 't' subTab)
 * @param {*} OLD_KEY e.g. "ORDERS"
 * @param {*} NEW_KEY e.g. "ORDERS_NEW"
 */
async function update_cascade_tablename_in_app_data_t(OLD_KEY, NEW_KEY, debug) {
    const M_value_T = GLOBAL_METADATA?.app_data?.t;
    if (debug) console.log(`[0][SERVICE] M_value_T:`, M_value_T);

    if (debug)
        console.log(`[1][SERVICE] -- OLD_KEY: ${OLD_KEY}, NEW_KEY: ${NEW_KEY}`);

    if (!M_value_T) return;

    const new_M_value_T = {};

    for (const KEY of Object.keys(M_value_T)) {
        const value = M_value_T[KEY];
        if (debug) console.log(`[2][SERVICE] -- KEY: ${KEY}, value: ${value}`);

        if (KEY === OLD_KEY) {
            new_M_value_T[NEW_KEY] = NEW_KEY.toLowerCase();
        } else {
            new_M_value_T[KEY] = value;
        }
    }

    if (debug) console.log(`[5][SERVICE] -- new_M_value_T:`, new_M_value_T);

    const cascade = {
        activeTab: "app_data",
        activeSubTab: "t",
    };

    await M_value_Service.update(new_M_value_T, cascade);
}

function prepare_M_value_for_update_f_s_entities(
    old_M_value,
    OLD_KEY,
    NEW_KEY,
) {
    const new_M_value = {};
    console.log(
        `[DEBUG - M_value_Service] -- prepare_M_value_for_update_f_s_entities -- old_M_value = `,
        old_M_value,
    );
    for (const KEY of Object.keys(old_M_value)) {
        if (OLD_KEY === NEW_KEY) return old_M_value;
        if (KEY === OLD_KEY) {
            const old_field_data = old_M_value[OLD_KEY];

            const activeTab = use_M_Store.getState().activeTab;
            const activeSubTab = use_M_Store.getState().activeSubTab;

            if (activeTab === "entities" && activeSubTab === "entities") {
                delete use_M_Store.getState().selected_F_S[OLD_KEY];
                new_M_value[NEW_KEY] = old_field_data;
            } else {
                /**
                 * * case app_data f::CLASS or s::SPECIAL_FIELD
                 * * must change fieldname in field_data too
                 * * M_value_KEY <=> fieldname
                 * * UPPPERCASE  <=> lowercase
                 * * e.g.
                 * * { "IMAGE": [ "image", ["d::STRING" , 255] , "u::FILE"]}
                 * * { "PRODUCT_IMAGE": [ "product_image", ["d::STRING" , 255] , "u::FILE"] }
                 */
                const M_value_with_new_fieldname =
                    change_fieldname_in_field_data(
                        old_field_data,
                        NEW_KEY.toLowerCase(),
                    );
                new_M_value[NEW_KEY] = M_value_with_new_fieldname;
            }
        } else {
            new_M_value[KEY] = old_M_value[KEY];
        }
    }
    return new_M_value;
}

/**
 * * CALLED after app_data f::CLASS fieldname changed
 * * to update cascade fieldname in Entities.json, if f:: usage exists there
 * @param {*} OLD_KEY e.g. IMAGE
 * @param {*} NEW_KEY e.g. PRODUCT_IMAGE
 */
async function update_cascade_fieldname_in_entities(OLD_KEY, NEW_KEY, debug) {
    const entities = GLOBAL_METADATA?.entities?.entities;

    if (debug)
        console.log(`[1][SERVICE] -- OLD_KEY: ${OLD_KEY}, NEW_KEY: ${NEW_KEY}`);
    if (debug) console.log(`[2][SERVICE] entities:`, entities);

    const new_M_value_Entities = {};

    for (const table in entities) {
        const fields = entities[table];
        if (debug) console.log(`[2.0][SERVICE] -- table: ${table}`);
        if (debug) console.log(`[2.1][SERVICE] -- fields: ${fields}`);
        const new_fields = fields.map((field) => {
            // for fields e.g. f::NAME, f::IMAGE, f::USER_ID
            if (field === "f::" + OLD_KEY) {
                return "f::" + NEW_KEY;
            }
            // for Special fields e.g. s::EMAIL, s::CURRENCY
            if (field === "s::" + OLD_KEY) {
                return "s::" + NEW_KEY;
            }
            if (debug)
                console.log(
                    `[3.2][SERVICE] -- field: ${field} === ${"f::" + OLD_KEY}`,
                );
            return field;
        });
        if (debug) console.log(`[4][SERVICE] -- New fields:`, new_fields);
        new_M_value_Entities[table] = new_fields;
    }

    if (debug)
        console.log(
            `[5][SERVICE] -- new_M_value_Entities:`,
            new_M_value_Entities,
        );
    const cascade = {
        activeTab: "entities",
        activeSubTab: "entities",
    };
    await M_value_Service.update(new_M_value_Entities, cascade);
}

/**
 * * CALLED by EntitiyField.jsx and Entities_select.jsx
 * * to update M_value with selected_F_S for current TABLENAME
 * @param {*} TABLENAME e.g. ORDERS , PRODUCTS
 */
export async function update_M_value_with_selected_F_S() {
    const debug = false;
    const selected_F_S = use_M_Store.getState().selected_F_S;
    const M_value = use_M_Store.getState().M_value;

    if (debug)
        console.log(
            "[1] !!!!!!!!!!! -- M_value_Service -- update_M_value_with_selected_F_S -- M_value = ",
            M_value,
        );

    if (debug)
        console.log(
            `[2] !!!!!!!!!!! -- M_value_Service -- update_M_value_with_selected_F_S -- ALL selected_F_S = `,
            selected_F_S,
        );

    /**
     * * Loop for updated M_value only ,
     * * if same KEY TABLENAME exist in both M_value and seleted_F_S
     */
    const new_M_value = { ...M_value };

    for (const TABLENAME of Object.keys(selected_F_S)) {
        if (debug)
            console.log(
                `[3][LOOP] !!!!!!!!!!! -- M_value_Service -- update_M_value_with_selected_F_S -- TABLENAME = `,
                TABLENAME,
            );
        if (debug)
            console.log(
                `[4][LOOP] !!!!!!!!!!! -- M_value_Service -- update_M_value_with_selected_F_S -- Object.keys(selected_F_S) = `,
                Object.keys(selected_F_S),
            );

        /**
         * update only , when TABLENAME exists in M_value
         */
        if (selected_F_S[TABLENAME] !== undefined) {
            new_M_value[TABLENAME] = selected_F_S[TABLENAME];
        }
    }

    if (debug)
        console.log(
            "[5] !!!!!!!!!!! -- M_value_Service -- update_M_value_with_selected_F_S -- new_M_value = ",
            new_M_value,
        );

    await M_value_Service.update(new_M_value);
}

/**
 * * CALLED by set_M_value to validate field_data convention
 * * -------------------------------------------------------
 * 1. check if M_value = app_data.f or s
 * * -------------------------------------------------------
 * 2. check bevor set_M_value
 * * if d:: exist in field_data
 * * -------------------------------------------------------
 * 3. move d:: to 2nd position of the field_data
 * @param {*} M_value
 */
export function move_d_to_2nd_position(M_value) {
    const state = use_M_Store.getState();
    // 1. do nothing to M_value if not app_data
    let is_app_data = false;
    if (
        (state.activeTab === "app_data" && state.activeSubTab === "f") ||
        (state.activeTab === "m_data" && state.activeSubTab === "s")
    ) {
        is_app_data = true;
    }
    if (!is_app_data) return M_value;

    // if M_value = app_data.f or s , begin correction
    const corrected_M_value = { ...M_value };
    Object.keys(M_value).forEach((M_value_KEY) => {
        const field_data = M_value[M_value_KEY];
        // Loop in field_data and do 2. 3.
        corrected_M_value[M_value_KEY] = sanitize_field_data(field_data);
    });
    return corrected_M_value;
}

/**
 * * Helper function to reorder 'd::' to the 2nd position in a single field_data array
 * @param {Array} field_data e.g.
 * * [ "name" , "u::TEXT" , "cud::REQUIRED" , ["d::STRING",255] ]
 * @returns {Array} reordered field_data e.g.
 * * [ "name" , ["d::STRING",255] ,"u::TEXT" , "cud::REQUIRED"  ]
 */
function sanitize_field_data(field_data) {
    if (!Array.isArray(field_data) || field_data.length <= 1) return field_data;

    const fieldName = field_data[0];
    const attributes = field_data.slice(1);

    // Find index of data type (d::)
    const dIndex = attributes.findIndex(
        (attr) =>
            (typeof attr === "string" && attr.startsWith("d::")) ||
            (Array.isArray(attr) &&
                typeof attr[0] === "string" &&
                attr[0].startsWith("d::")),
    );

    // If found, move it to the front of attributes (making it 2nd overall)
    if (dIndex !== -1) {
        const [dataTypeItem] = attributes.splice(dIndex, 1);
        attributes.unshift(dataTypeItem);
    }

    return [fieldName, ...attributes];
}

/**
 * * CALLED by add_field_ENTITIES() and delete_field()
 * * WORKAROUND : BUG M_value[M_VALUE_KEY] = undefined
 * * we Loop to build new_M_value by copy content from selected_F_S ,
 * * because if we use clone ...M_value , it makes
 * * BUG = empty content of all table
 * * -------------------------------
 * * SOLUTION : seleted_F_S = Entities on UI
 * * we keep seleted_F_S correct on the whole CRUD - Flow
 * * so seleted_F_S can be save backend as M_value anytime
 */
export function make_M_value_by_selected_F_S() {
    const new_M_value = {};
    for (const TABLENAME of Object.keys(use_M_Store.getState().selected_F_S)) {
        // get current data before save
        new_M_value[TABLENAME] = use_M_Store.getState().selected_F_S[TABLENAME];
    }
    return new_M_value;
}

/**
 * * get new fieldname from UI
 * * and save to JSON Backend
 * * BE CAREFULL to save convention : always like this
 * * fieldname = lowercase
 * * M_value [KEY] , KEY = UPPERCASE
 */
export async function add_field_ENTITIES({ isUser = false } = {}) {
    const set_selected_F_S = use_M_Store.getState().set_selected_F_S;
    const setActiveField = use_M_Store.getState().setActiveField;
    const set_FIELDNAME_to_add = use_M_Store.getState().set_FIELDNAME_to_add;

    // Case called by TabContent
    let fieldname = "";
    let input_box_fieldname = null;
    if (!isUser) {
        input_box_fieldname = document.querySelector(".new_field_name");
        const raw_name = input_box_fieldname ? input_box_fieldname.value : "";
        const trimmed_name = raw_name.trim();
        if (!trimmed_name) return;
        fieldname = trimmed_name;
    }

    // Case called by ADD USERS TABLE (Dashboards)
    if (isUser) fieldname = "users";
    const M_value_KEY = fieldname.toUpperCase();

    // add empty place for new seleted_F_S
    if (isUser) set_selected_F_S("USERS", []);
    else set_selected_F_S(M_value_KEY, []);

    const new_M_value = make_M_value_by_selected_F_S();

    await M_value_Service.update(new_M_value);

    if (fieldname) setActiveField(fieldname.toLowerCase()); // for auto scroll, not work

    // this make auto scroll for JSON_Content works if new field added
    use_M_Store.getState().set_is_new_field_added(true);

    //clear input box , after finish
    if (input_box_fieldname) input_box_fieldname.value = "";
    set_FIELDNAME_to_add("");

    await add_cascade_tablename_in_app_data_t(
        use_M_Store.getState().activeField,
    );
}
