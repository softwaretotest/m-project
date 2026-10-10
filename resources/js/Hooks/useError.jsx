// \resources\js\Hooks\useError.js
import { use_M_Store } from "@/Stores/0_M_Store";

/**
 * this component is for showing error of Fieldname to add or update
 * @returns
 */
export function useError() {
    const { Error_FIELDNAME, set_Error_FIELDNAME } = use_M_Store();
    const set_error = use_M_Store.getState().set_error;

    /**
     * * validate fieldname and show error if exists
     * * REGEX : explanation
     * * replace(/\s+/g, '_'); = replace whitepace
     * * alphanumeric_regex = /^[A-Z0-9_]*$/; = other symbole not allow except _
     * @param {*} event = value of fieldname input onChange
     * @returns
     */
    async function handle_Fieldname_Change(event) {
        const fieldname = event.target.value;
        const classNames = event.target.className;

        const is_ADD = classNames.includes("new_field_name");
        const is_UPDATE = classNames.includes("M_value_KEY");

        const set_FIELDNAME_to_add =
            use_M_Store.getState().set_FIELDNAME_to_add;
        const set_FIELDNAME_to_update =
            use_M_Store.getState().set_FIELDNAME_to_update;

        let FIELDNAME = fieldname.toUpperCase().replace(/\s+/g, "_");
        FIELDNAME = FIELDNAME.trim();

        // logic to validate alphanumeric
        const alphanumeric_regex = /^[A-Z0-9_]*$/;
        if (!alphanumeric_regex.test(FIELDNAME)) {
            set_error("Only alphanumeric and underscore are allowed.");
            return;
        }

        /**
         * * prevent Case t::12345 ,
         * * because of PHP static syntax not allow only number as static call
         */
        const pure_numeric_regex = /^\d+$/;
        if (pure_numeric_regex.test(FIELDNAME)) {
            set_error(`Fieldname ${FIELDNAME} cannot be purely numeric.`);
            return;
        }

        /**
         * * php does not allow variable starts with number
         * * e.g. not t::123_ , but t::_123
         */
        const starts_with_number_regex = /^\d/;
        if (starts_with_number_regex.test(FIELDNAME)) {
            set_error(`Fieldname ${FIELDNAME} cannot start with a number.`);
            return;
        }

        const M_value = use_M_Store.getState().M_value;
        const activeField = use_M_Store.getState().activeField;

        const isDuplicate = M_value && Object.keys(M_value).includes(FIELDNAME);

        if (isDuplicate) {
            set_error(
                `This field \u2003 ${fieldname.toUpperCase()} \u2003 already exists.`,
            );
            return;
        }

        if (!FIELDNAME) {
            set_error("Fieldname cannot be empty.");
            return;
        }

        set_Error_FIELDNAME("");

        // Case add_field()
        if (is_ADD) {
            set_FIELDNAME_to_add(FIELDNAME);
        }

        // Case update_field()
        if (is_UPDATE && activeField) {
            set_FIELDNAME_to_update(activeField, FIELDNAME);
        }
    }

    return {
        Error_FIELDNAME,
        handle_Fieldname_Change,
    };
}
