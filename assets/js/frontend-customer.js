/**
 * Subs Frontend Customer Portal JavaScript
 *
 * Handles customer portal interactions, profile updates, and account management.
 *
 * @package Subs
 * @version 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Handle profile form submission
     */
/**
 * Subs Frontend Customer Portal JavaScript
 *
 * Handles customer portal interactions, profile updates, and account management.
 *
 * @package Subs
 * @version 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Handle profile form submission
     */
    function handleProfileForm() {
        $('#subs-profile-form').on('submit', function(e) {
            e.preventDefault();

            const form = $(this);
            const submitBtn = form.find('button[type="submit"]');
            const resultDiv = form.find('.subs-form-result');
            const originalText = submitBtn.text();

            $.ajax({
                url: subs_customer.ajax_url,
                type: 'POST',
                data: form.serialize() + '&action=subs_update_customer_profile&nonce=' + subs_customer.nonce,
                dataType: 'json',
                beforeSend: function() {
                    submitBtn.prop('disabled', true).text(subs_customer.strings.processing);
                    resultDiv.hide().removeClass('success error');
                },
                success: function(response) {
                    if (response.success) {
                        showFormResult(resultDiv, response.data, 'success');
                    } else {
                        showFormResult(resultDiv, response.data, 'error');
                    }
                },
                error: function() {
                    showFormResult(resultDiv, subs_customer.strings.error, 'error');
                },
                complete: function() {
                    submitBtn.prop('disabled', false).text(originalText);
                }
            });
        });
    }

    /**
     * Handle address form submission
     */
    function handleAddressForm() {
        $('#subs-address-form').on('submit', function(e) {
            e.preventDefault();

            const form = $(this);
            const submitBtn = form.find('button[type="submit"]');
            const resultDiv = form.find('.subs-form-result');
            const originalText = submitBtn.text();

            $.ajax({
                url: subs_customer.ajax_url,
                type: 'POST',
                data: form.serialize() + '&action=subs_update_customer_address&nonce=' + subs_customer.nonce,
                dataType: 'json',
                beforeSend: function() {
                    submitBtn.prop('disabled', true).text(subs_customer.strings.processing);
                    resultDiv.hide().removeClass('success error');
                },
                success: function(response) {
                    if (response.success) {
                        showFormResult(resultDiv, response.data, 'success');
                    } else {
                        showFormResult(resultDiv, response.data, 'error');
                    }
                },
                error: function() {
                    showFormResult(resultDiv, subs_customer.strings.error, 'error');
                },
                complete: function() {
                    submitBtn.prop('disabled', false).text(originalText);
                }
            });
        });
    }

    /**
     * Handle password form submission
     */
    function handlePasswordForm() {
        $('#subs-password-form').on('submit', function(e) {
            e.preventDefault();

            const form = $(this);
            const submitBtn = form.find('button[type="submit"]');
            const resultDiv = form.find('.subs-form-result');
            const originalText = submitBtn.text();

            // Validate passwords match
            const newPassword = form.find('#new_password').val();
            const confirmPassword = form.find('#confirm_password').val();

            if (newPassword !== confirmPassword) {
                showFormResult(resultDiv, 'Passwords do not match', 'error');
                return;
            }

            if (newPassword.length < 8) {
                showFormResult(resultDiv, 'Password must be at least 8 characters', 'error');
                return;
            }

            $.ajax({
                url: subs_customer.ajax_url,
                type: 'POST',
                data: form.serialize() + '&action=subs_update_customer_password&nonce=' + subs_customer.nonce,
                dataType: 'json',
                beforeSend: function() {
                    submitBtn.prop('disabled', true).text(subs_customer.strings.processing);
                    resultDiv.hide().removeClass('success error');
                },
                success: function(response) {
                    if (response.success) {
                        showFormResult(resultDiv, response.data, 'success');
                        form[0].reset(); // Clear password fields
                    } else {
                        showFormResult(resultDiv, response.data, 'error');
                    }
                },
                error: function() {
                    showFormResult(resultDiv, subs_customer.strings.error, 'error');
                },
                complete: function() {
                    submitBtn.prop('disabled', false).text(originalText);
                }
            });
        });
    }

    /**
     * Show form result message
     */
    function showFormResult(resultDiv, message, type) {
        resultDiv.removeClass('success error')
                 .addClass(type)
                 .text(message)
                 .slideDown(300);

        // Scroll to result
        $('html, body').animate({
            scrollTop: resultDiv.offset().top - 100
        }, 500);

        // Auto-hide success messages after 5 seconds
        if (type === 'success') {
            setTimeout(function() {
                resultDiv.slideUp(300);
            }, 5000);
        }
    }

    /**
     * Handle portal navigation
     */
    function handlePortalNavigation() {
        $('.subs-nav-link').on('click', function(e) {
            const href = $(this).attr('href');

            // Only handle anchor links
            if (href && href.startsWith('#')) {
                e.preventDefault();

                // Update active nav
                $('.subs-nav-link').removeClass('active');
                $(this).addClass('active');

                // Show corresponding section
                $('.subs-portal-section').hide();
                $(href).fadeIn(300);

                // Update URL hash without jumping
                history.pushState(null, null, href);

                // Scroll to section
                $('html, body').animate({
                    scrollTop: $(href).offset().top - 100
                }, 500);
            }
        });

        // Handle initial hash on page load
        if (window.location.hash) {
            const hash = window.location.hash;
            $('.subs-nav-link[href="' + hash + '"]').trigger('click');
        } else {
            // Show first section by default
            $('.subs-portal-section').first().show();
        }
    }

    /**
     * Handle payment method management
     */
    function handlePaymentMethods() {
        // Show add payment form
        $('#subs-add-payment-btn').on('click', function() {
            $('#subs-payment-form').slideDown(300);
            $(this).hide();
        });

        // Cancel add payment
        $('#subs-cancel-payment-btn').on('click', function() {
            $('#subs-payment-form').slideUp(300);
            $('#subs-add-payment-btn').show();
        });

        // Delete payment method
        $('.subs-delete-payment-method').on('click', function(e) {
            e.preventDefault();

            if (!confirm('Are you sure you want to delete this payment method?')) {
                return;
            }

            const paymentMethodId = $(this).data('payment-method-id');
            const card = $(this).closest('.subs-payment-method-card');

            $.ajax({
                url: subs_customer.ajax_url,
                type: 'POST',
                data: {
                    action: 'subs_delete_payment_method',
                    nonce: subs_customer.nonce,
                    payment_method_id: paymentMethodId
                },
                dataType: 'json',
                beforeSend: function() {
                    card.css('opacity', '0.5');
                },
                success: function(response) {
                    if (response.success) {
                        card.slideUp(300, function() {
                            $(this).remove();

                            // Show empty state if no payment methods left
                            if ($('.subs-payment-method-card').length === 0) {
                                $('.subs-payment-methods-list').html(
                                    '<p class="subs-no-payment-methods">No payment methods on file.</p>'
                                );
                            }
                        });
                    } else {
                        alert(response.data);
                        card.css('opacity', '1');
                    }
                },
                error: function() {
                    alert(subs_customer.strings.error);
                    card.css('opacity', '1');
                }
            });
        });
    }

    /**
     * Handle account cancellation
     */
    function handleAccountCancellation() {
        $('#cancel-account-btn').on('click', function(e) {
            e.preventDefault();

            if (!confirm(subs_customer.strings.confirm_cancel)) {
                return;
            }

            // Double confirmation for account cancellation
            if (!confirm('This action cannot be undone. Are you absolutely sure?')) {
                return;
            }

            $.ajax({
                url: subs_customer.ajax_url,
                type: 'POST',
                data: {
                    action: 'subs_cancel_account',
                    nonce: subs_customer.nonce
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        if (response.data.redirect) {
                            window.location.href = response.data.redirect;
                        }
                    } else {
                        alert(response.data);
                    }
                },
                error: function() {
                    alert(subs_customer.strings.error);
                }
            });
        });
    }

    /**
     * Handle send email to customer
     */
    function handleSendEmail() {
        $('.subs-send-email').on('click', function(e) {
            e.preventDefault();

            const customerId = $(this).data('customer-id');
            const customerEmail = $(this).data('email');

            // This would show a modal for composing email
            showEmailModal(customerId, customerEmail);
        });
    }

    /**
     * Show email composition modal (placeholder)
     */
    function showEmailModal(customerId, customerEmail) {
        // This would show a modal with email composition form
        alert('Email modal would open here for customer ' + customerId + ' (' + customerEmail + ')');
        // Implementation would involve creating a modal with form fields
    }

    /**
     * Handle invoice viewing
     */
    function handleInvoiceView() {
        $('.subs-view-invoice').on('click', function(e) {
            e.preventDefault();

            const invoiceId = $(this).data('invoice');

            // This would open invoice in new window or modal
            window.open('/invoice/' + invoiceId, '_blank');
        });
    }

    /**
     * Form field validation
     */
    function setupFormValidation() {
        // Email validation
        $('input[type="email"]').on('blur', function() {
            const email = $(this).val();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            if (email && !emailRegex.test(email)) {
                $(this).addClass('error');
                showFieldError($(this), 'Please enter a valid email address');
            } else {
                $(this).removeClass('error');
                hideFieldError($(this));
            }
        });

        // Required field validation
        $('[required]').on('blur', function() {
            if (!$(this).val().trim()) {
                $(this).addClass('error');
                showFieldError($(this), 'This field is required');
            } else {
                $(this).removeClass('error');
                hideFieldError($(this));
            }
        });

        // Password strength indicator
        $('#new_password').on('input', function() {
            const password = $(this).val();
            const strength = calculatePasswordStrength(password);
            showPasswordStrength($(this), strength);
        });
    }

    /**
     * Show field error
     */
    function showFieldError(field, message) {
        let errorDiv = field.next('.subs-field-error');
        if (errorDiv.length === 0) {
            errorDiv = $('<div class="subs-field-error"></div>');
            field.after(errorDiv);
        }
        errorDiv.text(message).show();
    }

    /**
     * Hide field error
     */
    function hideFieldError(field) {
        field.next('.subs-field-error').hide();
    }

    /**
     * Calculate password strength
     */
    function calculatePasswordStrength(password) {
        let strength = 0;

        if (password.length >= 8) strength++;
        if (password.length >= 12) strength++;
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^a-zA-Z0-9]/.test(password)) strength++;

        return strength;
    }

    /**
     * Show password strength indicator
     */
    function showPasswordStrength(field, strength) {
        let indicator = field.next('.subs-password-strength');

        if (indicator.length === 0) {
            indicator = $('<div class="subs-password-strength"></div>');
            field.after(indicator);
        }

        const labels = ['Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
        const colors = ['#dc2626', '#f59e0b', '#fbbf24', '#10b981', '#059669'];

        if (strength === 0) {
            indicator.hide();
            return;
        }

        indicator.text(labels[strength - 1])
                 .css('color', colors[strength - 1])
                 .show();
    }

    /**
     * Handle responsive table on mobile
     */
    function handleResponsiveTables() {
        if ($(window).width() <= 768) {
            $('.subs-billing-table td').each(function() {
                const label = $(this).closest('table').find('th').eq($(this).index()).text();
                $(this).attr('data-label', label);
            });
        }
    }

    /**
     * Smooth scroll for anchor links
     */
    function setupSmoothScroll() {
        $('a[href^="#"]').on('click', function(e) {
            const target = $(this.getAttribute('href'));

            if (target.length) {
                e.preventDefault();
                $('html, body').stop().animate({
                    scrollTop: target.offset().top - 100
                }, 500);
            }
        });
    }

    /**
     * Auto-save form data to localStorage (optional)
     */
    function setupAutoSave() {
        const forms = $('.subs-customer-form');

        forms.each(function() {
            const formId = $(this).attr('id');
            if (!formId) return;

            // Load saved data
            const savedData = localStorage.getItem('subs_form_' + formId);
            if (savedData) {
                try {
                    const data = JSON.parse(savedData);
                    Object.keys(data).forEach(function(key) {
                        $(this).find('[name="' + key + '"]').val(data[key]);
                    }.bind(this));
                } catch (e) {
                    console.error('Error loading saved form data:', e);
                }
            }

            // Save on change (debounced)
            let saveTimeout;
            $(this).on('change input', function() {
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(function() {
                    const formData = {};
                    $(this).serializeArray().forEach(function(field) {
                        // Don't save password fields
                        if (field.name.indexOf('password') === -1) {
                            formData[field.name] = field.value;
                        }
                    });
                    localStorage.setItem('subs_form_' + formId, JSON.stringify(formData));
                }.bind(this), 1000);
            });

            // Clear saved data on successful submit
            $(this).on('submit', function() {
                localStorage.removeItem('subs_form_' + formId);
            });
        });
    }

    /**
     * Initialize tooltips (if needed)
     */
    function initTooltips() {
        $('[data-tooltip]').each(function() {
            $(this).attr('title', $(this).data('tooltip'));
        });
    }

    /**
     * Handle print functionality
     */
    function handlePrint() {
        $('.subs-print-invoice').on('click', function(e) {
            e.preventDefault();
            window.print();
        });
    }

    /**
     * Detect unsaved changes
     */
    function setupUnsavedChangesWarning() {
        let formChanged = false;

        $('.subs-customer-form').on('change input', function() {
            formChanged = true;
        });

        $('.subs-customer-form').on('submit', function() {
            formChanged = false;
        });

        $(window).on('beforeunload', function() {
            if (formChanged) {
                return 'You have unsaved changes. Are you sure you want to leave?';
            }
        });
    }

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        // Initialize form handlers
        handleProfileForm();
        handleAddressForm();
        handlePasswordForm();

        // Initialize portal features
        handlePortalNavigation();
        handlePaymentMethods();
        handleAccountCancellation();
        handleSendEmail();
        handleInvoiceView();

        // Initialize utilities
        setupFormValidation();
        handleResponsiveTables();
        setupSmoothScroll();
        initTooltips();
        handlePrint();
        setupUnsavedChangesWarning();

        // Optional: Auto-save (uncomment if desired)
        // setupAutoSave();

        // Handle window resize for responsive features
        $(window).on('resize', function() {
            handleResponsiveTables();
        });

        // Remove loading class if present
        $('.subs-loading').removeClass('subs-loading');
    });

})(jQuery);
