// resources/js/Components/0_M_EntityField.jsx
import { useEffect } from "react";
import { use_M_Store } from "@/Stores/0_M_Store";
import {
    delete_field,
    update_M_value_with_selected_F_S,
} from "@/Services/0_M_value_Service";

import { Render_fieldname_input } from "@/Components/0_M_Input_Group";

import { render_All_F_S } from "@/Components/0_M_Entities_select";

/**
 * @param f_s_Class_Array = e.g.
 * * ['f::NAME', 'f::IMAGE', 's::EMAIL', 'f::IS_ACTIVE']
 * @param TABLENAME = e.g. PRODUCTS , ORDERS , USERS
 * * DB Table with DB Column in it
 * * {
 * *     "_comment": "\/M_JSON\/Entities.json",
 * *  "entities": {
 * *         "t::ORDERS": [
 * *             "f::ORDER_NR",
 * *             "f::PRODUCT_ID",
 * *             "f::USER_ID",
 * *             "f::QUANTITY",
 * *             "f::CONFIRM_ORDER"
 * *         ],
 * *     }
 * * }
 */
export default function EntityField({ f_s_Class_Array, TABLENAME }) {
    /**
     * State to open / close Backdrop (lock UI during editig)
     */
    const set_is_Editing = use_M_Store().getState().set_is_Editing;

    const selected_F_S = use_M_Store((state) => state.selected_F_S);

    // const [TABLENAME_State, set_TABLENAME_State] = useState(TABLENAME);
    const set_FIELDNAME_to_update = use_M_Store().getState().set_FIELDNAME_to_update;
    const tablename = TABLENAME.toLowerCase();

    /**
     * useEffect to set input.M_value_KEY in ENTITIES
     */
    useEffect(() => {
        set_FIELDNAME_to_update(tablename, tablename);
    }, [f_s_Class_Array]);

    function render_TABLENAME_State_and_DELETE_Button() {
        return (
            <div className="entities-M_value_KEY-and-delete-button">
                {Render_fieldname_input(TABLENAME.toLowerCase(), "M_value_KEY")}
                <button
                    className="delete-button"
                    onClick={() => {
                        delete_field(TABLENAME.toLowerCase());
                        set_is_Editing(false);
                    }}
                >
                    DELETE
                </button>
                <label className="tch-label">{TABLENAME}</label>
            </div>
        );
    }

    function render_selected_F_S() {
        return (
            <div className="entities-selected-container">
                {Array.isArray(selected_F_S[TABLENAME]) &&
                selected_F_S[TABLENAME].length > 0 ? (
                    selected_F_S[TABLENAME].map((f_s_Class, index) => (
                        <div
                            key={index}
                            className="entities-selected-item"
                            onClick={() => {
                                // remove f::CLASS or s::CLASS from selected_F_S
                                use_M_Store
                                    .getState()
                                    .remove_F_S(TABLENAME, f_s_Class);
                                //save to backend
                                update_M_value_with_selected_F_S(TABLENAME);
                            }}
                        >
                            <span className="entities-icon">❌</span>
                            {f_s_Class.split("::")[1]}
                        </div>
                    ))
                ) : (
                    <div className="entities-label">
                        No fields selected yet.
                    </div>
                )}
            </div>
        );
    }

    return (
        <div className="entities-wrapper-box">
            <div className="entities-container">
                {/* selected_F_S */}
                <div className="entities-seleted_F_S">
                    <label className="entities-label">
                        Table / Selected Fields
                    </label>
                    <div className="entities-left-column">
                        {render_TABLENAME_State_and_DELETE_Button()}
                        {render_selected_F_S()}
                    </div>
                </div>

                {/* f:: s:: all choices */}
                <div>
                    <label className="entities-label">
                        all existing fields to choose
                    </label>
                    {render_All_F_S(f_s_Class_Array, TABLENAME)}
                </div>
            </div>
        </div>
    );
}
