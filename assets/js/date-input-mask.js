// Format card expiry date
function formatExpiryDate(input) {
    let value = input.value.replace(/\D/g, ''); // Remove non-digit characters 
    if (value.length > 2) {
        value = value.slice(0, 2) + '/' + value.slice(2, 4);
    }
    input.value = value;
}

// Replacing Inputs if selected country is not USA
const stateSelectInput = document.querySelector('.state-select-input');
const stateTextInput = document.querySelector('.state-text-input');
const countryInput = document.getElementById('COUNTRY');

stateTextInput.style.display = 'none';

countryInput.addEventListener('change', () => {
    if (countryInput.value !== 'United States') {
        stateSelectInput.style.display = 'none';
        stateTextInput.style.display = 'block';
    } else {
        stateTextInput.style.display = 'none';
        stateSelectInput.style.display = 'block';
    }
});