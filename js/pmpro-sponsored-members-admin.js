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
var pmprosm_settings = {
    init: function() {
        "use strict";

        this.new_mapping_btn = jQuery('input#pmprosm-add-new-mapping');
        this.save_mappings_btn = jQuery('input#pmprosm-save-btn');
        this.remove_btn = jQuery('input.pmprosm-remove');
        this.map_tbody = jQuery('.pmprosm-sponsormap-table .pmprosm-tbody');
        this.map_row_count = this.map_tbody.find('.pmprosm-row').size();
        this.spinner = jQuery('.pmprosm-tfoot').find('span.spinner');

        var self = this;

        self.new_mapping_btn.on('click', function() {

            self.duplicate_row();

            // update button list
            self.remove_btn = jQuery('input.pmprosm-remove');
            self.remove_btn.unbind('click').on('click', function() {
                var btn = jQuery(this);

                self.remove_row(btn);
            });

            self.map_tbody.find('.pmprosm-row').each(function(){
                jQuery(this).find('input.pmprosm-remove').removeAttr('disabled');
            });
        });

        self.save_mappings_btn.unbind('click').on('click', function() {
            self.spinner.addClass('is-active');
            self.save_mappings();
        });

        self.remove_btn.unbind('click').on('click', function() {
            var btn = jQuery(this);

            self.remove_row(btn);
        });

        console.log("Number of map rows: " + self.map_row_count);
        if (self.map_row_count === 1) {

            self.map_tbody.find('.pmprosm-row').each(function(){
                jQuery(this).find('input.pmprosm-remove').attr('disabled', 'disabled');
            });
        }

        return self;
    },
    duplicate_row: function() {
        "use strict";

        var self = this;

        var source = self.map_tbody.find('div.pmprosm-sponsor-map:last').clone();
        source.find('select.pmprosm-mainlevel').val(-1);
        source.find('select.pmprosm-sponsoredlevel').val(-1);
        source.find('input.pmprosm-seats').val(null);
        self.map_tbody.append(source);

    },
    remove_row: function( button ) {
        "use strict";

        console.log("Clicked 'remove' button");
        var self = this;

        button.closest('div.pmprosm-row.pmprosm-sponsor-map').remove();

        this.map_row_count = this.map_tbody.find('.pmprosm-row').size();

        if (self.map_row_count === 1) {
            self.map_tbody.find('.pmprosm-row').each(function(){
                jQuery(this).find('input.pmprosm-remove').attr('disabled', 'disabled');
            });
        }

    },
    save_mappings: function() {
        "use strict";

        event.preventDefault();

        var self = this;

        var mainlevel = [];
        var sponsoredlevel = [];
        var seats = [];
        var max_seats = [];
        var seat_cost = [];

        // search search through all pmpro-sponsor-map rows
        self.map_tbody.find('div.pmprosm-sponsor-map').each(function() {

            var mapDiv = jQuery(this);

            mapDiv.find('select.pmprosm-mainlevel').each(function() {
                mainlevel.push( jQuery(this).val() );
            });

            mapDiv.find('select.pmprosm-sponsoredlevel').each(function() {
                sponsoredlevel.push( jQuery(this).val() );
            });

            mapDiv.find('input.pmprosm-seats').each(function() {
                seats.push( jQuery(this).val() );
            });

            mapDiv.find('input.pmprosm-max-seats').each(function() {
                max_seats.push( jQuery(this).val() );
            });

            mapDiv.find('input.pmprosm-seat-cost').each(function() {
                seat_cost.push( jQuery(this).val() );
            });
        });

        console.log("Map:", mainlevel, sponsoredlevel, seats, max_seats, seat_cost);
        self._send( mainlevel, sponsoredlevel, seats, max_seats, seat_cost );
    },
    _send: function( main_levels, sponsored_levels, seats, max_seats, seat_cost ) {
        "use strict";

        var self = this;

        jQuery.ajax({
            url: ajaxurl,
            timeout: pmprosm.variables.timeout,
            type: 'POST',
            data: {
                action: 'pmprosm_save_map',
                'pmprosm_nonce': jQuery('#pmprosm_nonce').val(),
                'main_levels': main_levels,
                'sponsored_levels': sponsored_levels,
                'seats': seats,
                'max_seats': max_seats,
                'seat_cost': seat_cost,
                'sponsor_delete': jQuery('#pmprosm_sponsor_can_delete').is(':checked') ? 1 : 0
            },
            success: function(response) {
                "use strict";

                if (false === response.success) {
                    console.log("Error while calling remote: ", response.data);
                    window.alert(response.data);
                }
                console.log("Successfully called remote: ", response);
            },
            error: function(response, message) {
                "use strict";
                console.log("Error while calling remote: ", response.data);
                window.alert(message + ': ' + response.data);
            },
            complete: function() {
                self.spinner.removeClass('is-active');
            }
        });
    }
};

jQuery(document).ready(function() {
    "use strict";

    pmprosm_settings.init();
});
