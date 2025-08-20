jQuery(document).ready(function($) {

    $('#incompleteOrders').DataTable();
    // Search products dynamically
    $('#product_search').on('keyup', function() {
        let searchQuery = $(this).val();
        if (searchQuery.length < 3){
            $('#product_variations').hide();
            $('.sk_select_product').hide();

            return;
        }  // Wait until at least 3 characters

        $.ajax({
            url: adminAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'search_products',
                query: searchQuery
            },
            success: function(response) {
                let resultsDiv = $('#search_results');
                let product_variations = $('#product_variations');
                resultsDiv.empty();
                product_variations.empty();

                if (response.success) {
                    response.data.forEach(product => {

                        // check if its a simple product or variable product
                        if (product.type === 'simple') {
                            product_variations.hide();
                            // Handle simple product
                            let btn = $('<button>')
                                .text(product.name)
                                .addClass('sk_select_product button')
                                .attr('data-id', product.id)
                                .attr('data-name', product.name)
                                .attr('data-price', product.price);

                            resultsDiv.append(btn);

                        } else if (product.type === 'variable') {

                            let btn = $('<button>')
                                .text(product.name)
                                .addClass('sk_select_product button')
                                .attr('data-id', product.id)
                                .attr('data-name', product.name)
                                .attr('data-price', product.price);

                            resultsDiv.append(btn);

                            $('#sk_add_product')
                                .attr('data-id', product.id)
                                .attr('data-name', product.name);

                            // Handle variable product
                            product.variations.forEach(variation => {

                                const attributesString = Object.entries(variation.attributes)
                                .map(([key, value]) => `${key}: ${value}`)
                                .join(', ');

                                product_variations.show();
                                product_variations.append(
                                    $('<option>')
                                        .val(variation.id)
                                        .text(attributesString)
                                        .attr('data-price', variation.price)
                                );
                            });
                        }
                    });
                } else {
                    resultsDiv.text('No products found.');
                }
            }
        });
    });

    // variation selection
    $('#product_variations').on('change', function () {

        $('.sk_select_product').hide(); // hide suggestion button

        // fetch all selected data
        const selectedOption = $(this).find(':selected');
        const variationId = selectedOption.val();
        const variationPrice = selectedOption.data('price');
        const variationText = selectedOption.text();

        let _product_name = $('#sk_add_product').data('name'); // main name
        _product_name = _product_name + ':' + variationText;

        $('#sk_add_product')
        .attr('data-variation_id', variationId) // Update data-id
        .attr('data-price', variationPrice) // Update data-price
        .attr('data-name', _product_name); // Update data-name (optional, if you want to include variation details)
    });

    // Handle product selection and fetch variations
    $(document).on('click', '.sk_select_product', function(e) {
        e.preventDefault();

        let productId = $(this).data('id');
        let productName = $(this).data('name');
        let productPrice = $(this).data('price');

       // $('#product_variations').hide().empty();

        $('#sk_add_product')
            .attr('data-id', productId)
            .attr('data-name', productName)
            .attr('data-price', productPrice);

        $('.sk_select_product').hide();
    });

    // Add product to table
    $('#sk_add_product').on('click', function(event) {

        event.preventDefault();
        $('#product_variations').hide();
        $('#product_search').val();

        let selectedProduct = {
            id: $(this).data('id'),
            variation: $(this).data('variation_id'),
            name: $(this).data('name'),
            price: $(this).data('price'),
            quantity: 1
        };

        // let variationSelected = $('#product_variations').val();
        // if (variationSelected) {
        //     selectedProduct.id = variationSelected;
        //     selectedProduct.price = $('#product_variations option:selected').data('price');
        //     selectedProduct.name += ' (' + $('#product_variations option:selected').text() + ')';
        // }

        // Check if any property is undefined
        if (selectedProduct?.id && selectedProduct?.name && selectedProduct?.price) {
            
            let newRow = `<tr>
                <td>
                    ${selectedProduct.id}
                    <input type="hidden" name="productss[][product_id]" value="${selectedProduct.id}">
                </td>
                <td>
                    ${selectedProduct.name}
                    <input type="hidden" name="productss[][product_name]" value="${selectedProduct.name}">
                </td>
                <td>
                    ${selectedProduct.price}
                    <input type="hidden" name="productss[][price]" value="${selectedProduct.price}">
                </td>
                <td>
                    <input type="number" name="productss[][quantity]" value="1">
                </td>
                <td><a href="#" class="sk-delete-product" data-product-id="${selectedProduct.id}">Delete</a></td>
            </tr>`;

            $('table.widefat tbody').append(newRow);

        }
    });

    // Remove product from table
    $(document).on('click', '.remove-product', function() {
        $(this).closest('tr').remove();
    });

    // Handle the "Proceed to Checkout" button click
    $('#sk_redirect_to_checkout').on('click', function() {
        // Gather cart data
        var cartData = {
            id: $('input[name="cart_id"]').val(),
            first_name: $('input[name="first_name"]').val(),
            last_name: $('input[name="last_name"]').val(),
            email: $('input[name="email"]').val(),
            phone: $('input[name="phone"]').val(),
            address: $('textarea[name="address"]').val(),
            additional_text: $('textarea[name="additional_text"]').val(),
            checkout_method: $('input[name="checkout_method"]').val(),
            products: []
        };

        // Gather product data
        $('table.widefat tbody tr').each(function() {
            var product = {
                id: $(this).find('td:eq(0)').text(),
                name: $(this).find('td:eq(1)').text(),
                price: $(this).find('td:eq(2)').text().replace(/[^0-9.-]+/g, "").trim(),
                quantity: $(this).find('td:eq(3)').find('input').val().trim()
            };
            cartData.products.push(product);
        });

        //Send AJAX request to store cart data in session
        $.ajax({
            url: ajaxurl, // WordPress AJAX URL
            type: 'POST',
            data: {
                action: 'act_redirect_to_checkout',
                cart_data: cartData
            },
            success: function(response) {
                if (response.success) {
                    if (response.success) {
                        window.location.href = response.data.redirect;
                    }
                } else {
                    alert('Failed to proceed to checkout.');
                }
            }
        });
    });

});
