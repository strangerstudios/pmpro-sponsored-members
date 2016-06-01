<?php

if (!function_exists('pmpro_getAllLevels')) {
    _e("Warning: Paid Memberships Pro has either not been installed, or needs to be activated for this add-on to be useful.", "pmpro_sponsored_members");
    return;
}

global $pmprosm_sponsored_account_levels;

$level_map = get_option('pmprosm_level_map', array());
$settings = get_option('pmprosm_settings', array());

$existing_levels = pmpro_getAllLevels(true);
$level_map = apply_filters('pmprosm_sponsored_account_levels', $level_map);

if (empty($level_map)) {
    $level_map = array(
        -1 => array(
            'main_level_id' => -1,        //redundant but useful
            'sponsored_level_id' => array(-1),    //array or single id
            'seats' => null,
            'max_seats' => null,
            'seat_cost' => null,
        ),
    );
}

if (WP_DEBUG) {
    error_log("Level Map after merge: " . print_r($level_map, true));
}
?>
<h2 class="pmprosm-levelmap-header"><?php _e("Create Main-to-Sponsored Membership Level Map", "pmpro_sponsored_members"); ?></h2>
<hr class="pmprosm-divider"/>
<?php wp_nonce_field('pmprosm', 'pmprosm_nonce'); ?>
<div class="pmprosm-sponsormap-table pmprosm-table">
    <div class="pmprosm-thead">
        <div class="pmprosm-row pmprosm-header-row">
            <div class="pmprosm-column pmprosm-6-col pmprosm-header">
                <div class="pmprosm-mainlevel-header">
                    <?php _e("Main level", "pmpro_sponsored_members"); ?>
                </div>
            </div>
            <div class="pmprosm-column pmprosm-6-col pmprosm-header">
                <div class="pmprosm-sponsoredlevel-header">
                    <?php _e("Sponsored level", "pmpro_sponsored_members"); ?>
                </div>
            </div>
            <div class="pmprosm-column pmprosm-6-col pmprosm-header">
                <div class="pmprosm-seats-header">
                    <?php _e("No. of seats", "pmpro_sponsored_members"); ?>
                </div>
            </div>
            <div class="pmprosm-column pmprosm-6-col pmprosm-header">
                <div class="pmprosm-seats-header">
                    <?php _e("Max seats", "pmpro_sponsored_members"); ?>
                </div>
            </div>
            <div class="pmprosm-column pmprosm-6-col pmprosm-header">
                <div class="pmprosm-seats-header">
                    <?php _e("Seat cost", "pmpro_sponsored_members"); ?>
                </div>
            </div>

            <div class="pmprosm-column pmprosm-6-col pmprosm-header">
                <div class="pmprosm-addbutton-header">
                    <input type="button" id="pmprosm-add-new-mapping" class="pmprosm-new button button-secondary"
                           name="pmprosm-add[]"
                           value="<?php _e("Add", "pmpro_sponsored_members"); ?>"/>
                </div>
            </div>
        </div>
    </div>
    <div class="pmprosm-tbody"><?php
        foreach ($level_map as $main_level => $entry) { ?>
            <div class="pmprosm-row pmprosm-sponsor-map">
                <div class="pmprosm-column pmprosm-6-col pmprosm-mainlevel">
                    <select class="pmprosm-mainlevel" name="pmprosm-mainlevel[]">
                        <option value="-1" <?php selected($main_level, -1); ?>> ---</option>
                        <?php
                        foreach ($existing_levels as $level) { ?>

                            <option
                            value="<?php echo $level->id; ?>" <?php selected($level->id, $main_level); ?>><?php echo $level->name; ?></option><?php

                        } ?>
                    </select>
                </div>
                <div class="pmprosm-column pmprosm-6-col pmprosm-sponsoredlevel">
                    <select class="pmprosm-sponsoredlevel" name="pmprosm-sponsoredlevel[]" multiple="multiple"
                            size="2"> <?php
                        if (is_array($entry['sponsored_level_id'])) { ?>

                        <option
                            value="-1" <?php echo(in_array(-1, $entry['sponsored_level_id']) ? 'selected="selected"' : null); ?>>
                                --- </option><?php

                        } else { ?>

                        <option value="-1" <?php selected($entry['sponsored_level_id'], -1); ?>> --- </option><?php

                        } ?>

                        <?php

                        foreach ($existing_levels as $level) {
                            if (is_array($entry['sponsored_level_id'])) { ?>

                                <option
                                value="<?php echo $level->id; ?>" <?php echo(in_array($level->id, $entry['sponsored_level_id']) ? 'selected="selected"' : null); ?>><?php echo $level->name; ?></option><?php

                            } else { ?>

                                <option
                                value="<?php echo $level->id; ?>" <?php selected($level->id, $entry['sponsored_level_id']); ?>><?php echo $level->name; ?></option><?php

                            }
                        } ?>
                    </select>
                </div>
                <div class="pmprosm-column pmprosm-6-col pmprosm-seats">
                    <input type="number" class="pmprosm-seats" name="pmprosm-seats[]"
                           value="<?php echo($entry['seats'] == 0 ? null : $entry['seats']); ?>"/>
                </div>
                <div class="pmprosm-column pmprosm-6-col pmprosm-max-seats">
                    <input type="number" class="pmprosm-max-seats" name="pmprosm-max-seats[]"
                           value="<?php echo($entry['max_seats'] == 0 ? null : $entry['max_seats']); ?>"/>
                </div>
                <div class="pmprosm-column pmprosm-6-col pmprosm-seat-cost">
                    <input type="number" class="pmprosm-seat-cost" name="pmprosm-seat-cost[]"
                           value="<?php echo($entry['seat_cost'] == 0 ? null : $entry['seat_cost']); ?>"/>
                </div>

                <div class="pmprosm-column pmprosm-6-col pmprosm-remotebtn">
                    <div class="pmprosm-removebutton-header">
                        <input type="button" class="pmprosm-remove button button-secondary" name="pmprosm-remove[]"
                               value="<?php _e("Remove", "pmpro_sponsored_members"); ?>"/>
                    </div>
                </div>
            </div>
            <div class="clear"></div><?php
        }
        ?>
    </div>
</div>

<hr class="pmprosm-divider"/>
<div class="pmprosm-sponsor-settings">
    <h2 class="pmprosm-sponsor-settings-header"><?php _e("Permissions applied to the Sponsor", "pmpro_sponsored_members"); ?></h2>
    <div class="pmprosm-table">
        <div class="pmprosm-tbody">
            <div class="pmprosm-row pmprosm-sponsor-settings clear">
                <div class="pmprosm-column pmprosm-2-col">
                    <label for="pmprosm-sponsor-access-checkbox"
                           class="pmprosm-sponsor-settings"><?php _e("May delete their sponsored member's account", "pmpro_sponsored_members"); ?></label><br/>
                    <small><?php _e("Enabling this setting will result in the removal of the sponsored user's WordPress account whenever the Sponsor disables the sponsored member's access on their \"Accounts\" membership page.", "pmpro_sponsored_members"); ?></small>
                </div>
                <div class="pmprosm-column pmprosm-2-col">
                    <div class="pmprosm-ckbox">
                        <input class="pmprosm-checkbox pmprosm-sponsor-options" type="checkbox"
                               id="pmprosm_sponsor_can_delete" name="pmprosm_sponsor_can_delete"
                               value="1" <?php echo isset($settings['sponsor_can_delete']) && $settings['sponsor_can_delete'] == 1 ? 'checked="checked"' : null; ?>>
                        <label><i></i></label>
                    </div>
                </div>
            </div>
            <div class="pmprosm-tfoot">
                <div class="pmprosm-row pmprosm-footer">
                    <div class="pmprosm-column pmprosm-2-col pmprosm-savebtn">
                        <span class="spinner"></span>
                        <input type="button" id="pmprosm-save-btn" class="button button-primary"
                               value="<?php _e("Save Settings", "pmpro_sponsored_members"); ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>



