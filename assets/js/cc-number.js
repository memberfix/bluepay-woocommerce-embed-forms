document.addEventListener("DOMContentLoaded", function () {
    const ccInput = document.getElementById("CC_NUM");
    if (ccInput) {
        ccInput.addEventListener("input", function (e) {
            let value = e.target.value.replace(/\D/g, ''); // Remove non-digit characters
            value = value.match(/.{1,4}/g)?.join(' ') || value; // Group digits in sets of 4
            e.target.value = value;
        });
    } else {
        console.error("Element with ID 'CC_NUM' not found");
    }
});
