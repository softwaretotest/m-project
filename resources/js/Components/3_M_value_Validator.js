import { GLOBAL_METADATA } from "@/Providers/0_M_DataProvider";

/**
 * Main validation function for imported JSON payload against global metadata.
 *
 * @param {Object} imported_json_data
 * @returns {Object} Validation result containing error status and messages.
 */
export function validate_imported_json(imported_json_data) {
    const seen_key_set = new Set();
    const duplicate_key_list = [];
    const invalid_value_list = [];

    const comment_string = imported_json_data?._comment || "";
    const current_metadata = GLOBAL_METADATA || {};

    const is_app_data =
        comment_string.includes("App-Data.json") || imported_json_data.f;
    const is_entities =
        comment_string.includes("Entities.json") || imported_json_data.entities;

    if (is_app_data) {
        const existing_f =
            current_metadata?.app_data?.f || current_metadata?.f || {};
        Object.keys(existing_f).forEach((key) => seen_key_set.add(key));

        const existing_s =
            current_metadata?.m_data?.s || current_metadata?.s || {};
        Object.keys(existing_s).forEach((key) => seen_key_set.add(key));

        validateAppData(
            imported_json_data,
            seen_key_set,
            duplicate_key_list,
            invalid_value_list,
        );
    } else if (is_entities) {
        const existing_entities = current_metadata?.entities || {};
        Object.keys(existing_entities).forEach((table) =>
            seen_key_set.add(table),
        );

        validateEntities(imported_json_data, seen_key_set, duplicate_key_list);
    }

    const total_errors = [...duplicate_key_list, ...invalid_value_list];

    return {
        has_error: total_errors.length > 0,
        duplicate_list: duplicate_key_list,
        invalid_value_list: invalid_value_list,
        error_message:
            // add newline to show in dangerouslySetInnerHTML={{ __html: error_Dashboard }}
            total_errors.length > 0 ? total_errors.join("<br />") : null,
    };
}

/**
 * Validate app data categories (f and s) for duplicate keys and naming conventions.
 *
 * @param {Object} imported_data
 * @param {Set} seen_set
 * @param {Array} dup_list
 * @param {Array} invalid_list
 */
function validateAppData(imported_data, seen_set, dup_list, invalid_list) {
    const category_list = ["f", "s"];

    category_list.forEach((category_key) => {
        const category_obj = imported_data?.[category_key];
        if (!category_obj) return;

        Object.entries(category_obj).forEach(([M_value_KEY, field_value]) => {
            if (seen_set.has(M_value_KEY)) {
                dup_list.push(M_value_KEY);
            } else {
                seen_set.add(M_value_KEY);
            }

            const fieldname = field_value[0];
            if (fieldname !== M_value_KEY.toLowerCase()) {
                invalid_list.push(
                    `INVALID fieldname convention ===> '${M_value_KEY}' : '${fieldname}'`,
                );
            }
        });
    });
}

/**
 * Validate entities mapping for duplicate table names and field names.
 *
 * @param {Object} imported_data
 * @param {Set} seen_set
 * @param {Array} dup_list
 */
function validateEntities(imported_data, seen_set, dup_list) {
    const entity_map = imported_data?.entities || {};

    Object.entries(entity_map).forEach(([table_name, field_array]) => {
        if (seen_set.has(table_name)) {
            dup_list.push(`Table: ${table_name}`);
        } else {
            seen_set.add(table_name);
        }

        const field_seen_set = new Set();
        (field_array || []).forEach((field_item) => {
            const fieldname = field_item[0];
            if (field_seen_set.has(fieldname)) {
                dup_list.push(fieldname);
            } else {
                field_seen_set.add(fieldname);
            }
        });
    });
}
