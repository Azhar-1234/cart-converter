jQuery(document).ready(function($) {
    // Function to save form data
    function saveFormData() {
        if ($('body').hasClass('woocommerce-checkout')) {
            var formData = {
                session_id: adminAjax.session_id,
                billing_first_name: $('input[name="billing_first_name"]').val(),
                billing_last_name: $('input[name="billing_last_name"]').val(),
                billing_email: $('input[name="billing_email"]').val(),
                billing_phone: $('input[name="billing_phone"]').val(),
                billing_address_1: $('input[name="billing_address_1"]').val(),
                billing_address_2: $('input[name="billing_address_2"]').val(),
                billing_city: $('input[name="billing_city"]').val(),
                billing_state: $('input[name="billing_state"]').val(),
                billing_postcode: $('input[name="billing_postcode"]').val(),
                billing_country: $('select[name="billing_country"]').val(),
                order_comments: $('textarea[name="order_comments"]').val(),
                payment_method: $('input[name="payment_method"]:checked').val()
            };

            $.ajax({
                url: adminAjax.ajax_url, // Corrected reference
                type: 'POST',
                data: {
                    action: 'act_save_abandoned_cart',
                    form_data: formData,
                },
                success: function(response) {
                    //console.log('Abandoned Cart Saved:', response.data);
                },
                error: function(error) {
                   // console.error('AJAX Error:', error);
                }
            });
        }
    }

    // Listen for changes in input fields
    $('input, textarea, select').on('input change', function() {
        saveFormData();
    });

    // Also save data when the user leaves the page
    $(window).on('beforeunload', function() {
        saveFormData();
    });
});