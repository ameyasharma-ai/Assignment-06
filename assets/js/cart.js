// cart.js - Handles AJAX operations for Shopping Cart & Wishlist

// Add to Cart
function addToCart(productId, quantity = 1, variationId = null) {
    const data = new FormData();
    data.append('action', 'add');
    data.append('product_id', productId);
    data.append('quantity', quantity);
    if (variationId) {
        data.append('variation_id', variationId);
    }

    fetch('cart.php', {
        method: 'POST',
        body: data
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            window.showToast(result.message || 'Product added to cart!', 'success');
            // Update cart counter in navbar
            const counter = document.getElementById('cart-counter');
            if (counter && result.cart_count !== undefined) {
                counter.innerText = result.cart_count;
                counter.style.display = 'flex';
            }
        } else {
            window.showToast(result.message || 'Failed to add product.', 'error');
        }
    })
    .catch(err => {
        console.error('Error adding to cart:', err);
        window.showToast('Network error, please try again.', 'error');
    });
}

// Toggle Wishlist Item
function toggleWishlist(productId, btnElement) {
    const data = new FormData();
    data.append('action', 'toggle');
    data.append('product_id', productId);

    fetch('profile.php?wishlist_api=1', {
        method: 'POST',
        body: data
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            window.showToast(result.message, 'success');
            if (result.added) {
                btnElement.classList.add('active');
                btnElement.style.color = '#ef4444';
            } else {
                btnElement.classList.remove('active');
                btnElement.style.color = '#fff';
            }
        } else {
            window.showToast(result.message || 'Please log in to manage your wishlist.', 'warning');
        }
    })
    .catch(err => {
        console.error('Error modifying wishlist:', err);
        window.showToast('Could not update wishlist.', 'error');
    });
}

// Update Cart Quantity
function updateCartQty(productId, newQty, variationDetails = '') {
    if (newQty < 1) return;
    
    const data = new FormData();
    data.append('action', 'update');
    data.append('product_id', productId);
    data.append('quantity', newQty);
    data.append('variation', variationDetails);

    fetch('cart.php', {
        method: 'POST',
        body: data
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            window.showToast('Cart updated.', 'success');
            location.reload(); // Reload page to update sums and tables
        } else {
            window.showToast(result.message || 'Failed to update quantity.', 'error');
        }
    })
    .catch(err => {
        console.error('Error updating cart qty:', err);
    });
}
