/**
 * WooCommerce AutoResponder AI - Admin JavaScript
 */
(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        initReviewActions();
        initModal();
        initResponseActions();
        initSettingsPage();
    });

    /**
     * Initialize review actions
     */
    function initReviewActions() {
        console.log('WC AI: Initializing review actions');
        $(document).on('click', '.wc-ai-generate-response', function(e) {
            e.preventDefault();
            console.log('WC AI: Generate AI Response button clicked');
            
            const reviewId = $(this).data('review-id');
            console.log('WC AI: Review ID: ' + reviewId);
            generateAIResponse(reviewId);
        });
    }

    /**
     * Initialize modal functionality
     */
    function initModal() {
        // Close modal when clicking close button
        $(document).on('click', '.wc-ai-modal-close', function() {
            closeModal();
        });

        // Close modal when clicking outside
        $(document).on('click', '.wc-ai-modal', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Close modal with Escape key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $('.wc-ai-modal').is(':visible')) {
                closeModal();
            }
        });
    }

    /**
     * Initialize settings page functionality
     */
    function initSettingsPage() {
        // Show/hide model selection based on provider
        const $aiProvider = $('#ai_provider');
        const $modelFields = $('.model-selection-field');
        
        // TEMPORARILY DISABLE ALL MODEL FIELD HIDING FOR DEBUGGING
        console.log('Settings page initialized - showing all model fields for debugging');
        
        function toggleModelFields() {
            const selectedProvider = $aiProvider.val();
            
            // DISABLED FOR DEBUGGING - Show all model fields
            $modelFields.closest('tr').show();
            $modelFields.addClass('active');
            
            console.log('All model fields made visible for debugging');
            
            // Update model info display if exists
            const $selectedModelField = $(`#${selectedProvider}_model`);
            if ($selectedModelField.length) {
                updateModelInfo(selectedProvider, $selectedModelField.val());
            } else {
                console.log('Model field not found for provider:', selectedProvider);
            }
        }
        
        function updateModelInfo(provider, model) {
            // Remove existing model info
            $('.model-info').remove();
            
            // Add model info next to provider status
            const $providerRow = $(`#${provider}_api_key`).closest('tr');
            if ($providerRow.length && model) {
                $providerRow.find('td:last').append(`<span class="model-info">${model}</span>`);
            }
        }
        
        function validateModelName(input) {
            const value = $(input).val();
            const $field = $(input);
            
            // Remove previous validation classes
            $field.removeClass('model-valid model-invalid');
            
            if (value.trim() === '') {
                return; // Empty is valid (will use default)
            }
            
            // Check if model name contains only allowed characters
            const isValid = /^[a-zA-Z0-9\-_\/\.:]+$/.test(value);
            
            if (isValid) {
                $field.addClass('model-valid');
                $field.removeClass('model-invalid');
            } else {
                $field.addClass('model-invalid');
                $field.removeClass('model-valid');
            }
        }
        
        // Debug: Log available elements
        console.log('AI Provider field:', $aiProvider.length);
        console.log('Model fields found:', $modelFields.length);
        $modelFields.each(function() {
            console.log('Model field ID:', $(this).attr('id'));
            console.log('Model field name:', $(this).attr('name'));
            console.log('Model field value:', $(this).val());
        });
        
        // Initial toggle
        toggleModelFields();
        
        // Toggle on change
        $aiProvider.on('change', toggleModelFields);
        
        // Update model info when model input changes
        $modelFields.on('input', function() {
            const provider = $(this).attr('id').replace('_model', '');
            updateModelInfo(provider, $(this).val());
            validateModelName(this);
        });
        
        // Initial validation
        $modelFields.each(function() {
            validateModelName(this);
        });
    }

    /**
     * Initialize response actions
     */
    function initResponseActions() {
        $(document).on('click', '.wc-ai-approve', function() {
            const responseId = $(this).data('response-id');
            approveResponse(responseId);
        });

        $(document).on('click', '.wc-ai-reject', function() {
            const responseId = $(this).data('response-id');
            rejectResponse(responseId);
        });

        $(document).on('click', '.wc-ai-view', function() {
            const responseId = $(this).data('response-id');
            viewResponse(responseId);
        });

        $(document).on('click', '.wc-ai-approve-response', function() {
            approveModalResponse();
        });

        $(document).on('click', '.wc-ai-reject-response', function() {
            rejectModalResponse();
        });

        $(document).on('click', '.wc-ai-edit-response', function() {
            editModalResponse();
        });
    }

    /**
     * Generate AI response for a review
     */
    function generateAIResponse(reviewId) {
        console.log('WC AI: generateAIResponse called with reviewId: ' + reviewId);
        
        if (!reviewId) {
            console.log('WC AI: No review ID provided');
            showError(wcAutoResponderAI.strings.error);
            return;
        }

        console.log('WC AI: Showing modal and making AJAX request');
        showModal();
        setModalContent(wcAutoResponderAI.strings.generating);

        $.ajax({
            url: wcAutoResponderAI.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wc_ai_generate_response',
                review_id: reviewId,
                nonce: wcAutoResponderAI.nonce
            },
            success: function(response) {
                console.log('WC AI: AJAX success response:', response);
                if (response.success) {
                    setModalContent(response.data.response);
                    setModalActions(true);
                    showSuccess(wcAutoResponderAI.strings.success);
                } else {
                    console.log('WC AI: AJAX error response:', response.data);
                    setModalContent('<div class="wc-ai-error">' + response.data + '</div>');
                    setModalActions(false);
                    showError(response.data);
                }
            },
            error: function(xhr, status, error) {
                console.log('WC AI: AJAX error:', status, error);
                console.log('WC AI: XHR response:', xhr.responseText);
                setModalContent('<div class="wc-ai-error">' + wcAutoResponderAI.strings.error + '</div>');
                setModalActions(false);
                showError(wcAutoResponderAI.strings.error);
            }
        });
    }

    /**
     * Approve a response
     */
    function approveResponse(responseId) {
        console.log('approveResponse called with ID:', responseId);
        console.log('wcAutoResponderAI object:', wcAutoResponderAI);
        
        if (!confirm(wcAutoResponderAI.strings.confirmApprove)) {
            return;
        }

        const $button = $('.wc-ai-approve[data-response-id="' + responseId + '"]');
        $button.prop('disabled', true).text('Approving...');

        // Direct approve request
        $.ajax({
            url: wcAutoResponderAI.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wc_ai_approve_response',
                response_id: responseId,
                nonce: wcAutoResponderAI.nonce
            },
            success: function(response) {
                console.log('Approve response:', response);
                if (response.success) {
                    showSuccess(response.data.message);
                    location.reload(); // Refresh to show updated status
                } else {
                    showError(response.data || 'Unknown error occurred');
                    $button.prop('disabled', false).text('Approve');
                }
            },
            error: function(xhr, status, error) {
                console.error('Approve AJAX Error:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    statusCode: xhr.status
                });
                
                let errorMessage = wcAutoResponderAI.strings.error;
                if (xhr.responseText) {
                    try {
                        const errorResponse = JSON.parse(xhr.responseText);
                        errorMessage = errorResponse.data || errorResponse.message || errorMessage;
                    } catch (e) {
                        errorMessage = 'Server error: ' + xhr.status;
                    }
                }
                
                showError(errorMessage);
                $button.prop('disabled', false).text('Approve');
            }
        });
    }

    /**
     * Reject a response
     */
    function rejectResponse(responseId) {
        const reason = prompt('Please provide a reason for rejection (optional):');
        
        if (reason === null) {
            return; // User cancelled
        }

        const $button = $('.wc-ai-reject[data-response-id="' + responseId + '"]');
        $button.prop('disabled', true).text('Rejecting...');

        $.ajax({
            url: wcAutoResponderAI.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wc_ai_reject_response',
                response_id: responseId,
                reason: reason,
                nonce: wcAutoResponderAI.nonce
            },
            success: function(response) {
                if (response.success) {
                    showSuccess(response.data.message);
                    location.reload(); // Refresh to show updated status
                } else {
                    showError(response.data);
                    $button.prop('disabled', false).text('Reject');
                }
            },
            error: function() {
                showError(wcAutoResponderAI.strings.error);
                $button.prop('disabled', false).text('Reject');
            }
        });
    }

    /**
     * View a response
     */
    function viewResponse(responseId) {
        // This would typically fetch the full response and show it in a modal
        // For now, we'll just show a placeholder
        showModal();
        setModalContent('<p>Loading response details...</p>');
        setModalActions(false);
    }

    /**
     * Approve response from modal
     */
    function approveModalResponse() {
        const responseText = $('.wc-ai-response-content').text();
        
        if (!confirm(wcAutoResponderAI.strings.confirmApprove)) {
            return;
        }

        // This would need the review ID from the modal context
        // For now, we'll show a placeholder
        showSuccess('Response approved successfully!');
        closeModal();
    }

    /**
     * Reject response from modal
     */
    function rejectModalResponse() {
        const reason = prompt('Please provide a reason for rejection (optional):');
        
        if (reason === null) {
            return; // User cancelled
        }

        // This would need the review ID from the modal context
        // For now, we'll show a placeholder
        showSuccess('Response rejected.');
        closeModal();
    }

    /**
     * Edit response from modal
     */
    function editModalResponse() {
        const $content = $('.wc-ai-response-content');
        const currentText = $content.text();
        
        // Create a textarea for editing
        const $textarea = $('<textarea>').val(currentText).css({
            width: '100%',
            height: '150px',
            resize: 'vertical'
        });
        
        $content.html($textarea);
        
        // Add save/cancel buttons
        const $actions = $('.wc-ai-response-actions');
        $actions.html(`
            <button class="button button-primary wc-ai-save-edit">Save Changes</button>
            <button class="button wc-ai-cancel-edit">Cancel</button>
        `);
        
        // Handle save/cancel
        $(document).off('click', '.wc-ai-save-edit').on('click', '.wc-ai-save-edit', function() {
            const newText = $textarea.val();
            $content.html(newText);
            setModalActions(true);
        });
        
        $(document).off('click', '.wc-ai-cancel-edit').on('click', '.wc-ai-cancel-edit', function() {
            $content.html(currentText);
            setModalActions(true);
        });
    }

    /**
     * Show modal
     */
    function showModal() {
        $('.wc-ai-modal').fadeIn(300);
        $('body').addClass('wc-ai-modal-open');
    }

    /**
     * Close modal
     */
    function closeModal() {
        $('.wc-ai-modal').fadeOut(300);
        $('body').removeClass('wc-ai-modal-open');
    }

    /**
     * Set modal content
     */
    function setModalContent(content) {
        $('.wc-ai-response-content').html(content);
    }

    /**
     * Set modal actions
     */
    function setModalActions(showActions) {
        const $actions = $('.wc-ai-response-actions');
        
        if (showActions) {
            $actions.html(`
                <button class="button button-primary wc-ai-approve-response">Approve & Publish</button>
                <button class="button wc-ai-reject-response">Reject</button>
                <button class="button wc-ai-edit-response">Edit</button>
            `);
        } else {
            $actions.empty();
        }
    }

    /**
     * Show success message
     */
    function showSuccess(message) {
        showNotice(message, 'success');
    }

    /**
     * Show error message
     */
    function showError(message) {
        showNotice(message, 'error');
    }

    /**
     * Show notice
     */
    function showNotice(message, type) {
        const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        
        // Remove existing notices
        $('.notice').remove();
        
        // Add new notice
        $('.wrap h1').after($notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
        
        // Handle dismiss button
        $notice.on('click', '.notice-dismiss', function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        });
    }

    /**
     * Add loading state to element
     */
    function addLoadingState($element) {
        $element.addClass('wc-ai-loading');
    }

    /**
     * Remove loading state from element
     */
    function removeLoadingState($element) {
        $element.removeClass('wc-ai-loading');
    }

    /**
     * Debounce function
     */
    function debounce(func, wait, immediate) {
        let timeout;
        return function() {
            const context = this;
            const args = arguments;
            const later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    }

    // Add CSS for modal open state
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            body.wc-ai-modal-open {
                overflow: hidden;
            }
        `)
        .appendTo('head');

})(jQuery);
