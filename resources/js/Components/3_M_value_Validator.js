     /**
      * Validate input data for duplicate keys or tables.
      * 
      * @param {Object} imported_json_data - The JSON payload to validate.
      *   {
      *     _comment: String,
      *     f: { field_name: String, ... },
      *     s: { field_name: String, ... },
      *     entities: { table_name: [field_name, ...], ... }
      *   }
      * @return {Object} Validation result containing error status and duplicate lists.
      *   {
      *     has_error: Boolean,
      *     duplicate_list: Array,
      *     error_message: String|null
      *   }
      */
     export function check_duplicate(imported_json_data) {
         const seen_key_set = new Set();
         const duplicate_key_list = [];
         const comment_string = imported_json_data?._comment || "";

         if (comment_string.includes("App-Data.json") || imported_json_data.f) {
             const category_list = ["f", "s"];
             category_list.forEach(category_key => {
                 if (imported_json_data[category_key]) {
                     Object.keys(imported_json_data[category_key]).forEach(field_name => {
                         if (seen_key_set.has(field_name)) {
                             duplicate_key_list.push(field_name);
                         } else {
                             seen_key_set.add(field_name);
                         }
                     });
                 }
             });
         } else if (comment_string.includes("Entities.json") || imported_json_data.entities) {
             const entity_map = imported_json_data.entities || imported_json_data;
             Object.keys(entity_map).forEach(table_name => {
                 if (seen_key_set.has(table_name)) {
                     duplicate_key_list.push(`Table: ${table_name}`);
                 } else {
                     seen_key_set.add(table_name);
                 }
                 
                 const field_array = entity_map[table_name];
                 if (Array.isArray(field_array)) {
                     const field_seen_set = new Set();
                     field_array.forEach(field_name => {
                         if (field_seen_set.has(field_name)) {
                             duplicate_key_list.push(field_name);
                         } else {
                             field_seen_set.add(field_name);
                         }
                     });
                 }
             });
         }

         return {
             has_error: duplicate_key_list.length > 0,
             duplicate_list: duplicate_key_list,
             error_message: duplicate_key_list.length > 0 
                 ? `Duplicate keys found: ${duplicate_key_list.join(", ")}` 
                 : null
         };
     }