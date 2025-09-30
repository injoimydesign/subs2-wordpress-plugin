/**
 * Subs Frontend Subscription JavaScript
 *
 * Handles subscription form interactions, Stripe Elements, and AJAX submissions.
 *
 * @package Subs
 * @version 1.0.0
 */

(function($) {
    'use strict';

    // Stripe instance
    let stripe = null;
    let cardElement = null;

    /**
     * Initialize Stripe
     */
    function initStripe() {
        if (typeof Stripe === 'undefined' || !subs_subscription.stripe_key) {
            console.error('Stripe.js not loaded or API key missing');
            return false;
        }

        stripe = Stripe(subs_subscription.stripe_key);
        return true;
    }

    /**
     * Initialize card element
     */
    function initCardElement() {
        if (!stripe) return;

        const elements = stripe.elements();

        const style = {
            base: {
                fontSize: '16px',
                color: '#1f2937',
                fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                '::placeholder': {
                    color: '#9ca3af'
                }
            },
            invalid: {
                color: '#dc2626',
                iconColor: '#dc2626'
            }
        };

        cardElement = elements.create('card', { style: style });

        const cardElementContainer = document.getElementById('card-element');
        if (cardElementContainer) {
            cardElement.mount('#card-element');

            // Handle real-time validation errors
            cardElement.on('change', function(event) {
                const displayError = document.getElementById('card-errors');
                if (event.error) {
                    displayError.textContent = event.error.message;
                } else {
                    displayError.textContent = '';
                }
            });
        }
    }

    /**
     * Handle subscription form submission
     */
    function handleSubscriptionForm() {
        const form = document.getElementById('subs-subscription-form-element');
        if (!form) return;

        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            // Show loading state
            const submitBtn = document.getElementById('subs-submit-btn');
            const submitText = submitBtn.querySelector('.subs-submit-text');
            const submitLoading = submitBtn.querySelector('.subs-submit-loading');

            submitBtn.disabled = true;
            submitText.style.display = 'none';
            submitLoading.style.display = 'inline-block';

            // Clear previous errors
            clearErrors();

            try {
                // Validate form
                if (!validateForm(form)) {
                    throw new Error(subs_subscription.strings.error);
                }

                // Create payment method with Stripe
                const { paymentMethod, error } = await stripe.createPaymentMethod({
                    type: 'card',
                    card: cardElement,
                    billing_details: {
                        name: form.first_name.value + ' ' + form.last_name.value,
                        email: form.email.value,
                        phone: form.phone.value,
                        address: {
                            line1: form.address_line_1.value,
                            line2: form.address_line_2.value,
                            city: form.city.value,
                            state: form.state.value,
                            postal_code: form.postal_code.value,
                            country: form.country.value
                        }
                    }
                });

                if (error) {
                    throw new Error(error.message);
                }

                // Submit form via AJAX
                const formData = new FormData(form);
                formData.append('action', 'subs_create_subscription');
                formData.append('nonce', subs_subscription.nonce);
                formData.append('payment_method_id', paymentMethod.id);

                const response = await $.ajax({
                    url: subs_subscription.ajax_url,
                    type: 'POST',
                    data: Object.fromEntries(formData),
                    dataType: 'json'
                });

                if (response.success) {
                    showSuccess(response.data.message || subs_subscription.strings.subscription_created);

                    // Redirect if URL provided
                    if (response.data.redirect_url) {
                        setTimeout(() => {
                            window.location.href = response.data.redirect_url;
                        }, 1500);
                    }
                } else {
                    throw new Error(response.data || subs_subscription.strings.error);
                }

            } catch (error) {
                showError(error.message);

                // Reset button state
                submitBtn.disabled = false;
                submitText.style.display = 'inline-block';
                submitLoading.style.display = 'none';
            }
        });
    }

    /**
     * Validate form fields
     */
    function validateForm(form) {
        let isValid = true;
        const requiredFields = form.querySelectorAll('[required]');

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('error');
                isValid = false;
            } else {
                field.classList.remove('error');
            }
        });

        // Validate email
        const emailField = form.querySelector('[type="email"]');
        if (emailField && emailField.value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(emailField.value)) {
                emailField.classList.add('error');
                isValid = false;
            }
        }

        // Validate terms acceptance
        const termsCheckbox = form.querySelector('#accept_terms');
        if (termsCheckbox && !termsCheckbox.checked) {
            showError('Please accept the terms and conditions');
            isValid = false;
        }

        return isValid;
    }

    /**
     * Handle coupon code application
     */
    function handleCouponCode() {
        $('.subs-toggle-coupon').on('click', function(e) {
            e.preventDefault();
            $('.subs-coupon-form').slideToggle(300);
        });

        $('.subs-apply-coupon').on('click', function(e) {
            e.preventDefault();

            const couponCode = $('#coupon_code').val().trim();
            const planId = $('[name="plan_id"]:checked').val() || $('[name="plan_id"]').val();

            if (!couponCode) {
                showCouponResult('Please enter a coupon code', 'error');
                return;
            }

            if (!planId) {
                showCouponResult('Please select a plan first', 'error');
                return;
            }

            // Get plan amount (you'd need to store this somewhere accessible)
            const amount = getPlanAmount(planId);

            $.ajax({
                url: subs_subscription.ajax_url,
                type: 'POST',
                data: {
                    action: 'subs_apply_coupon',
                    nonce: subs_subscription.nonce,
                    coupon_code: couponCode,
                    amount: amount
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showCouponResult(response.data.message, 'success');
                        // Update displayed price if needed
                        updatePriceDisplay(response.data.discounted_amount);
                    } else {
                        showCouponResult(response.data, 'error');
                    }
                },
                error: function() {
                    showCouponResult(subs_subscription.strings.error, 'error');
                }
            });
        });
    }

    /**
     * Show coupon result message
     */
    function showCouponResult(message, type) {
        const resultDiv = $('.subs-coupon-result');
        resultDiv.removeClass('success error').addClass(type);
        resultDiv.text(message).slideDown(200);
    }

    /**
     * Get plan amount (placeholder - implement based on your data structure)
     */
    function getPlanAmount(planId) {
        // This would need to be populated from your plan data
        const planData = {
            'basic': 9.99,
            'premium': 19.99,
            'enterprise': 49.99
        };
        return planData[planId] || 0;
    }

    /**
     * Update price display after coupon
     */
    function updatePriceDisplay(newAmount) {
        // Find and update price displays
        $('.subs-plan-price, .subs-subscription-price').each(function() {
            // Format the price appropriately
            $(this).text('$' + newAmount.toFixed(2));
        });
    }

    /**
     * Handle subscription actions (cancel, pause, resume)
     */
    function handleSubscriptionActions() {
        // Change status
        $('.subs-change-status').on('click', function(e) {
            e.preventDefault();

            const subscriptionId = $(this).data('id');
            const newStatus = $(this).data('status');
            const actionText = $(this).text().trim();

            if (!confirm(subs_subscription.strings.confirm_cancel)) {
                return;
            }

            $.ajax({
                url: subs_subscription.ajax_url,
                type: 'POST',
                data: {
                    action: 'subs_change_subscription_status',
                    nonce: subs_subscription.nonce,
                    subscription_id: subscriptionId,
                    status: newStatus
                },
                dataType: 'json',
                beforeSend: function() {
                    $(this).prop('disabled', true).text(subs_subscription.strings.processing);
                },
                success: function(response) {
                    if (response.success) {
                        showSuccess(response.data.message);
                        // Reload page to show updated status
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showError(response.data);
                    }
                },
                error: function() {
                    showError(subs_subscription.strings.error);
                }
            });
        });

        // Pause subscription
        $(document).on('click', '[data-action="pause"]', function(e) {
            e.preventDefault();
            handleSubscriptionAction($(this), 'subs_pause_subscription', subs_subscription.strings.confirm_pause);
        });

        // Cancel subscription
        $(document).on('click', '[data-action="cancel"]', function(e) {
            e.preventDefault();
            handleSubscriptionAction($(this), 'subs_cancel_subscription', subs_subscription.strings.confirm_cancel);
        });

        // Resume subscription
        $(document).on('click', '[data-action="resume"]', function(e) {
            e.preventDefault();
            handleSubscriptionAction($(this), 'subs_resume_subscription');
        });

        // Update payment method
        $(document).on('click', '[data-action="update_payment"]', function(e) {
            e.preventDefault();
            const subscriptionId = $(this).data('subscription-id');
            showPaymentMethodModal(subscriptionId);
        });
    }

    /**
     * Handle generic subscription action
     */
    function handleSubscriptionAction(button, ajaxAction, confirmMessage) {
        if (confirmMessage && !confirm(confirmMessage)) {
            return;
        }

        const subscriptionId = button.data('subscription-id');

        $.ajax({
            url: subs_subscription.ajax_url,
            type: 'POST',
            data: {
                action: ajaxAction,
                nonce: subs_subscription.nonce,
                subscription_id: subscriptionId
            },
            dataType: 'json',
            beforeSend: function() {
                button.prop('disabled', true).text(subs_subscription.strings.processing);
            },
            success: function(response) {
                if (response.success) {
                    showSuccess(response.data.message || subs_subscription.strings.subscription_updated);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showError(response.data);
                    button.prop('disabled', false).text(button.data('original-text'));
                }
            },
            error: function() {
                showError(subs_subscription.strings.error);
                button.prop('disabled', false).text(button.data('original-text'));
            }
        });
    }

    /**
     * Show payment method update modal (placeholder)
     */
    function showPaymentMethodModal(subscriptionId) {
        // This would show a modal with Stripe Elements to update payment method
        alert('Payment method update modal would open here for subscription ' + subscriptionId);
        // Implementation would involve creating a new Stripe Elements form in a modal
    }

    /**
     * Show error message
     */
    function showError(message) {
        const errorDiv = $('#subs-form-errors');
        errorDiv.html('<p>' + message + '</p>').slideDown(300);

        // Scroll to error
        $('html, body').animate({
            scrollTop: errorDiv.offset().top - 100
        }, 500);
    }

    /**
     * Show success message
     */
    function showSuccess(message) {
        const successDiv = $('<div class="subs-success">' + message + '</div>');
        $('.subs-subscription-form, .subs-customer-subscriptions').prepend(successDiv);

        // Scroll to success message
        $('html, body').animate({
            scrollTop: successDiv.offset().top - 100
        }, 500);
    }

    /**
     * Clear all error messages
     */
    function clearErrors() {
        $('#subs-form-errors').slideUp(200).html('');
        $('.error').removeClass('error');
        $('#card-errors').text('');
    }

    /**
     * Handle plan selection highlighting
     */
    function handlePlanSelection() {
        $('input[name="plan_id"]').on('change', function() {
            $('.subs-plan-card').removeClass('selected');
            $(this).closest('.subs-plan-option').find('.subs-plan-card').addClass('selected');
        });
    }

    /**
     * Handle navigation between form sections
     */
    function handleFormNavigation() {
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

                // Scroll to section
                $('html, body').animate({
                    scrollTop: $(href).offset().top - 100
                }, 500);
            }
        });
    }

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        // Initialize Stripe
        if (initStripe()) {
            initCardElement();
        }

        // Initialize form handlers
        handleSubscriptionForm();
        handleCouponCode();
        handleSubscriptionActions();
        handlePlanSelection();
        handleFormNavigation();

        // Remove loading class if present
        $('.subs-loading').removeClass('subs-loading');
    });

})(jQuery);
