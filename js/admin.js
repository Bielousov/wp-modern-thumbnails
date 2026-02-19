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
    
    // Regenerate all thumbnails
    $('#mmt-regenerate-all').on('click', function (e) {
        e.preventDefault();
        
        var $button = $(this);
        var originalText = $button.text();
        var $message = $('#mmt-regenerate-message');
        
        $button.prop('disabled', true);
        $button.text(mmtData.i18n.regenerating);
        $message.hide();
        
        $.ajax({
            url: mmtData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mmt_regenerate_all',
                nonce: mmtData.nonce
            },
            success: function (response) {
                if (response.success) {
                    $message.html(
                        '<div class="notice notice-success is-dismissible"><p>' +
                        response.data.message +
                        '</p></div>'
                    ).show();
                } else {
                    $message.html(
                        '<div class="notice notice-error is-dismissible"><p>' +
                        (response.data || mmtData.i18n.error) +
                        '</p></div>'
                    ).show();
                }
            },
            error: function () {
                $message.html(
                    '<div class="notice notice-error is-dismissible"><p>' +
                    mmtData.i18n.error +
                    '</p></div>'
                ).show();
            },
            complete: function () {
                $button.prop('disabled', false);
                $button.text(originalText);
            }
        });
    });
    
    // Regenerate individual size thumbnails
    $('.mmt-regenerate-size').on('click', function (e) {
        e.preventDefault();
        
        var $button = $(this);
        var sizeName = $button.data('size');
        var originalText = $button.text();
        
        $button.prop('disabled', true);
        $button.text(mmtData.i18n.regenerating);
        
        $.ajax({
            url: mmtData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mmt_regenerate_size',
                nonce: mmtData.nonce,
                size: sizeName
            },
            success: function (response) {
                if (response.success) {
                    // Show a brief success message as a tooltip/alert
                    alert(response.data.message);
                } else {
                    alert(response.data || mmtData.i18n.error);
                }
            },
            error: function () {
                alert(mmtData.i18n.error);
            },
            complete: function () {
                $button.prop('disabled', false);
                $button.text(originalText);
            }
        });
    });
});

