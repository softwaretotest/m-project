// resources/js/Providers/0_M_DataProvider.jsx

import { createContext, useContext, useEffect, useState } from "react";
import { API } from "@/Configs/api";
import { use_M_Store } from "@/Stores/0_M_Store";

export let GLOBAL_METADATA = null;

const MetadataContext = createContext();

export const M_DataProvider = ({ children }) => {
    const has_M_value_Change = use_M_Store((state) => state.has_M_value_Change);
    const set_has_M_value_Change = use_M_Store(
        (state) => state.set_has_M_value_Change,
    );
    const set_hasJSON_Change = use_M_Store((state) => state.set_hasJSON_Change);

    const [metadata, setMetadata] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const fetchMetadata = async () => {
            try {
                const res = await fetch(API.M_VALUE_ENDPOINT);
                if (!res.ok)
                    throw new Error("HTTP error! status: " + res.status);
                const data = await res.json();

                GLOBAL_METADATA = data;
                setMetadata(data);
                setLoading(false);
                set_hasJSON_Change(true);
                set_has_M_value_Change(false);
            } catch (err) {
                console.warn(
                    "Metadata not found or empty. Initializing default structure...",
                    err.message,
                );

                // TODO: automation case failer e.g fire API to send Initial Default Template to Backend to create new JSON

                setLoading(false);
            }
        };

        fetchMetadata();
    }, [has_M_value_Change]);

    if (loading)
        return (
            <div>
                M_DataProvider is loading Metadata... from Backen [SEE ERROR IN
                DevTool Console]{" "}
            </div>
        );

    return (
        <MetadataContext.Provider value={metadata}>
            {children}
        </MetadataContext.Provider>
    );
};

export const use_M_Data = () => useContext(MetadataContext);

/**
 * * logic to get all fieldnames from GLOBAL_METADATA
 * * @returns all f:: and s:: field names
 * * e.g. [NAME, PRICE, STOCK, EMAIL, CURRENCY, etc. ]
 */
export function get_all_fieldnames() {
    let all_fieldnames = [];
    const field_datas = {
        ...GLOBAL_METADATA?.app_data?.f,
        ...GLOBAL_METADATA?.m_data?.s,
    };

    Object.values(field_datas).forEach((field_data) => {
        all_fieldnames.push(field_data[0].toUpperCase());
    });

    return all_fieldnames;
}

/**
 * find out if selected choice from f::CLASS or s::CLASS
 * @param {*} choice_F_S e.g. EMAIL , NAME , IS_ACTIVE
 * @returns e.g. s::EMAIL , f::NAME , f::IS_ACTIVE
 */
export function findout_F_or_S(choice_F_S) {
    const all_F_KEY = Object.keys(GLOBAL_METADATA.app_data.f);
    const all_S_KEY = Object.keys(GLOBAL_METADATA?.m_data?.s);

    if (all_F_KEY?.includes(choice_F_S)) {
        return `f::${choice_F_S}`;
    }
    if (all_S_KEY?.includes(choice_F_S)) {
        return `s::${choice_F_S}`;
    }

    return choice_F_S;
}
