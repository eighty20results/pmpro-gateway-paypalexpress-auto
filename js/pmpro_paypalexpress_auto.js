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
    init: function () {

        "use strict";
        this.gateway_input = jQuery("input[name='gateway']");
        this.gateway_radiobtn = jQuery("table#pmpro_payment_method a.pmpro_radio");
        this.gateway_name = this.gateway_input.val();

        this.payment_checkout = jQuery('#pmpro_' + this.gateway_name + '_checkout');
        this.payment_billing_info = jQuery('#pmpro_billing_address_fields');
        this.payment_info_fields = jQuery('#pmpro_payment_information_fields');
        this.payment_btn = jQuery('#pmpro_submit_span');
        this.paypal_btn = jQuery('span#pmpro_paypalexpress_checkout');
        this.paypal_auto_btn = jQuery('span#pmpro_paypalexpress_auto_checkout');
        this.sponsored_memberships = jQuery('table#pmpro_extra_seats');
        this.user_fields = jQuery("#pmpro_user_fields");
        this.user_fields_lnk = jQuery('#pmpro_user_fields_a');
        this.billing_info = jQuery('#pmpro_billing_address_fields');
        this.payment_method = jQuery('table#pmpro_payment_method');

        this.confirm_isset = jQuery('#pmpro_submit_span input[name="confirm"]').val();
        this.token_isset = jQuery('#pmpro_submit_span input[name="token"]').val();

        var self = this;

        self.gateway_input.unbind('click').on('click', function () {
            self.show_for_gateway();
        });

        //selects the radio button if the user clicks on the the associated label
        self.gateway_radiobtn.unbind('click').on('click', function () {
            jQuery(this).prev().click();
        });

        self.show_for_gateway();

        // Do we need to display the billing address fields?
        if (pmpro_ppea_gw.variables.show_billing_address === 1) {
            self.show_billing_address();
        }
    },
    show_billing_address: function () {
        "use strict";

        var self = this;

        if (self.billing_info.length > 0) {
            console.log("Billing fields are present on the page");
            self.billing_info.hide();
        }

    },
    show_for_gateway: function () {

        "use strict";
        var self = this;

        console.log("Processing for...: " + self.gateway_name);

        switch (self.gateway_name) {
            case 'paypal':
                console.log("Show for PayPal gateway is TODO");
                break;
            case 'paypalexpress':
                console.log("Show for PayPal Express gateway is TODO");
                break;

            case 'paypalexpress_auto':
                console.log("Showing/hiding for the PayPal Express Auto-confirmation gateway");
                self.user_fields.show();
                self.user_fields_lnk.hide();
                self.payment_method.hide();

                if (( typeof self.token_isset !== 'undefined'  ) && (self.confirm_isset > 0)) {
                    console.log("Will show the confirmation button");
                    self.payment_btn.show();
                } else {
                    console.log("Will hide the confirmation/payment button");
                    self.payment_btn.hide();
                }

                if ( typeof self.sponsored_memberships !== 'undefined' ) {
                    self.paypal_auto_btn.show();
                }

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


jQuery(document).ready(function () {
    "use strict";

    var PayPal = pmpro_PayPalGW;
    PayPal.init();
});
