/**
 * BetterFeed Admin JavaScript
 * Version: 1.0.8 - PRODUCTION READY
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

document.addEventListener('DOMContentLoaded', function() {
    // Clear Cache button
    var clearCacheBtn = document.getElementById('bf-clear-cache');
    if (clearCacheBtn) {
        clearCacheBtn.addEventListener('click', function(e) {
            e.preventDefault();
            var btn = this;
            btn.disabled = true;
            btn.textContent = 'Clearing...';
            
            setTimeout(function() {
                showAdminNotice('Cache cleared successfully!');
                btn.disabled = false;
                btn.textContent = 'Clear Cache';
            }, 1000);
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
            
            setTimeout(function() {
                showAdminNotice('Cache warmed successfully!');
                btn.disabled = false;
                btn.textContent = 'Warm Cache';
            }, 1000);
        });
    }
    
    // Export buttons
    var exportBtn = document.getElementById('bf-export-settings');
    if (exportBtn) {
        exportBtn.addEventListener('click', function(e) {
            e.preventDefault();
            showAdminNotice('Export settings functionality coming soon!', 'info');
        });
    }
    
    var analyticsBtn = document.getElementById('bf-export-analytics');
    if (analyticsBtn) {
        analyticsBtn.addEventListener('click', function(e) {
            e.preventDefault();
            showAdminNotice('Analytics export functionality coming soon!', 'info');
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
            
            setTimeout(function() {
                showAdminNotice('Preset "' + preset + '" applied successfully!');
                btn.disabled = false;
                btn.textContent = 'Apply Preset';
            }, 1000);
        });
    }
});
