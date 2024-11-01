jQuery(document).ready(function($) {

    $(document).on('keypress', '#coupon_code', function(evt, options) {
        options = options || {};

        if ( !options.sc_check_done ) {

            if (evt.which == 13) {
                evt.preventDefault();

                var credit_cheque = $(this).val();

                check_sc_creditcheque(credit_cheque);
            }
        }
    });

    $(document).on('click', 'button[name="apply_coupon"]', function(evt, options) {
        options = options || {};

        if ( !options.sc_check_done ) {
            evt.preventDefault();

            var credit_cheque = $('#coupon_code').val();
            check_sc_creditcheque(credit_cheque);
        }
    });

    function check_sc_creditcheque( credit_cheque ){

        console.log( 'Check SC Credit' );

        jQuery.ajax({
            type: "POST",
            url: storecontrl_object.ajaxurl,
            data: {
                'action': 'check_storecontrl_credit_cheque',
                'credit_cheque': credit_cheque
            },
            success: function (response) {
                if( !response.success ){

                    console.log('Invalid SC Credit');

                    // retrigger the default Woocommerce event
                    $('button[name="apply_coupon"]').trigger('click', {'sc_check_done': true});
                    $('.creditcheque-notice').hide();
                }
                else{
                    var cheque_url = document.location.protocol +"//"+ document.location.hostname + document.location.pathname + '?cheque=' + credit_cheque;
                    window.location.href = cheque_url;
                }
            }
        });
    }

});
