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

var gateway_ppea_auto = {
    init: function () {

        // Admin setting
        this.gateway_name = jQuery("select#gateway").val();
        this.setting_row_element = "tr.gateway.gateway_" + this.gateway_name;
        console.log("Settings page for PayPal Express (auto) Payment Gateway: " + this.gateway_name);

        this.tr_settings = jQuery(this.setting_row_element);

        this.tr_settings.each(function () {

            console.log("Show() for a settings section: ", this);
            jQuery(this).show();
        });

        var self = this;
    }
};

jQuery(document).ready(function () {

    var gateway = gateway_ppea_auto;
    gateway.init();
});
