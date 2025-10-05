/**
 * BetterFeed Admin JavaScript
 * 
 * This file handles admin interface interactions for the BetterFeed WordPress plugin.
 * It provides client-side functionality including REST API communication, form handling,
 * and user feedback mechanisms.
 * 
 * @package BetterFeed
 * @since   1.0.0
 * 
 * @link    https://github.com/your-repo/betterfeed
 */

/**
 * Display admin notice with proper styling and context.
 * 
 * Creates user-friendly admin notices that follow WordPress UI patterns
 * and provide clear feedback to users about operation results.
 * 
 * @since 1.0.0
 * 
 * @param {string} message - The message to display to the user
 * @param {string} type    - The notice type (success, error, warning, info)
 * 
 * @example
 * showAdminNotice('Operation completed successfully!', 'success');
 * showAdminNotice('An error occurred', 'error');
 */
function showAdminNotice(message, type) {
    type = type || 'success';
    
    var noticeHtml = '<div class="notice notice-' + type + ' is-dismissible">' +
        '<p><strong>' + message + '</strong></p>' +
        '<button type="button" class="notice-dismiss">' +
        '<span class="screen-reader-text">Dismiss this notice.</span>' +
        '</button></div>';
    
    var title = document.querySelector('.wrap h1');
    if (title) {
        title.insertAdjacentHTML('afterend', noticeHtml);
    } else {
        document.body.insertAdjacentHTML('afterbegin', noticeHtml);
    }
}

// Download helper function
function downloadFile(content, filename, contentType) {
    var blob = new Blob([content], { type: contentType });
    var url = window.URL.createObjectURL(blob);
    var link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
}

document.addEventListener('DOMContentLoaded', function() {
    // Clear Cache button
    var clearCacheBtn = document.getElementById('bf-clear-cache');
    if (clearCacheBtn) {
        clearCacheBtn.addEventListener('click', function(e) {
            e.preventDefault();
            var btn = this;
            btn.disabled = true;
            btn.textContent = 'Clearing...';
            
            fetchWithErrorHandling('clear-cache', {
                method: 'POST'
            })
            .then(data => {
                if (data.success) {
                    showAdminNotice(data.message);
                } else {
                    showAdminNotice(data.message, 'error');
                }
            })
            .catch(error => {
                showAdminNotice('Error: ' + error.message, 'error');
            })
            .finally(() => {
                btn.disabled = false;
                btn.textContent = 'Clear Cache';
            });
        });
    }
    
    // Warm Cache button
    var warmCacheBtn = document.getElementById('bf-warm-cache');
    if (warmCacheBtn) {
        warmCacheBtn.addEventListener('click', function(e) {
            e.preventDefault();
            var btn = this;
            btn.disabled = true;
            btn.textContent = 'Warming...';
            
            fetchWithErrorHandling('warm-cache', {
                method: 'POST'
            })
            .then(data => {
                if (data.success) {
                    showAdminNotice(data.message);
                } else {
                    showAdminNotice(data.message, 'error');
                }
            })
            .catch(error => {
                showAdminNotice('Error: ' + error.message, 'error');
            })
            .finally(() => {
                btn.disabled = false;
                btn.textContent = 'Warm Cache';
            });
        });
    }
    
    // Analytics Export button
    var exportAnalyticsBtn = document.getElementById('bf-export-analytics');
    if (exportAnalyticsBtn) {
        exportAnalyticsBtn.addEventListener('click', function(e) {
            e.preventDefault();
            var btn = this;
            btn.disabled = true;
            btn.textContent = 'Exporting...';
            
            var format = 'csv';
            var days = 30;
            
            fetchWithErrorHandling('export-analytics?format=' + format + '&days=' + days, {
                method: 'GET'
            })
            .then(data => {
                if (data.success) {
                    var contentType = data.format === 'csv' ? 'text/csv' : 'application/json';
                    downloadFile(data.data, data.filename, contentType);
                    showAdminNotice(data.filename + ' downloaded successfully!');
                } else {
                    showAdminNotice(data.message, 'error');
                }
            })
            .catch(error => {
                showAdminNotice('Export failed: ' + error.message, 'error');
            })
            .finally(() => {
                btn.disabled = false;
                btn.textContent = 'Export Analytics';
            });
        });
    }
    
    // Export Settings button - REAL FUNCTIONALITY!
    var exportBtn = document.getElementById('bf-export-settings');
    if (exportBtn) {
        exportBtn.addEventListener('click', function(e) {
            e.preventDefault();
            var btn = this;
            btn.disabled = true;
            btn.textContent = 'Exporting...';
            
            fetchWithErrorHandling('export-settings', {
                method: 'GET'
            })
            .then(data => {
                if (data.success) {
                    downloadFile(data.data, data.filename, 'application/json');
                    showAdminNotice(data.filename + ' downloaded successfully!');
                } else {
                    showAdminNotice(data.message, 'error');
                }
            })
            .catch(error => {
                showAdminNotice('Export failed: ' + error.message, 'error');
            })
            .finally(() => {
                btn.disabled = false;
                btn.textContent = 'Export Settings';
            });
        });
    }
    
    // Preset handling
    var presetSelect = document.getElementById('bf-preset-select');
    var applyBtn = document.getElementById('bf-apply-preset');
    
    if (presetSelect && applyBtn) {
        presetSelect.addEventListener('change', function() {
            applyBtn.disabled = !this.value;
        });
        
        applyBtn.addEventListener('click', function(e) {
            e.preventDefault();
            var preset = presetSelect.value;
            if (!preset) return;
            
            var btn = this;
            btn.disabled = true;
            btn.textContent = 'Applying...';
            
            fetchWithErrorHandling('apply-preset', {
                method: 'POST',
                body: {
                    preset: preset
                }
            })
            .then(data => {
                if (data.success) {
                    showAdminNotice(data.message);
                } else {
                    showAdminNotice(data.message, 'error');
                }
            })
            .catch(error => {
                showAdminNotice('Error: ' + error.message, 'error');
            })
            .finally(() => {
                btn.disabled = false;
                btn.textContent = 'Apply Preset';
            });
        });
    }
});

// Dashboard Functions
/**
 * Clear the feed cache to force regeneration of cached content.
 * 
 * This function clears all cached feed content, forcing WordPress to
 * regenerate feeds on the next request. Useful for troubleshooting
 * or after making feed configuration changes.
 * 
 * @since 1.0.0
 * 
 * @return {void}
 * 
 * @example
 * clearFeedCache(); // Clears cache and shows user feedback
 */
function clearFeedCache() {
    const button = event.target;
    const originalText = button.textContent;
    
    button.disabled = true;
    button.textContent = 'Clearing...';
    
    fetchWithErrorHandling('clear-cache')
        .then(data => {
            if (data.success) {
                showAdminNotice('Cache cleared successfully!', 'success');
            } else {
                throw new Error(data.message || 'Failed to clear cache');
            }
        })
        .catch(error => {
            console.error('Cache clear error:', error);
            // Error message already shown by fetchWithErrorHandling
        })
        .finally(() => {
            button.disabled = false;
            button.textContent = originalText;
        });
}

function runPerformanceTest() {
    const button = event.target;
    const originalText = button.textContent;
    
    button.disabled = true;
    button.textContent = 'Testing...';
    
    fetchWithErrorHandling('run-performance-test', {
        method: 'POST'
    })
    .then(data => {
        if (data.success) {
            showAdminNotice('Performance test completed successfully!', 'success');
            // Reload page to show updated results
            setTimeout(() => location.reload(), 1000);
        } else {
            showAdminNotice('Performance test failed: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('Performance test error:', error);
        showAdminNotice('Performance test failed: ' + error.message, 'error');
    })
    .finally(() => {
        button.disabled = false;
        button.textContent = originalText;
    });
}

function generateOptimizationReport() {
    const button = event.target;
    const originalText = button.textContent;
    
    button.disabled = true;
    button.textContent = 'Generating...';
    
    fetchWithErrorHandling('generate-optimization-report', {
        method: 'POST'
    })
    .then(data => {
        if (data.success) {
            showAdminNotice('Optimization report generated successfully!', 'success');
            // Download the report if URL provided
            if (data.report_url) {
                window.open(data.report_url, '_blank');
            }
        } else {
            showAdminNotice('Failed to generate report: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('Report generation error:', error);
        showAdminNotice('Failed to generate report: ' + error.message, 'error');
    })
    .finally(() => {
        button.disabled = false;
        button.textContent = originalText;
    });
}

function applySuggestion(suggestionId) {
    console.log('BetterFeed: Applying suggestion:', suggestionId);
    console.log('BetterFeed: REST API URL:', bf_config.rest_api_url);
    console.log('BetterFeed: Nonce:', bf_config.nonce);
    
    const button = event.target;
    const originalText = button.textContent;
    
    button.disabled = true;
    button.textContent = 'Applying...';
    
    fetchWithErrorHandling('apply-suggestion', {
        method: 'POST',
        body: {
            suggestion_id: suggestionId
        }
    })
    .then(data => {
        console.log('BetterFeed: Response data:', data);
        if (data.success) {
            showAdminNotice('Optimization suggestion applied successfully!', 'success');
            // Reload page to show updated suggestions
            setTimeout(() => location.reload(), 1000);
        } else {
            showAdminNotice('Failed to apply suggestion: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('BetterFeed: Fetch error:', error);
        showAdminNotice('Failed to apply suggestion: ' + error.message, 'error');
    })
    .finally(() => {
        button.disabled = false;
        button.textContent = originalText;
    });
}

/**
 * Fetch data from REST API with comprehensive error handling.
 * 
 * This utility function provides consistent error handling for all REST API calls,
 * including network errors, HTTP errors, and response validation.
 * 
 * @since 1.0.0
 * 
 * @param {string} endpoint - The API endpoint to call (relative to bf_config.rest_api_url)
 * @param {Object} options  - Fetch options object
 * @param {string} options.method - HTTP method (default: 'POST')
 * @param {Object} options.body   - Request body data
 * @param {Object} options.headers - Additional headers
 * 
 * @return {Promise<Object>} Promise resolving to API response data
 * 
 * @example
 * fetchWithErrorHandling('clear-cache', {
 *   method: 'POST',
 *   body: { action: 'clear' }
 * })
 * .then(data => console.log('Success:', data))
 * .catch(error => console.error('Error:', error));
 */
function fetchWithErrorHandling(endpoint, options = {}) {
    // Default options
    const defaultOptions = {
        method: 'POST',
        credentials: 'include',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': bf_config.nonce
        }
    };
    
    // Merge options
    const fetchOptions = {
        ...defaultOptions,
        ...options,
        headers: {
            ...defaultOptions.headers,
            ...options.headers
        }
    };
    
    // Add body if provided
    if (options.body && typeof options.body === 'object') {
        fetchOptions.body = JSON.stringify(options.body);
    }
    
    return fetch(bf_config.rest_api_url + endpoint, fetchOptions)
        .then(response => {
            // Check if response is ok
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status} ${response.statusText}`);
            }
            
            // Parse JSON response
            return response.json();
        })
        .then(data => {
            // Validate response structure
            if (!data || typeof data !== 'object') {
                throw new Error('Invalid response format from server');
            }
            
            return data;
        })
        .catch(error => {
            // Log error for debugging
            console.error('BetterFeed API Error:', error);
            
            // Show user-friendly error message
            showAdminNotice(`Operation failed: ${error.message}`, 'error');
            
            // Re-throw for caller to handle if needed
            throw error;
        });
}

/**
 * Flush WordPress rewrite rules to update custom feed URLs.
 * 
 * This function calls the WordPress REST API to flush rewrite rules,
 * which is necessary after adding or removing custom feeds to ensure
 * the new URLs are properly registered.
 * 
 * @since 1.0.0
 * 
 * @return {Promise<Object>} Promise resolving to flush operation result
 * 
 * @example
 * flushRewriteRules()
 *   .then(data => console.log('Rewrite rules flushed'))
 *   .catch(error => console.error('Failed to flush:', error));
 */
function flushRewriteRules() {
    console.log('BetterFeed: Flushing rewrite rules');
    
    return fetchWithErrorHandling('flush-rewrite-rules')
        .then(data => {
            console.log('BetterFeed: Flush rewrite rules response data:', data);
            if (data.success) {
                console.log('BetterFeed: Rewrite rules flushed successfully');
                return data;
            } else {
                throw new Error(data.message || 'Failed to flush rewrite rules');
            }
        });
}

// Custom Feeds Functions
function addCustomFeed() {
    console.log('BetterFeed: Adding custom feed');
    
    // Get form data - be more specific with the selector
    const form = document.querySelector('.bf-add-feed form');
    console.log('BetterFeed: Found form element:', form);
    console.log('BetterFeed: Form type:', typeof form);
    console.log('BetterFeed: Form is HTMLFormElement:', form instanceof HTMLFormElement);
    
    if (!form) {
        console.error('BetterFeed: Custom feed form not found');
        console.error('BetterFeed: Available forms:', document.querySelectorAll('form'));
        showAdminNotice('Error: Custom feed form not found', 'error');
        return;
    }
    
    // Try FormData first, fallback to manual collection
    let feedData = {};
    
    try {
        const formData = new FormData(form);
        // Convert FormData to object
        for (let [key, value] of formData.entries()) {
            if (key === 'feed_post_types[]') {
                if (!feedData.feed_post_types) feedData.feed_post_types = [];
                feedData.feed_post_types.push(value);
            } else {
                feedData[key] = value;
            }
        }
    } catch (error) {
        console.warn('BetterFeed: FormData failed, using manual collection:', error);
        // Fallback: manually collect form data
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            if (input.type === 'checkbox') {
                if (input.name === 'feed_post_types[]') {
                    if (!feedData.feed_post_types) feedData.feed_post_types = [];
                    if (input.checked) feedData.feed_post_types.push(input.value);
                } else if (input.name === 'feed_enabled') {
                    feedData[input.name] = input.checked;
                }
            } else if (input.type === 'radio') {
                if (input.checked) {
                    feedData[input.name] = input.value;
                }
            } else {
                feedData[input.name] = input.value;
            }
        });
    }
    
    console.log('BetterFeed: Feed data:', feedData);
    
    // Get the submit button and disable it
    const button = document.querySelector('.bf-add-feed button[onclick="addCustomFeed()"]');
    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Adding...';
    
    fetchWithErrorHandling('add-custom-feed', {
        method: 'POST',
        body: feedData
    })
    .then(data => {
        console.log('BetterFeed: Add feed response data:', data);
        if (data.success) {
            showAdminNotice('Custom feed added successfully!', 'success');
            
            // Flush rewrite rules to make the feed accessible
            flushRewriteRules().then(() => {
                // Reload page to show new feed
                setTimeout(() => location.reload(), 1000);
            }).catch(error => {
                console.warn('BetterFeed: Failed to flush rewrite rules:', error);
                // Still reload even if flush fails
                setTimeout(() => location.reload(), 1000);
            });
        } else {
            showAdminNotice('Failed to add custom feed: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('BetterFeed: Add feed error:', error);
        showAdminNotice('Failed to add custom feed: ' + error.message, 'error');
    })
    .finally(() => {
        button.disabled = false;
        button.textContent = originalText;
    });
}

function editFeed(feedIndex) {
    // Feed editing not implemented - delete and recreate instead
    showAdminNotice('Feed editing not available. Please delete and recreate the feed with new settings.', 'info');
}

function deleteFeed(feedIndex) {
    if (confirm('Are you sure you want to delete this custom feed?')) {
        console.log('BetterFeed: Deleting custom feed at index:', feedIndex);
        
        fetchWithErrorHandling('delete-custom-feed', {
            method: 'POST',
            body: {
                feed_index: feedIndex
            }
        })
        .then(data => {
            console.log('BetterFeed: Delete feed response data:', data);
            if (data.success) {
                showAdminNotice('Custom feed deleted successfully!', 'success');
                // Reload page to show updated feed list
                setTimeout(() => location.reload(), 1000);
            } else {
                showAdminNotice('Failed to delete custom feed: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('BetterFeed: Delete feed error:', error);
            // Error message already shown by fetchWithErrorHandling
        });
    }
}

// Feed Redirects Functions
function addRedirect() {
    console.log('BetterFeed: Adding redirect');
    
    // Get form data - be more specific with the selector
    const form = document.querySelector('.bf-add-redirect form');
    if (!form) {
        console.error('BetterFeed: Redirect form not found');
        showAdminNotice('Error: Redirect form not found', 'error');
        return;
    }
    
    const formData = new FormData(form);
    
    // Convert FormData to object
    const redirectData = {};
    for (let [key, value] of formData.entries()) {
        redirectData[key] = value;
    }
    
    console.log('BetterFeed: Redirect data:', redirectData);
    
    // Get the submit button and disable it
    const button = document.querySelector('.bf-add-redirect button[onclick="addRedirect()"]');
    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Adding...';
    
    fetchWithErrorHandling('add-redirect', {
        method: 'POST',
        body: redirectData
    })
    .then(data => {
        console.log('BetterFeed: Add redirect response data:', data);
        if (data.success) {
            showAdminNotice('Redirect added successfully!', 'success');
            // Reload page to show new redirect
            setTimeout(() => location.reload(), 1000);
        } else {
            showAdminNotice('Failed to add redirect: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('BetterFeed: Add redirect error:', error);
        showAdminNotice('Failed to add redirect: ' + error.message, 'error');
    })
    .finally(() => {
        button.disabled = false;
        button.textContent = originalText;
    });
}

function editRedirect(redirectIndex) {
    // Redirect editing not implemented - delete and recreate instead
    showAdminNotice('Redirect editing not available. Please delete and recreate the redirect with new settings.', 'info');
}

function deleteRedirect(redirectIndex) {
    if (confirm('Are you sure you want to delete this redirect?')) {
        console.log('BetterFeed: Deleting redirect at index:', redirectIndex);
        
        fetchWithErrorHandling('delete-redirect', {
            method: 'POST',
            body: {
                redirect_index: redirectIndex
            }
        })
        .then(data => {
            console.log('BetterFeed: Delete redirect response data:', data);
            if (data.success) {
                showAdminNotice('Redirect deleted successfully!', 'success');
                // Reload page to show updated redirect list
                setTimeout(() => location.reload(), 1000);
            } else {
                showAdminNotice('Failed to delete redirect: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('BetterFeed: Delete redirect error:', error);
            showAdminNotice('Failed to delete redirect: ' + error.message, 'error');
        });
    }
}

/**
 * Apply optimization suggestion
 * 
 * Handles applying optimization suggestions from the dashboard.
 * Sends the suggestion ID to the REST API endpoint for processing.
 * 
 * @since 1.0.0
 * 
 * @param {string} suggestionId - The ID of the suggestion to apply
 * 
 * @example
 * applySuggestion('enable_etag');
 */
function applySuggestion(suggestionId) {
    if (!suggestionId) {
        showAdminNotice('Invalid suggestion ID', 'error');
        return;
    }
    
    const button = document.querySelector(`[data-suggestion-id="${suggestionId}"]`);
    if (button) {
        button.disabled = true;
        button.textContent = 'Applying...';
    }
    
    fetchWithErrorHandling('apply-suggestion', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': bf_admin.nonce
        },
        body: JSON.stringify({
            suggestion_id: suggestionId
        })
    })
    .then(data => {
        if (data.success) {
            showAdminNotice(data.message || 'Suggestion applied successfully!', 'success');
            if (button) {
                button.textContent = 'Applied';
                button.classList.add('applied');
                button.disabled = true;
            }
        } else {
            showAdminNotice(data.message || 'Failed to apply suggestion', 'error');
            if (button) {
                button.disabled = false;
                button.textContent = 'Apply';
            }
        }
    })
    .catch(error => {
        console.error('BetterFeed: Apply suggestion error:', error);
        showAdminNotice('Failed to apply suggestion: ' + error.message, 'error');
        if (button) {
            button.disabled = false;
            button.textContent = 'Apply';
        }
    });
}

// Initialize suggestion button handlers when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Handle suggestion Apply button clicks
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('bf-apply-btn') && e.target.dataset.suggestionId) {
            e.preventDefault();
            applySuggestion(e.target.dataset.suggestionId);
        }
    });
    
    // Handle Run New Scan button
    const runScanBtn = document.getElementById('bf-run-scan');
    if (runScanBtn) {
        runScanBtn.addEventListener('click', function() {
            runOptimizationScan();
        });
    }
    
    // Handle Apply All High Priority button
    const applyAllBtn = document.getElementById('bf-apply-all');
    if (applyAllBtn) {
        applyAllBtn.addEventListener('click', function() {
            applyAllHighPrioritySuggestions();
        });
    }
});

/**
 * Run optimization scan to generate new suggestions
 * 
 * Triggers a new optimization scan to generate fresh suggestions
 * based on current site configuration.
 * 
 * @since 1.0.0
 */
function runOptimizationScan() {
    const button = document.getElementById('bf-run-scan');
    if (button) {
        button.disabled = true;
        button.textContent = 'Scanning...';
    }
    
    fetchWithErrorHandling('generate-optimization-report', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': bf_admin.nonce
        }
    })
    .then(data => {
        if (data.success) {
            showAdminNotice('Optimization scan completed! New suggestions generated.', 'success');
            // Reload the page to show new suggestions
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showAdminNotice(data.message || 'Failed to run optimization scan', 'error');
            if (button) {
                button.disabled = false;
                button.textContent = 'Run New Scan';
            }
        }
    })
    .catch(error => {
        console.error('BetterFeed: Run scan error:', error);
        showAdminNotice('Failed to run optimization scan: ' + error.message, 'error');
        if (button) {
            button.disabled = false;
            button.textContent = 'Run New Scan';
        }
    });
}

/**
 * Apply all high priority suggestions
 * 
 * Automatically applies all high priority optimization suggestions.
 * 
 * @since 1.0.0
 */
function applyAllHighPrioritySuggestions() {
    const button = document.getElementById('bf-apply-all');
    if (button) {
        button.disabled = true;
        button.textContent = 'Applying...';
    }
    
    // Get all high priority suggestion buttons
    const highPriorityButtons = document.querySelectorAll('.bf-suggestion-item[data-priority="high"] .bf-apply-btn');
    
    if (highPriorityButtons.length === 0) {
        showAdminNotice('No high priority suggestions found to apply.', 'info');
        if (button) {
            button.disabled = false;
            button.textContent = 'Apply All High Priority';
        }
        return;
    }
    
    let completed = 0;
    let total = highPriorityButtons.length;
    
    highPriorityButtons.forEach(function(btn) {
        const suggestionId = btn.dataset.suggestionId;
        if (suggestionId) {
            fetchWithErrorHandling('apply-suggestion', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': bf_admin.nonce
                },
                body: JSON.stringify({
                    suggestion_id: suggestionId
                })
            })
            .then(data => {
                completed++;
                if (completed === total) {
                    showAdminNotice(`Successfully applied ${total} high priority suggestions!`, 'success');
                    if (button) {
                        button.disabled = false;
                        button.textContent = 'Apply All High Priority';
                    }
                    // Reload to show updated state
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                }
            })
            .catch(error => {
                console.error('BetterFeed: Apply all error:', error);
                completed++;
                if (completed === total) {
                    showAdminNotice('Some suggestions may have failed to apply. Please check individual suggestions.', 'warning');
                    if (button) {
                        button.disabled = false;
                        button.textContent = 'Apply All High Priority';
                    }
                }
            });
        }
    });
}
