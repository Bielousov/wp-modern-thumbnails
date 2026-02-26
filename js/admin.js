// MMT Admin Script loaded
console.log('MMT admin.js script file loaded');

jQuery(function ($) {
    
    console.log('jQuery ready - DOM loaded');
    console.log('mmtData available:', typeof mmtData !== 'undefined');
    if (typeof mmtData !== 'undefined') {
        console.log('mmtData:', mmtData);
    }
    
    // Function to save settings
    function saveSettings() {
        var $form = $('#mmt-settings-form');
        
        // Debug: Check if form exists
        if ($form.length === 0) {
            console.error('Form #mmt-settings-form not found');
            return;
        }
        
        console.log('saveSettings() called, collecting form data...');
        
        // Collect checkbox values
        var settings = {
            keep_original: $form.find('input[name="settings[keep_original]"]').is(':checked') ? 1 : 0,
            generate_avif: $form.find('input[name="settings[generate_avif]"]').is(':checked') ? 1 : 0,
            convert_gif: $form.find('input[name="settings[convert_gif]"]').is(':checked') ? 1 : 0,
            keep_exif: $form.find('input[name="settings[keep_exif]"]').is(':checked') ? 1 : 0,
            keep_exif_thumbnails: $form.find('input[name="settings[keep_exif_thumbnails]"]').is(':checked') ? 1 : 0,
            webp_quality: parseInt($form.find('input[name="settings[webp_quality]"]').val()) || 80,
            original_quality: parseInt($form.find('input[name="settings[original_quality]"]').val()) || 85,
            avif_quality: parseInt($form.find('input[name="settings[avif_quality]"]').val()) || 75,
        };
        
        console.log('Settings collected:', settings);
        
        var nonce = $form.find('input[name="mmt_settings_nonce"]').val();
        console.log('Nonce value:', nonce);
        
        $.ajax({
            url: mmtData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mmt_save_settings',
                nonce: nonce,
                settings: settings
            },
            success: function (response) {
                console.log('AJAX success response:', response);
                
                if (response.success) {
                    console.log('Response indicates success');
                    
                    // Check if persistent note already exists, only create once
                    if ($('#mmt-regeneration-note').length === 0) {
                        var regenerationMessage = '<strong>Note:</strong> Settings changes will apply to new uploads automatically. ' +
                            'To apply these settings to all existing media files, please visit the ' +
                            '<a href="?page=mmt-settings&tab=sizes" style="text-decoration: underline;">Theme Image Sizes</a> ' +
                            'tab and use the regeneration options.';
                        
                        var $noteNotice = $('<div id="mmt-regeneration-note" class="notice notice-info"></div>');
                        $noteNotice.append($('<p></p>').html(regenerationMessage));
                        $form.before($noteNotice);
                        console.log('Regeneration note added');
                    }
                    
                    // Show short-lived success toast (fixed position, doesn't shift layout)
                    showToast(response.data.message || 'Settings updated successfully', 'success');
                    console.log('Toast displayed');
                } else {
                    console.log('Response indicates failure:', response.data);
                    showToast(response.data || 'Error saving settings', 'error');
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error('AJAX error:', textStatus, errorThrown);
                console.error('Response:', jqXHR.responseText);
                showToast('Error connecting to server. Please try again.', 'error');
            }
        });
    }
    
    // Toast notification helper function
    function showToast(message, type) {
        // Close any existing success toasts immediately when showing a new one
        if (type === 'success') {
            $('.mmt-toast-success').each(function() {
                $(this).removeClass('mmt-toast-show');
                var $el = $(this);
                setTimeout(function() {
                    $el.remove();
                }, 300); // Wait for fade animation
            });
        }
        
        // Create toast element
        var $toast = $('<div class="mmt-toast mmt-toast-' + type + '" role="status" aria-live="polite"><div class="mmt-toast-content">' + message + '</div></div>');
        
        // Inject into page
        $('body').append($toast);
        
        // Trigger animation
        setTimeout(function() {
            $toast.addClass('mmt-toast-show');
        }, 10);
        
        // Auto-remove after 3 seconds
        setTimeout(function() {
            $toast.removeClass('mmt-toast-show');
            setTimeout(function() {
                $toast.remove();
            }, 300); // Wait for fade animation
        }, 3000);
    }
    
    // Update quality value display when slider changes
    $(document).on('input', '.mmt-quality-slider', function () {
        $(this).next('.mmt-quality-value').text(this.value);
    });
    
    // Toggle quality control visibility based on checkbox state
    $(document).on('change', '#mmt_keep_original', function () {
        $(this).closest('.mmt-setting-card').find('.mmt-quality-control').toggle(this.checked);
    });
    
    // Auto-save settings on checkbox change
    $(document).on('change', '#mmt-settings-form input[type="checkbox"]', function () {
        saveSettings();
    });
    
    // Auto-save settings on quality slider change
    $(document).on('change', '.mmt-quality-slider', function () {
        saveSettings();
    });
    
    // Regenerate all thumbnails - create a job
    $('#mmt-regenerate-all').on('click', function (e) {
        e.preventDefault();
        
        var job = new ThumbnailRegenerationJob({
            type: 'all'
        });
        job.start();
    });

    // Restore originals - prompt and start restore job
    $('#mmt-restore-originals').on('click', function (e) {
        e.preventDefault();

        var confirmMsg = 'This will remove all WebP and AVIF thumbnails and regenerate WordPress default thumbnails using the GD library. Do you want to proceed?';
        if (!confirm(confirmMsg)) {
            return;
        }

        var job = new ThumbnailRegenerationJob({
            type: 'restore'
        });
        job.start();
    });
    
    // Regenerate individual size thumbnails - create a job
    $('.mmt-regenerate-size').on('click', function (e) {
        e.preventDefault();
        
        var $button = $(this);
        var sizeName = $button.data('size');
        
        var job = new ThumbnailRegenerationJob({
            type: 'size',
            size: sizeName
        });
        job.start();
    });
});

