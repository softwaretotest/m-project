// \resources\js\Components\0_M_Data_Helper.js
import { M_value_Service } from "@/Services/0_M_value_Service";
import { use_M_Store } from "@/Stores/0_M_Store";
import { find_NEW_D_Params_in_M_MAP } from "@/Components/0_M_D_Params_Service";
import { GLOBAL_METADATA } from "@/Providers/0_M_DataProvider";

/**
 * @param {*} field_data = e.g.
 * * ['image', 'u::FILE', 'd::INTEGER',  ['cd::DEFAULT', 0] ]
 * * ['price', 'u::NUMBER', ['d::DECIMAL',10,2] ,  ['cd::DEFAULT', 0] ]
 * @returns d_items[0] = 'd::INTEGER' or ['d::DECIMAL',10,2]
 * * there is always only one d:: in field_data
 */
export function find_d_item(field_data) {
    /**
     * * in case M_DATA -- Subtab = D ,
     * * typeOf field_data = string , e.g. boolean, integer
     * * TODO: make logic for this , if needed
     */
    if (!Array.isArray(field_data)) return;

    const d_item = field_data.find((item) => {
        const value = Array.isArray(item) ? item[0] : item;
        return typeof value === "string" && value.startsWith("d::");
    });
    return d_item;
}

/**
 * @param {*} field_data = e.g.
 * * ['price', 'u::NUMBER', 'uf::CURRENCY', ['d::DECIMAL',10,2] ,  ['cd::DEFAULT', 0] ]
 * @returns uf_items[0] = 'uf::CURRENCY'
 * * there is always only one d:: in field_data
 */
export function find_uf_item(field_data) {
    if (!Array.isArray(field_data)) return;

    const uf_item = field_data.find((item) => {
        return typeof item === "string" && item.startsWith("uf::");
    });
    // console.log(
    //     "fjdksajfkdlsöfjdsklaö --- Data_Helper - find_uf_item = ",
    //     uf_item,
    // );
    return uf_item;
}

/**
 * @param {*} field_data = e.g.
 * * ['image', 'u::FILE', 'd::INTEGER',  ['cd::DEFAULT', 0] ]
 * * ['price', 'u::NUMBER', ['d::DECIMAL',10,2] ,  ['cd::DEFAULT', 0] ]
 * @returns u_items[0] = 'u::FILE' , 'u::NUMBER'
 * * there is always only one d:: in field_data
 */
export function find_u_item(field_data) {
    /**
     * * in case M_DATA -- Subtab = D ,
     * * typeOf field_data = string , e.g. boolean, integer
     * * TODO: make logic for this , if needed
     */
    if (!Array.isArray(field_data)) return;

    const u_item = field_data.find((item) => {
        return typeof item === "string" && item.startsWith("u::");
    });
    return u_item;
}

/**
 * * function for pull D Class from field_data
 * * e.g. ['image', 'd::INTEGER'] -> 'INTEGER'
 */
export function get_D_NAME(field_data) {
    const d_item = find_d_item(field_data);

    if (!d_item) return;

    // pull String
    const base_d_value = Array.isArray(d_item) ? d_item[0] : d_item;

    return base_d_value.replace("d::", "");
}

/**
 * * function for pull D Class from field_data
 * * e.g. ['image', 'd::INTEGER'] -> 'INTEGER'
 */
export function get_U_NAME(field_data) {
    const u_item = find_u_item(field_data);

    if (!u_item) return;

    // pull String
    const base_u_value = Array.isArray(u_item) ? u_item[0] : u_item;

    return base_u_value.replace("u::", "");
}

/**
 * * function for pull D Class from field_data
 * * e.g. ['price', ['d::DECIMAL',10,2] ,  'u::NUMBER', 'uf::CURRENCY'] -> 'CURRENCY'
 */
export function get_UF_NAME(field_data) {
    const uf_item = find_uf_item(field_data);

    if (!uf_item) return;

    // pull String
    const base_uf_value = Array.isArray(uf_item) ? uf_item[0] : uf_item;

    return base_uf_value.replace("uf::", "");
}

/**
 * * remove all cd: from field_data
 * * e.g. ['image', 'cd::INDEX' , 'u::TEXT'] -> ['image', 'u::TEXT']
 * * e.g. ['image', ['cd::DEFAULT',10,2] ] -> ['image']
 */
export function remove_cd(field_data) {
    if (!Array.isArray(field_data)) return [];
    return field_data.filter((item) => {
        const targetString = Array.isArray(item) ? item[0] : item;
        const isCD =
            typeof targetString === "string" && targetString.startsWith("cd::");
        return !isCD;
    });
}

/**
 * * remove all d: from field_data
 * * e.g. ['image', 'd::STRING' , 'u::TEXT'] -> ['image', 'u::TEXT']
 * * e.g. ['image', ['d::STRING',255] ] -> ['image']
 */
export function remove_d_u_uf(field_data) {
    if (!Array.isArray(field_data)) return [];
    return field_data.filter((item) => {
        const targetString = Array.isArray(item) ? item[0] : item;
        const isD =
            typeof targetString === "string" && targetString.startsWith("d::");
        const isU =
            typeof targetString === "string" && targetString.startsWith("u::");
        const isUF =
            typeof targetString === "string" && targetString.startsWith("uf::");
        return !isD && !isU && !isUF;
    });
}

/**
 * * add new d:: get D_Params from D_PARAMS_MAP
 * * e.g. ['image'] -> ['image' , 'd::INTEGER' , 'u::TEXT']
 * * e.g. ['stock'] -> ['stock' , ['d::DECIMAL',10,2] , 'u::TEXT']
 * * ------------------------------------------------
 * * U_NAME could be undefined by user
 * * e.g. ['price'] -> ['price' , ['d::DECIMAL',10,2]]
 * * ------------------------------------------------
 * * D_NAME = "INTEGER" :
 * * in case user wanna change a Foreign Key field
 */
export function add_NEW_d_u_uf(
    field_data_without_d_u,
    D_NAME = "INTEGER",
    U_NAME,
    UF_NAME,
) {
    // ADD NEW D
    if (!Array.isArray(field_data_without_d_u)) return [];
    const d_params = find_NEW_D_Params_in_M_MAP(D_NAME);
    let field_data_with_NEW_d = [];
    if (!d_params) {
        field_data_with_NEW_d = [...field_data_without_d_u, `d::${D_NAME}`];
    } else {
        field_data_with_NEW_d = [
            ...field_data_without_d_u,
            [`d::${D_NAME}`, ...d_params],
        ];
    }

    // ADD NEW U (could be undefined by user)
    let field_data_with_NEW_d_u = null;
    if (U_NAME) {
        field_data_with_NEW_d_u = [...field_data_with_NEW_d, `u::${U_NAME}`];
    } else {
        field_data_with_NEW_d_u = [...field_data_with_NEW_d];
    }

    // ADD NEW U (could be undefined by user)
    let field_data_with_NEW_d_u_uf = null;
    if (UF_NAME) {
        field_data_with_NEW_d_u_uf = [
            ...field_data_with_NEW_d_u,
            `uf::${UF_NAME}`,
        ];
    } else {
        field_data_with_NEW_d_u_uf = [...field_data_with_NEW_d_u];
    }

    return field_data_with_NEW_d_u_uf;
}

/**
 * * check if field_data has "d::NAME" or ["d::NAME", params ]
 */
export function has_d_in_field_data(field_data) {
    if (!Array.isArray(field_data)) return false;

    return field_data.some((item) => {
        const targetString = Array.isArray(item) ? item[0] : item;

        return (
            typeof targetString === "string" && targetString.startsWith("d::")
        );
    });
}

/**
 * * check if field_data has "d::NAME" or ["d::NAME", params ]
 * * this func. called e.g. onChange of checkbox FOREIGN
 * * before calling value_updater_CD, where cd::FOREIGN
 * * will be added to M_value Backend like any other CDs
 */
export async function remove_D_U_UF_from_Backend() {
    const store = use_M_Store.getState();
    const activeField = use_M_Store.getState().activeField;
    const fieldname = activeField.toLowerCase();
    const new_M_value = { ...store.M_value };
    const field_data = new_M_value[fieldname.toUpperCase()];
    const field_data_without_d_u_uf = remove_d_u_uf(field_data);
    new_M_value[fieldname.toUpperCase()] = field_data_without_d_u_uf;
    await M_value_Service.update(new_M_value);
}

export async function update_D_U_UF_SAVE_Backend(D_NAME, U_NAME, UF_NAME) {
    const store = use_M_Store.getState();
    const activeField = use_M_Store.getState().activeField;
    const fieldname = activeField.toLowerCase();
    const new_M_value = { ...store.M_value };
    const field_data = new_M_value[fieldname.toUpperCase()];
    const field_data_without_d_u_uf = remove_d_u_uf(field_data);

    const field_data_with_NEW_d_u_uf = add_NEW_d_u_uf(
        field_data_without_d_u_uf,
        D_NAME,
        U_NAME,
        UF_NAME,
    );
    new_M_value[fieldname.toUpperCase()] = field_data_with_NEW_d_u_uf;
    await M_value_Service.update(new_M_value);
}

/**
 *
 * @param {*} FIELDNAME e.g IMAGE , STOCK
 * @returns e.g. INTEGER , DECIMAL
 */
export function get_D_NAME_by_FIELDNAME(FIELDNAME) {
    const store = use_M_Store.getState();
    const fiel_data = store.M_value[FIELDNAME];
    return get_D_NAME(fiel_data);
}

/**
 *
 * @param {*} FIELDNAME e.g IMAGE , STOCK
 * @returns e.g. TEXT , NUMBER
 */
export function get_U_NAME_by_FIELDNAME(FIELDNAME) {
    const store = use_M_Store.getState();
    const fiel_data = store.M_value[FIELDNAME];
    return get_U_NAME(fiel_data);
}

/**
 *
 * @param {*} FIELDNAME e.g PRICE
 * @returns e.g. CURRENCY
 */
export function get_UF_NAME_by_FIELDNAME(FIELDNAME) {
    const store = use_M_Store.getState();
    const fiel_data = store.M_value[FIELDNAME];
    return get_UF_NAME(fiel_data);
}

/**
 * set seleted_D and selected_U and selected_*_FOREIGN by field_data
 * @param {*} field_data
 */
export function set_selected_D_U_UF_FOREIGN(field_data) {
    const fieldname = field_data[0];

    const selected_D = use_M_Store.getState().selected_D;
    const selected_U = use_M_Store.getState().selected_U;
    const selected_UF = use_M_Store.getState().selected_UF;
    const set_selected_D = use_M_Store.getState().set_selected_D;
    const set_selected_U = use_M_Store.getState().set_selected_U;
    const set_selected_UF = use_M_Store.getState().set_selected_UF;
    const set_selected_D_FOREIGN =
        use_M_Store.getState().set_selected_D_FOREIGN;
    const set_selected_U_FOREIGN =
        use_M_Store.getState().set_selected_U_FOREIGN;
    const set_selected_UF_FOREIGN =
        use_M_Store.getState().set_selected_UF_FOREIGN;

    const D_NAME = get_D_NAME(field_data);
    const U_NAME = get_U_NAME(field_data);
    const UF_NAME = get_UF_NAME(field_data);

    if (D_NAME) set_selected_D(fieldname, D_NAME);
    if (U_NAME) set_selected_U(fieldname, U_NAME);
    if (UF_NAME) set_selected_UF(fieldname, UF_NAME);

    set_selected_D_FOREIGN(fieldname, selected_D[fieldname]);
    set_selected_U_FOREIGN(fieldname, selected_U[fieldname]);
    set_selected_UF_FOREIGN(fieldname, selected_UF[fieldname]);
}

/**
 * * clone field_data with new_fieldname
 * @param {*} old_field_data e.g. ['image' , ['d::STRING',255] , 'u::FILE ]
 * @param {*} new_fieldname
 * @returns new_field_data e.g. ['product_image' , ['d::STRING',255] , 'u::FILE ]
 */
export function change_fieldname_in_field_data(field_data, new_fieldname) {
    const new_field_data = [...field_data];
    new_field_data[0] = new_fieldname;
    return new_field_data;
}
