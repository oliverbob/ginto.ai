// Country detection and selection
function detectAndSetCountry() {
    const countrySelect = document.getElementById('country');
    if (!countrySelect) return;

    // Ensure there's a placeholder option at the top and select it while we detect
    let placeholder = countrySelect.querySelector('option[value=""]');
    if (!placeholder) {
        placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.disabled = true;
        countrySelect.insertBefore(placeholder, countrySelect.firstChild);
    }
    placeholder.textContent = 'Loading country...';
    placeholder.selected = true;
    countrySelect.disabled = true;

    // Function to set country with fallback
    function setCountry(code) {
        if (!code) return false;
        code = code.trim().toUpperCase();
        
        // Try to find the option with exact match
        for (let i = 0; i < countrySelect.options.length; i++) {
            if (countrySelect.options[i].value.toUpperCase() === code) {
                // Remove placeholder option if it exists (we've found the country)
                const loadingOption = countrySelect.querySelector('option[value=""]');
                if (loadingOption) {
                    loadingOption.remove();
                }
                
                // Set the selected country
                countrySelect.selectedIndex = i;
                countrySelect.value = countrySelect.options[i].value;
                countrySelect.disabled = false;
                countrySelect.dispatchEvent(new Event('change'));
                return true;
            }
        }
        return false;
    }

    // First try: IP-based detection
    fetch('https://ipapi.co/json/')
        .then(response => {
            if (!response.ok) throw new Error('IP API response not ok');
            return response.json();
        })
        .then(data => {
            console.log('Country detected:', data.country_code);  // Debug log
            if (data && data.country_code) {
                if (!setCountry(data.country_code)) {
                    throw new Error('Country code not found in list: ' + data.country_code);
                }
            } else {
                throw new Error('No country code in response');
            }
        })
        .catch(err => {
            console.warn('IP-based country detection failed:', err);
            
            // Second try: Browser locale
            try {
                const locale = navigator.language || navigator.userLanguage;
                if (locale) {
                    const countryCode = locale.split('-')[1] || locale.split('_')[1];
                    console.log('Browser locale country:', countryCode);  // Debug log
                    if (!countryCode || !setCountry(countryCode)) {
                        throw new Error('Browser locale country not found or invalid');
                    }
                } else {
                    throw new Error('No browser locale available');
                }
            } catch (e) {
                console.warn('Browser locale detection failed:', e);
                
                // Final fallback: keep a clear placeholder and enable select so user can pick
                const loadingOption = countrySelect.querySelector('option[value=""]');
                if (loadingOption) {
                    loadingOption.textContent = 'Select your country';
                    loadingOption.selected = true;
                    loadingOption.disabled = true;
                }
                countrySelect.disabled = false;
            }
        });
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', detectAndSetCountry);

// Reinitialize after form reset
const memberRegistrationForm = document.getElementById('memberRegistrationForm');
if (memberRegistrationForm) {
    memberRegistrationForm.addEventListener('reset', () => {
        setTimeout(detectAndSetCountry, 100);
    });
}