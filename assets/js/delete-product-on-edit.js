document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.sk-delete-product').forEach(function(button) {
        button.addEventListener('click', function(event) {
            event.preventDefault(); // Prevent the default link behavior

            var productId = this.getAttribute('data-product-id');
            var row = document.getElementById('product-row-' + productId);

            if (row) {
                row.remove(); // Remove the row from the DOM

                // Optionally, send an AJAX request to the server to update the backend
                fetch('your-server-endpoint.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ product_id: productId })
                })
                .then(response => response.json())
                .then(data => {
                    //console.log('Product deleted:', data);
                })
                .catch(error => {
                    //console.error('Error:', error);
                });
            }
        });
    });
});

