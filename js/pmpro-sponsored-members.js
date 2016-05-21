
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

var pmprosmManager = {
    init: function() {
        "use strict";

        this.disable_user_checkbox = jQuery('input.pmprosm-checkbox');

        var self = this;

        self.disable_user_checkbox.unbind('click').on('click', function() {

            console.log("Clicked on membership checkbox");

            var member_id = self._collect_member_id(this);
            var status = jQuery(this).is('checked') ? 1 : 0;

            self.disable_memberships(member_id, status);
        });
    },
    disable_memberships: function( member_id, status ) {
        "use strict";

        var self = this;

        jQuery.ajax({
            url: pmprosm.variables.ajaxurl,
            type: 'POST',
            timeout: pmprosm.variables.timeout,
            dataType: 'JSON',
            data: {
                action: 'pmprosm_disable_membership',
                pmprosm_user: member_id,
                pmprosm_nonce: jQuery('#pmprosm_nonce').val(),
                pmprosm_status: status
            },
            error: function() {

            },
            success: function() {

            }
        });
    },
    _collect_member_id: function($checkbox) {
        "use strict";

        var memberId = jQuery($checkbox).val();
        return memberId;
    }
};

jQuery(document).ready(function() {

    var sponsored = pmprosmManager;
    sponsored.init();
});