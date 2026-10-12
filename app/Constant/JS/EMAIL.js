export function validateEmail(value) {
    if (!value || value.trim() === "") {
        return L.must_insert_data;
    }

    const isValidEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!isValidEmail.test(value)) {
        return L.must_correct_email;
    }

    return "";
}