
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

            self.disable_memberships(member_id, status, this);
        });
    },
    disable_memberships: function( member_id, status, element ) {
        "use strict";

        var self = this;

        if (0 === status) {

            if ( true === window.confirm(pmprosm.messages.confirmation_1) ) {

                jQuery.ajax({
                    url: pmprosm.variables.ajaxurl,
                    type: 'POST',
                    timeout: pmprosm.variables.timeout,
                    dataType: 'JSON',
                    data: {
                        action: 'pmprosm_disable_membership',
                        pmprosm_user: member_id,
                        pmprosm_code: jQuery('#pmprosm_code_id').val(),
                        pmprosm_nonce: jQuery('#pmprosm_nonce').val(),
                        pmprosm_status: status
                    },
                    error: function( jqXHR, $s, error ) {
                        console.log("Error: ", $s, $error, jqXHR);
                        
                        alert("Error while attempting to update status for user");
                        
                        // reset the checkbox value
                        if (status === 0) {
                            jQuery(element).prop('checked', true);
                        }

                        if (status === 1) {
                            jQuery(element).prop('checked', false);
                        }

                    },
                    success: function( response, $status, jqXHR ) {

                        console.log("Response from server: ", response, $status);

                        if (false === response.success) {
                            window.alert(response.data);
                            return;
                        }

                        var row = jQuery(element).closest('div.div-table-row.pmprosm-userlist-row');
                        var usage_cnt_elem = jQuery('p.pmprosm-usage-heading > span.pmprosm_code_usage');
                        var usage_cnt = usage_cnt_elem.text();

                        // We've successfully disabled this user.
                        if (status === 0 && true === response.success ) {

                            // decrement usage counter
                            usage_cnt--;
                            row.fadeTo( 500, 0.5);
                        }

                        if (status === 1 && true === response.success ) {
                            // increment usage counter
                            usage_cnt++;
                            row.fadeTo( 500, 1.0);
                        }
                        
                        usage_cnt_elem.html(usage_cnt);
                    }
                });
            }
        }
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