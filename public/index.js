function add(productId) {
    var btnAddId = `#product-${productId}-add-btn`;
    var btnRemoveId = `#product-${productId}-remove-btn`;

    $.ajax({ method: "POST", url: `/ajax/add/${productId}` }).fail(function () {
        alert("Fail " + productId);
    }).done(function (msg) {
        $(btnAddId).hide();
        $(btnRemoveId).show();
        $('#cart-count').html(msg.length);
    });
}

function remove(productId) {
    var btnAddId = `#product-${productId}-add-btn`;
    var btnRemoveId = `#product-${productId}-remove-btn`;

    $.ajax({ method: "POST", url: `/ajax/remove/${productId}` }).fail(function () {
        alert("Fail " + productId);
    }).done(function (msg) {
        $(btnRemoveId).hide();
        $(btnAddId).show();
        $('#cart-count').html(msg.length);
    });
}