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
        this.$container = null;
        this.jobId = 'mmt-job-' + Date.now();
        this.leaveWarningHandler = null;
        this.linkClickHandler = null;
        this.originalOnBeforeUnload = undefined;
    };
    
    /**
     * Start a regeneration job
     */
    window.ThumbnailRegenerationJob.prototype.start = function() {
        var self = this;
        
        // Create and show job UI
        this.createJobUI();
        
        // Add page leave warning
        this.enableLeaveWarning();
        
        // Get all media files
        this.loadMediaFiles(function(mediaIds) {
            self.mediaFiles = mediaIds;
            self.totalCount = mediaIds.length;
            self.currentIndex = 0;
            self.isProcessing = true;
            
            // Update UI
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
        var html = '<div id="' + this.jobId + '" class="mmt-job-progress" style="' +
            'background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%); ' +
            'border: 1px solid #e9ecef; border-radius: 6px; padding: 16px; ' +
            'margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);">' +
            '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">' +
            '<div>' +
            '<h4 style="margin: 0; font-size: 14px; font-weight: 600; color: #2c3e50;">Regenerating Thumbnails</h4>' +
            '<p style="margin: 4px 0 0 0; font-size: 12px; color: #6c757d;">Processing your media library...</p>' +
            '</div>' +
            '<button class="mmt-job-stop" style="background: #dc3545; color: white; border: none; border-radius: 3px; padding: 6px 12px; font-size: 12px; font-weight: 500; cursor: pointer; transition: background 0.2s;">Stop</button>' +
            '</div>' +
            '<div style="position: relative;">' +
            '<progress id="' + this.jobId + '-progress" value="0" max="100" style="' +
            'width: 100%; height: 6px; border-radius: 3px; border: none; ' +
            'background-color: #e9ecef; appearance: none; -webkit-appearance: none; -moz-appearance: none;"' +
            '></progress>' +
            '<span id="' + this.jobId + '-count" style="' +
            'display: inline-block; margin-top: 8px; font-size: 12px; color: #6c757d; font-weight: 500;"' +
            '>0 / 0</span>' +
            '</div>' +
            '</div>';
        
        // Add progress bar styling for webkit browsers
        var style = document.createElement('style');
        style.textContent = '@media all {\n' +
            '#' + this.jobId + '-progress::-webkit-progress-bar {\n' +
            '  background-color: #e9ecef;\n' +
            '  border-radius: 3px;\n' +
            '  height: 6px;\n' +
            '}\n' +
            '#' + this.jobId + '-progress::-webkit-progress-value {\n' +
            '  background: linear-gradient(90deg, #0066cc 0%, #0052a3 100%);\n' +
            '  border-radius: 3px;\n' +
            '  transition: width 0.3s ease;\n' +
            '}\n' +
            '#' + this.jobId + '-progress::-moz-progress-bar {\n' +
            '  background: linear-gradient(90deg, #0066cc 0%, #0052a3 100%);\n' +
            '  border-radius: 3px;\n' +
            '  border: none;\n' +
            '}\n' +
            '.mmt-job-stop {\n' +
            '  background: #dc3545;\n' +
            '}\n' +
            '.mmt-job-stop:hover {\n' +
            '  background: #c82333 !important;\n' +
            '}\n' +
            '}';
        document.head.appendChild(style);
        
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
     * Load list of media files
     */
    window.ThumbnailRegenerationJob.prototype.loadMediaFiles = function(callback) {
        var self = this;
        
        jQuery.ajax({
            url: mmtData.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'mmt_get_media_files_list',
                nonce: mmtData.nonce
            },
            success: function(response) {
                if (response.success && response.data && response.data.media_ids) {
                    callback(response.data.media_ids);
                } else {
                    alert('Error: Could not load media files list');
                    self.stop();
                }
            },
            error: function(xhr, status, error) {
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
        
        if (this.currentIndex >= this.totalCount) {
            // All done
            this.complete();
            return;
        }
        
        var attachmentId = this.mediaFiles[this.currentIndex];
        var sizeName = this.options.size || null;
        
        // Prepare AJAX data
        var ajaxData = {
            action: 'mmt_regenerate_size',
            nonce: mmtData.nonce,
            attachment_id: attachmentId
        };
        
        if (sizeName) {
            ajaxData.size = sizeName;
        }
        
        jQuery.ajax({
            url: mmtData.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: ajaxData,
            timeout: 30000,
            success: function(response) {
                self.currentIndex++;
                self.updateProgress(self.currentIndex, self.totalCount);
                
                // Process next after a small delay
                setTimeout(function() {
                    self.processNext();
                }, 200);
            },
            error: function(xhr, status, error) {
                self.currentIndex++;
                self.updateProgress(self.currentIndex, self.totalCount);
                
                // Continue with next even on error
                setTimeout(function() {
                    self.processNext();
                }, 200);
            }
        });
    };
    
    /**
     * Update progress bar and counter
     */
    window.ThumbnailRegenerationJob.prototype.updateProgress = function(current, total) {
        if (!this.$container) return;
        
        var progressEl = this.$container.querySelector('#' + this.jobId + '-progress');
        var countEl = this.$container.querySelector('#' + this.jobId + '-count');
        
        if (progressEl) {
            var percent = total > 0 ? Math.round((current / total) * 100) : 0;
            progressEl.setAttribute('max', 100);
            progressEl.value = percent;
        }
        
        if (countEl) {
            countEl.textContent = current + ' / ' + total;
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
        
        if (this.$container) {
            var countEl = this.$container.querySelector('#' + this.jobId + '-count');
            if (countEl) {
                countEl.style.color = '#28a745';
                countEl.textContent = 'Complete!';
            }
        }
    };
    
    /**
     * Stop the job
     */
    window.ThumbnailRegenerationJob.prototype.stop = function() {
        this.isProcessing = false;
        this.disableLeaveWarning();
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
