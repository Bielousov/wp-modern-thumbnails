// MMT Admin Script loaded
console.log('MMT admin.js script file loaded');

jQuery(function ($) {
    
    // Function to save settings
    function saveSettings() {
        var $form = $('#mmt-settings-form');
        
        // Collect checkbox values
        var settings = {
            keep_original: $form.find('input[name="settings[keep_original]"]').is(':checked') ? 1 : 0,
            generate_avif: $form.find('input[name="settings[generate_avif]"]').is(':checked') ? 1 : 0,
            convert_gif: $form.find('input[name="settings[convert_gif]"]').is(':checked') ? 1 : 0,
            webp_quality: parseInt($form.find('input[name="settings[webp_quality]"]').val()) || 80,
            original_quality: parseInt($form.find('input[name="settings[original_quality]"]').val()) || 85,
            avif_quality: parseInt($form.find('input[name="settings[avif_quality]"]').val()) || 75,
        };
        
        $.ajax({
            url: mmtData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mmt_save_settings',
                nonce: $form.find('input[name="mmt_settings_nonce"]').val(),
                settings: settings
            },
            success: function (response) {
                if (response.success) {
                    // Check if persistent note already exists, only create once
                    if ($('#mmt-regeneration-note').length === 0) {
                        var regenerationMessage = '<strong>Note:</strong> Settings changes will apply to new uploads automatically. ' +
                            'To apply these settings to all existing media files, please visit the ' +
                            '<a href="?page=mmt-settings&tab=sizes" style="text-decoration: underline;">Theme Image Sizes</a> ' +
                            'tab and use the regeneration options.';
                        
                        var $noteNotice = $('<div id="mmt-regeneration-note" class="notice notice-info"></div>');
                        $noteNotice.append($('<p></p>').html(regenerationMessage));
                        $form.before($noteNotice);
                    }
                    
                    // Show short-lived success message
                    var $successNotice = $('<div class="notice notice-success is-dismissible"></div>');
                    $successNotice.append($('<p></p>').text(response.data.message || 'Settings saved successfully'));
                    
                    var $noteNotice = $('#mmt-regeneration-note');
                    if ($noteNotice.length > 0) {
                        $noteNotice.after($successNotice);
                    } else {
                        $form.before($successNotice);
                    }
                    
                    // Auto-dismiss success notice after 3 seconds
                    setTimeout(function () {
                        $successNotice.slideUp(function () {
                            $(this).remove();
                        });
                    }, 3000);
                    
                    console.log('Settings saved, notices displayed');
                } else {
                    // Show error message
                    var $notice = $('<div class="notice notice-error is-dismissible"><p>' +
                        (response.data || 'Error saving settings') +
                        '</p></div>');
                    $form.before($notice);
                    console.log('Settings save failed');
                }
            },
            error: function () {
                // Show error message
                var $notice = $('<div class="notice notice-error is-dismissible"><p>' +
                    'Error connecting to server. Please try again.' +
                    '</p></div>');
                $form.before($notice);
            }
        });
    }
    
    // Update quality value display when slider changes
    $(document).on('input', '.mmt-quality-slider', function () {
        $(this).next('.mmt-quality-value').text(this.value);
    });
    
    // Toggle quality control visibility based on checkbox state
    $(document).on('change', '#mmt_keep_original', function () {
        $(this).closest('.mmt-setting-card').find('.mmt-quality-control').toggle(this.checked);
        saveSettings();
    });
    
    $(document).on('change', '#mmt_generate_avif', function () {
        $(this).closest('.mmt-setting-card').find('.mmt-quality-control').toggle(this.checked);
        saveSettings();
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

