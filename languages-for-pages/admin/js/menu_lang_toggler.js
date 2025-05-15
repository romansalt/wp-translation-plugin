jQuery(document).ready(function($) {
    const languageCodes = languages.codes

    // language visibility check
    /* $('.language-visibility-settings').each(function() {
        const container = $(this);
        const allBox = container.find('.lang-all');

        
        const others = container.find('.lang-lv, .lang-en, .lang-ru');

        function toggleBoxes() {
            if (allBox.is(':checked')) {
                others.prop('checked', false).prop('disabled', true);
            } else {
                others.prop('disabled', false);
            }
        }

        allBox.on('change', toggleBoxes);
        others.on('change', function() {
            if (others.filter(':checked').length > 0) {
                allBox.prop('checked', false);
            }
            toggleBoxes();
        });

        toggleBoxes(); // initialize
    }); */


    function checkCurrentLanguageOnNewMenuItem(container) {
        // Skip if already set (i.e., if any language is checked)
        if (container.find('input[class^="lang-"]:checked').length > 0) {
            return;
        }
    
        // Check the current language box
        const currentLanguageBox = container.find('.lang-' + languages.current_language);
        if (currentLanguageBox.length) {
            currentLanguageBox.prop('checked', true);
        }
    }
    
    const observer = new MutationObserver((mutationsList) => {
        mutationsList.forEach(mutation => {
            if (mutation.type === 'childList') {
                mutation.addedNodes.forEach(node => {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        const $node = $(node);
    
                        // Check if this node or any of its children have .language-visibility-settings
                        const languageSettingsContainers = $node.find('.language-visibility-settings').addBack('.language-visibility-settings');
    
                        languageSettingsContainers.each(function() {
                            const container = $(this);
                            // Wait a bit to let WP fully initialize fields
                            setTimeout(() => checkCurrentLanguageOnNewMenuItem(container), 100);
                        });
                    }
                });
            }
        });
    });
    
    const targetNode = document.getElementById('menu-to-edit');
    if (targetNode) {
        observer.observe(targetNode, { childList: true, subtree: true });
    }



    // adds the synchronization option to the menu settings.
    const menuSettings = document.querySelector('.menu-settings');
    if (!menuSettings) return;

    const wrapper = document.createElement('div');
    wrapper.className = 'synchronize-menu-wrapper';
    wrapper.style.marginTop = '20px';

    // Static HTML content
    wrapper.innerHTML = `
        <hr>
        <h3>Synchronize Menu</h3>
        <p>Duplicate this menu to another language:</p>
    `;

    // Create the select element
    const select = document.createElement('select');
    select.id = 'synchronize-menu-language-select';
    select.style.minWidth = '200px';

    // Assuming languageCodes is an object like: { en: "English", fr: "French", ... }
    Object.entries(languageCodes).forEach(([code, name]) => {
        const option = document.createElement('option');
        option.value = code;
        option.textContent = name;
        select.appendChild(option);
    });

    // Create the button
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'button';
    button.style.marginLeft = '10px';
    button.textContent = 'Synchronize';
    button.onclick = synchronizeMenuLanguage;

    // Append elements to wrapper
    wrapper.appendChild(select);
    wrapper.appendChild(button);


    menuSettings.appendChild(wrapper);

    // handles synchronization
    function synchronizeMenuLanguage() {
        const lang = document.getElementById('synchronize-menu-language-select').value;
    
        $.ajax({
            url: languages.ajax_url,
            type: 'GET', // or 'POST' preferred
            data: {
                action: 'lfp_sync_menu', // must match add_action
                menu_id: languages.current_menu,
                target_language: lang,
                current_language: languages.current_language
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    console.log('Success:', response.data);
                    // make it reload menu with this get stuff set up action=edit&menu=
                    const menuId = response.data.menu_id;
                    window.location.href = `?action=edit&menu=${menuId}`;
                } else {
                    console.error('Error:', response.data);
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', error);
            }
        });
    }

    // Function to filter menu items
    function filterMenuItems() {
        $('.menu-item').each(function() {
            let menuItem = $(this);
            let hasCurrentLanguage = false;
        
            let hiddenInputLang = menuItem.find(".hidden-lang-menu-input")[0].value;

            if (hiddenInputLang === languages.current_language) {
                menuItem.removeClass('hidden-language-item').show();
            } else {
                menuItem.find(".lang-vis-checkbox:checked").each(function() {
                    if (this.value === languages.current_language || hiddenInputLang === languages.current_language) {
                        hasCurrentLanguage = true;
                        return false;
                    }
                });

                if (hasCurrentLanguage) {
                    menuItem.removeClass('hidden-language-item').show();
                } else {
                    menuItem.addClass('hidden-language-item').hide();
                }
            }
        });
    }
    
    // Initial filtering
    filterMenuItems();
});