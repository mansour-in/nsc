/**
 * NSC Video Upload JavaScript
 */
jQuery(document).ready(function($) {
    const MAX_FILE_SIZE = nsc_ajax.max_file_size || 30 * 1024 * 1024; // 30MB in bytes
    const MAX_DURATION = nsc_ajax.max_duration || 120; // 2 minutes in seconds
    
    // Video Upload Form
    const uploadForm = $('#nsc-video-upload-form');
    if (uploadForm.length) {
        const fileInput = $('#nsc-video-file');
        const uploadButton = $('#nsc-upload-button');
        const progressContainer = $('.nsc-progress-container');
        const progressBar = $('.nsc-progress-bar');
        const progressPercentage = $('.nsc-progress-percentage');
        const errorMessage = $('#nsc-upload-error');
        
        // File input change handler - validate file
        fileInput.on('change', function() {
            errorMessage.hide();
            
            const file = this.files[0];
            if (!file) return;
            
            // Check file type
            if (file.type !== 'video/mp4') {
                errorMessage.text('Only MP4 videos are allowed.').show();
                fileInput.val('');
                return;
            }
            
            // Check file size
            if (file.size > MAX_FILE_SIZE) {
                errorMessage.text(`File size exceeds the maximum limit of ${MAX_FILE_SIZE / (1024 * 1024)}MB.`).show();
                fileInput.val('');
                return;
            }
            
            // Check video duration if browser supports it
            if (window.URL && window.URL.createObjectURL) {
                const video = document.createElement('video');
                video.preload = 'metadata';
                
                video.onloadedmetadata = function() {
                    URL.revokeObjectURL(video.src);
                    
                    if (video.duration > MAX_DURATION) {
                        errorMessage.text(`Video length exceeds the maximum limit of ${MAX_DURATION} seconds.`).show();
                        fileInput.val('');
                    }
                };
                
                video.src = URL.createObjectURL(file);
            }
        });
        
        // Form submit handler - upload file
        uploadForm.on('submit', function(e) {
            e.preventDefault();
            
            const file = fileInput[0].files[0];
            if (!file) {
                errorMessage.text('Please select a video file.').show();
                return;
            }
            
            // Create FormData object
            const formData = new FormData();
            formData.append('action', 'nsc_upload_video');
            formData.append('nsc_upload_nonce', $('#nsc_upload_nonce').val());
            formData.append('nsc_video_file', file);
            formData.append('user_id', $('input[name="user_id"]').val());
            formData.append('category', $('input[name="category"]').val());
            
            // Disable form and show progress
            uploadButton.prop('disabled', true).text('Uploading...');
            progressContainer.show();
            progressBar.css('width', '0%');
            progressPercentage.text('0%');
            
            // Upload file with progress tracking
            $.ajax({
                url: nsc_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            const percent = Math.round((e.loaded / e.total) * 100);
                            progressBar.css('width', percent + '%');
                            progressPercentage.text(percent + '%');
                        }
                    }, false);
                    
                    return xhr;
                },
                success: function(response) {
                    if (response.success) {
                        // Redirect to confirmation page
                        window.location.href = response.data.redirect_url;
                    } else {
                        errorMessage.text(response.data.message || 'Upload failed.').show();
                        uploadButton.prop('disabled', false).text('Upload Video');
                        progressContainer.hide();
                    }
                },
                error: function() {
                    errorMessage.text('An error occurred during upload. Please try again.').show();
                    uploadButton.prop('disabled', false).text('Upload Video');
                    progressContainer.hide();
                }
            });
        });
    }
    
    // Delete Video Button
    const deleteButton = $('#nsc-delete-video');
    if (deleteButton.length) {
        deleteButton.on('click', function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to delete this video and upload a new one?')) {
                return;
            }
            
            const uploadId = $(this).data('upload-id');
            
            $.ajax({
                url: nsc_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'nsc_delete_video',
                    nsc_delete_nonce: $('#nsc_delete_nonce').val(),
                    upload_id: uploadId
                },
                beforeSend: function() {
                    deleteButton.prop('disabled', true).text('Deleting...');
                },
                success: function(response) {
                    if (response.success) {
                        window.location.href = response.data.redirect_url;
                    } else {
                        alert(response.data.message || 'Failed to delete video.');
                        deleteButton.prop('disabled', false).text('Delete & Re-upload');
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                    deleteButton.prop('disabled', false).text('Delete & Re-upload');
                }
            });
        });
    }
    
    // Confirm Video Form
    const confirmForm = $('#nsc-confirm-video-form');
    if (confirmForm.length) {
        confirmForm.on('submit', function(e) {
            e.preventDefault();
            
            const confirmButton = $('#nsc-confirm-button');
            const errorMessage = $('#nsc-confirm-error');
            
            $.ajax({
                url: nsc_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'nsc_confirm_video',
                    nsc_confirm_nonce: $('#nsc_confirm_nonce').val(),
                    upload_id: $('input[name="upload_id"]').val()
                },
                beforeSend: function() {
                    confirmButton.prop('disabled', true).text('Processing...');
                    errorMessage.hide();
                },
                success: function(response) {
                    if (response.success) {
                        window.location.href = response.data.redirect_url;
                    } else {
                        errorMessage.text(response.data.message || 'Confirmation failed.').show();
                        confirmButton.prop('disabled', false).text('Confirm Submission');
                    }
                },
                error: function() {
                    errorMessage.text('An error occurred. Please try again.').show();
                    confirmButton.prop('disabled', false).text('Confirm Submission');
                }
            });
        });
    }
    
    // Delete Video Form
    const deleteForm = $('#nsc-delete-video-form');
    if (deleteForm.length) {
        deleteForm.on('submit', function(e) {
            e.preventDefault();
            
            const deleteButton = $('#nsc-delete-button');
            const errorMessage = $('#nsc-confirm-error');
            
            $.ajax({
                url: nsc_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'nsc_delete_video',
                    nsc_delete_nonce: $('#nsc_delete_nonce').val(),
                    upload_id: $('input[name="upload_id"]').val()
                },
                beforeSend: function() {
                    deleteButton.prop('disabled', true).text('Processing...');
                    errorMessage.hide();
                },
                success: function(response) {
                    if (response.success) {
                        window.location.href = response.data.redirect_url;
                    } else {
                        errorMessage.text(response.data.message || 'Deletion failed.').show();
                        deleteButton.prop('disabled', false).text('Upload New Video');
                    }
                },
                error: function() {
                    errorMessage.text('An error occurred. Please try again.').show();
                    deleteButton.prop('disabled', false).text('Upload New Video');
                }
            });
        });
    }
});
