/**
 * MonsterInsights Admin Setup Wizard Handler
 * Handles the click event for monsterinsights-setup-wizard-link buttons
 */

(function() {
    'use strict';

    /**
     * Launch the setup wizard by making an AJAX request
     */
    function launchSetupWizard() {
        // Show loading message
        if (window.monsterinsights && window.monsterinsights.loading_toast) {
            window.monsterinsights.loading_toast('Preparing setup wizard...');
        }

        // Create form data for AJAX request
        let formData = new FormData();
        formData.append('action', 'monsterinsights_generate_setup_wizard_url');
        formData.append('nonce', window.monsterinsights.nonce);

        // Make AJAX request
        fetch(window.monsterinsights.ajax, {
            method: 'POST',
            body: formData,
        })
        .then(response => response.json())
        .then(response => {
            const { data } = response;
            if (response.success && data.wizard_url) {
                window.location.href = data.wizard_url;
            } else {
                console.error('Setup wizard error:', response);
                if (window.monsterinsights && window.monsterinsights.close_swal) {
                    window.monsterinsights.close_swal();
                }
            }
        })
        .catch((error) => {
            console.error('Error launching wizard:', error);
            if (window.monsterinsights && window.monsterinsights.close_swal) {
                window.monsterinsights.close_swal();
            }
        });
    }

    /**
     * Initialize the setup wizard functionality
     */
    function initSetupWizard() {
        // Find all elements with the monsterinsights-setup-wizard-link class
        const setupWizardLinks = document.querySelectorAll('.monsterinsights-setup-wizard-link');
        
        if (setupWizardLinks.length > 0) {
            // Add click event listener to each link
            setupWizardLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    launchSetupWizard();
                });
            });
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSetupWizard);
    } else {
        initSetupWizard();
    }

})();