wp.domReady(function() {
    let language = 'en'; // Default language
    let categoriesCache = {}; // Store categories by language
    
    // Debug console output with timestamp
    function debug(...args) {
        //console.log(`[CLF ${new Date().toLocaleTimeString()}]`, ...args);
    }
    
    debug('Category Language Filter loaded');
    
    // Function to get the current post language
    function getPostLanguage() {
        const meta = wp.data.select('core/editor').getEditedPostAttribute('meta');
        debug('Current meta:', meta);
        const newLanguage = meta && meta._custom_language ? meta._custom_language : 'en';
        debug('Retrieved language from meta:', newLanguage);
        return newLanguage;
    }
    
    // Function to fetch categories and directly replace them in the UI
    async function fetchAndReplaceCategories(lang) {
        debug('Fetching categories for language:', lang);
        
        try {
            // Use a unique timestamp to prevent caching
            const timestamp = Date.now();
            const path = `/clf/v1/categories?language=${encodeURIComponent(lang)}&_=${timestamp}`;
            
            const categories = await wp.apiFetch({ path });
            debug('Received categories:', categories);
            
            // Cache the categories for this language
            categoriesCache[lang] = categories;
            
            // First clear the WP data store
            wp.data.dispatch('core').invalidateResolution('getEntityRecords', ['taxonomy', 'category']);
            
            // Then inject the categories into all possible query formats
            const queries = [
                { per_page: -1 },
                { context: 'edit', per_page: -1 },
                { per_page: 100 },
                { context: 'edit', per_page: 100 },
                {}
            ];
            
            queries.forEach(query => {
                wp.data.dispatch('core').receiveEntityRecords('taxonomy', 'category', categories, query);
            });
            
            debug('Categories added to store for language:', lang);
            
            // Now directly replace the categories in the DOM
            directlyReplaceCategoriesInDOM();
            
            // Also force a re-render of the post editor
            wp.data.dispatch('core/editor').editPost({ 
                meta: { _refresh_trigger: Date.now() }
            });
            
            // Create a notification
            wp.data.dispatch('core/notices').createNotice(
                'info',
                `Categories updated for language: ${lang}`,
                {
                    id: 'clf-categories-updated',
                    type: 'snackbar',
                    isDismissible: true
                }
            );
            
            return categories;
        } catch (error) {
            debug('Error fetching categories:', error);
            return [];
        }
    }
    
    // Function to directly manipulate the DOM to replace categories
    function directlyReplaceCategoriesInDOM() {
        debug('Attempting to directly replace categories in DOM');
        
        // Find the categories list container
        const categoriesListContainer = document.querySelector('.editor-post-taxonomies__hierarchical-terms-list');
        if (!categoriesListContainer) {
            debug('Categories list container not found in DOM');
            return;
        }
        
        // Find the closest container (panel)
        const container = categoriesListContainer.closest('.components-panel__body');
        if (!container) {
            debug('Categories container not found');
            return;
        }
        
        // Get the current language and cached categories
        const currentLang = getPostLanguage();
        const categories = categoriesCache[currentLang] || [];
        
        debug('Building categories UI for language:', currentLang, 'with', categories.length, 'categories');
        
        if (categories.length === 0) {
            debug('No categories found for language:', currentLang);
            categoriesListContainer.innerHTML = '<div class="components-notice is-warning">No categories available for this language.</div>';
            return;
        }
        
        // Get the current post's categories
        const currentPostCats = wp.data.select('core/editor').getEditedPostAttribute('categories') || [];
        debug('Current post categories:', currentPostCats);
        
        // Build HTML for all categories
        let categoryHTML = '';
        
        categories.forEach(category => {
            // Check if this category is selected
            const isChecked = currentPostCats.includes(category.id);
            
            categoryHTML += `
                <div class="editor-post-taxonomies__hierarchical-terms-choice" data-category-id="${category.id}">
                    <label class="components-checkbox-control__label">
                        <input 
                            id="inspector-checkbox-control-${category.id}"
                            class="components-checkbox-control__input"
                            type="checkbox"
							style="height: 15px"
                            value="${category.id}"
                            ${isChecked ? 'checked="checked"' : ''}
                        />
                        ${category.name}
                    </label>
                </div>
            `;
        });
        
        // Replace the entire contents of the categories list
        categoriesListContainer.innerHTML = categoryHTML;
        
        // Now add event listeners to all new checkboxes
        categoriesListContainer.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const isChecked = this.checked;
                const categoryId = parseInt(this.value, 10);
                
                debug(`Category ${categoryId} ${isChecked ? 'checked' : 'unchecked'}`);
                
                // Get current categories
                let postCategories = [...(wp.data.select('core/editor').getEditedPostAttribute('categories') || [])];
                
                if (isChecked && !postCategories.includes(categoryId)) {
                    // Add category
                    postCategories.push(categoryId);
                } else if (!isChecked && postCategories.includes(categoryId)) {
                    // Remove category
                    postCategories = postCategories.filter(id => id !== categoryId);
                }
                
                // Update post categories
                wp.data.dispatch('core/editor').editPost({ categories: postCategories });
            });
        });
        
        debug('Successfully rebuilt categories UI with', categories.length, 'items');
    }
    
    // Function to update the category checkboxes based on current language
    function updateCategoryCheckboxes() {
        debug('Updating category checkboxes');
        const currentLang = getPostLanguage();
        const categories = categoriesCache[currentLang] || [];
        
        if (categories.length === 0) {
            debug('No categories found for language:', currentLang);
            return;
        }
        
        // Find all category checkboxes
        const checkboxes = document.querySelectorAll('.editor-post-taxonomies__hierarchical-terms-choice input[type="checkbox"]');
        const checkboxParents = document.querySelectorAll('.editor-post-taxonomies__hierarchical-terms-choice');
        
        debug('Found', checkboxes.length, 'category checkboxes');
        
        // If we have checkboxes, we need to manipulate the DOM carefully
        if (checkboxes.length > 0) {
            // First, hide all existing checkboxes
            checkboxParents.forEach(parent => {
                parent.style.display = 'none';
            });
            
            // Now add our categories if they don't exist
            const categoryList = document.querySelector('.editor-post-taxonomies__hierarchical-terms-list');
            if (categoryList) {
                // Get the current post's categories
                const currentPostCats = wp.data.select('core/editor').getEditedPostAttribute('categories') || [];
                debug('Current post categories:', currentPostCats);
                
                // Clear existing categories
                // categoryList.innerHTML = '';
                
                // Add our categories
                categories.forEach(category => {
                    // Check if this category already exists in the DOM
                    const existingItem = document.querySelector(`[data-category-id="${category.id}"]`);
                    
                    if (existingItem) {
                        // If it exists, make it visible
                        existingItem.style.display = 'block';
                    } else {
                        // Create a new checkbox for this category
                        const newItem = document.createElement('div');
                        newItem.className = 'editor-post-taxonomies__hierarchical-terms-choice';
                        newItem.setAttribute('data-category-id', category.id);
                        
                        // Check if this category is selected
                        const isChecked = currentPostCats.includes(category.id);
                        
                        newItem.innerHTML = `
                            <label class="components-checkbox-control__label">
                                <input 
                                    id="inspector-checkbox-control-${category.id}"
                                    class="components-checkbox-control__input"
                                    type="checkbox"
                                    value="${category.id}"
                                    ${isChecked ? 'checked="checked"' : ''}
                                />
                                ${category.name}
                            </label>
                        `;
                        
                        // Add event listener to the checkbox
                        setTimeout(() => {
                            const checkbox = newItem.querySelector('input[type="checkbox"]');
                            if (checkbox) {
                                checkbox.addEventListener('change', function() {
                                    const isChecked = this.checked;
                                    const categoryId = parseInt(this.value, 10);
                                    
                                    debug(`Category ${categoryId} ${isChecked ? 'checked' : 'unchecked'}`);
                                    
                                    // Get current categories
                                    let postCategories = [...(wp.data.select('core/editor').getEditedPostAttribute('categories') || [])];
                                    
                                    if (isChecked && !postCategories.includes(categoryId)) {
                                        // Add category
                                        postCategories.push(categoryId);
                                    } else if (!isChecked && postCategories.includes(categoryId)) {
                                        // Remove category
                                        postCategories = postCategories.filter(id => id !== categoryId);
                                    }
                                    
                                    // Update post categories
                                    wp.data.dispatch('core/editor').editPost({ categories: postCategories });
                                });
                            }
                        }, 0);
                        
                        // Add this category to the list
                        categoryList.appendChild(newItem);
                    }
                });
                
                // Go through all items in the list
                document.querySelectorAll('[data-category-id]').forEach(item => {
                    const catId = parseInt(item.getAttribute('data-category-id'), 10);
                    
                    // Check if this category exists in our current language
                    const categoryExists = categories.some(cat => cat.id === catId);
                    
                    // If it doesn't exist, hide it
                    if (!categoryExists) {
                        item.style.display = 'none';
                    } else {
                        item.style.display = 'block';
                    }
                });
                
                debug('Updated category checkboxes in DOM');
            }
        }
    }
    
    // Override category fetch to use custom endpoint with current language
    wp.apiFetch.use((options, next) => {
        if (options.path && typeof options.path === 'string' && options.path.includes('/wp/v2/categories')) {
            try {
                // Parse the original URL to preserve query params
                const url = new URL(options.path, window.location.origin);
                const searchParams = url.searchParams;
                
                // Get current language
                language = getPostLanguage();
                
                // Build new path with language parameter
                const newPath = `/clf/v1/categories?language=${encodeURIComponent(language)}&_=${Date.now()}`;
                
                // Add original query params
                if (searchParams.toString()) {
                    options.path = `${newPath}&${searchParams.toString()}`;
                } else {
                    options.path = newPath;
                }
                
                debug('Overriding fetch, using path:', options.path);
                
                // Return modified request but also intercept response
                return next(options).then(response => {
                    debug('Intercepted response:', response);
                    
                    // Cache the response
                    categoriesCache[language] = response;
                    
                    // Update all possible queries in the store
                    const queries = [
                        { per_page: -1 },
                        { context: 'edit', per_page: -1 },
                        { per_page: 100 },
                        { context: 'edit', per_page: 100 },
                        {}
                    ];
                    
                    queries.forEach(query => {
                        wp.data.dispatch('core').receiveEntityRecords('taxonomy', 'category', response, query);
                    });
                    
                    // Return the original response
                    return response;
                });
            } catch (e) {
                debug('Error in intercept:', e);
                return next(options);
            }
        }
        return next(options);
    });
    
    // Handle language selector changes with multiple selector detection
    const setupLanguageSelectors = function() {
        // List all possible selectors that might be the language selector
        const potentialSelectors = [
            '#post_lang_choice',
            '#custom_language',
            'select[name="post_lang_choice"]',
            'select[id*="lang"]',
            'select[name*="lang"]'
        ];
        
        // Function to check for and attach event listeners to language selectors
        const findAndAttachToSelectors = () => {
            let found = false;
            
            // Try each potential selector
            for (const selector of potentialSelectors) {
                const elements = document.querySelectorAll(selector);
                
                elements.forEach(el => {
                    if (!el.hasAttribute('clf-listener')) {
                        debug('Found language selector:', selector);
                        found = true;
                        
                        // Mark it as having a listener
                        el.setAttribute('clf-listener', 'true');
                        
                        // Add change event listener
                        el.addEventListener('change', function() {
                            const newLanguage = this.value;
                            debug('Language changed to:', newLanguage);
                            
                            // Update post meta with new language
                            wp.data.dispatch('core/editor').editPost({ 
                                meta: { _custom_language: newLanguage } 
                            });
                            
                            // Update our global language variable
                            language = newLanguage;
                            
                            // Apply changes after a delay to let meta update
                            setTimeout(() => {
                                debug('Fetching categories for new language:', newLanguage);
                                fetchAndReplaceCategories(newLanguage);
                            }, 100);
                        });
                        
                        // If we're using WPML, also intercept their language change events
                        if (selector === '#post_lang_choice' && window.icl_post_editor) {
                            debug('WPML detected, adding extra handlers');
                            
                            // Override WPML's language change handler to include our functionality
                            const originalHandler = window.wpml_post_edit_config.wpml_callbacks.switch_post_lang;
                            
                            window.wpml_post_edit_config.wpml_callbacks.switch_post_lang = function(lang) {
                                // Call original handler first
                                if (originalHandler) {
                                    originalHandler(lang);
                                }
                                
                                // Then update our language and fetch categories
                                debug('WPML language changed to:', lang);
                                language = lang;
                                
                                // Update post meta
                                wp.data.dispatch('core/editor').editPost({ 
                                    meta: { _custom_language: lang } 
                                });
                                
                                // Fetch and replace categories
                                setTimeout(() => {
                                    fetchAndReplaceCategories(lang);
                                }, 100);
                            };
                        }
                    }
                });
            }
            
            return found;
        };
        
        // Check immediately
        const found = findAndAttachToSelectors();
        
        // If we didn't find anything, set up a MutationObserver to keep trying
        if (!found) {
            debug('No language selector found, setting up observer');
            
            // Create an observer instance
            const observer = new MutationObserver(mutations => {
                // Check for language selectors after DOM changes
                const found = findAndAttachToSelectors();
                
                // If we found something, we can disconnect the observer
                if (found) {
                    debug('Language selector found by observer, disconnecting');
                    observer.disconnect();
                }
            });
            
            // Start observing
            observer.observe(document.body, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['id', 'class']
            });
            
            return observer;
        }
        
        return null;
    };
    
    // Watch for the post editor becoming ready
    const editorReadyCheck = wp.data.subscribe(() => {
        const isPostLoaded = wp.data.select('core/editor').getCurrentPostId();
        const isEditorReady = wp.data.select('core/editor').getEditedPostAttribute('meta');
        
        if (isPostLoaded && isEditorReady) {
            debug('Editor ready, post loaded with ID:', isPostLoaded);
            
            // Get initial language
            language = getPostLanguage();
            debug('Initial language:', language);
            
            // Fetch initial categories
            fetchAndReplaceCategories(language);
            
            // Set up language selectors
            setupLanguageSelectors();
            
            // Unsubscribe from this check
            editorReadyCheck();
            
            // Add a refresh button to the categories panel
            addRefreshButton();
        }
    });
    
    // Function to add a refresh button to the categories panel
    function addRefreshButton() {
        // Wait for the panel to be available
        const checkForPanel = setInterval(() => {
            const panel = document.querySelector('.components-panel [data-slug="categories"] .components-panel__body-title');
            
            if (panel && !panel.querySelector('.clf-refresh-button')) {
                debug('Adding refresh button to categories panel');
                
                // Create the button
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'components-button is-secondary is-small clf-refresh-button';
                button.style.marginLeft = '10px';
                button.textContent = 'Refresh';
                
                // Add click handler
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    debug('Refresh button clicked');
                    const currentLang = getPostLanguage();
                    fetchAndReplaceCategories(currentLang);
                });
                
                // Add the button to the panel
                panel.appendChild(button);
                
                // Stop checking
                clearInterval(checkForPanel);
            }
        }, 500);
        
        // Stop checking after 10 seconds regardless
        setTimeout(() => {
            clearInterval(checkForPanel);
        }, 10000);
    }
    
    // Expose a global refresh function for debugging
    window.refreshCategoriesForLanguage = function(lang) {
        debug('Manual refresh triggered for language:', lang || getPostLanguage());
        fetchAndReplaceCategories(lang || getPostLanguage());
    };
});