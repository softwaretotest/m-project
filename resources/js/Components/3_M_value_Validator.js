import { GLOBAL_METADATA } from "@/Providers/0_M_DataProvider";
import { check_SpecialField } from "@/Components/0_M_Data_Helper";
import { merge_M_value_App_Data_T } from "@/Services/0_M_value_Service";
/**
 * Main validation function for imported JSON payload against global metadata.
 *
 * @param {Object} imported_json_data
 * @returns {Object} Validation result containing error status and messages.
 */
export function validate_imported_json(imported_json_data) {
    const seen_key_set = new Set();
    const duplicate_key_list = [];

    const duplicate_key_list_error = [];
    const invalid_value_list = [];
    const existence_entities_f_s_list = [];
    const duplicate_entities_f_s_list = [];
    const new_tablename_list = [];

    const comment_string = imported_json_data?._comment || "";

    const is_app_data =
        comment_string.includes("App-Data.json") || imported_json_data.f;
    const is_entities =
        comment_string.includes("Entities.json") || imported_json_data.entities;

    if (is_app_data) {
        const F_items =
            GLOBAL_METADATA?.app_data?.f || GLOBAL_METADATA?.f || {};
        Object.keys(F_items).forEach((key) => seen_key_set.add(key));

        const S_items = GLOBAL_METADATA?.m_data?.s || GLOBAL_METADATA?.s || {};
        Object.keys(S_items).forEach((key) => seen_key_set.add(key));

        check_Duplicate_f_s_entities_and_validate_fieldname(
            imported_json_data,
            /* subTabs */ ["f", "s"],
        );
    }

    if (is_entities) {
        const entities = GLOBAL_METADATA?.entities?.entities || {};
        Object.keys(entities).forEach((table) => seen_key_set.add(table));

        check_Duplicate_f_s_entities_and_validate_fieldname(
            imported_json_data,
            /* subTabs */ ["entities"],
        );

        //other Entities Checks
        const entities_imported = imported_json_data.entities;
        check_Existence_Entities_F_S(entities_imported);
        check_Duplicate_Entities_F_S(entities_imported);
        insert_new_tablename(entities_imported);
    }

    const total_errors = [
        ...duplicate_key_list_error,
        ...invalid_value_list,
        ...existence_entities_f_s_list,
        ...duplicate_entities_f_s_list,
    ];

    return {
        has_error: total_errors.length > 0,
        error_message:
            // add newline to show in dangerouslySetInnerHTML={{ __html: error_Dashboard }}
            total_errors.length > 0 ? total_errors.join("<br />") : null,
    };

    /**
     * Check if fields referenced in entities exist in GLOBAL_METADATA (f or s).
     *
     * @param {Object} entities_imported
     */
    function check_Existence_Entities_F_S(entities_imported) {
        const existing_f =
            GLOBAL_METADATA?.app_data?.f || GLOBAL_METADATA?.f || {};
        const existing_s =
            GLOBAL_METADATA?.m_data?.s || GLOBAL_METADATA?.s || {};

        Object.entries(entities_imported || {}).forEach(
            ([table_name, field_list]) => {
                if (!Array.isArray(field_list)) return;

                field_list.forEach((field_string) => {
                    // Expected format: "f::NAME" or "s::EMAIL"
                    const parts = field_string.split("::");
                    if (parts.length === 2) {
                        const prefix = parts[0].toLowerCase();
                        const field_key = parts[1].toUpperCase();

                        let exists = false;
                        if (prefix === "f") {
                            exists = Object.prototype.hasOwnProperty.call(
                                existing_f,
                                field_key,
                            );
                        } else if (prefix === "s") {
                            exists = Object.prototype.hasOwnProperty.call(
                                existing_s,
                                field_key,
                            );
                        }

                        if (!exists) {
                            existence_entities_f_s_list.push(
                                `ENTITY Field NOT FOUND ===> Table: '${table_name}' references missing field '${field_string}'`,
                            );
                        }
                    }
                });
            },
        );
    }

    /**
     * Check for duplicate fields within each entity/table.
     *
     * @param {Object} entities_imported
     */
    function check_Duplicate_Entities_F_S(entities_imported) {
        Object.entries(entities_imported || {}).forEach(
            ([table_name, field_list]) => {
                if (!Array.isArray(field_list)) return;

                const seen_fields_in_table = new Set();

                field_list.forEach((field_string) => {
                    if (seen_fields_in_table.has(field_string)) {
                        duplicate_entities_f_s_list.push(
                            `DUPLICATE FIELD IN ENTITY ===> Table: '${table_name}' has duplicate field '${field_string}'`,
                        );
                    } else {
                        seen_fields_in_table.add(field_string);
                    }
                });
            },
        );
    }

    /**
     * Validate :
     * xxxxxxxxxx
     * 1. app data categories (f and s) for duplicate keys
     * 2. fieldname = naming conventions.
     *
     * @param {Object} imported_json_data
     * @param {Set} seen_key_set
     * @param {Array} duplicate_key_list
     * @param {Array} invalid_value_list
     */
    function check_Duplicate_f_s_entities_and_validate_fieldname(
        imported_json_data,
        subTabs,
    ) {
        subTabs.forEach((subTab) => {
            const subTab_data = imported_json_data?.[subTab];
            if (!subTab_data) return;

            Object.entries(subTab_data).forEach(([M_value_KEY, field_data]) => {
                // 1. check app data categories (f and s) for duplicate keys
                if (seen_key_set.has(M_value_KEY)) {
                    duplicate_key_list.push(M_value_KEY);
                    const is_SpecialField = check_SpecialField(M_value_KEY);

                    const text_SpecialField = is_SpecialField
                        ? "( = Special Field in M-Data )"
                        : "";
                    duplicate_key_list_error.push(
                        `Duplicate KEY : ${M_value_KEY} ${text_SpecialField}`,
                    );
                } else {
                    seen_key_set.add(M_value_KEY);
                }

                if (["f", "s"].includes(subTab)) {
                    const fieldname = field_data[0];
                    if (fieldname !== M_value_KEY.toLowerCase()) {
                        invalid_value_list.push(
                            `INVALID fieldname convention ===> '${M_value_KEY}' : '${fieldname}'`,
                        );
                    }
                }
            });
        });
    }

    /**
     * Check entities table names against app_data.t,
     * and collect missing ones into new_tablename_list.
     *
     * @param {Object} entities_imported
     */
    function insert_new_tablename(entities_imported) {
        const T_items = GLOBAL_METADATA.app_data?.t;
        const T_items_set = new Set(
            Object.keys(T_items).map((key) => key.toUpperCase()),
        );

        Object.keys(entities_imported || {}).forEach((table_name) => {
            const upper_table_name = table_name.toUpperCase();
            if (!T_items_set.has(upper_table_name)) {
                // เก็บชื่อตารางใหม่ในรูปแบบที่พร้อมนำไปอัปเดตเข้า app_data.t
                new_tablename_list.push({
                    key: upper_table_name,
                    value: table_name.toLowerCase(),
                });
            }
        });

        merge_M_value_App_Data_T(new_tablename_list);
    }
}
