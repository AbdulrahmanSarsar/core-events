/**
 * Core Events Pro - Admin JavaScript.
 * 
 * Handles the WordPress Media Uploader logic for the Event Gallery.
 * Allows the user to select multiple images, displays a live preview, 
 * and updates a hidden input field with the attachment IDs.
 *
 * @package CoreEventsPro\Assets
 * @since 4.0.0
 */

jQuery(document).ready(function($) {
    'use strict';

    /**
     * Translation Helper Function.
     * 
     * Safely utilizes WordPress wp.i18n for translating JavaScript strings
     * if the dependency is loaded, otherwise falls back to the original string.
     * 
     * @param {string} text   The string to translate.
     * @param {string} domain The text domain.
     * @return {string}       The translated string.
     */
    function __(text, domain) {
        if (typeof wp !== 'undefined' && wp.i18n && wp.i18n.__) {
            return wp.i18n.__(text, domain);
        }
        return text;
    }

    /**
     * Holds the instance of the WordPress media frame.
     * @type {Object}
     */
    var mediaUploader;

    /**
     * Event Listener: Open Media Uploader.
     * Triggered when the "Manage Images" button is clicked.
     */
    $('#cep_upload_gallery_btn').click(function(e) {
        e.preventDefault();

        // If the uploader object has already been created, reopen the dialog.
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        // Extend and initialize the wp.media object.
        mediaUploader = wp.media.frames.file_frame = wp.media({
            title: __('Select Event Images', 'core-events-pro'),
            button: {
                text: __('Add to Gallery', 'core-events-pro')
            },
            multiple: true // Allow selecting multiple images.
        });

        /**
         * Event Listener: Media Selection.
         * Triggered when the user clicks "Add to Gallery" in the media modal.
         */
        mediaUploader.on('select', function() {
            var selection = mediaUploader.state().get('selection');
            var ids = [];
            
            // Retrieve currently selected IDs to append to them.
            var currentIDs = $('#cep_gallery_ids').val();
            if (currentIDs) {
                // Split by comma and convert strings to Numbers.
                ids = currentIDs.split(',').map(Number);
            }

            // Iterate through the newly selected attachments.
            selection.map(function(attachment) {
                attachment = attachment.toJSON();
                
                // Prevent duplicate images from being added.
                if (!ids.includes(attachment.id)) {
                    ids.push(attachment.id);
                    
                    // Determine the best image URL to display (thumbnail if available, else full size).
                    var imgUrl = (attachment.sizes && attachment.sizes.thumbnail) ? attachment.sizes.thumbnail.url : attachment.url;
                    
                    // Inject the image preview HTML dynamically.
                    $('#cep_gallery_preview').append(
                        '<div class="cep-img-wrap" data-id="' + attachment.id + '">' +
                        '<img src="' + imgUrl + '">' +
                        '<span class="cep-remove-img">&times;</span>' +
                        '</div>'
                    );
                }
            });

            // Update the hidden input field with the new comma-separated IDs.
            $('#cep_gallery_ids').val(ids.join(','));
        });

        // Open the media modal.
        mediaUploader.open();
    });

    /**
     * Event Listener: Remove Image.
     * Triggered when the user clicks the "x" (remove) button on a preview image.
     */
    $(document).on('click', '.cep-remove-img', function() {
        var wrapper    = $(this).parent();
        var idToRemove = wrapper.data('id');
        
        // Remove the visual preview element from the DOM.
        wrapper.remove();
        
        // Retrieve current IDs, remove the deleted ID, and update the hidden input.
        var currentVal = $('#cep_gallery_ids').val();
        if (currentVal) {
            var idsArray = currentVal.split(',');
            
            // Filter out the ID that was just removed.
            idsArray = idsArray.filter(function(item) {
                return item != idToRemove;
            });
            
            // Update the hidden input field.
            $('#cep_gallery_ids').val(idsArray.join(','));
        }
    });

});
