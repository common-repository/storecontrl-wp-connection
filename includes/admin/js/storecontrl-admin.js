jQuery(document).ready(function($) {
    $(document).on("click", "button[name='resend_new_order_to_storecontrl']", function(e) {
        var button = $(this);
        var order_id = button.attr("order_id");

        $.ajax({
            type: "POST",
            url: ajaxurl,
            data: {
                'action': 'resend_new_order_to_storecontrl',
                'order_id': order_id
            },
            success: function (response) {
                var JSON = $.parseJSON(response);
                if (typeof JSON.Status !== 'undefined' && JSON.Status == 'Success') {
                    button.replaceWith("Succesvol teruggekoppeld");
                }
                else {
                    alert('Controleer je order en verstuur deze opnieuw: ' + JSON.Message);
                }
            },
            error: function() {
                alert('Er is een fout opgetreden. Controleer de logs op de status tab van de koppeling.');
            }
        });


        e.stopPropagation();
        e.preventDefault();
    });

    $(document).on("click", '#storecontrl_synchronize_product', function(e) {
        e.preventDefault();

        $('.notice').remove();
        if (confirm('Please confirm to update this product.')) {

            $(this).find('.loading').show();

            var data = {
                'action': 'storecontrl_synchronize_product',
                'sc_product_id': $(this).attr('sc_product_id')
            };
            jQuery.post(ajaxurl, data, function (response) {
                $('#storecontrl_synchronize_product').html('Processing...');
            });
        }
    });

});
