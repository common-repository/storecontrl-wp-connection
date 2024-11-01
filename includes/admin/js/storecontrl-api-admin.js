jQuery(document).ready(function($) {

    // Check API settings
    if( $( "#storecontrl_auth_key" ).length !== 0 ){
        if( $( "#storecontrl_auth_key" ).val().length == 0 || $( "#storecontrl_auth_key" ).val().length == 0 ){
            $( "#test-storecontrl-api-connection" ).attr('disabled', true);
        }
    }

    $('#cronjobTimepicker').timepicker({
        'timeFormat': 'H:i'
    });

    $(document).on("click", "#storecontrl_settings div.btn.toggle[data-toggle='toggle']", function() {
        $("#storecontrl_settings #storecontrl_settings_notice_changes_box").addClass("show");
    });

    $(document).on("change, input", "#storecontrl_settings input[name], #storecontrl_settings select[name]", function() {
        $("#storecontrl_settings #storecontrl_settings_notice_changes_box").addClass("show");
    });

    $('.list-group.checked-list-box .list-group-item').each(function () {

        // Settings
        var $widget = $(this),
            $checkbox = $('<input type="checkbox" class="hidden" />'),
            color = ($widget.data('color') ? $widget.data('color') : "success"),
            style = ($widget.data('style') == "button" ? "btn-" : "list-group-item-"),
            settings = {
                on: {
                    icon: 'glyphicon glyphicon-check'
                },
                off: {
                    icon: 'glyphicon glyphicon-unchecked'
                }
            };

        $widget.css('cursor', 'pointer')
        $widget.append($checkbox);

        // Event Handlers
        $widget.on('click', function () {
            $checkbox.prop('checked', !$checkbox.is(':checked'));
            $checkbox.triggerHandler('change');
            updateDisplay();
        });
        $checkbox.on('change', function () {
            updateDisplay();
        });


        // Actions
        function updateDisplay() {
            var isChecked = $checkbox.is(':checked');

            // Set the button's state
            $widget.data('state', (isChecked) ? "on" : "off");

            // Set the button's icon
            $widget.find('.state-icon')
                .removeClass()
                .addClass('state-icon ' + settings[$widget.data('state')].icon);

            // Update the button's color
            if (isChecked) {
                $widget.addClass(style + color + ' active');
            } else {
                $widget.removeClass(style + color + ' active');
            }
        }

        // Initialization
        function init() {

            if ($widget.data('checked') == true) {
                $checkbox.prop('checked', !$checkbox.is(':checked'));
            }

            updateDisplay();

            // Inject the icon if applicable
            if ($widget.find('.state-icon').length == 0) {
                $widget.prepend('<span class="state-icon ' + settings[$widget.data('state')].icon + '"></span>');
            }
        }
        init();
    });

    // AJAX - Test API settings/connection
    $("#test-storecontrl-api-connection").click( function(e){
        e.preventDefault();

        // Get the key
        var key = jQuery("#storecontrl_api_arture_key").val();

        jQuery.ajax({
            type: "POST",
            url: ajaxurl,
            data: {
                'action': 'check_storecontrl_api_connection',
                'key': key
            },
            success: function (response) {
                $('.tab-content h2').append(response.data.html);
            }
        });
    });

    // AJAX - Trigger total synchronization
    $('#storecontrl-refresh-masterdata').click(function() {
        $('.notice').remove();

        $(this).find('.loading').show();

        var data = {
            'action': 'storecontrl_refresh_masterdata'
        };
        jQuery.post(ajaxurl, data, function (response) {

            $('#storecontrl-refresh-masterdata').find('.loading').hide();

            $('.tab-content h2').append(response.data.html);
            $("html, body").animate({scrollTop: 0}, "slow");

            $('#storecontrl-refresh-masterdata').hide();
        });
    });

    // AJAX - Trigger total synchronization
    $('#storecontrl-total-synchronization').click(function() {
        $('.notice').remove();

        $(this).find('.loading').show();

        var data = {
            'action': 'storecontrl_total_synchronization'
        };
        jQuery.post(ajaxurl, data, function (response) {

            $('#storecontrl-total-synchronization').find('.loading').hide();

            $('.tab-content h2').append(response.data.html);
            $("html, body").animate({scrollTop: 0}, "slow");

            $('#storecontrl-total-synchronization').hide();
            $('#storecontrl-process-test-batch').show();
        });
    });

    // AJAX - Trigger process test batch
    $('#storecontrl-process-test-batch').click(function() {
        $('.notice').remove();

        $(this).find('.loading').show();

        var data = {
            'action': 'storecontrl_process_test_batch'
        };
        jQuery.post(ajaxurl, data, function(response) {

            $('#storecontrl-process-test-batch').find('.loading').hide();

            $('.tab-content h2').append(response.data.html);
            $("html, body").animate({ scrollTop: 0 }, "slow");
        });
    });

    // AJAX - Change selected log file
    $('#log_file_select').change(function(e) {
        e.preventDefault();

        var filename = $(this).val();

        var data = {
            'action': 'get_log_file',
            'filename': filename
        };

        jQuery.post(ajaxurl, data, function(response){
            $('#log_field').html(response.html);
            $('#btnDownloadLog').attr('value', response.download_url);
        });
    });

    // AJAX - Change selected batch file
    $('.batchFileLink').click(function(e) {
        e.preventDefault();

        // Save the file name
        var filename = $(this).attr('name');

        // Create the ajax call
        var data = {
            'action': 'get_batch_file',
            'filename': filename
        };

        // Get the information
        $.post(ajaxurl, data, function(response) {
            console.log(JSON.stringify(JSON.parse(response.html), null, "\t"));

            // Clear the content of the container
            $('#batch_container').html('');

            // Change the content of the container
            $('#batch_container').html(document.createTextNode(JSON.stringify(JSON.parse(response.html), null, 4))).clone();

            // Make the content visible
            $('#batch_container').show();
        })
    });

    // Function for checking the api key
    function check_api_key() {
        // Create an array that contains all the inputs of the api
        var api_input_fields = ["storecontrl_api_ftp_password", "storecontrl_api_ftp_user", "storecontrl_api_images_url", "storecontrl_api_secret", "storecontrl_api_key", "storecontrl_api_url"];

        // Get the key
        var key = jQuery("#storecontrl_api_arture_key").val();

        // Check whether the value of the textbox equals 12
        if (key.length == 12 || key.length == 17) {
            // Use ajax to check the key
            jQuery.ajax({
                url: ajaxurl,
                type: 'post',
                data: {
                    action: 'check_storecontrl_api_connection',
                    key: key
                },
                success: function(response) {
                    if (response.data.answer == 'false') {
                        $('.tab-content h2').append(response.data.html);
                        $("html, body").animate({scrollTop: 0}, "slow");
                    }
                }
            })
        } else {
            // Disable the other options of the API
            change_field_access(api_input_fields, false);
        }
    }

    // This function is used to check for the arture api key. Before calling the Ajax function, we need to be sure that 12 characters have been entered
    jQuery("#storecontrl_api_arture_key").on('change keyup paste', function() {
        check_api_key();
    });


    // Call the function immediately when the page is done loading
    check_api_key();
});

// This function will be used to change the enabled state of multiple field
function change_field_access(fields, turn_on) {
    // Loop over the field
    fields.forEach(function(field) {
        if (!turn_on) {
            // Turn the fields off
            jQuery("#" + field).attr('readonly', 'readonly')
        } else {
            // Turn the fields on
            jQuery("#" + field).removeAttr('readonly');
        }
    });
}
