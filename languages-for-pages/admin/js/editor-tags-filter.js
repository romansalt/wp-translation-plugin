wp.domReady(function() {
    let language = 'lv'; // Default language
    let tagsCache = {}; // Store tags by language
    let refreshTimeout = null;
    let setupComplete = false;
    let requestInProgress = false;
    
    // Debug console output with timestamp - only when debugging is enabled
    function debug(...args) {
        if (tlfSettings && tlfSettings.debug) {
            console.log(`[TLF ${new Date().toLocaleTimeString()}]`, ...args);
        }
    }
    
    debug('Tag Language Filter loaded');
    
    // Function to get the current post language - memoized with better error handling
    let cachedLanguage = null;
    let lastLanguageCheck = 0;
    function getPostLanguage() {
        const now = Date.now();
        // Only check every 500ms to avoid constant recalculations
        if (cachedLanguage && (now - lastLanguageCheck) < 500) {
            return cachedLanguage;
        }
        
        try {
            const editor = wp.data.select('core/editor');
            if (!editor) {
                debug('Editor not available yet');
                return language; // Return current language as fallback
            }
            
            const meta = editor.getEditedPostAttribute('meta');
            
            // Better validation of language value
            let newLanguage = 'en'; // Default fallback
            
            if (meta && meta._custom_language && typeof meta._custom_language === 'string') {
                newLanguage = meta._custom_language.trim().toLowerCase();
                // Validate language code format (simple validation)
                if (!/^[a-z]{2,3}(-[a-z]{2,3})?$/i.test(newLanguage)) {
                    debug('Invalid language format detected:', newLanguage, 'falling back to default');
                    newLanguage = 'en';
                }
            } else {
                // Try to detect language from other potential sources
                const langSelectors = [
                    '#post_lang_choice', 
                    '#custom_language', 
                    'select[name="post_lang_choice"]'
                ];
                
                for (const selector of langSelectors) {
                    const el = document.querySelector(selector);
                    if (el && el.value) {
                        newLanguage = el.value.trim().toLowerCase();
                        debug('Language detected from selector:', selector, newLanguage);
                        break;
                    }
                }
            }
            
            debug('Language detected:', newLanguage);
            cachedLanguage = newLanguage;
            lastLanguageCheck = now;
            return newLanguage;
        } catch (e) {
            debug('Error in getPostLanguage:', e);
            return language; // Return current language as fallback
        }
    }
    
    // Debounced function to fetch tags with better error handling
    function debouncedFetchTags(lang) {
        if (refreshTimeout) {
            clearTimeout(refreshTimeout);
        }
        
        refreshTimeout = setTimeout(() => {
            try {
                // Validate language again before fetching
                if (!lang || typeof lang !== 'string' || lang.trim() === '') {
                    lang = getPostLanguage();
                    debug('Invalid language provided to fetch, using:', lang);
                }
                
                fetchAndReplaceTags(lang);
            } catch (e) {
                debug('Error in debouncedFetchTags:', e);
            }
        }, 300);
    }
    
    // Function to fetch tags but with request locking to prevent concurrent requests
    async function fetchAndReplaceTags(lang) {
        if (requestInProgress) {
            debug('Request already in progress, skipping');
            return;
        }
        
        // Validate language
        if (!lang || typeof lang !== 'string' || lang.trim() === '') {
            debug('Invalid language provided to fetchAndReplaceTags');
            return;
        }
        
        lang = lang.trim().toLowerCase();
        debug('Fetching tags for language:', lang);
        requestInProgress = true;
        
        try {
            // Check if we already have these tags cached
            if (tagsCache[lang] && Array.isArray(tagsCache[lang]) && tagsCache[lang].length > 0) {
                debug('Using cached tags for language:', lang);
                updateTagsInStore(tagsCache[lang]);
                updateTagsUI(lang);
                requestInProgress = false;
                return tagsCache[lang];
            }
            
            // Use a unique timestamp to prevent caching
            const timestamp = Date.now();
            const path = `/tlf/v1/tags?language=${encodeURIComponent(lang)}&_=${timestamp}`;
            
            const tags = await wp.apiFetch({ path });
            
            // Validate tags
            if (!Array.isArray(tags)) {
                throw new Error('Invalid tags response: not an array');
            }
            
            debug('Received tags:', tags.length);
            
            // Cache the tags for this language - make a deep copy to prevent mutation
            tagsCache[lang] = JSON.parse(JSON.stringify(tags));
            
            // Clean old cache entries to prevent memory bloat
            cleanTagsCache();
            
            // Update the store
            updateTagsInStore(tags);
            
            // Update the UI
            updateTagsUI(lang);
            
            return tags;
        } catch (error) {
            debug('Error fetching tags:', error);
            // Clear the cache for this language to force a refresh next time
            delete tagsCache[lang];
            
            // Show error to user
            wp.data.dispatch('core/notices').createNotice(
                'error',
                `Failed to load tags for language: ${lang}. Please try refreshing.`,
                {
                    id: 'tlf-tags-error',
                    type: 'snackbar',
                    isDismissible: true
                }
            );
            
            return [];
        } finally {
            requestInProgress = false;
        }
    }
    
    // Clean up the tags cache to prevent memory issues
    function cleanTagsCache() {
        const maxCacheEntries = 3; // Only keep the last 3 languages
        const cacheKeys = Object.keys(tagsCache);
        
        if (cacheKeys.length > maxCacheEntries) {
            // Sort keys by when they were last accessed (use a simple approach)
            const currentLang = getPostLanguage();
            
            // Always keep the current language
            const keysToRemove = cacheKeys
                .filter(key => key !== currentLang)
                .slice(0, cacheKeys.length - maxCacheEntries);
                
            keysToRemove.forEach(key => {
                debug('Cleaning cache for language:', key);
                delete tagsCache[key];
            });
        }
    }
    
    // Function to update tags in the store - improved with error handling
    function updateTagsInStore(tags) {
        try {
            if (!Array.isArray(tags)) {
                debug('Invalid tags provided to updateTagsInStore');
                return;
            }
            
            // First clear the WP data store
            wp.data.dispatch('core').invalidateResolution('getEntityRecords', ['taxonomy', 'post_tag']);
            
            // Then inject the tags into common query formats
            const queries = [
                { per_page: -1 },
                { context: 'edit', per_page: -1 },
                {}
            ];
            
            queries.forEach(query => {
                wp.data.dispatch('core').receiveEntityRecords('taxonomy', 'post_tag', tags, query);
            });
            
            // Force refresh of UI components
            wp.data.dispatch('core/editor').editPost({});
        } catch (e) {
            debug('Error in updateTagsInStore:', e);
        }
    }
    
    // Function to update the UI - separated for performance and improved
    function updateTagsUI(lang) {
        try {
            // Find the tag panel container
            const tagPanelContainer = document.querySelector('.editor-post-taxonomies__flat-term-selector');
            if (!tagPanelContainer) {
                debug('Tag panel container not found');
                return;
            }
            
            // Get cached tags for current language
            const tags = tagsCache[lang] || [];
            
            // Update language info display
            let infoEl = tagPanelContainer.querySelector('.tlf-language-info');
            if (!infoEl) {
                // Create language info element if it doesn't exist
                infoEl = document.createElement('div');
                infoEl.className = 'tlf-language-info';
                infoEl.style.marginBottom = '10px';
                infoEl.style.fontStyle = 'italic';
                infoEl.style.fontSize = '12px';
                infoEl.style.color = '#007cba';
                tagPanelContainer.insertBefore(infoEl, tagPanelContainer.firstChild);
            }
            
            // Update the text with more info
            infoEl.textContent = `Language: ${lang.toUpperCase()} (${tags.length} tags)`;
            
            // Add refresh link inside the info element
            if (!infoEl.querySelector('.tlf-refresh-link')) {
                const refreshLink = document.createElement('a');
                refreshLink.href = '#';
                refreshLink.className = 'tlf-refresh-link';
                refreshLink.textContent = ' - Refresh';
                refreshLink.style.marginLeft = '5px';
                
                refreshLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Force cache invalidation
                    delete tagsCache[lang];
                    fetchAndReplaceTags(lang);
                });
                
                infoEl.appendChild(refreshLink);
            }
            
            // Don't show a notification every time
            if (!setupComplete) {
                // Only show notification on initial setup
                wp.data.dispatch('core/notices').createNotice(
                    'info',
                    `Tags loaded for language: ${lang.toUpperCase()}`,
                    {
                        id: 'tlf-tags-updated',
                        type: 'snackbar',
                        isDismissible: true
                    }
                );
                setupComplete = true;
            }
        } catch (e) {
            debug('Error in updateTagsUI:', e);
        }
    }
    
    // Clear tags cache completely - useful for troubleshooting
    function clearAllTagsCache() {
        debug('Clearing all tags cache');
        tagsCache = {};
        
        // Force a refresh of the current language
        const currentLang = getPostLanguage();
        fetchAndReplaceTags(currentLang);
        
        wp.data.dispatch('core/notices').createNotice(
            'info',
            'Tags cache cleared completely',
            {
                id: 'tlf-cache-cleared',
                type: 'snackbar',
                isDismissible: true
            }
        );
    }
    
    // Improved API intercept with better error handling and filtering
    wp.apiFetch.use((options, next) => {
        // Only intercept tag requests
        if (options.path && 
            typeof options.path === 'string' && 
            options.path.includes('/wp/v2/tags')) {
            
            try {
                // Get current language
                const currentLang = getPostLanguage();
                
                // Debug extra info about the request
                debug('Intercepted tags request:', options.path, 'Current language:', currentLang);
                
                // If we already have tags cached for this language, use them
                if (tagsCache[currentLang] && Array.isArray(tagsCache[currentLang]) && tagsCache[currentLang].length > 0) {
                    debug('Using cached tags for API request');
                    
                    // Check if this is a search request
                    if (options.path.includes('search=')) {
                        // For search requests, we need to filter the cached tags
                        const url = new URL(options.path, window.location.origin);
                        const searchTerm = url.searchParams.get('search') || '';
                        
                        if (searchTerm) {
                            const lowercaseSearch = searchTerm.toLowerCase();
                            const filteredTags = tagsCache[currentLang].filter(
                                tag => tag.name.toLowerCase().includes(lowercaseSearch)
                            );
                            
                            debug('Search term:', searchTerm, 'Found matches:', filteredTags.length);
                            return Promise.resolve(filteredTags);
                        }
                    }
                    
                    // For non-search requests, return all cached tags
                    return Promise.resolve(tagsCache[currentLang]);
                }
                
                // No cache hit, construct a new request path
                const newPath = `/tlf/v1/tags?language=${encodeURIComponent(currentLang)}&_=${Date.now()}`;
                
                // Preserve any query parameters from the original request
                if (options.path.includes('?')) {
                    const originalParams = options.path.split('?')[1];
                    options.path = `${newPath}&${originalParams}`;
                } else {
                    options.path = newPath;
                }
                
                debug('Redirecting request to:', options.path);
                
                // Make the request and cache the result
                return next(options).then(response => {
                    // Validate response
                    if (Array.isArray(response)) {
                        debug('Caching tags from API response:', response.length);
                        tagsCache[currentLang] = response;
                        
                        // Update UI to reflect the new tags
                        updateTagsUI(currentLang);
                    } else {
                        debug('Invalid API response format:', response);
                    }
                    
                    return response;
                });
            } catch (e) {
                debug('Error intercepting request:', e);
                return next(options);
            }
        }
        
        // For all other requests, proceed normally
        return next(options);
    });
    
    // Enhanced language selector setup with better detection
    function setupLanguageSelectors() {
        // Common selectors for language fields
        const potentialSelectors = [
            '#post_lang_choice',
            '#custom_language',
            'select[name="post_lang_choice"]',
            'select[id*="lang"]',
            'select[name*="lang"]',
            // Add specific Polylang/WPML selectors
            '#wpml-language-switcher select',
            '.pll-language-column select'
        ];
        
        // Find and attach to any language selectors
        let found = false;
        potentialSelectors.forEach(selector => {
            document.querySelectorAll(selector).forEach(el => {
                // Skip if already handled
                if (el.hasAttribute('tlf-listener')) {
                    return;
                }
                
                // Validate it's actually a language selector
                if (el.tagName.toLowerCase() !== 'select') {
                    return;
                }
                
                // Mark as handled
                el.setAttribute('tlf-listener', 'true');
                found = true;
                
                debug('Setup language selector for:', selector);
                
                // Add change listener
                el.addEventListener('change', function() {
                    const newLanguage = this.value;
                    debug('Language changed to:', newLanguage);
                    
                    // Update post meta
                    wp.data.dispatch('core/editor').editPost({ 
                        meta: { _custom_language: newLanguage } 
                    });
                    
                    // Update cached language
                    cachedLanguage = newLanguage;
                    lastLanguageCheck = Date.now();
                    
                    // Always force a refresh of tags when language changes
                    delete tagsCache[newLanguage];
                    debouncedFetchTags(newLanguage);
                });
            });
        });
        
        // If no selectors found and we haven't set up an observer yet
        if (!found && !window.tlfObserver) {
            debug('No language selectors found, setting up observer');
            
            // Create a single observer for the whole page
            window.tlfObserver = new MutationObserver(() => {
                // Check again for language selectors
                let newFound = false;
                potentialSelectors.forEach(selector => {
                    document.querySelectorAll(selector).forEach(el => {
                        if (!el.hasAttribute('tlf-listener')) {
                            newFound = true;
                            setupLanguageSelectors(); // Recursively set up newly found selectors
                        }
                    });
                });
                
                // If we found selectors, disconnect the observer
                if (newFound) {
                    window.tlfObserver.disconnect();
                    window.tlfObserver = null;
                }
            });
            
            // Observe only what's necessary
            window.tlfObserver.observe(document.body, {
                childList: true,
                subtree: true,
                attributes: false,
                characterData: false
            });
        }
    }
    
    // Improved editor ready check
    let editorCheckCount = 0;
    const maxEditorChecks = 50; // Limit checks to avoid infinite loops
    
    const editorReadyCheck = wp.data.subscribe(() => {
        editorCheckCount++;
        
        // Stop checking after too many attempts
        if (editorCheckCount > maxEditorChecks) {
            debug('Stopping editor checks after', maxEditorChecks, 'attempts');
            editorReadyCheck(); // Unsubscribe
            return;
        }
        
        try {
            const editor = wp.data.select('core/editor');
            const isEditorReady = editor && 
                                  editor.getCurrentPostId() && 
                                  editor.getEditedPostAttribute('meta') !== undefined;
            
            if (isEditorReady) {
                debug('Editor ready after', editorCheckCount, 'checks');
                
                // Get initial language
                language = getPostLanguage();
                debug('Initial language:', language);
                
                // Set up event listeners
                setupLanguageSelectors();
                
                // Fetch initial tags (with a slight delay to ensure editor is fully loaded)
                setTimeout(() => {
                    fetchAndReplaceTags(language);
                }, 300);
                
                // Add refresh button (with delay to avoid DOM race conditions)
                setTimeout(addRefreshButton, 1000);
                
                // Unsubscribe from this check
                editorReadyCheck();
            }
        } catch (e) {
            debug('Error in editor ready check:', e);
        }
    });
    
    // Enhanced refresh button
    function addRefreshButton() {
        try {
            // Look for the tags panel
            const panel = document.querySelector('.components-panel [data-slug="tags"] .components-panel__body-title, .components-panel [data-slug="post_tag"] .components-panel__body-title');
            
            if (panel && !panel.querySelector('.tlf-refresh-button')) {
                // Create refresh button
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'components-button is-secondary is-small tlf-refresh-button';
                button.style.marginLeft = '10px';
                button.textContent = 'Refresh Tags';
                button.setAttribute('aria-label', 'Refresh language-specific tags');
                
                // Add simple click handler
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const currentLang = getPostLanguage();
                    debug('Manual refresh requested for language:', currentLang);
                    
                    // Force cache invalidation
                    delete tagsCache[currentLang];
                    fetchAndReplaceTags(currentLang);
                });
                
                // Add to panel
                panel.appendChild(button);
                
                // Add clear cache button (only in debug mode)
                if (tlfSettings && tlfSettings.debug) {
                    const clearButton = document.createElement('button');
                    clearButton.type = 'button';
                    clearButton.className = 'components-button is-tertiary is-small tlf-clear-button';
                    clearButton.style.marginLeft = '5px';
                    clearButton.textContent = 'Clear Cache';
                    
                    clearButton.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        clearAllTagsCache();
                    });
                    
                    panel.appendChild(clearButton);
                }
            }
        } catch (e) {
            debug('Error adding refresh button:', e);
        }
    }
    
    // For debugging - expose utilities to global scope
    window.tlfDebug = {
        refreshTagsForLanguage: function(lang) {
            const currentLang = lang || getPostLanguage();
            debug('Manual refresh requested via console for language:', currentLang);
            
            // Force cache invalidation
            delete tagsCache[currentLang];
            fetchAndReplaceTags(currentLang);
        },
        clearCache: clearAllTagsCache,
        getTagsCache: function() {
            return tagsCache;
        },
        getCurrentLanguage: getPostLanguage
    };
});