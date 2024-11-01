<div id='storecontrl_settings' class='wrap'>
    <div id="storecontrl_settings_notice_changes_box"><?php echo __("Let op! Er zijn onopgeslagen wijzigingen", "storecontrl-wp-connection-plugin"); ?></div>

	<h1><?php echo __('StoreContrl API', 'storecontrl-wp-connection-plugin'); ?></h1>

    <!-- Nav tabs -->
    <ul class="nav nav-tabs" role="tablist">
        <li role="presentation" class="active"><a href="#default_section" aria-controls="default_section" role="tab" data-toggle="tab"><?php echo __('API settings', 'storecontrl-wp-connection-plugin'); ?></a></li>
        <li role="presentation"><a href="#setup_section" aria-controls="setup_section" role="tab" data-toggle="tab"><?php echo __('Setup', 'storecontrl-wp-connection-plugin'); ?></a></li>
        <li role="presentation"><a href="#import_section" aria-controls="import_section" role="tab" data-toggle="tab"><?php echo __('Import settings', 'storecontrl-wp-connection-plugin'); ?></a></li>
        <li role="presentation"><a href="#woocommerce_section" aria-controls="woocommerce_section" role="tab" data-toggle="tab"><?php echo __('Woocommerce', 'storecontrl-wp-connection-plugin'); ?></a></li>
        <li role="presentation"><a href="#addons_section" aria-controls="addons_section" role="tab" data-toggle="tab"><?php echo __('Add-Ons', 'storecontrl-wp-connection'); ?></a></li>
        <li role="presentation"><a href="#debug_section" aria-controls="debug_section" role="tab" data-toggle="tab"><?php echo __('Status', 'storecontrl-wp-connection-plugin'); ?></a></li>
    </ul>

    <!-- Tab panes -->
    <div class="tab-content">

        <div role="tabpanel" class="tab-pane fade in active" id="default_section">

            <?php
            $storecontrl_api_arture_key = get_option('storecontrl_api_arture_key');
            if( !isset($storecontrl_api_arture_key) || empty($storecontrl_api_arture_key) ): ?>
                <div id="ArtureKey">
                    <a href="https://www.arture.nl/abonnementen/" title="API key aanvragen" target="_blank">API key aanvragen</a>
                </div>
            <?php endif; ?>

            <form method='post' action='options.php'>
                <?php
                settings_fields( 'storecontrl_api_options' );
                do_settings_sections( 'storecontrl_api_options' );
                submit_button();
                ?>
                <button id="test-storecontrl-api-connection" class="button button-primary"><?php echo __( 'Check API key and secret', 'storecontrl-wp-connection-plugin' ); ?></button>
                <div class="clear"></div>
            </form>
        </div>

        <div role="tabpanel" class="tab-pane fade" id="setup_section">
            <?php
            settings_fields( 'storecontrl_setup_options' );
            do_settings_sections( 'storecontrl_setup_options' );
            ?>
        </div>

        <div role="tabpanel" class="tab-pane fade" id="import_section">
            <form method='post' action='options.php'>
                <?php
                settings_fields( 'storecontrl_import_options' );
                do_settings_sections( 'storecontrl_import_options' );
                submit_button();
                ?>
            </form>
        </div>

        <div role="tabpanel" class="tab-pane fade" id="woocommerce_section">
            <form method='post' action='options.php'>
			    <?php
			    settings_fields( 'storecontrl_woocommerce_options' );
			    do_settings_sections( 'storecontrl_woocommerce_options' );
			    submit_button();
			    ?>
            </form>
        </div>

        <div role="tabpanel" class="tab-pane fade" id="addons_section">
            <form method='post' action='options.php'>
                <?php
                settings_fields( 'storecontrl_addons_options' );
                do_settings_sections( 'storecontrl_addons_options' );
                submit_button();
                ?>
            </form>
        </div>

        <div role="tabpanel" class="tab-pane fade" id="debug_section">
            <form method='post' action='options.php'>
                <?php
                settings_fields( 'storecontrl_debug_options' );
                do_settings_sections( 'storecontrl_debug_options' );
                ?>
            </form>
        </div>

    </div>

    <!-- Support -->
    <div class="tab-content">
        <div class="tabpanel">
            <br/>
            <p><?php echo __("If you encounter problems or have questions regarding the functionality of the API. Please refer to the frequently asked questions or contact us", "storecontrl-wp-connection"); ?>.</p>
            <p>
                <a href="http://www.arture.nl/support/" title="FAQ" target="_blank"><?php echo __('Frequently Asked Questions', 'storecontrl-wp-connection'); ?></a> <br />
                <a href="mail:support@arture.nl" title="Support ticket aanmaken">Ticket insturen</a>
            </p>
        </div>
    </div>

</div>

<div class="arture_banner" style="background: #1b8fcc; border-radius: 5px; display: flex; margin-top: 25px; width: 99%; color: #fff;">
    <div style="width: 30%; float: left;">
        <img style="max-width: 100%;" src="https://orderpickingapp.com/wp-content/uploads/2022/12/Logo-OPA-wit-e1671097850602.png"/>
    </div>
    <div style="width: 70%; float: right; padding: 20px;">
        <h2 style="color: #fff;">De nieuwe manier van orders verzamelen</h2>
        <ul class="cta-feature-list list-unstyled">
            <li class="d-flex align-items-center py-1"><span class="dashicons dashicons-yes"></span> Maak geen onnodige dure fouten meer met verzamelen</li>
            <li class="d-flex align-items-center py-1"><span class="dashicons dashicons-yes"></span> Creëer een efficiënte looproute door de winkel</li>
            <li class="d-flex align-items-center py-1"><span class="dashicons dashicons-yes"></span> Bundel producten uit orders tijdens het verzamelen</li>
            <li class="d-flex align-items-center py-1"><span class="dashicons dashicons-yes"></span> Barcodescanner, afbeelding en voorraadinformatie op je telefoon</li>
            <li class="d-flex align-items-center py-1"><span class="dashicons dashicons-yes"></span> Android en Apple app</li>
            <li class="d-flex align-items-center py-1"><span class="dashicons dashicons-yes"></span> Alle medewerkers kunnen helpen verzamelen</li>
        </ul>
        <p>Probeer de Orderpicking App nu 30 dagen vrijblijvend uit en ontdek de voordelen zelf.  Download de Woocommerce plugin in Wordpress en koppel de webshop aan ons portal en de app. Binnen 10 minuten start je met het besparen van veel tijd & kosten door onnodig verkeerd verzamelende en opgestuurde producten.</p>
        <a href="https://orderpickingapp.com/plans-and-pricing/" class="buttont button-primary" target="_blank">Meer informatie</a>
        <br/>
        <i style="font-size: 12px; margin-top: 20px;">Een product van Arture B.V. | Trusted company</i>
    </div>
</div>
