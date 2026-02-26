/**
 * Thumbnail Regeneration Job Handler
 * 
 * Manages frontend regeneration jobs with UI and progress tracking
 */

(function() {
    'use strict';
    
    /**
     * ThumbnailRegenerationJob - Handles regenerating thumbnails for media files
     */
    window.ThumbnailRegenerationJob = function(options) {
        this.options = options || {};
        this.mediaFiles = [];
        this.currentIndex = 0;
        this.totalCount = 0;
        this.isProcessing = false;
        this.stopped = false;
        this.$container = null;
        this.jobId = 'mmt-job-' + Date.now();
        this.leaveWarningHandler = null;
        this.linkClickHandler = null;
        this.originalOnBeforeUnload = undefined;
        this.pendingTimeout = null;
        this.currentAjaxRequest = null;
    };
    
    // Global static instance tracking
    window.ThumbnailRegenerationJob.activeJob = null;
    
    // Static method to get the title based on job type
    window.ThumbnailRegenerationJob.getJobTitle = function(options) {
        var title = 'Regenerating Thumbnails';

        if (options.type === 'all') {
            title = 'Regenerating All Thumbnails';
        } else if (options.type === 'size' && options.size) {
            var displayName = options.size === 'Thumbnails' ? 'Thumbnails' : options.size;
            title = 'Regenerating ' + displayName + ' Thumbnails';
        } else if (options.type === 'restore') {
            title = 'Restoring WordPress Originals';
        }
        
        return title;
    };
    
    /**
     * Start a regeneration job
     */
    window.ThumbnailRegenerationJob.prototype.start = function() {
        var self = this;
        
        // Check if another job is already running
        if (window.ThumbnailRegenerationJob.activeJob !== null) {
            alert('A regeneration job is already running. Please wait for it to complete or click Stop.');
            return;
        }
        
        // Set this as the active job
        window.ThumbnailRegenerationJob.activeJob = this;
        
        // Create and show job UI
        this.createJobUI();
        
        // Disable all regenerate buttons
        this.disableRegenerateButtons();
        
        // Add page leave warning
        this.enableLeaveWarning();
        
        // Get all media files
        this.loadMediaFiles(function(mediaIds) {
            self.mediaFiles = mediaIds;
            self.totalCount = mediaIds.length;
            self.currentIndex = 0;
            self.isProcessing = true;
            
            // Update UI with initial progress
            self.updateProgress(0, self.totalCount);
            
            // Start processing
            self.processNext();
        });
    };
    
    /**
     * Create the job UI block
     */
    window.ThumbnailRegenerationJob.prototype.createJobUI = function() {
        var self = this;
        
        // Check if job UI already exists and remove it
        var existing = document.getElementById(this.jobId);
        if (existing) {
            existing.remove();
        }
        
        // Look for the current tab content area to insert into
        var tabContent = document.querySelector('.tab-content');
        var insertTarget = tabContent;
        
        // Fallback to wrap div if no tab content found
        if (!insertTarget) {
            insertTarget = document.querySelector('.wrap');
        }
        
        // Create job container with modern styling
        var title = window.ThumbnailRegenerationJob.getJobTitle(this.options);
        var html = '<div id="' + this.jobId + '" class="mmt-job-progress">' +
            '<div class="mmt-job-header">' +
            '<div>' +
            '<h3 class="mmt-job-title">' + title + '</h3>' +
            '<p class="mmt-job-subtitle">Keep this window open while processing your media files.</p>' +
            '</div>' +
            '<button class="mmt-job-stop">Stop</button>' +
            '</div>' +
            '<div class="mmt-job-progress-bar-container">' +
            '<progress id="' + this.jobId + '-progress" class="mmt-job-progress-element" value="0" max="100"></progress>' +
            '<span id="' + this.jobId + '-count" class="mmt-job-count">0 / 0</span>' +
            '</div>' +
            '<div id="' + this.jobId + '-path" class="mmt-job-path"></div>' +
            '</div>';
        
        // CSS is now enqueued via wp_enqueue_style in PHP
        
        // Insert at the beginning of the tab content
        if (insertTarget) {
            var container = document.createElement('div');
            container.innerHTML = html;
            insertTarget.insertBefore(container.firstChild, insertTarget.firstChild);
        }
        
        this.$container = document.getElementById(this.jobId);
        
        // Stop button handler
        var stopBtn = this.$container.querySelector('.mmt-job-stop');
        stopBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (self.isProcessing) {
                if (confirm('Regeneration is in progress. Do you want to stop it?')) {
                    self.stop();
                }
            } else {
                self.$container.remove();
            }
        });
    };
    
    /**
     * Disable all regenerate buttons while job is running
     */
    window.ThumbnailRegenerationJob.prototype.disableRegenerateButtons = function() {
        var buttons = document.querySelectorAll('.mmt-regenerate-size, #mmt-regenerate-all, #mmt-restore-originals');
        buttons.forEach(function(btn) {
            btn.disabled = true;
            btn.classList.add('mmt-regenerate-button');
        });
    };
    
    /**
     * Enable all regenerate buttons when job is done
     */
    window.ThumbnailRegenerationJob.prototype.enableRegenerateButtons = function() {
        var buttons = document.querySelectorAll('.mmt-regenerate-size, #mmt-regenerate-all, #mmt-restore-originals');
        buttons.forEach(function(btn) {
            btn.disabled = false;
            btn.classList.remove('mmt-regenerate-button');
        });
    };
    
    /**
     * Load list of media files
     */
    window.ThumbnailRegenerationJob.prototype.loadMediaFiles = function(callback) {
        var self = this;
        
        this.currentAjaxRequest = jQuery.ajax({
            url: mmtData.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'mmt_get_media_files_list',
                nonce: mmtData.nonce
            },
            success: function(response) {
                self.currentAjaxRequest = null;
                if (response.success && response.data && response.data.media_ids) {
                    callback(response.data.media_ids);
                } else {
                    alert('Error: Could not load media files list');
                    self.stop();
                }
            },
            error: function(xhr, status, error) {
                self.currentAjaxRequest = null;
                alert('Error loading media files: ' + error);
                self.stop();
            }
        });
    };
    
    /**
     * Process next media file
     */
    window.ThumbnailRegenerationJob.prototype.processNext = function() {
        var self = this;
        
        // If job was stopped, don't process further
        if (this.stopped) {
            return;
        }
        
        if (this.currentIndex >= this.totalCount) {
            // All done
            this.complete();
            return;
        }
        
        var attachmentId = this.mediaFiles[this.currentIndex];
        var sizeName = this.options.size || null;
        
        // Prepare AJAX data. Use restore endpoints if this is a restore job.
        var ajaxData = {
            action: this.options.type === 'restore' ? 'mmt_restore_queue_process' : 'mmt_regenerate_size',
            nonce: mmtData.nonce,
            attachment_id: attachmentId
        };

        if (sizeName) {
            ajaxData.size = sizeName;
        }
        
        // Store the AJAX request for potential abort, capturing the jqXHR object
        this.currentAjaxRequest = jQuery.ajax({
            url: mmtData.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: ajaxData,
            timeout: 30000,
            success: function(response) {
                // Clear the stored request reference
                self.currentAjaxRequest = null;
                
                // If job was stopped, don't continue
                if (self.stopped) {
                    return;
                }
                
                self.currentIndex++;
                
                // Format message based on response count
                var displayPath = '';
                var responseData = response.data || response;
                
                if (responseData.file_path) {
                    if (responseData.count > 0) {
                        displayPath = 'Last processed media file: ' + responseData.file_path;
                    } else if (responseData.count === 0) {
                        displayPath = 'Skipped ' + responseData.file_path + ': file not found';
                    }
                }
                
                self.updateProgress(self.currentIndex, self.totalCount, displayPath);
                
                // Process next after a small delay
                self.pendingTimeout = setTimeout(function() {
                    self.pendingTimeout = null;
                    self.processNext();
                }, 200);
            },
            error: function(xhr, status, error) {
                // Clear the stored request reference
                self.currentAjaxRequest = null;
                
                // If job was stopped, don't continue
                if (self.stopped) {
                    return;
                }
                
                self.currentIndex++;
                self.updateProgress(self.currentIndex, self.totalCount, null);
                
                // Continue with next even on error
                self.pendingTimeout = setTimeout(function() {
                    self.pendingTimeout = null;
                    self.processNext();
                }, 200);
            }
        });
    };
    
    /**
     * Update progress bar and counter
     */
    window.ThumbnailRegenerationJob.prototype.updateProgress = function(current, total, filePath) {
        if (!this.$container) return;
        
        var progressEl = this.$container.querySelector('#' + this.jobId + '-progress');
        var countEl = this.$container.querySelector('#' + this.jobId + '-count');
        var pathEl = this.$container.querySelector('#' + this.jobId + '-path');
        
        if (progressEl) {
            var percent = total > 0 ? Math.round((current / total) * 100) : 0;
            progressEl.setAttribute('max', 100);
            progressEl.value = percent;
        }
        
        if (countEl) {
            countEl.textContent = current + ' / ' + total;
        }
        
        // Update file path immediately if provided
        if (filePath && typeof filePath === 'string' && filePath.length > 0) {
            if (pathEl) {
                var isSkipped = filePath.indexOf('Skipped') === 0;
                pathEl.textContent = filePath;
                pathEl.className = 'mmt-job-path ' + (isSkipped ? 'mmt-job-path-skipped' : 'mmt-job-path-success');
            }
        }
    };
    
    /**
     * Log message to job UI (no-op for simplified UI)
     */
    window.ThumbnailRegenerationJob.prototype.log = function(message) {
        // Logging disabled for simplified UI
        // Message can still be logged to console if needed
        if (window.console && window.console.log) {
            console.log('[MMT Job] ' + message);
        }
    };
    
    /**
     * Finish the job
     */
    window.ThumbnailRegenerationJob.prototype.complete = function() {
        this.isProcessing = false;
        this.disableLeaveWarning();
        
        // Enable buttons and clear active job
        this.enableRegenerateButtons();
        window.ThumbnailRegenerationJob.activeJob = null;
        
        if (this.$container) {
            var countEl = this.$container.querySelector('#' + this.jobId + '-count');
            if (countEl) {
                countEl.style.color = '#00ff88';
                countEl.textContent = 'Complete!';
            }
        }
    };
    
    /**
     * Stop the job
     */
    window.ThumbnailRegenerationJob.prototype.stop = function() {
        this.isProcessing = false;
        this.stopped = true;
        
        // Clear any pending timeout
        if (this.pendingTimeout !== null) {
            clearTimeout(this.pendingTimeout);
            this.pendingTimeout = null;
        }
        
        // Abort any in-flight AJAX request
        if (this.currentAjaxRequest !== null && typeof this.currentAjaxRequest.abort === 'function') {
            this.currentAjaxRequest.abort();
            this.currentAjaxRequest = null;
        }
        
        this.disableLeaveWarning();
        
        // Enable buttons and clear active job
        this.enableRegenerateButtons();
        window.ThumbnailRegenerationJob.activeJob = null;
        
        if (this.$container) {
            this.$container.remove();
        }
    };
    
    /**
     * Enable page leave warning
     */
    window.ThumbnailRegenerationJob.prototype.enableLeaveWarning = function() {
        var self = this;
        
        // Store WordPress's original onbeforeunload handler
        this.originalOnBeforeUnload = window.onbeforeunload;
        
        // Store original pagenow and do_page_check WordPress state
        if (typeof jQuery !== 'undefined' && jQuery.fn.on) {
            // Prevent form dirty checking
            if (typeof window.beforeunload !== 'undefined') {
                this.originalBeforeUnload = window.beforeunload;
            }
        }
        
        // Handler for beforeunload (back button, close window, etc.)
        this.leaveWarningHandler = function(e) {
            if (self.isProcessing) {
                e.preventDefault();
                e.returnValue = 'Thumbnail regeneration is in progress. The page must remain open to complete the process.';
                return 'Thumbnail regeneration is in progress. The page must remain open to complete the process.';
            }
        };
        
        // Handler for link clicks (prevent navigation away from settings page or tab changes)
        this.linkClickHandler = function(e) {
            if (!self.isProcessing) return;
            
            var target = e.target;
            var href = null;
            
            // Find the actual link element
            while (target && target !== document) {
                if (target.tagName === 'A') {
                    href = target.getAttribute('href');
                    break;
                }
                target = target.parentNode;
            }
            
            if (!href || href === '#') return;
            
            // Show warning for any navigation (tab changes or leaving page)
            e.preventDefault();
            e.stopPropagation();
            if (confirm('Thumbnail regeneration is in progress. If you leave this page, the process will be interrupted. Click OK to leave anyway, or Cancel to continue regenerating.')) {
                self.stop();
                // Allow navigation by manually following the link
                setTimeout(function() {
                    window.location.href = href;
                }, 0);
            }
        };
        
        // Replace WordPress's beforeunload with our custom one
        window.onbeforeunload = this.leaveWarningHandler;
        document.addEventListener('click', this.linkClickHandler, true);
    };
    
    /**
     * Disable page leave warning
     */
    window.ThumbnailRegenerationJob.prototype.disableLeaveWarning = function() {
        // Restore WordPress's original onbeforeunload handler
        if (this.originalOnBeforeUnload !== undefined) {
            window.onbeforeunload = this.originalOnBeforeUnload;
        } else {
            window.onbeforeunload = null;
        }
        
        if (this.leaveWarningHandler) {
            window.removeEventListener('beforeunload', this.leaveWarningHandler);
            this.leaveWarningHandler = null;
        }
        
        if (this.linkClickHandler) {
            document.removeEventListener('click', this.linkClickHandler, true);
            this.linkClickHandler = null;
        }
    };
})();
