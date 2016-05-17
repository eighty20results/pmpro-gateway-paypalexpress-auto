/**
 Copyright (c) 2016 - Eighty / 20 Results by Wicked Strong Chicks. ALL RIGHTS RESERVED

 This program is free software: you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation, either version 3 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program.  If not, see <http://www.gnu.org/licenses/>.
 **/

var pmpro_PayPalGW = {
    init: function() {

        "use strict";
        this.gateway_input = jQuery("input[name='gateway']");
        this.gateway_radiobtn = jQuery("table#pmpro_payment_method a.pmpro_radio");
        this.gateway_name = this.gateway_input.val();

        this.payment_checkout = jQuery('#pmpro_' + this.gateway_name + '_checkout');
        this.payment_billing_info = jQuery('#pmpro_billing_address_fields');
        this.payment_info_fields = jQuery('#pmpro_payment_information_fields');
        this.payment_btn_submit = jQuery('#pmpro_submit_span');

        var self = this;

        self.gateway_input.unset('click').on('click', function() {
            self.show_for_gateway();
        });

        //selects the radio button if the user clicks on the the associated label
        self.gateway_radiobtn.unset('click').on('click', function () {
            jQuery(this).prev().click();
        });

        self.show_for_gateway();
    },
    show_for_gateway: function() {

        "use strict";
        var self = this;

        console.log("Processing for...: " + self.gateway_name );

        switch(self.gateway_name) {
            case 'paypal':

                break;
            case 'paypalexpress':

                break;

            case 'paypalexpress_auto':

                break;

            default:

                self.payment_billing_info.hide();
                self.payment_info_fields.hide();
                self.payment_btn_submit.hide();
                self.payment_checkout.show();

        }
    }
};


jQuery(document).ready(function() {

    "use strict";

    var PayPal = pmpro_PayPalGW;

    PayPal.init();

    PayPal.show_for_gateway();

    //choosing payment method
    jQuery('input[name="gateway"]').click(function () {

        var $gw_name = jQuery(this);

        if ($gw_name.val() === 'paypal') {
            console.log("Processing for PayPal gateway");
            jQuery('#pmpro_paypalexpress_auto_checkout').hide();
            jQuery('#pmpro_billing_address_fields').show();
            jQuery('#pmpro_payment_information_fields').show();
            jQuery('#pmpro_submit_span').show();
        }

        if ( $gw_name.val() === 'paypalexpress_auto') {
            console.log("Processing for PayPal Express (auto) gateway");
            jQuery('#pmpro_billing_address_fields').hide();
            jQuery('#pmpro_payment_information_fields').hide();
            jQuery('#pmpro_submit_span').hide();
            jQuery('#pmpro_paypalexpress_auto_checkout').show();
        }

        if ( $gw_name.val() === 'paypalexpress') {
            console.log("Processing for PayPal Express gateway");
        }
    });

});
