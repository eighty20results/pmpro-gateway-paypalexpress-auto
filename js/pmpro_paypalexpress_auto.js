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
        this.payment_btn = jQuery('#pmpro_submit_span');
        this.paypal_btn = jQuery('span#pmpro_paypalexpress_checkout');

        this.payment_method = jQuery('table#pmpro_payment_method');

        var self = this;

        self.gateway_input.unbind('click').on('click', function() {
            self.show_for_gateway();
        });

        //selects the radio button if the user clicks on the the associated label
        self.gateway_radiobtn.unbind('click').on('click', function () {
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
                console.log("Show for PayPal gateway is TODO");
                break;
            case 'paypalexpress':
                console.log("Show for PayPal Express gateway is TODO");
                break;

            case 'paypalexpress_auto':
                console.log("Showing/hiding for the PayPal Express Auto-confirmation gateway");
                self.payment_method.hide();
                self.payment_btn.hide();
                self.paypal_btn.hide();
                break;

            default:
                console.log("No recognized PayPal gateway");
                self.payment_billing_info.show();
                self.payment_info_fields.show();
                self.payment_btn.show();
                self.payment_checkout.show();

        }
    }
};


jQuery(document).ready(function() {
    "use strict";

    var PayPal = pmpro_PayPalGW;
    PayPal.init();
});
