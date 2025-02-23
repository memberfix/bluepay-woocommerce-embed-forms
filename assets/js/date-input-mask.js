// Format card expiry date
function formatExpiryDate(e) {
    var inputChar = String.fromCharCode(event.keyCode);
    var code = event.keyCode;
    var allowedKeys = [8];
    if (allowedKeys.indexOf(code) !== -1) {
      return;
    }
  
    event.target.value = event.target.value.replace(
      /^([1-9]\/|[2-9])$/g, '0$1/' // 3 > 03/
    ).replace(
      /^(0[1-9]|1[0-2])$/g, '$1/' // 11 > 11/
    ).replace(
      /^([0-1])([3-9])$/g, '0$1/$2' // 13 > 01/3
    ).replace(
      /^(0?[1-9]|1[0-2])([0-9]{2})$/g, '$1/$2' // 141 > 01/41
    ).replace(
      /^([0]+)\/|[0]+$/g, '0' // 0/ > 0 and 00 > 0
    ).replace(
      /[^\d\/]|^[\/]*$/g, '' // To allow only digits and `/`
    ).replace(
      /\/\//g, '/' // Prevent entering more than 1 `/`
    );
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