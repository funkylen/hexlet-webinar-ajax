function handleProduct(productElement, type) {
    const id = productElement.data('id');
    productElement.hide();
    $(`button[data-type="${type === 'add' ? 'remove' : 'add'}"][data-id=${id}]`).show();

    $.ajax({ url: `/ajax/${type}/${id}`, method: 'POST' })
        .done(function (response) {
            const cartCount = Object.values(response).reduce(function (acc, value) {
                return acc + value;
            }, 0);

            $('#cart-counter').text(cartCount);
        });
}

$('button[data-type="add"]').on('click', function () {
    handleProduct($(this), 'add');
});

$('button[data-type="remove"]').on('click', function () {
    handleProduct($(this), 'remove');
});

$('#product-search').autocomplete({
    source: '/ajax/products/autocomplete',
    minLength: 2,
});