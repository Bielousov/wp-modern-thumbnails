/**
 * Bulk Actions UI
 * 
 * Handles UI feedback for bulk actions like loading states.
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('#posts-filter');
        const theList = document.getElementById('the-list');

        if (!form || !theList) {
            return;
        }

        // Option 1: Intercept form submit event
        form.addEventListener('submit', handleFormSubmit, true);  // useCapture = true to catch event early
        
        // Option 2: Also intercept if jQuery is being used for form submission
        if (typeof jQuery !== 'undefined') {
            jQuery(form).on('submit', handleFormSubmit);
        }
        
        // Option 3: Intercept button clicks (WordPress bulk actions might use buttons instead of form submission)
        const submitButtons = form.querySelectorAll('button[type="submit"]');
        submitButtons.forEach((btn, idx) => {
            btn.addEventListener('click', function(e) {
                handleFormSubmit(e);
            }, true);
        });

        function handleFormSubmit(e) {
            // Get the selected action (either from action or action2 dropdown)
            const actionSelect = form.querySelector('select[name="action"]');
            const actionSelect2 = form.querySelector('select[name="action2"]');
            
            let selectedAction = actionSelect && actionSelect.value ? actionSelect.value : '';
            if (!selectedAction && actionSelect2) {
                selectedAction = actionSelect2.value;
            }

            // Only handle our bulk action
            if (selectedAction !== 'mmt_regenerate_thumbnails') {
                return true;
            }

            // Get all checked checkboxes
            const checkedBoxes = Array.from(form.querySelectorAll('input[name="media[]"]:checked'));

            // If no items selected, let WordPress handle validation without our interference
            if (checkedBoxes.length === 0) {
                return true;
            }

            // PREVENT FORM SUBMISSION - This is critical!
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();

            // Show loading state on selected items
            checkedBoxes.forEach(checkbox => {
                const row = checkbox.closest('tr');
                if (row && row.id) {
                    const idRow = document.getElementById(row.id);
                    if (idRow) {
                        idRow.classList.add('mmt-processing');
                    }
                }
            });

            // Disable action dropdowns and buttons
            const buttons = form.querySelectorAll('button[type="submit"]');
            buttons.forEach(btn => btn.disabled = true);
            
            const selects = form.querySelectorAll('select[name="action"], select[name="action2"]');
            selects.forEach(sel => sel.disabled = true);

            // Show info notice
            const notice = document.createElement('div');
            notice.id = 'mmt-bulk-notice';
            notice.className = 'notice notice-info is-dismissible';
            notice.innerHTML = `
                <p>
                    <strong>Modern Thumbnails:</strong> 
                    <span id="mmt-progress">Processing ${checkedBoxes.length} image(s)...</span>
                </p>
            `;

            const submitDiv = form.querySelector('.tablenav .actions');
            if (submitDiv) {
                submitDiv.insertBefore(notice, submitDiv.firstChild);
            }

            // Submit the form via fetch to avoid page reload
            submitBulkActionForm(form, checkedBoxes, buttons, selects);
            
            return false;  // Explicitly return false to prevent form submission
        }
    });

    /**
     * Submit bulk action form via AJAX, processing one image at a time
     */
    function submitBulkActionForm(form, checkedBoxes, buttons, selects) {
        // Extract image IDs from checked boxes
        const imageIds = checkedBoxes.map(checkbox => checkbox.value);
        let processed = 0;
        let errors = 0;
        
        // Get nonce from localized script (preferred) or form fallback
        const nonce = (typeof mmtBulkActions !== 'undefined' && mmtBulkActions.nonce) 
            ? mmtBulkActions.nonce 
            : form.querySelector('input[name="_wpnonce"]')?.value || '';
        
        // Get ajax URL (from localized script or WordPress global)
        const ajaxUrl = (typeof mmtBulkActions !== 'undefined' && mmtBulkActions.ajaxUrl) 
            ? mmtBulkActions.ajaxUrl 
            : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');

        // Process images one by one
        function processNextImage() {
            if (processed + errors >= imageIds.length) {
                // Update notice with completion message
                const progress = document.getElementById('mmt-progress');
                if (progress) {
                    if (errors === 0) {
                        progress.textContent = `✓ All ${imageIds.length} image(s) processed successfully!`;
                    } else {
                        progress.textContent = `Completed: ${processed} successful, ${errors} failed out of ${imageIds.length}`;
                    }
                }
                
                // Re-enable buttons and selects
                const buttons = form.querySelectorAll('button[type="submit"]');
                buttons.forEach(btn => btn.disabled = false);
                
                const selects = form.querySelectorAll('select[name="action"], select[name="action2"]');
                selects.forEach(sel => sel.disabled = false);
                
                return;
            }

            const currentIndex = processed + errors;
            const imageId = imageIds[currentIndex];
            const row = document.getElementById('post-' + imageId);

            if (!row) {
                errors++;
                processNextImage();
                return;
            }

            // Send AJAX request for this image
            const data = new FormData();
            data.append('action', 'mmt_regenerate_single');
            data.append('post_id', imageId);
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
                if (result.success) {
                    processed++;
                    
                    // Update thumbnail image with new WebP URL from response
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
                            const postElement = document.getElementById('post-' + imageId);
                            
                            // Update the media-icon image
                            const mediaIcon = postElement?.querySelector('.media-icon img');
                            if (mediaIcon) {
                                mediaIcon.src = selectedThumbnailUrl;
                                
                                // Update width and height attributes
                                // Try to get dimensions from metadata.sizes.thumbnail first
                                let thumbWidth = null;
                                let thumbHeight = null;
                                
                                if (result.data && result.data.attachment && result.data.attachment.sizes && result.data.attachment.sizes.thumbnail) {
                                    thumbWidth = result.data.attachment.sizes.thumbnail.width;
                                    thumbHeight = result.data.attachment.sizes.thumbnail.height;
                                } else {
                                    // Fallback: extract dimensions from class (e.g., "attachment-60x60" -> 60)
                                    const classMatch = mediaIcon.className.match(/attachment-(\d+)x(\d+)/);
                                    if (classMatch) {
                                        thumbWidth = parseInt(classMatch[1]);
                                        thumbHeight = parseInt(classMatch[2]);
                                    }
                                }
                                
                                // Update attributes if we have dimensions
                                if (thumbWidth && thumbHeight) {
                                    mediaIcon.setAttribute('width', thumbWidth);
                                    mediaIcon.setAttribute('height', thumbHeight);
                                } else {
                                    // If we can't determine dimensions, remove the broken 1x1 attributes
                                    mediaIcon.removeAttribute('width');
                                    mediaIcon.removeAttribute('height');
                                }
                                
                                // Also update srcset and other attributes if they exist
                                if (mediaIcon.hasAttribute('srcset')) {
                                    mediaIcon.removeAttribute('srcset');
                                }
                                if (mediaIcon.hasAttribute('sizes')) {
                                    mediaIcon.removeAttribute('sizes');
                                }
                            }
                            
                            // Also try to update any other img elements in the row
                            const allImgs = postElement?.querySelectorAll('img');
                            if (allImgs) {
                                allImgs.forEach(img => {
                                    if (img.closest('.media-icon')) {
                                        img.src = selectedThumbnailUrl;
                                        
                                        // Update width and height attributes
                                        let thumbWidth = null;
                                        let thumbHeight = null;
                                        
                                        if (result.data && result.data.attachment && result.data.attachment.sizes && result.data.attachment.sizes.thumbnail) {
                                            thumbWidth = result.data.attachment.sizes.thumbnail.width;
                                            thumbHeight = result.data.attachment.sizes.thumbnail.height;
                                        } else {
                                            // Fallback: extract dimensions from class
                                            const classMatch = img.className.match(/attachment-(\d+)x(\d+)/);
                                            if (classMatch) {
                                                thumbWidth = parseInt(classMatch[1]);
                                                thumbHeight = parseInt(classMatch[2]);
                                            }
                                        }
                                        
                                        if (thumbWidth && thumbHeight) {
                                            img.setAttribute('width', thumbWidth);
                                            img.setAttribute('height', thumbHeight);
                                        } else {
                                            img.removeAttribute('width');
                                            img.removeAttribute('height');
                                        }
                                        
                                        if (img.hasAttribute('srcset')) {
                                            img.removeAttribute('srcset');
                                        }
                                        if (img.hasAttribute('sizes')) {
                                            img.removeAttribute('sizes');
                                        }
                                    }
                                });
                            }
                        }
                    }
                    
                    // Update image dimensions if available in response
                    if (result.data && result.data.image_width && result.data.image_height) {
                        const postElement = document.getElementById('post-' + imageId);
                        if (postElement) {
                            const dimensionText = `${result.data.image_width} × ${result.data.image_height}`;
                            
                            // Try multiple selectors to find the dimensions column in different WordPress versions
                            let dimensionFound = false;
                            
                            // Try data-attribute selector
                            const colByAttribute = postElement.querySelector('[data-colname="dimensions"]');
                            if (colByAttribute) {
                                const span = colByAttribute.querySelector('span');
                                if (span) {
                                    span.textContent = dimensionText;
                                } else {
                                    colByAttribute.textContent = dimensionText;
                                }
                                dimensionFound = true;
                            }
                            
                            // Try finding by class
                            if (!dimensionFound) {
                                const colByClass = postElement.querySelector('.column-media .desc, .column-dimensions');
                                if (colByClass) {
                                    colByClass.textContent = dimensionText;
                                    dimensionFound = true;
                                }
                            }
                            
                            // Try finding any cell that contains dimension-looking text
                            if (!dimensionFound) {
                                const allCells = postElement.querySelectorAll('td');
                                for (const cell of allCells) {
                                    const text = cell.textContent.trim();
                                    // Check if this cell looks like it contains dimensions (e.g., "1920 × 1277")
                                    if (/^\d+\s*×\s*\d+$/.test(text)) {
                                        cell.textContent = dimensionText;
                                        dimensionFound = true;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                    
                    // Remove processing state from this row
                    if (row) {
                        row.classList.remove('mmt-processing');
                    }
                } else {
                    errors++;
                }

                // Update progress notice
                const progress = document.getElementById('mmt-progress');
                if (progress) {
                    progress.textContent = `Processing ${processed + errors} of ${imageIds.length} image(s)... (${processed} completed, ${errors} failed)`;
                }

                // Process next image
                processNextImage();
            })
            .catch(error => {
                errors++;

                // Update progress notice
                const progress = document.getElementById('mmt-progress');
                if (progress) {
                    progress.textContent = `Processing ${processed + errors} of ${imageIds.length} image(s)... (${processed} completed, ${errors} failed)`;
                }

                // Process next image
                processNextImage();
            });
        }

        // Start processing
        processNextImage();
    }

    /**
     * Clean up after the page loads (post-redirect)
     */
    function cleanupAfterRedirect() {
        // Check if we came from a bulk action redirect
        const urlParams = new URLSearchParams(window.location.search);
        const regenerated = urlParams.get('mmt_regenerated');

        if (regenerated === null) {
            return;
        }

        const form = document.querySelector('#posts-filter');
        if (!form) {
            return;
        }

        // Remove loading states from rows
        document.querySelectorAll('#the-list tr.mmt-processing').forEach(row => {
            row.classList.remove('mmt-processing');

            // Uncheck the checkbox
            const checkbox = row.querySelector('input[name="post[]"]');
            if (checkbox) {
                checkbox.checked = false;
            }
        });

        // Remove the processing notice
        const notice = document.getElementById('mmt-bulk-notice');
        if (notice) {
            notice.remove();
        }

        // Re-enable buttons and selects
        const buttons = form.querySelectorAll('button[type="submit"]');
        buttons.forEach(btn => btn.disabled = false);
        
        const selects = form.querySelectorAll('select[name="action"], select[name="action2"]');
        selects.forEach(sel => sel.disabled = false);

        // Clean up URL to remove our parameters
        if (window.history.replaceState) {
            const newUrl = window.location.origin + window.location.pathname;
            window.history.replaceState({}, document.title, newUrl);
        }
    }

    // Clean up after page loads
    setTimeout(cleanupAfterRedirect, 500);
})();
