/**
 * Media Details Page Integration
 * 
 * Handles regenerate thumbnail button on individual media edit pages.
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        const button = document.getElementById('mmt-regenerate-btn');
        const statusDiv = document.getElementById('mmt-regenerate-status');

        if (!button) {
            return;
        }

        button.addEventListener('click', function(e) {
            e.preventDefault();

            // Get data from localized script
            const postId = (typeof mmtMediaDetails !== 'undefined' && mmtMediaDetails.postId) 
                ? mmtMediaDetails.postId 
                : null;
            const ajaxUrl = (typeof mmtMediaDetails !== 'undefined' && mmtMediaDetails.ajaxUrl) 
                ? mmtMediaDetails.ajaxUrl 
                : '/wp-admin/admin-ajax.php';
            const nonce = (typeof mmtMediaDetails !== 'undefined' && mmtMediaDetails.nonce) 
                ? mmtMediaDetails.nonce 
                : '';

            if (!postId || !nonce) {
                if (statusDiv) {
                    statusDiv.textContent = 'Error: Missing required data';
                    statusDiv.style.display = 'inline-block';
                    statusDiv.className = 'mmt-regenerate-status mmt-error';
                }
                return;
            }

            // Disable button and show progress
            button.disabled = true;
            button.textContent = 'Regenerating...';

            if (statusDiv) {
                statusDiv.textContent = 'Processing...';
                statusDiv.style.display = 'inline-block';
                statusDiv.className = 'mmt-regenerate-status mmt-processing';
            }

            // Send AJAX request
            const data = new FormData();
            data.append('action', 'mmt_regenerate_single');
            data.append('post_id', postId);
            data.append('_wpnonce', nonce);

            fetch(ajaxUrl, {
                method: 'POST',
                body: data
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`AJAX error: ${response.status} ${response.statusText}`);
                }
                return response.json();
            })
            .then(result => {
                // Re-enable button
                button.disabled = false;
                button.textContent = 'Regenerate Thumbnails';

                if (result.success) {
                    // Show success message
                    if (statusDiv) {
                        statusDiv.textContent = result.data?.message || 'Successfully regenerated thumbnail';
                        statusDiv.className = 'mmt-regenerate-status mmt-success';
                        statusDiv.style.display = 'inline-block';

                        // Auto-hide success message after 5 seconds
                        setTimeout(() => {
                            statusDiv.style.display = 'none';
                        }, 5000);
                    }

                    // Update thumbnail preview with new URL from response
                    if (result.data && result.data.thumbnails) {
                        // Prefer post-thumbnail first (used by featured images), then thumbnail
                        let selectedThumbnailUrl = null;
                        
                        if (result.data.thumbnails['post-thumbnail']) {
                            selectedThumbnailUrl = result.data.thumbnails['post-thumbnail'];
                        } else if (result.data.thumbnails['thumbnail']) {
                            selectedThumbnailUrl = result.data.thumbnails['thumbnail'];
                        } else {
                            // Find the smallest size by looking for common small sizes
                            const smallSizes = ['square', 'small', 'thumb'];
                            for (const size of smallSizes) {
                                if (result.data.thumbnails[size]) {
                                    selectedThumbnailUrl = result.data.thumbnails[size];
                                    break;
                                }
                            }
                            // If still not found, use the first available
                            if (!selectedThumbnailUrl) {
                                selectedThumbnailUrl = Object.values(result.data.thumbnails)[0];
                            }
                        }
                        
                        if (selectedThumbnailUrl) {
                            const img = document.querySelector('.attachment-preview img');
                            if (img) {
                                img.src = selectedThumbnailUrl;
                                // Remove responsive image attributes that might override our URL
                                if (img.hasAttribute('srcset')) {
                                    img.removeAttribute('srcset');
                                }
                                if (img.hasAttribute('sizes')) {
                                    img.removeAttribute('sizes');
                                }
                            }
                        }
                    }
                } else {
                    // Show error message
                    if (statusDiv) {
                        statusDiv.textContent = result.data?.message || 'An error occurred';
                        statusDiv.className = 'mmt-regenerate-status mmt-error';
                        statusDiv.style.display = 'inline-block';
                    }
                }
            })
            .catch(error => {
                // Re-enable button
                button.disabled = false;
                button.textContent = 'Regenerate Thumbnails';

                // Show error message
                if (statusDiv) {
                    statusDiv.textContent = 'Error: ' + error.message;
                    statusDiv.className = 'mmt-regenerate-status mmt-error';
                    statusDiv.style.display = 'inline-block';
                }
            });
        });
    });
})();
