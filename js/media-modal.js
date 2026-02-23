/**
 * Media Modal Integration
 * 
 * Handles regenerate thumbnail button in classic editor image dialogs.
 */

(function() {
    'use strict';

    const MMTMediaModal = {
        processedModals: new Set(),
        
        init: function() {
            // Monitor for wp-media-modal
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.addedNodes.length) {
                        jQuery(mutation.addedNodes).each(function() {
                            if (jQuery(this).attr('id') === 'wp-media-modal' || jQuery(this).find('#wp-media-modal').length > 0) {
                                setTimeout(() => MMTMediaModal.injectButton(), 500);
                            }
                        });
                    }
                });
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
            
            // Initial check for existing modal
            setTimeout(() => MMTMediaModal.injectButton(), 1000);
        },
        
        injectButton: function() {
            const $modal = jQuery('#wp-media-modal');
            if ($modal.length === 0) {
                return;
            }
            
            // Check if button already exists
            if ($modal.find('.mmt-regenerate-modal-btn').length > 0) {
                return;
            }
            
            // Look for image in the modal
            const $img = $modal.find('img[id*="preview"], img[src*="uploads"], #image-preview, [role="presentation"] img').first();
            
            if ($img.length === 0) {
                return;
            }
            
            const imageUrl = $img.attr('src');
            if (!imageUrl) {
                return;
            }
            
            // Check if already processed
            const modalKey = imageUrl;
            if (MMTMediaModal.processedModals.has(modalKey)) {
                return;
            }
            
            MMTMediaModal.processedModals.add(modalKey);
            
            // Get attachment ID from image URL
            MMTMediaModal.getAttachmentIdFromUrl(imageUrl, function(attachmentId) {
                if (!attachmentId) {
                    return;
                }
                
                // Find place to insert button
                const $insertPoint = $modal.find('.embed-media-settings .actions');
                
                if ($insertPoint.length === 0) {
                    return;
                }
                
                // Create button
                const nonce = (typeof mmtMediaModal !== 'undefined' && mmtMediaModal.nonce) ? mmtMediaModal.nonce : '';
                
                const $button = jQuery(
                    '<input type="button" class="mmt-regenerate-modal-btn mmt-button-wrapper button" ' +
                    'data-attachment-id="' + attachmentId + '" ' +
                    'data-nonce="' + nonce + '" ' +
                    'value="Regenerate Thumbnails">'
                );
                
                const $status = jQuery('<span class="mmt-regenerate-modal-status"></span>');
                
                // Click handler
                $button.on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    MMTMediaModal.handleClick($button, $status, attachmentId, nonce);
                });
                
                // Insert button and status
                $insertPoint.append($button);
                $insertPoint.append($status);
            });
        },
        
        getAttachmentIdFromUrl: function(imageUrl, callback) {
            let ajaxUrl = '/wp-admin/admin-ajax.php';
            if (typeof mmtMediaModal !== 'undefined' && mmtMediaModal.ajaxUrl) {
                ajaxUrl = mmtMediaModal.ajaxUrl;
            }
            
            const nonce = (typeof mmtMediaModal !== 'undefined' && mmtMediaModal.nonce) ? mmtMediaModal.nonce : '';
            
            jQuery.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mmt_get_attachment_id_by_url',
                    image_url: imageUrl,
                    _wpnonce: nonce
                },
                success: function(response) {
                    if (response.success && response.data && response.data.attachment_id) {
                        callback(response.data.attachment_id);
                    } else {
                        callback(null);
                    }
                },
                error: function() {
                    callback(null);
                }
            });
        },
        
        handleClick: function($button, $status, attachmentId, nonce) {
            let ajaxUrl = '/wp-admin/admin-ajax.php';
            if (typeof mmtMediaModal !== 'undefined' && mmtMediaModal.ajaxUrl) {
                ajaxUrl = mmtMediaModal.ajaxUrl;
            }
            
            if (!nonce && typeof mmtMediaModal !== 'undefined' && mmtMediaModal.nonce) {
                nonce = mmtMediaModal.nonce;
            }
            
            if (!nonce) {
                $status.text('Error: No security token').addClass('mmt-error').show();
                return;
            }
            
            // Disable button and show progress
            $button.val('Regenerating...').prop('disabled', true);
            
            $status.text('Processing...')
                .removeClass('mmt-error mmt-success')
                .addClass('mmt-processing')
                .show();
            
            // Send AJAX request
            jQuery.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mmt_regenerate_size',
                    attachment_id: attachmentId,
                    _wpnonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.text(response.data.message || 'Done!')
                            .removeClass('mmt-error mmt-processing')
                            .addClass('mmt-success')
                            .show();
                        
                        // Refresh the image
                        const $sizeSelect = jQuery('#image-details-size');
                        if ($sizeSelect.length > 0) {
                            $sizeSelect.trigger('change');
                        } else {
                            setTimeout(function() {
                                const $img = jQuery('.column-image .image img');
                                if ($img.length > 0) {
                                    const src = $img.attr('src');
                                    if (src) {
                                        const bustedUrl = src + (src.indexOf('?') >= 0 ? '&' : '?') + 'v=' + Date.now();
                                        $img.attr('src', bustedUrl);
                                    }
                                }
                            }, 500);
                        }
                        
                        // Reset button after a delay
                        setTimeout(function() {
                            $button.val('Regenerate').prop('disabled', false);
                            $status.fadeOut('slow');
                        }, 3000);
                    } else {
                        $status.text(response.data || 'Error')
                            .removeClass('mmt-processing mmt-success')
                            .addClass('mmt-error')
                            .show();
                        
                        $button.val('Regenerate').prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    $status.text('Error: ' + error)
                        .removeClass('mmt-processing mmt-success')
                        .addClass('mmt-error')
                        .show();
                    
                    $button.val('Regenerate').prop('disabled', false);
                }
            });
        }
    };
    
    // Initialize when document is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', MMTMediaModal.init);
    } else {
        MMTMediaModal.init();
    }
    
    // Also initialize when jQuery is ready
    if (typeof jQuery !== 'undefined') {
        jQuery(function() {
            MMTMediaModal.init();
        });
    }

})();

