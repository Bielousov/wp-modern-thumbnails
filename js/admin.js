// MMT Admin Script loaded
console.log('MMT admin.js script file loaded');

jQuery(function ($) {
    
    // Auto-save settings on toggle change
    $(document).on('change', '#mmt-settings-form input[type="checkbox"]', function () {
        var $form = $('#mmt-settings-form');
        
        // Collect checkbox values
        var settings = {
            keep_original: $form.find('input[name="settings[keep_original]"]').is(':checked') ? 1 : 0,
            generate_avif: $form.find('input[name="settings[generate_avif]"]').is(':checked') ? 1 : 0,
            convert_gif: $form.find('input[name="settings[convert_gif]"]').is(':checked') ? 1 : 0,
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
                    // Show success message
                    var $notice = $('<div class="notice notice-success is-dismissible"><p>' +
                        (response.data.message || 'Settings saved successfully') +
                        '</p></div>');
                    $form.before($notice);
                    
                    // Auto-dismiss after 3 seconds
                    setTimeout(function () {
                        $notice.slideUp(function () {
                            $(this).remove();
                        });
                    }, 3000);
                } else {
                    // Show error message
                    var $notice = $('<div class="notice notice-error is-dismissible"><p>' +
                        (response.data || 'Error saving settings') +
                        '</p></div>');
                    $form.before($notice);
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

