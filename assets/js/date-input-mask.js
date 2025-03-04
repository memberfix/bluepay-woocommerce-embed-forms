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

// Handle State/Region field based on country selection
document.addEventListener('DOMContentLoaded', function() {
    const stateSelectInput = document.getElementById('STATE_SELECT');
    const stateTextInput = document.getElementById('STATE_TEXT');
    const countryInput = document.getElementById('COUNTRY');
    const form = document.getElementById('bluepay-payment-form');

    if (!stateSelectInput || !stateTextInput || !countryInput || !form) {
        console.error('Required form elements not found');
        return;
    }

    // Initial state
    updateStateFields(countryInput.value);

    // Handle country changes
    countryInput.addEventListener('change', () => {
        updateStateFields(countryInput.value);
    });

    // Handle form submission
    form.addEventListener('submit', function(e) {
        const isUS = countryInput.value === 'United States';
        
        // Update field properties right before submission
        updateStateFields(countryInput.value);
        
        // Validate the visible field
        if (isUS) {
            if (!stateSelectInput.value) {
                e.preventDefault();
                alert('Please select a state');
            }
        } else {
            if (!stateTextInput.value.trim()) {
                e.preventDefault();
                alert('Please enter your region/state');
            }
        }
    });

    function updateStateFields(country) {
        const isUS = country === 'United States';
        stateSelectInput.style.display = isUS ? 'block' : 'none';
        stateTextInput.style.display = isUS ? 'none' : 'block';
        
        // Update required attributes and disable the hidden field
        if (isUS) {
            stateSelectInput.required = true;
            stateSelectInput.name = 'STATE';
            stateTextInput.required = false;
            stateTextInput.name = 'STATE_UNUSED'; // Rename to avoid duplicate names
            stateTextInput.disabled = true; // Disable so it's not included in form submission
        } else {
            stateTextInput.required = true;
            stateTextInput.name = 'STATE';
            stateTextInput.disabled = false;
            stateSelectInput.required = false;
            stateSelectInput.name = 'STATE_UNUSED'; // Rename to avoid duplicate names
            stateSelectInput.disabled = true; // Disable so it's not included in form submission
        }
        
        // Clear values when switching
        stateSelectInput.value = isUS ? stateSelectInput.value : '';
        stateTextInput.value = !isUS ? stateTextInput.value : '';
    }
});