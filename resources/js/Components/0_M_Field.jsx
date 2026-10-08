// resources/js/Components/0_M_Field.jsx
import { useEffect, useState } from "react";

import { use_M_Store } from "@/Stores/0_M_Store";

import { delete_field } from "@/Services/0_M_value_Service";

import { renderDropdown_D } from "@/Components/0_M_Dropdown_D";
import { renderDropdown_U } from "@/Components/0_M_Dropdown_U";
import { renderDropdown_UF } from "@/Components/0_M_Dropdown_UF";
import { renderCheckboxList } from "@/Components/0_M_CheckBox";
import { Render_fieldname_input } from "@/Components/0_M_Input_Group.jsx";
import { Input_Group_JS } from "@/Components/0_M_Input_Group_JS";
import { get_UF_NAME } from "@/Components/0_M_Data_Helper";

export default function Field({ field_data }) {
    const fieldname = field_data[0];
    const is_CURRENCY = field_data[0].toLowerCase() === "currency";

    const { FIELDNAME_to_update, set_FIELDNAME_to_update } = use_M_Store();
    const [show_Input_Group_JS, set_show_Input_Group_JS] = useState(false);

    /**
     * useEffect to set input.M_value_KEY in APP DATA
     */
    useEffect(() => {
        set_FIELDNAME_to_update(fieldname, fieldname);
    }, [field_data]);

    /**
     * State to open / close Backdrop (lock UI during editig)
     */
    const { set_is_Editing } = use_M_Store();

    function make_dropdown_D(label, names) {
        return (
            <div className="field-column">
                <div className="field-label">{label}</div>
                {renderDropdown_D(names, field_data)}
            </div>
        );
    }

    function make_dropdown_U(label, names) {
        return (
            <div className="field-column">
                <div className="field-label">{label}</div>
                {renderDropdown_U(names, field_data)}
            </div>
        );
    }

    function make_dropdown_UF(label, names) {
        return (
            <div className="field-column">
                <div className="field-label">{label}</div>
                {renderDropdown_UF(
                    label,
                    names,
                    field_data,
                    set_show_Input_Group_JS,
                )}
            </div>
        );
    }

    function make_checkbox(label, names) {
        return (
            <div className="field-column">
                <div className="field-label">{label}</div>
                {renderCheckboxList(names, label, field_data)}
            </div>
        );
    }

    /**
     * * for now to make M_Project_UI works
     * * we prevent user from edit CURRENCY field
     * * xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
     * * uf::CURRENCY (UI Formatter) which is neither DB nor UI property ,
     * * uf::CLASS has separate logic e.g. function validata currency
     * * xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
     * * if has_U = true , then render dropdown U and dropdown UF
     */
    const CHECKBOX_and_DROPDOWN = !is_CURRENCY && (
        <div className="field-dropdown-grid">
            {make_dropdown_D("D", ["d"])}
            {make_checkbox("CD", ["cd", "cud"])}
            <div className="u-uf">
                {make_dropdown_U("U", ["u"])}
                <br />
                {make_dropdown_UF("UF", ["uf"])}
            </div>
            {make_checkbox("CU", ["cu", "cud"])}
        </div>
    );

    return (
        <>
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
            {CHECKBOX_and_DROPDOWN}
            {show_Input_Group_JS && (
                <div className="Input_Grup_JS-container">
                    <Input_Group_JS
                        uf_name={get_UF_NAME(field_data)}
                        initial_code=""
                        onClose={() => set_show_Input_Group_JS(false)}
                        onSave={async (ufName, code) => {
                            const xsrf = decodeURIComponent(
                                document.cookie
                                    .split("; ")
                                    .find((c) => c.startsWith("XSRF-TOKEN="))
                                    ?.split("=")[1] || "",
                            );
                            try {
                                const res = await fetch("/api/uf-js/save", {
                                    method: "POST",
                                    headers: {
                                        "Content-Type": "application/json",
                                        Accept: "application/json",
                                        "X-XSRF-TOKEN": xsrf,
                                    },
                                    body: JSON.stringify({
                                        uf_name: ufName,
                                        code,
                                    }),
                                });
                                if (!res.ok) {
                                    const err = await res
                                        .json()
                                        .catch(() => ({}));
                                    alert(
                                        `Save failed (${res.status}): ${err.message || "unknown error"}`,
                                    );
                                    return false;
                                }
                                return true;
                            } catch (e) {
                                alert(`Save failed: ${e.message}`);
                                return false;
                            }
                        }}
                    />
                </div>
            )}
        </>
    );
}
