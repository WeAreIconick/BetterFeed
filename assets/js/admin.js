/**
 * BetterFeed Admin JavaScript
 * Version: 1.1.6 - CLEAN REST API URLS!
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
            
            fetch('/wp-json/betterfeed/v1/clear-cache', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.bf_config ? window.bf_config.nonce : ''
                },
                credentials: 'include'
            })
            .then(response => response.json())
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
            
            fetch('/wp-json/betterfeed/v1/warm-cache', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.bf_config ? window.bf_config.nonce : ''
                },
                credentials: 'include'
            })
            .then(response => response.json())
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
            
            fetch('/wp-json/betterfeed/v1/export-analytics?format=' + format + '&days=' + days, {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': window.bf_config ? window.bf_config.nonce : ''
                },
                credentials: 'include'
            })
            .then(response => response.json())
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
    
    // Export Settings button
    var exportBtn = document.getElementById('bf-export-settings');
    if (exportBtn) {
        exportBtn.addEventListener('click', function(e) {
            e.preventDefault();
            showAdminNotice('Export settings functionality coming soon!', 'info');
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
            
            fetch('/wp-json/betterfeed/v1/apply-preset', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.bf_config ? window.bf_config.nonce : ''
                },
                credentials: 'include',
                body: JSON.stringify({
                    preset: preset
                })
            })
            .then(response => response.json())
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
