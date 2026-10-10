// resources/js/Stores/0_M_Store.jsx

import { create } from "zustand";
import {
    get_D_NAME_by_FIELDNAME,
    get_U_NAME_by_FIELDNAME,
    get_UF_NAME_by_FIELDNAME,
} from "@/Components/0_M_Data_Helper";

import { move_d_to_2nd_position } from "@/Services/0_M_value_Service";

import { M_value_Service } from "@/Services/0_M_value_Service";

export const use_M_Store = create((set) => ({
    debug: false,
    debug_M_value: true,

    debug_selected_F_S: false,

    debug_selected_U: false,
    debug_selected_U_FOREIGN: false,

    debug_selected_UF: false,
    debug_selected_UF_FOREIGN: false,

    debug_selected_D: false,
    debug_selected_D_FOREIGN: false,

    debug_checked_CU: false,
    debug_checked_CD: false,

    debug_active_Target_App: true,
    debug_activeField: false,
    debug_activeTab: false,
    debug_activeSubTab: false,

    debug_is_new_field_added: false,

    debug_NEW_added_fieldname: false,

    debug_has_Fieldname_Change: false,

    debug_hasJSON_Change: false,

    has_M_value_Change: false,

    debug_Error_FIELDNAME: false,

    debug_set_error: true,

    debug_D_Params_State: false,

    debug_is_auto_uncheck_FOREIGN_by_CU_CD: false,

    debug_is_Editing: false,

    debug_FIELDNAME_to_update: false,

    debug_FIELDNAME_to_add: false,

    /**
     * * CALLED by TabContent.add_field()
     * * to handle add new fieldname or new tablename
     */
    FIELDNAME_to_add: "",
    set_FIELDNAME_to_add: (FIELDNAME_to_add) =>
        set((state) => {
            if (state.debug || state.debug_FIELDNAME_to_add) {
                console.log(
                    `[M_STORE_DEBUG] FIELDNAME_to_add : ${FIELDNAME_to_add}`,
                );
                console.log("------------------------------------");
            }
            return { FIELDNAME_to_add: FIELDNAME_to_add };
        }),

    is_Editing: false,
    set_is_Editing: (is_Editing) =>
        set((state) => {
            if (is_Editing === false) {
                state.set_Error_FIELDNAME(""); // clear Errors
            }
            if (state.debug || state.debug_is_Editing) {
                console.log(`[M_STORE_DEBUG] is_Editing :`, is_Editing);
                console.log("------------------------------------");
            }
            return {
                is_Editing: is_Editing,
            };
        }),

    is_auto_uncheck_FOREIGN_by_CU_CD: false,
    set_is_auto_uncheck_FOREIGN_by_CU_CD: (is_auto_uncheck_FOREIGN_by_CU_CD) =>
        set((state) => {
            if (state.debug || state.debug_is_auto_uncheck_FOREIGN_by_CU_CD) {
                console.log(
                    `[M_STORE_DEBUG] is_auto_uncheck_FOREIGN_by_CU_CD :`,
                    is_auto_uncheck_FOREIGN_by_CU_CD,
                );
                console.log("------------------------------------");
            }
            return {
                is_auto_uncheck_FOREIGN_by_CU_CD:
                    is_auto_uncheck_FOREIGN_by_CU_CD,
            };
        }),

    is_new_field_added: false,
    set_is_new_field_added: (is_new_field_added) =>
        set((state) => {
            if (state.debug || state.debug_is_new_field_added) {
                console.log(
                    `[M_STORE_DEBUG] is_new_field_added :`,
                    is_new_field_added,
                );
                console.log("------------------------------------");
            }
            return { is_new_field_added: is_new_field_added };
        }),

    /**
     * * for useEffect in Dropdown_D
     * * need to know, if is_this_field_new = true
     */
    NEW_added_fieldname: null,
    set_NEW_added_fieldname: (NEW_added_fieldname) =>
        set((state) => {
            if (state.debug || state.debug_NEW_added_fieldname) {
                console.log(
                    `[M_STORE_DEBUG] NEW_added_fieldname :`,
                    NEW_added_fieldname,
                );
                console.log("------------------------------------");
            }
            return { NEW_added_fieldname: NEW_added_fieldname };
        }),

    Error_FIELDNAME: "",
    set_Error_FIELDNAME: (Error_FIELDNAME) =>
        // set({ Error_FIELDNAME: Error_FIELDNAME }),
        set((state) => {
            if (state.debug || state.debug_Error_FIELDNAME) {
                console.log(
                    `[M_STORE_DEBUG] Error_FIELDNAME :`,
                    Error_FIELDNAME,
                );
                console.log("------------------------------------");
            }
            return { Error_FIELDNAME: Error_FIELDNAME };
        }),
    /**
     * * error blinks
     * * because of css .error-text (animation)
     * * and setTimeout to reset error text
     * * setTimeout is a trick to make
     * * React see the change and re-render the component
     * * otherwise, changing same React state immediately
     * * will not trigger re-render and React will ignore the change
     * @param {*} error_text = Error to show in DOM
     * @param {*} clear_in_ms = reset error timeout
     */
    set_error: (error_text, { clear_in_ms = null } = {}) => {
        set({ Error_FIELDNAME: "" });
        setTimeout(() => {
            set({
                Error_FIELDNAME: (
                    <span className="error-text">{error_text}</span>
                ),
            });
        }, 50);
        if (clear_in_ms) {
            setTimeout(() => {
                // syntax for call set_Error_FIELDNAME in M_Store
                set({ Error_FIELDNAME: "" });
            }, clear_in_ms);
        }
        set((state) => {
            if (state.debug || state.debug_set_error) {
                console.log(
                    `[M_STORE_DEBUG] set_error - error_text :`,
                    error_text,
                );
                console.log("------------------------------------");
            }
            return { has_M_value_Change: has_M_value_Change };
        });
    },

    has_M_value_Change: false,
    set_has_M_value_Change: (has_M_value_Change) =>
        set((state) => {
            if (state.debug || state.debug_has_M_value_Change) {
                console.log(
                    `[M_STORE_DEBUG] has_M_value_Change :`,
                    has_M_value_Change,
                );
                console.log("------------------------------------");
            }
            return { has_M_value_Change: has_M_value_Change };
        }),

    hasJSON_Change: false,
    set_hasJSON_Change: (hasJSON_Change) =>
        // set({ hasJSON_Change: hasJSON_Change }),
        set((state) => {
            if (state.debug || state.debug_hasJSON_Change) {
                console.log(
                    `[M_STORE_DEBUG] hasJSON_Change : ${hasJSON_Change} === ${state.has_Fieldname_Change} === has_Fieldname_Change`,
                );
                console.log("------------------------------------");
            }
            return { hasJSON_Change: hasJSON_Change };
        }),

    has_Fieldname_Change: false,
    set_has_Fieldname_Change: (has_Fieldname_Change) =>
        set((state) => {
            if (state.debug || state.debug_has_Fieldname_Change) {
                console.log(
                    `[M_STORE_DEBUG] has_Fieldname_Change : ${has_Fieldname_Change} === ${state.hasJSON_Change} === hasJSON_Change`,
                );
                console.log("------------------------------------");
            }
            return { has_Fieldname_Change: has_Fieldname_Change };
        }),

    /* * ------------------
     * * ATOMIC STATES
     * * ------------------
     */

    /**
     * * CALLED by Render_fieldname_input
     * * to handle update fieldname and tablename
     */
    FIELDNAME_to_update: {}, // atomic states
    set_FIELDNAME_to_update: (fieldname, FIELDNAME_to_update) =>
        set((state) => {
            const NEW_FIELDNAME_to_update = {
                ...state.FIELDNAME_to_update,
                [fieldname]: FIELDNAME_to_update,
            };

            if (state.debug || state.debug_FIELDNAME_to_update) {
                console.log(
                    `[M_STORE_DEBUG] NEW_FIELDNAME_to_update[${fieldname}] :`,
                    NEW_FIELDNAME_to_update[fieldname],
                );
                console.log("------------------------------------");
            }
            return {
                FIELDNAME_to_update: NEW_FIELDNAME_to_update,
            };
        }),

    D_Params_State: {}, // atomic states
    set_D_Params_State: (fieldname, D_Params_State) =>
        set((state) => {
            if (!fieldname) return { D_Params_State: {} };

            const NEW_D_Params_State = {
                ...state.D_Params_State,
                [fieldname]: D_Params_State,
            };

            /**
             * * we need this log because,
             * * D_Params_State containt HTML <D_Params />
             * * to make console.log more readable
             * */
            if (state.debug || state.debug_D_Params_State) {
                console.log(`[DEBUG_DETAIL] Field: ${fieldname}`);
                console.log(
                    `[DEBUG_DETAIL] Type of content:`,
                    typeof NEW_D_Params_State[fieldname],
                );
                console.log(
                    `[DEBUG_DETAIL] Full Object:`,
                    NEW_D_Params_State[fieldname],
                );
                if (
                    NEW_D_Params_State[fieldname] &&
                    NEW_D_Params_State[fieldname].props
                ) {
                    console.log(
                        `[DEBUG_DETAIL] Props inside:`,
                        NEW_D_Params_State[fieldname].props,
                    );
                }
                console.log("------------------------------------");
            }

            return { D_Params_State: NEW_D_Params_State };
        }),

    selected_F_S: {}, // atomic states
    set_selected_F_S: (tablename, selected_F_S) =>
        set((state) => {
            if (!tablename) return { selected_F_S: {} };

            const NEW_selected_F_S = {
                ...state.selected_F_S,
                [tablename]: selected_F_S,
            };

            const sorted_selected_F_S = sort_selected_F_S(NEW_selected_F_S);

            if (state.debug || state.debug_selected_F_S) {
                console.log(
                    `[M_STORE_DEBUG] NEW selected_F_S[${tablename}]:`,
                    sorted_selected_F_S[tablename],
                );
                console.log(
                    `[M_STORE_DEBUG] ALL selected_F_S]:`,
                    sorted_selected_F_S,
                );
                console.log("------------------------------------");
            }

            return { selected_F_S: sorted_selected_F_S };
        }),

    /**
     * * CALLED by useEffect - TabContent
     * * when activeSubTab = "entities"
     * * set all table in selected_F_S at once
     * * update backend
     */
    set_selected_F_S_by_M_value_T: async (M_value_T) => {
        if (!M_value_T || typeof M_value_T !== "object") {
            set({ selected_F_S: {} });
            return;
        }

        const sorted_selected_F_S = sort_selected_F_S(M_value_T);

        set((state) => {
            if (state.debug || state.debug_selected_F_S) {
                console.log(
                    "[M_STORE_DEBUG] SET ALL selected_F_S:",
                    sorted_selected_F_S,
                );
                console.log("------------------------------------");
            }
            return { selected_F_S: sorted_selected_F_S };
        });

        await M_value_Service.update(sorted_selected_F_S);
    },

    /**
     * * CALLED by Sidebar
     * * when drag-drop = re-order sidebar buttons
     * * correctly like real order in selected_F_S
     * * USERS always at the top
     * @param {*} reorderedKeys
     * @returns
     */
    reorder_selected_F_S: (reorderedKeys) =>
        set((state) => {
            const currentData = state.selected_F_S;
            const new_selected_F_S = {};

            // USERS always at the top
            let keysToProcess = [...reorderedKeys];
            if (keysToProcess.includes("USERS")) {
                keysToProcess = [
                    "USERS",
                    ...keysToProcess.filter((k) => k !== "USERS"),
                ];
            }

            keysToProcess.forEach((TABLENAME) => {
                if (currentData[TABLENAME] !== undefined) {
                    new_selected_F_S[TABLENAME] = currentData[TABLENAME];
                }
            });

            if (state.debug || state.debug_selected_F_S) {
                console.log(
                    `[M_STORE_DEBUG] ALL selected_F_S]:`,
                    new_selected_F_S,
                );
                console.log("------------------------------------");
            }

            return { selected_F_S: new_selected_F_S };
        }),

    /**
     * * CALLED by Entities_select to add
     * *    new f::Field ,
     * *    s::SpecialField to selected_F_S
     * @param {*} tablename
     * @param {*} field_item
     * @returns
     */
    add_F_S: (
        tablename,
        field_item, // function of atomic states
    ) =>
        set((state) => {
            if (!tablename) return state;
            const current_list = state.selected_F_S[tablename];
            const NEW_list = [...current_list, field_item];
            const NEW_selected_F_S = {
                ...state.selected_F_S,
                [tablename]: NEW_list,
            };

            if (state.debug || state.debug_selected_F_S) {
                console.log(
                    `[M_STORE_DEBUG] ADDED to selected_F_S[${tablename}]:`,
                    field_item,
                );

                console.log(
                    `[M_STORE_DEBUG] NEW selected_F_S[${tablename}]:`,
                    NEW_selected_F_S[tablename],
                );
                console.log(
                    `[M_STORE_DEBUG] ALL selected_F_S]:`,
                    NEW_selected_F_S,
                );
                console.log("------------------------------------");
            }

            return { selected_F_S: NEW_selected_F_S };
        }),

    /**
     * * CALLED by EntityField to remove
     * *    new f::Field ,
     * *    s::SpecialField to selected_F_S
     * @param {*} tablename
     * @param {*} field_item
     * @returns
     */
    remove_F_S: (
        tablename,
        field_item, // function atomic states
    ) =>
        set((state) => {
            if (!tablename) return state;
            const current_list = state.selected_F_S[tablename];
            const NEW_list = current_list.filter((item) => item !== field_item);
            const NEW_selected_F_S = {
                ...state.selected_F_S,
                [tablename]: NEW_list,
            };

            if (state.debug || state.debug_selected_F_S) {
                console.log(
                    `[M_STORE_DEBUG] REMOVED from selected_F_S[${tablename}]:`,
                    field_item,
                );

                console.log(
                    `[M_STORE_DEBUG] NEW selected_F_S[${tablename}]:`,
                    NEW_selected_F_S[tablename],
                );
                console.log(
                    `[M_STORE_DEBUG] ALL selected_F_S]:`,
                    NEW_selected_F_S,
                );
                console.log("------------------------------------");
            }

            return { selected_F_S: NEW_selected_F_S };
        }),

    selected_UF_FOREIGN: {}, // atomic states
    set_selected_UF_FOREIGN: (fieldname, selected_UF_FOREIGN) =>
        set((state) => {
            if (!fieldname) return { selected_UF_FOREIGN: {} };

            const NEW_selected_UF_FOREIGN = {
                ...state.selected_UF_FOREIGN,
                [fieldname]: selected_UF_FOREIGN,
            };

            if (state.debug || state.debug_selected_UF_FOREIGN) {
                console.log(
                    `[M_STORE_DEBUG] NEW selected_UF_FOREIGN[${fieldname}]:`,
                    NEW_selected_UF_FOREIGN[fieldname],
                );
                console.log("------------------------------------");
            }

            return { selected_UF_FOREIGN: NEW_selected_UF_FOREIGN };
        }),

    selected_U_FOREIGN: {}, // atomic states
    set_selected_U_FOREIGN: (fieldname, selected_U_FOREIGN) =>
        set((state) => {
            if (!fieldname) return { selected_U_FOREIGN: {} };

            const NEW_selected_U_FOREIGN = {
                ...state.selected_U_FOREIGN,
                [fieldname]: selected_U_FOREIGN,
            };

            if (state.debug || state.debug_selected_U_FOREIGN) {
                console.log(
                    `[M_STORE_DEBUG] NEW selected_U_FOREIGN[${fieldname}]:`,
                    NEW_selected_U_FOREIGN[fieldname],
                );
                console.log("------------------------------------");
            }

            return { selected_U_FOREIGN: NEW_selected_U_FOREIGN };
        }),

    selected_D_FOREIGN: {}, // atomic states
    set_selected_D_FOREIGN: (fieldname, selected_D_FOREIGN) =>
        set((state) => {
            if (!fieldname) return { selected_D_FOREIGN: {} };

            const NEW_selected_D_FOREIGN = {
                ...state.selected_D_FOREIGN,
                [fieldname]: selected_D_FOREIGN,
            };

            if (state.debug || state.debug_selected_D_FOREIGN) {
                console.log(
                    `[M_STORE_DEBUG] NEW_selected_D_FOREIGN[${fieldname}]:`,
                    NEW_selected_D_FOREIGN[fieldname],
                );
                console.log("------------------------------------");
            }

            return { selected_D_FOREIGN: NEW_selected_D_FOREIGN };
        }),

    selected_D: {}, // atomic states
    set_selected_D: (fieldname, selected_D_Value) =>
        set((state) => {
            if (!fieldname) return { selected_D: {} };

            const NEW_selected_D = {
                ...state.selected_D,
                [fieldname]: selected_D_Value,
            };

            if (state.debug || state.debug_selected_D) {
                console.log(
                    `[M_STORE_DEBUG] NEW_selected_D[${fieldname}]:`,
                    NEW_selected_D[fieldname],
                );
                console.log("------------------------------------");
            }

            return { selected_D: NEW_selected_D };
        }),

    selected_U: {}, // atomic states
    set_selected_U: (fieldname, selected_U_Value) =>
        set((state) => {
            if (!fieldname) return { selected_U: {} };

            const NEW_selected_U = {
                ...state.selected_U,
                [fieldname]: selected_U_Value,
            };

            if (state.debug || state.debug_selected_U) {
                console.log(
                    `[M_STORE_DEBUG] NEW_selected_U[${fieldname}]:`,
                    NEW_selected_U[fieldname],
                );
                console.log("------------------------------------");
            }

            return { selected_U: NEW_selected_U };
        }),

    selected_UF: {}, // atomic states
    set_selected_UF: (fieldname, selected_UF_Value) =>
        set((state) => {
            if (!fieldname) return { selected_UF: {} };

            const NEW_selected_UF = {
                ...state.selected_UF,
                [fieldname]: selected_UF_Value,
            };

            if (state.debug || state.debug_selected_UF) {
                console.log(
                    `[M_STORE_DEBUG] NEW_selected_UF[${fieldname}]:`,
                    NEW_selected_UF[fieldname],
                );
                console.log("------------------------------------");
            }

            return { selected_UF: NEW_selected_UF };
        }),

    checked_CD: {}, // atomic states
    setChecked_CD: (fieldname, checked_CD_States) =>
        set((state) => {
            if (!fieldname) return { checked_CD: {} };

            const NEW_checked_CD = {
                ...state.checked_CD,
                [fieldname]: checked_CD_States,
            };

            if (state.debug || state.debug_checked_CD) {
                console.log(
                    `[M_STORE_DEBUG] NEW_checked_CD[${fieldname}]:`,
                    NEW_checked_CD[fieldname],
                );
                console.log(
                    `[M_STORE_DEBUG] state.checked_CU[${fieldname}] :`,
                    state.checked_CU[fieldname],
                );
                console.log("------------------------------------");
            }

            return { checked_CD: NEW_checked_CD };
        }),

    checked_CU: {}, // atomic states
    setChecked_CU: (fieldname, checked_CU_States) =>
        set((state) => {
            if (!fieldname) return { checked_CU: {} };

            const NEW_checked_CU = {
                ...state.checked_CU,
                [fieldname]: checked_CU_States,
            };

            if (state.debug || state.debug_checked_CU) {
                console.log(
                    `[M_STORE_DEBUG] state.checked_CD[${fieldname}]:`,
                    state.checked_CD[fieldname],
                );
                console.log(
                    `[M_STORE_DEBUG] New state.checked_CU[${fieldname}] :`,
                    NEW_checked_CU[fieldname],
                );
                console.log("------------------------------------");
            }

            return { checked_CU: NEW_checked_CU };
        }),

    /* * ------------------
     * * MOST IN USE STATES
     * * ------------------
     */
    active_Target_App: "",
    set_active_Target_App: (app) =>
        set((state) => {
            if (state.debug || state.debug_active_Target_App) {
                console.log(`[M_STORE_DEBUG] New active_Target_App :`, app);
                console.log("------------------------------------");
            }

            return { active_Target_App: app };
        }),

    activeTab: "m_data", //default on refresh
    setActiveTab: (tab) =>
        set((state) => {
            if (state.debug || state.debug_activeTab) {
                console.log(`[M_STORE_DEBUG] New setActiveTab :`, tab);
                console.log("------------------------------------");
            }

            return { activeTab: tab };
        }),

    activeSubTab: "d", // default on refresh
    setActiveSubTab: (subTab, ini_data_subtab = null) =>
        set((state) => {
            if (state.debug || state.debug_activeSubTab) {
                console.log(`[M_STORE_DEBUG] New setActiveSubTab :`, subTab);
                console.log("------------------------------------");
            }

            let updates = { activeSubTab: subTab };
            if (ini_data_subtab) {
                updates.M_value = ini_data_subtab;
            }
            return updates;
        }),

    activeField: null,
    setActiveField: (fieldname) =>
        set((state) => {
            if (state.debug || state.debug_activeField) {
                console.log(`[M_STORE_DEBUG] New activeField :`, fieldname);
                console.log("------------------------------------");
            }

            // selected_D can be save to prepare for the case, if FOREIGN clicked
            const D_NAME = get_D_NAME_by_FIELDNAME(fieldname?.toUpperCase());
            state.set_selected_D(fieldname, D_NAME);

            // selected_D can be save to prepare for the case, if FOREIGN clicked
            const U_NAME = get_U_NAME_by_FIELDNAME(fieldname?.toUpperCase());
            state.set_selected_U(fieldname, U_NAME);

            // selected_D can be save to prepare for the case, if FOREIGN clicked
            const UF_NAME = get_UF_NAME_by_FIELDNAME(fieldname?.toUpperCase());
            state.set_selected_UF(fieldname, UF_NAME);

            return { activeField: fieldname };
        }),

    /**
     * * M_value = all Data from each json file
     * * it depends on which Main Tab in Dashboard you clicked
     * * e.g. M_DATA, APP_DATA, ENTITIES
     */
    M_value: {},
    set_M_value: (new_M_value) => {
        let corrected_M_value = null;
        set((state) => {
            corrected_M_value = move_d_to_2nd_position(new_M_value);

            if (state.debug || state.debug_M_value) {
                console.log(`[M_STORE_DEBUG] New M_value:`, new_M_value);
                console.log(
                    `[M_STORE_DEBUG] corrected_M_value:`,
                    corrected_M_value,
                );
                console.log("------------------------------------");
            }
            return { M_value: corrected_M_value };
        });
        return corrected_M_value;
    },
}));

/**
 * * CALLED by set_selected_F_S[TABLENAME]
 * * when TABLENAME === USERS
 * * this function always move USERS to top
 */
function sort_selected_F_S(NEW_selected_F_S) {
    if (!NEW_selected_F_S || typeof NEW_selected_F_S !== "object") return {};

    const TABLENAMES = Object.keys(NEW_selected_F_S);

    if (!TABLENAMES.includes("USERS")) return NEW_selected_F_S;

    // take out USERS
    const OTHER_TABLENAME = TABLENAMES.filter((NAME) => NAME !== "USERS");
    // put back USERS at the first place
    const sorted_TABLENAMES = ["USERS", ...OTHER_TABLENAME];

    const sorted_selected_F_S = {};
    sorted_TABLENAMES.forEach((TABLENAME) => {
        sorted_selected_F_S[TABLENAME] = NEW_selected_F_S[TABLENAME];
    });

    return sorted_selected_F_S;
}
