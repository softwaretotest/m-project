/**
 * sanitize currency input while typing
 * - allow numbers, dot, and comma
 * - convert comma to dot
 * - prevent multiple decimal points
 */ 
export const normalizeCurrencyInput = (value) => {
    // allow only numbers, dot, comma
    if (!/^[0-9.,]*$/.test(value)) {
        return null;
    }

    // convert comma to dot
    value = value.replace(/,/g, ".");

    // split decimal parts
    const parts = value.split(".");

    // prevent multiple dots
    if (parts.length > 2) {
        return null;
    } 

    // rebuild clean value
    let result = parts[0] ?? "";

    if (value.includes(".")) {
        result = parts[0] + "." + (parts[1] ?? "");
    }

    return result;
};