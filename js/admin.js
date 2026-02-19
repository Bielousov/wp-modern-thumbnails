// MMT Admin Script loaded
console.log('MMT admin.js script file loaded');

jQuery(function ($) {
    // Load media statistics asynchronously
    function loadMediaStats() {
        console.log('loadMediaStats called, looking for stats table...');
        
        var $statsTable = $('#mmt-media-stats');
        console.log('Stats table found:', $statsTable.length);
        
        if ($statsTable.length === 0) {
            console.log('Stats table not present on this page');
            return; // Table not present on current page
        }
        
        if (typeof mmtData === 'undefined') {
            console.error('mmtData is not defined - assets may not have loaded');
            $('#mmt-media-stats .mmt-stat-value').text('Script error');
            return;
        }
        
        console.log('Making AJAX call with nonce:', mmtData.nonce);
        
        // Show loading state in console
        $('#mmt-media-stats .mmt-stat-value').text('Loading...');
        
        $.ajax({
            url: mmtData.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            timeout: 30000, // 30 second timeout
            data: {
                action: 'mmt_get_media_stats',
                nonce: mmtData.nonce
            },
            success: function (response) {
                console.log('AJAX success response:', response);
                
                if (response.success && response.data) {
                    console.log('Populating stats with data:', response.data);
                    // Populate each stat value
                    $('#mmt-media-stats tr[data-stat="media-count"] .mmt-stat-value').text(response.data.media_count);
                    $('#mmt-media-stats tr[data-stat="original-size"] .mmt-stat-value').text(response.data.original_size);
                    $('#mmt-media-stats tr[data-stat="thumbnail-size"] .mmt-stat-value').text(response.data.thumbnail_size);
                    $('#mmt-media-stats tr[data-stat="total-size"] .mmt-stat-value').text(response.data.total_size);
                } else {
                    // Show error in value cells
                    var errorMsg = response.data || 'Unknown error';
                    console.error('Error response:', errorMsg);
                    $('#mmt-media-stats .mmt-stat-value').text('Error: ' + errorMsg);
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX error - Status:', status, 'Error:', error);
                console.error('Response text:', xhr.responseText);
                // Show error in value cells
                var errorDisplay = status === 'timeout' ? 'Timeout (media library too large)' : 'Error: ' + status;
                $('#mmt-media-stats .mmt-stat-value').text(errorDisplay);
            }
        });
    }
    
    // Load media stats on document ready
    if (typeof mmtData !== 'undefined') {
        console.log('mmtData is available, nonce:', mmtData.nonce);
        loadMediaStats();
    } else {
        console.log('mmtData is NOT available');
        // Retry after a short delay in case scripts are still loading
        setTimeout(function() {
            if (typeof mmtData !== 'undefined') {
                console.log('mmtData became available, calling loadMediaStats');
                loadMediaStats();
            } else {
                console.error('mmtData still not available after delay');
            }
        }, 500);
    }
    
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

