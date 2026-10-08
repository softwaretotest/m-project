// resources/js/Components/0_M_Dropdown_U.jsx

import { prepare_new_M_value_for_Update_UF } from "@/Components/0_M_value_Updater_UF";
import { find_uf_item } from "@/Components/0_M_Data_Helper";

import { use_M_Store } from "@/Stores/0_M_Store";
import { M_value_Service } from "@/Services/0_M_value_Service";

/**
 * Renders a dropdown select element
 * *
 * * Example usage:
 * * M_Class_Name_List: e.g. ['uf']
 * * field_data = e.g. ['price', ['d::DECIMAL',10,2], 'uf::NUMBER', 'uf::CURRENCY']
 * *
 * * selected_UF_of_field_data = only for refresh e.g.
 * * <select selected_UF_of_field_data="NUMBER"> ... </select>
 * *
 * * Params:
 * * selected_UF_of_field_data = fieldname , e.g. NUMBER , FILE , TEXT
 */
export function render_Button_UF_checker(label, name, field_data) {
    const { M_value } = use_M_Store();

    const fieldname = field_data[0];
    const fieldDataList = field_data ? field_data.slice(1) : [];

    /**
     * UF_String = e.g. uf::NUMBER , uf::FILE , uf::TEXT
     */
    const UF_String = fieldDataList.find((item) => {
        let valueToTest = Array.isArray(item) ? item[0] : item;

        let isMatch =
            typeof valueToTest === "string" && valueToTest.startsWith("uf::");

        return isMatch;
    });

    let selected_UF_of_field_data = "";
    /**
     * * UF_String = e.g. u::NUMBER, uf::CURRENCY , [d:DECIMAL,10,2]
     * */
    if (UF_String && UF_String.startsWith("uf::")) {
        //case string e.g. uf:: and  uf::
        selected_UF_of_field_data = UF_String.split("::")[1];
    }

    // console.log(
    //     ")=)=)=)=)=)=) Dropdown_U fieldname =",
    //     fieldname.padEnd(13),
    //     "\t\t UF_String =",
    //     UF_String,
    // );

    /**
     * * selected_U: state from dropdown e.g. STRING , DECIMAL , INTEGER
     * * handle_Change: update state when option change
     */
    const selected_UF = use_M_Store((state) => state.selected_UF);
    const set_selected_UF = use_M_Store.getState().set_selected_UF;

    const selected_UF_to_show =
        selected_UF[fieldname] !== undefined
            ? selected_UF[fieldname]
            : selected_UF_of_field_data;

    /**
     * * set_selected_UF
     * * prepare_new_M_value_for_Update_UF
     * * set_M_value
     * * update JSON file (App-Data.json , M-Data.json , Entities.json)
     * * update JSON View (JSON_Content.jsx)
     */
    async function set_UF_Actions(event) {
        const new_selected_UF = event.target.value;

        // update UI
        set_selected_UF(fieldname, new_selected_UF);

        // prepare new data
        const new_M_value = prepare_new_M_value_for_Update_UF(
            new_selected_UF,
            M_value,
        );

        await M_value_Service.update(new_M_value);
    }

    const has_UF = Boolean(find_uf_item(field_data));
    const is_Foreign = use_M_Store
        .getState()
        .checked_CD[fieldname]?.includes("FOREIGN");
    //     return (
    //                {has_UF && !is_Foreign && (
    //        <>
    //            <div className="field-label">{label} Checker</div>
    //            <button
    //                className="btn-setting"
    //                value={selected_UF_to_show}
    //                disabled={is_Foreign || !has_UF}
    //            >
    //                SYNC
    //            </button>
    //        </>
    //    )}
    //     );

    return (
        <>
            {has_UF && !is_Foreign && (
                <>
                    <div className="field-label">{label} Checker</div>
                    <button
                        className="btn-setting"
                        value={selected_UF_to_show}
                        disabled={is_Foreign || !has_UF}
                    >
                        SYNC
                    </button>
                </>
            )}
        </>
    );
}
