<?php
/*
Plugin Name: PMPro Sponsored Members
Plugin URI: http://www.paidmembershipspro.com/add-ons/pmpro-sponsored-members/
Description: Generate discount code for a main account holder to distribute to sponsored members.
Version: .4.3
Author: Stranger Studios
Author URI: http://www.strangerstudios.com
*/

/*
	Set these to the ids of your main and sponsored levels. 	
	
	Now using a global array so you can have multiple main and sponsored levels.	
	Array keys should be the main account level.
	
	global $pmprosm_sponsored_account_levels;	
	$pmprosm_sponsored_account_levels = array(
		//set 5 seats at checkout
		1 => array(
			'main_level_id' => 1,		//redundant but useful
			'sponsored_level_id' => array(1,2),	//array or single id
			'seats' => 5			
		),
		//seats based on field at checkout
		3 => array(
			'main_level_id' => 3,		//redundant but useful
			'sponsored_level_id' => 4,			
			'seat_cost' => 250,
			'max_seats' => 10
		)
	);
*/

/*
	Set $pmprosm_sponsored_account_levels above here or in a custom plugin.
*/

//old constant values for reference. not used anymore
//define('PMPROSM_MAIN_ACCOUNT_LEVEL', 1);
//define('PMPROSM_SPONSORED_ACCOUNT_LEVEL', 2);
//define('PMPROSM_NUM_SEATS', 5);
//define('PMPROSM_SEAT_COST', 250);
//define('PMPROSM_MAX_SEATS', 10);

//check if a level id is a "main account" level
function pmprosm_isMainLevel($level_id)
{
	global $pmprosm_sponsored_account_levels;
	
	if(empty($pmprosm_sponsored_account_levels))
		return false;
	
	foreach($pmprosm_sponsored_account_levels as $key => $values)
	{
		if($level_id == $key)
			return true;
	}
	
	return false;
}

//check if a level id is a "sponsored level"
function pmprosm_isSponsoredLevel($level_id)
{
	global $pmprosm_sponsored_account_levels;
		
	if(empty($pmprosm_sponsored_account_levels))
		return false;
	
	foreach($pmprosm_sponsored_account_levels as $key => $values)
	{		
		if(is_array($values['sponsored_level_id']))
		{
			if(in_array($level_id, $values['sponsored_level_id']))
				return true;
		}
		else
		{
			if($values['sponsored_level_id'] == $level_id)
				return true;
		}
	}
	
	return false;
}

//get values by main account level
function pmprosm_getValuesByMainLevel($level_id)
{
	global $pmprosm_sponsored_account_levels;
	if(isset($pmprosm_sponsored_account_levels[$level_id]))
		return $pmprosm_sponsored_account_levels[$level_id];
	else
		return false;
}

//get values by sponsored account level
function pmprosm_getValuesBySponsoredLevel($level_id)
{
	global $pmprosm_sponsored_account_levels;
	
	foreach($pmprosm_sponsored_account_levels as $key => $values)
	{
		if(is_array($values['sponsored_level_id']))
		{
			if(in_array($key, $values['sponsored_level_id']))
				return $pmprosm_sponsored_account_levels[$key];
		}
		else
		{
			if($values['sponsored_level_id'] == $key)
				return $pmprosm_sponsored_account_levels[$key];
		}
	}
}

//cancel sub members when a main account cancels
//activate sub members when changed to main account
//generate a discount code when changing to main account level
function pmprosm_pmpro_after_change_membership_level($level_id, $user_id)
{
	global $wpdb;
	
	//are they cancelling?
	if(empty($level_id))
	{
		//is there a discount code attached to this user?
		$code_id = pmprosm_getCodeByUserID($user_id);		
		
		//if so find all users who signed up with that and cancel them as well
		if(!empty($code_id))
		{			
			$sqlQuery = "SELECT user_id FROM $wpdb->pmpro_discount_codes_uses WHERE code_id = '" . $code_id . "'";			
			$sub_user_ids = $wpdb->get_col($sqlQuery);
			
			if(!empty($sub_user_ids))
			{
				foreach($sub_user_ids as $sub_user_id)
				{
					//cancel their membership
					pmpro_changeMembershipLevel(0, $sub_user_id);
				}
			}
		}
		
		//remove seats from meta
		update_user_meta($user_id, "pmprosm_seats", "");
	}
	elseif(pmprosm_isMainLevel($level_id))
	{
		//get values for this sponsorship
		$pmprosm_values = pmprosm_getValuesByMainLevel($level_id);
				
		//check if this user already has a discount code
		$code_id = pmprosm_getCodeByUserID($user_id);
				
		if(empty($code_id))
		{
			//if seats cost money and there are no seats, just return
			if(!empty($pmprosm_values['seat_cost']) && empty($_REQUEST['seats']))
				return;
			
			//generate a new code. change these values if you want.
			if(version_compare(PMPRO_VERSION, "1.7.5") > 0)
				$code = "S" . pmpro_getDiscountCode($user_id); 	//seed parameter added in version 1.7.6
			else
				$code = "S" . pmpro_getDiscountCode();
			$starts = date("Y-m-d");
			$expires = date("Y-m-d", strtotime("+1 year"));
			
			//check for seats
			if(isset($_REQUEST['seats']))
				$uses = intval($_REQUEST['seats']);
			elseif(!empty($pmprosm_values['seats']))
				$uses = $pmprosm_values['seats'];
			else
				$uses = "";
			
			$sqlQuery = "INSERT INTO $wpdb->pmpro_discount_codes (code, starts, expires, uses) VALUES('" . esc_sql($code) . "', '" . $starts . "', '" . $expires . "', '" . intval($uses) . "')";
			
			if($wpdb->query($sqlQuery) !== false)
			{
				//set code in user meta
				$code_id = $wpdb->insert_id;				
				pmprosm_setCodeUserID($code_id, $user_id);
				
				//okay update levels for code
				if(!is_array($pmprosm_values['sponsored_level_id']))
					$sponsored_levels = array($pmprosm_values['sponsored_level_id']);
				else
					$sponsored_levels = $pmprosm_values['sponsored_level_id'];
					
				foreach($sponsored_levels as $sponsored_level)
				{
					$sqlQuery = "INSERT INTO $wpdb->pmpro_discount_codes_levels (code_id, level_id, initial_payment, billing_amount, cycle_number, cycle_period, billing_limit, trial_amount, trial_limit, expiration_number, expiration_period) VALUES('" . esc_sql($code_id) . "', '" . esc_sql($sponsored_level) . "', '0', '0', '0', 'Month', '0', '0', '0', '0', 'Month')";
					$wpdb->query($sqlQuery);										
				}
			}
		}	
		elseif(!empty($pmprosm_values['sponsored_level_id']))
		{
			//update sponsor code and sub accounts
			pmprosm_sponsored_account_change($level_id, $user_id);
			
			//make sure we only do it once
			remove_action("pmpro_after_checkout", "pmprosm_pmpro_after_checkout_sponsor_account_change", 10, 2);
		}	
	}
}
add_action("pmpro_after_change_membership_level", "pmprosm_pmpro_after_change_membership_level", 10, 2);

/*
	This is the function that handles updating discount codes and sub accounts when a sponsor renews or changes levels.
*/
function pmprosm_sponsored_account_change($level_id, $user_id)
{
	global $wpdb;
	
	$pmprosm_values = pmprosm_getValuesByMainLevel($level_id);
	$code_id = pmprosm_getCodeByUserID($user_id);

	//update seats for code
	if(isset($_REQUEST['seats']))			
		$seats = intval($_REQUEST['seats']);
	elseif(!empty($pmprosm_values['seats']))
		$seats = $pmprosm_values['seats'];
	else
		$seats = "";
			
	$sqlQuery = "UPDATE $wpdb->pmpro_discount_codes SET uses = '" . $seats . "' WHERE id = '" . $code_id . "' LIMIT 1";
	$wpdb->query($sqlQuery);
	
	//activate/deactivate old accounts
	if(!empty($pmprosm_values['sponsored_accounts_at_checkout']))
	{
		if(isset($_REQUEST['old_sub_accounts_active']))
		{		
			$old_sub_accounts_active = $_REQUEST['old_sub_accounts_active'];						
			$children = pmprosm_getChildren($user_id);
						
			for($i = 0; $i < count($children); $i++)
			{
				if(in_array($children[$i], $old_sub_accounts_active))
				{					
					//they should have their level/etc from before
				}
				else
				{				
					//remove their level
					pmpro_changeMembershipLevel(0, $children[$i]);
					
					//remove discount code use
					pmprosm_removeDiscountCodeUse($children[$i], $code_id);
				}
			}			
		}
	}
	
	//see if we should enable some accounts
	$sqlQuery = "SELECT user_id FROM $wpdb->pmpro_discount_codes_uses WHERE code_id = '" . $code_id . "'";
	$sub_user_ids = $wpdb->get_col($sqlQuery);
		
	if(!empty($sub_user_ids))
	{
		//check if they have enough seats				
		if($seats >= count($sub_user_ids))
		{				
			$count = 0;
			foreach($sub_user_ids as $sub_user_id)
			{
				$count++;
				
				//change their membership
				if(is_array($pmprosm_values['sponsored_level_id']))
				{
					//what level did this user have last that is a sponsored level?
					$last_level_id = $wpdb->get_var("SELECT membership_id FROM $wpdb->pmpro_memberships_users WHERE user_id = '" . $sub_user_id . "' AND status = 'inactive' ORDER BY id DESC");
					
					//okay give them that level back
					if(in_array($last_level_id, $pmprosm_values['sponsored_level_id']))
						pmprosm_changeMembershipLevelWithCode($last_level_id, $sub_user_id, $code_id);
				}
				else
					pmprosm_changeMembershipLevelWithCode($pmprosm_values['sponsored_level_id'], $sub_user_id, $code_id);
			}
		}
		else
		{			
			//get code
			$code = $wpdb->get_var("SELECT code FROM $wpdb->pmpro_discount_codes WHERE id = '" . $code_id . "' LIMIT 1");
			
			//cancel sponsnored accounts
			foreach($sub_user_ids as $sub_user_id)
			{
				//cancel their membership
				pmpro_changeMembershipLevel(0, $sub_user_id);
			}
			
			//detach sponsored accounts
			$sqlQuery = "DELETE FROM $wpdb->pmpro_discount_codes_uses WHERE code_id = '" . $code_id . "'";					
			$wpdb->query($sqlQuery);
						
			//we better warn them					
			if(is_admin())
			{
				//assuming an admin update
				set_transient("pmprosm_error", sprintf(__("This user has fewer seats than they had sponsored accounts. The sponsored accounts have been deactivated. The user must have his sponsored accounts checkout again using the code: %s.", "pmpro_sponsored_members"), $code));
			}
			else
			{
				//assuming a checkout
				add_filter("pmpro_confirmation_url", "pmprosm_pmpro_confirmation_url_lowseats");				
			}
		}
	}
}

/*
	Want to make sure we trigger an update when a sponsor renews the same level.
*/
function pmprosm_pmpro_after_checkout_sponsor_account_change($user_id)
{
	//get level
	$level_id = intval($_REQUEST['level']);
	
    // handle sponsored accounts

    if (pmprosm_isMainLevel($level_id))
        pmprosm_sponsored_account_change($level_id, $user_id);
}
add_action("pmpro_after_checkout", "pmprosm_pmpro_after_checkout_sponsor_account_change", 10, 2);

/*
	low seats message for confirmation message
*/
//add param to checkout URL (queued up in pmprosm_sponsored_account_change())
function pmprosm_pmpro_confirmation_url_lowseats($url)
{
	$url .= "&lowseats=1";
	
	return $url;
}
//add message to confirmation page
function pmprosm_pmpro_confirmation_message_lowseats($message)
{
	global $wpdb, $current_user;
	$code_id = pmprosm_getCodeByUserID($current_user->ID);
	$code = $wpdb->get_var("SELECT code FROM $wpdb->pmpro_discount_codes WHERE id = '" . $code_id . "' LIMIT 1");
	
	$message .= sprintf(__("<p><strong>Notice:</strong>Your current membership has fewer seats than you had sponsored accounts. The accounts have been deactivated. You must have your sponsored accounts checkout again using your code: %s.</p>", "pmpro_sponsored_members"), $code);
	
	return $message;
}
if(!empty($_REQUEST['lowseats']))
	add_filter("pmpro_confirmation_message", "pmprosm_pmpro_confirmation_message_lowseats");

//low seats message for edit user page
function pmprosm_admin_head_errors()
{
	$error = get_transient("pmprosm_error");
	if(!empty($error))
	{		
	?>
		<script>
		jQuery(document).ready(function() {
			jQuery('div.wrap h2').after('<div id="message" class="updated"><p><?php echo $error;?></p></div>');
		});
		</script>
	<?php
	
		delete_transient("pmprosm_error");
	}
}
add_action("admin_head", "pmprosm_admin_head_errors");

//function to get children of aponsor
function pmprosm_getChildren($user_id = NULL) {

    global $wpdb, $current_user;

    $children = array();

    if(empty($user_id)) {
        if(is_user_logged_in())
            $user_id = $current_user->ID;
        else
            return false;
    }

    $code_id = pmprosm_getCodeByUserID($user_id);

    if(!empty($code_id))
        $children = $wpdb->get_col("SELECT user_id FROM $wpdb->pmpro_memberships_users WHERE code_id = $code_id AND status = 'active'");

    return $children;
}

//functions to get and set a code user ID
function pmprosm_getCodeUserID($code_id)
{
	$code_user_ids = get_option("pmpro_code_user_ids");	
		
	if(!empty($code_user_ids[$code_id]))
		return $code_user_ids[$code_id];
	else
		return false;
}
function pmprosm_setCodeUserID($code_id, $user_id)
{
	$code_user_ids = get_option("pmpro_code_user_ids");	
	$code_user_ids[$code_id] = $user_id;
	
	return update_option("pmpro_code_user_ids", $code_user_ids);
}
function pmprosm_deleteCodeUserID($code_id)
{
	$code_user_ids = get_option("pmpro_code_user_ids");	
	unset($code_user_ids[$code_id]);
	
	return update_option("pmpro_code_user_ids", $code_user_ids);
}

//get discount code by user
function pmprosm_getCodeByUserID($user_id)
{
	$code_user_ids = get_option("pmpro_code_user_ids");
		
	if(is_array($code_user_ids))
	{
		foreach($code_user_ids as $code_id => $code_user_id)
		{
			if($code_user_id == $user_id)
				return $code_id;
		}
	}
	
	return false;
}

//get user by discount code
function pmprosm_getUserByCodeID($needle)
{
	$code_user_ids = get_option("pmpro_code_user_ids");
		
	if(is_array($code_user_ids))
	{
		foreach($code_user_ids as $code_id => $code_user_id)
		{
			if($code_id == $needle)
				return $code_user_id;
		}
	}
	
	return false;
}

//show a user's discount code on the confirmation page
function pmprosm_pmpro_confirmation_message($message)
{
	global $current_user, $wpdb;
	
	$code_id = pmprosm_getCodeByUserID($current_user->ID);	
	
	if(!empty($code_id))
	{
		$pmprosm_values = pmprosm_getValuesByMainLevel($current_user->membership_level->ID);
		$code = $wpdb->get_row("SELECT * FROM $wpdb->pmpro_discount_codes WHERE id = '" . esc_sql($code_id) . "' LIMIT 1");
		
		if(!is_array($pmprosm_values['sponsored_level_id']))
			$sponsored_level_ids = array($pmprosm_values['sponsored_level_id']);
		else
			$sponsored_level_ids = $pmprosm_values['sponsored_level_id'];				
		
		//no sponsored levels to use codes for
		if(empty($sponsored_level_ids) || empty($sponsored_level_ids[0]))
			return $message;
		
		//no uses for this code
		if(empty($code->uses))
			return $message;
		
		$pmpro_levels = pmpro_getAllLevels(false, true);
				
		$code_urls = array();
		foreach($sponsored_level_ids as $sponsored_level_id)
		{						
			$level_name = $pmpro_levels[$sponsored_level_id]->name;
			$code_urls[] = array("name"=>$level_name, "url"=>pmpro_url("checkout", "?level=" . $sponsored_level_id . "&discount_code=" . $code->code));
		}
	}
			
	if(!empty($code))
	{
		if(count($code_urls) > 1)
			$message .= "<div class=\"pmpro_content_message\"><p>" . __("Give this code to your sponsored members to use at checkout:", "pmpro_sponsored_members") . " <strong>" . $code->code . "</strong></p><p>" . __("Or provide one of these direct links to register:", "pmpro_sponsored_members") . "<br /></p>";
		else
			$message .= "<div class=\"pmpro_content_message\"><p>" . __("Give this code to your sponsored members to use at checkout:", "pmpro_sponsored_members") . " <strong>" . $code->code . "</strong></p><p>" . __("Or provide this direct link to register:", "pmpro_sponsored_members") . "<br /></p>";
			
		$message .= "<ul>";
			foreach($code_urls as $code_url)
				$message .= "<li>" . $code_url['name'] . ":<strong> " . $code_url['url'] . "</strong></li>";
		$message .= "</ul>";
		
		if(empty($code->uses))
			$message .= __("This code has unlimited uses.", "pmpro_sponsored_members");
		else
			$message .= sprintf(__("This code has %d uses.", "pmpro_sponsored_members"), $code->uses);
		
		$message .= "</div>";
	}
	return $message;
}
add_filter("pmpro_confirmation_message", "pmprosm_pmpro_confirmation_message");

//delete code connection when a discount code is deleted
function pmprosm_pmpro_delete_discount_code($code_id)
{
	pmprosm_deleteCodeUserID($code_id);
}
add_action("pmpro_delete_discount_code", "pmprosm_pmpro_delete_discount_code");

//only let members using a sponsored discount code sign up for the sponsored level
function pmprosm_pmpro_registration_checks($pmpro_continue_registration)
{
	//only bother if things are okay so far
	if(!$pmpro_continue_registration)
		return $pmpro_continue_registration;

	//level = PMPROSM_SPONSORED_ACCOUNT_LEVEL and there is no discount code, then show an error message
	global $pmpro_level, $discount_code, $wpdb;
	if(pmprosm_isSponsoredLevel($pmpro_level->id) && empty($discount_code) && !pmprosm_isMainLevel($pmpro_level->id))
	{
		pmpro_setMessage(__("You must use a valid discount code to register for this level.", "pmpro_sponsored_members"), "pmpro_error");
		return false;
	}
		
	//if a discount code is being used, check that the main account is active
	if(pmprosm_isSponsoredLevel($pmpro_level->id) && !empty($discount_code))
	{
		$pmprosm_values = pmprosm_getValuesBySponsoredLevel($pmpro_level->id);
		$code_id = $wpdb->get_var("SELECT id FROM $wpdb->pmpro_discount_codes WHERE code = '" . esc_sql($discount_code) . "' LIMIT 1");
		if(!empty($code_id))
		{
			$code_user_id = pmprosm_getCodeUserID($code_id);
						
			if(!empty($code_user_id) && !pmpro_hasMembershipLevel($pmprosm_values['main_level_id'], $code_user_id))
			{
				pmpro_setMessage(__("The sponsor for this code is inactive. Ask them to renew their account.", "pmpro_sponsored_members"), "pmpro_error");
				return false;
			}
		}
	}
	
	//if the level has max or min seats, check them
	if(pmprosm_isMainLevel($pmpro_level->id))
	{
		$pmprosm_values = pmprosm_getValuesBySponsoredLevel($pmpro_level->id);
		if(isset($pmprosm_values['max_seats']) && intval($_REQUEST['seats']) > intval($pmprosm_values['max_seats']))
		{
			pmpro_setMessage(__("The maximum number of seats allowed is " . intval($pmprosm_values['max_seats']) . ".", "pmpro_sponsored_members"), "pmpro_error");
			return false;
		}
		elseif(isset($pmprosm_values['min_seats']) && intval($_REQUEST['seats']) < intval($pmprosm_values['min_seats']))
		{
			pmpro_setMessage(__("The minimum number of seats allowed is " . intval($pmprosm_values['min_seats']) . ".", "pmpro_sponsored_members"), "pmpro_error");
			return false;
		}
	}
	
	return $pmpro_continue_registration;
}
add_filter("pmpro_registration_checks", "pmprosm_pmpro_registration_checks");

// add parent account column to the discount codes table view
function pmprosm_pmpro_discountcodes_extra_cols_header()
{
	?>
	<th><?php _e("Parent Account", "pmpro_sponsored_members");?></th>
	<?php
}
add_action("pmpro_discountcodes_extra_cols_header", "pmprosm_pmpro_discountcodes_extra_cols_header");

function pmprosm_pmpro_discountcodes_extra_cols_body($code)
{
	$code_user_id = pmprosm_getCodeUserID($code->id);
	$code_user = get_userdata($code_user_id);
	?>
	<th><?php if(!empty($code_user_id) && !empty($code_user)) { ?><a href="<?php echo get_edit_user_link($code_user_id); ?>"><?php echo $code_user->user_login; ?></a><?php } elseif(!empty($code_user_id) && empty($code_user)) { ?><em>Missing User</em><?php } else { ?><?php } ?></th>
	<?php
}
add_action("pmpro_discountcodes_extra_cols_body", "pmprosm_pmpro_discountcodes_extra_cols_body");


//add user id field to discount code page.
function pmprosm_pmpro_discount_code_after_settings()
{	
	$code_id = intval($_REQUEST['edit']);
	
	if(!empty($_REQUEST['user_id']))
		$code_user_id = intval($_REQUEST['user_id']);
	elseif($code_id > -1)
		$code_user_id = pmprosm_getCodeUserID($code_id);
	else
		$code_user_id = "";
?>
<h3><?php _e("For Sponsored Accounts", "pmpro_sponsored_members");?></h3>
<table class="form-table">
<tbody>
<tr>
    <th scope="row" valign="top"><label for="user_id"><?php _e("User ID:", "pmpro_sponsored_members");?></label></th>
    <td>
		<input name="user_id" type="text" size="10" value="<?php if(!empty($code_user_id)) echo esc_attr($code_user_id);?>" />
		<small class="pmpro_lite"><?php _e("The user ID of the main account holder.", "pmpro_sponsored_members");?></small>
	</td>
</tr>
</tbody>
</table>
<?php
}
add_action("pmpro_discount_code_after_settings", "pmprosm_pmpro_discount_code_after_settings");

//save the code user id when saving a discount code
function pmprosm_pmpro_save_discount_code($code_id)
{
	//fix in case this is a new discount code (for PMPro versions < 1.7.1)
	if($code_id < 0)
	{
		global $wpdb;
		$code_id = $wpdb->get_var("SELECT id FROM $wpdb->pmpro_discount_codes ORDER BY id DESC LIMIT 1");		
	}
	
	if(!empty($code_id))
	{
		$code_user_id = intval($_REQUEST['user_id']);
		pmprosm_setCodeUserID($code_id, $code_user_id);
	}	
}
add_action("pmpro_save_discount_code", "pmprosm_pmpro_save_discount_code", 5);

//show existing sponsored accounts and add a dropdown to choose number of seats on checkout page
function pmprosm_pmpro_checkout_boxes()
{
	global $current_user, $pmpro_level, $pmpro_currency_symbol;

	//only for PMPROSM_MAIN_ACCOUNT_LEVEL
	if(empty($pmpro_level) || !pmprosm_isMainLevel($pmpro_level->id))
		return;

	//make sure options are defined for this
	$pmprosm_values = pmprosm_getValuesByMainLevel($pmpro_level->id);
		
	if(empty($pmprosm_values['max_seats']) || !isset($pmprosm_values['seat_cost']))
	{
		return;
	}
	
	//get seats from submit
	if(isset($_REQUEST['seats']))
		$seats = intval($_REQUEST['seats']);
	elseif(!empty($current_user->ID))
		$seats = get_user_meta($current_user->ID, "pmprosm_seats", true);
	else
		$seats = "";			
	?>
	<table id="pmpro_extra_seats" class="pmpro_checkout top1em" width="100%" cellpadding="0" cellspacing="0" border="0">
	<thead>
		<tr>
			<th><?php _e("Would you like to purchase extra seats?", "pmpro_sponsored_members");?></th>
		</tr>
	</thead>
	<tbody>		
		<tr>
			<td>
				<div>
					<label for="seats"><?php echo __("How many?", "pmpro_sponsored_members");?></label>
					<input type="text" id="seats" name="seats" value="<?php echo esc_attr($seats);?>" size="10" />
					<small>
						<?php							
							//min seats defaults to 1
							if(!empty($pmprosm_values['min_seats']))
								$min_seats = $pmprosm_values['min_seats'];
							else
								$min_seats = 1;

							if(isset($pmprosm_values['seat_cost_text']))
								printf(__("Enter a number from %d to %d. %s", "pmpro_sponsored_members"), $min_seats, $pmprosm_values['max_seats'], $pmprosm_values['seat_cost_text']);
							else
								printf(__("Enter a number from %d to %d. +%s per extra seat.", "pmpro_sponsored_members"), $min_seats, $pmprosm_values['max_seats'], $pmpro_currency_symbol . $pmprosm_values['seat_cost']);
						?>						
					</small>					
					
					<?php
					//adding sub accounts at checkout?
					if(!empty($pmprosm_values['sponsored_accounts_at_checkout']))
					{
						//look for existing sponsored accounts	
						$children = pmprosm_getChildren($current_user->ID);	
						if(!empty($children))
						{
							echo "<hr />";
							
							//get checkbox values if there
							if(isset($_REQUEST['old_sub_accounts_active']))
								$old_sub_accounts_active = $_REQUEST['old_sub_accounts_active'];
							else
								$old_sub_accounts_active = array();	
						
							$i = 0;
							foreach($children as $child_id) 
							{ 
							?>
							<div>
								<?php 
									//get user
									$child = get_userdata($child_id);
									
									//acive?
									if(pmpro_hasMembershipLevel(NULL, $child_id))
										$active = true;
									else
										$active = false;
										
									//checked?
									if(isset($old_sub_accounts_active[$i]))
										$checked = $old_sub_accounts_active[$i];
									else
										$checked = $active;
								?>
								<label><?php echo $child->display_name;?></label>
								<input type="checkbox" id="old_sub_accounts_active_<?php echo $i;?>" class="old_sub_accounts_active" name="old_sub_accounts_active[]" value="<?php echo $child_id;?>" <?php checked($checked, true);?> />
								<label class="pmpro_normal pmpro_clickable" for="old_sub_accounts_active_<?php echo $i;?>">
								<?php if(!empty($active)) { ?>
									<?php _e('Keep checked to keep this account active.', 'pmprosm'); ?>
								<?php } else { ?>
									<?php _e('Check to reactivate this account.', 'pmprosm'); ?>
								<?php } ?>
								</label>
							</div>
							<?php
									$i++;
							}
						}	//end existing sponsored accounts
						
						echo "<div id = 'sponsored_accounts'>";				
						
						if(!empty($_REQUEST['add_sub_accounts_username']))
							$child_usernames = $_REQUEST['add_sub_accounts_username'];
						elseif($seats)
							$child_usernames = array_fill(0, $seats, '');
						else
							$child_usernames = array();

						if(!empty($_REQUEST['add_sub_accounts_first_name']))
							$child_first_names = $_REQUEST['add_sub_accounts_first_name'];
						elseif($seats)
							$child_first_names = array_fill(0, $seats, '');
						else
							$child_first_names = array();
							
						if(!empty($_REQUEST['add_sub_accounts_last_name']))
							$child_last_names = $_REQUEST['add_sub_accounts_last_name'];
						elseif($seats)
							$child_last_names = array_fill(0, $seats, '');
						else
							$child_last_names = array();
							
						if(!empty($_REQUEST['add_sub_accounts_email']))
							$child_emails = $_REQUEST['add_sub_accounts_email'];
						elseif($seats)
							$child_emails = array_fill(0, $seats, '');
						else
							$child_emails = array();
						
						for($i = 0; $i < count($child_usernames); $i++)
						{
							if(is_array($child_usernames))
								$child_username = $child_usernames[$i];
							else
								$child_username = "";
							
							if(is_array($child_usernames))
								$child_first_name = $child_first_names[$i];
							else
								$child_first_name = "";
								
							if(is_array($child_usernames))
								$child_last_name = $child_last_names[$i];
							else
								$child_last_name = "";
								
							if(is_array($child_usernames))
								$child_email = $child_emails[$i];
							else
								$child_email = "";													
						?>										
						<div id="sponsored_account_<?php echo $i;?>">
							<hr />
							<?php if(!empty($pmprosm_values['children_get_name'])) { ?>
								<label><?php echo __("First Name", "pmpro_sponsored_members");?></label>
								<input type="text" name="add_sub_accounts_first_name[]" value="<?php echo esc_attr($child_first_name);?>" size="20" />
								<br>
								<label><?php echo __("Last Name", "pmpro_sponsored_members");?></label>
								<input type="text" name="add_sub_accounts_last_name[]" value="<?php echo esc_attr($child_last_name);?>" size="20" />
								<br>
							<?php } ?>
							<?php if(empty($pmprosm_values['children_hide_username'])) { ?>
								<label><?php echo __("Username", "pmpro_sponsored_members");?></label>
								<input type="text" name="add_sub_accounts_username[]" value="<?php echo esc_attr($child_username);?>" size="20" />
								<br>
							<?php } ?>
							<?php if(empty($pmprosm_values['children_hide_email'])) { ?>
								<label><?php echo __("Email", "pmpro_sponsored_members");?></label>
								<input type="text" name="add_sub_accounts_email[]" value="<?php echo esc_attr($child_email);?>" size="20" />
								<br>
							<?php } ?>
							<?php if(empty($pmprosm_values['children_hide_password'])) { ?>
								<label><?php echo __("Password", "pmpro_sponsored_members");?></label>
								<input type="password" name="add_sub_accounts_password[]" value="" size="20" />
							<?php } ?>
							<?php do_action('pmprosm_children_fields', $i, $seats);?>
						</div>
						<?php
						}									
					
						echo "</div>";
						
						/*
							Get the HTML for the empty extra fields and save it to a variable.
						*/
						ob_start();
						do_action("pmprosm_children_fields", false, $seats);
						$empty_child_fields = ob_get_contents();
						ob_end_clean();
						//also clean it up a bit
						$empty_child_fields = str_replace("\n", "", $empty_child_fields);
					}	//if(!empty($pmprosm_values['sponsored_accounts_at_checkout']))					
					?>
						
					<script>
					jQuery(document).ready(function() {
						var pmpro_base_level_is_free = <?php if(pmpro_isLevelFree($pmpro_level)) echo "true"; else echo "false";?>;
						var seat_cost = <?php echo intval($pmprosm_values['seat_cost']);?>;
						var min_seats = <?php if(!empty($pmprosm_values['min_seats'])) echo intval($pmprosm_values['min_seats']); else echo "0";?>;
						var max_seats = <?php if(!empty($pmprosm_values['max_seats'])) echo intval($pmprosm_values['max_seats']); else echo "false";?>;
						
						//update things when the # of seats changes
						jQuery('#seats, input.old_sub_accounts_active').bind("change", function() { 
							seatsChanged();
						});

						//run it once on load too
						seatsChanged();

						function seatsChanged()
						{
							//num seats entered
							seats = parseInt(jQuery('#seats').val());										

							//num of old seats checked
							old_sub_accounts_active = 0;
							jQuery("input.old_sub_accounts_active:checked").each(function(){
								old_sub_accounts_active += 1;
							});
														
							//max sure not over max
							if(max_seats && seats > max_seats)
							{
								seats = max_seats;
								jQuery('#seats').val(seats);
							}
							
							//and not under min
							if(min_seats && seats < min_seats)
							{
								seats = min_seats;
								jQuery('#seats').val(seats);
							}
							
							<?php
								//how many child seats are shown now (if sponsored_accounts_at_checkout is set)							
								if(!empty($pmprosm_values['sponsored_accounts_at_checkout']))		
								{
								?>
								if(jQuery('#sponsored_accounts'))
								{
									children = jQuery('#sponsored_accounts').children();											
									i = children.length-1;
														
									//how many should we show
									newseats = seats - old_sub_accounts_active;
														
									if(newseats < children.length)
									{
										while(i >= newseats)
										{
											jQuery(children[i]).remove();
											i--;
										}
									}
									else if(newseats > children.length)
									{	
										i = children.length;
										
										while (i < newseats)
										{																
											jQuery('#sponsored_accounts').append('<div id = "sponsored_account_'+i+'"><hr /><?php if(!empty($pmprosm_values["children_get_name"])) { ?><label>First Name</label><input type="text" name="add_sub_accounts_first_name[]" value="" size="20" /><br><label>Last Name</label><input type="text" name="add_sub_accounts_last_name[]" value="" size="20" /><br><?php } ?><?php if(empty($pmprosm_values["children_hide_username"])) { ?><label>Username</label><input type="text" name="add_sub_accounts_username[]" value="" size="20" /><br><?php } ?><label>Email</label><input type="text" name="add_sub_accounts_email[]" value"" size="20" /><br><label>Password</label><input type="password" name="add_sub_accounts_password[]" value="" size="20" /><?php echo $empty_child_fields;?></div>');
											i++;
										}
									}
								}
								<?php
								}
							?>
							
							if(pmpro_base_level_is_free && seat_cost && seats)
							{
								//need to show billing fields
								jQuery('#pmpro_payment_method').show();
								jQuery('#pmpro_billing_address_fields').show();
								jQuery('#pmpro_payment_information_fields').show();
							}
							else if(pmpro_base_level_is_free)
							{
								//need to hide billing fields
								jQuery('#pmpro_payment_method').hide();
								jQuery('#pmpro_billing_address_fields').hide();
								jQuery('#pmpro_payment_information_fields').hide();
							}
							
							<?php do_action('pmprosm_seats_changed_js'); ?>
						}
					});
					</script>					
				</div>
			</td>
		</tr>
	</tbody>
</table>
<?php
}
add_action("pmpro_checkout_boxes", "pmprosm_pmpro_checkout_boxes");

//adjust price based on seats
function pmprosm_pmpro_checkout_levels($level)
{	
	//get seats from submit
	if(isset($_REQUEST['seats']))
		$seats = intval($_REQUEST['seats']);
	else
		$seats = "";
		
	if(!empty($seats))
	{
		$pmprosm_values = pmprosm_getValuesByMainLevel($level->id);						
		if(!empty($pmprosm_values['seat_cost']))
		{		
			if((!isset($pmprosm_values['apply_seat_cost_to_initial_payment']) && $level->initial_payment > 0) || !empty($pmprosm_values['apply_seat_cost_to_initial_payment']))
			{
				if(!empty($pmprosm_values['apply_seat_cost_to_initial_payment']) && $pmprosm_values['apply_seat_cost_to_initial_payment'] === "sponsored_level")
				{
					$sponsored_level = pmpro_getLevel($pmprosm_values['sponsored_level_id']);
					$level->initial_payment += $sponsored_level->initial_payment * $seats;
				}
				else				
					$level->initial_payment += $pmprosm_values['seat_cost'] * $seats;					
			}
			
			if((!isset($pmprosm_values['apply_seat_cost_to_billing_amount']) && $level->billing_amount > 0) || !empty($pmprosm_values['apply_seat_cost_to_billing_amount']))
			{				
				if(!empty($pmprosm_values['apply_seat_cost_to_billing_amount']) && $pmprosm_values['apply_seat_cost_to_billing_amount'] === "sponsored_level")
				{
					$sponsored_level = pmpro_getLevel($pmprosm_values['sponsored_level_id']);
					$level->billing_amount += $sponsored_level->billing_amount * $seats;
					$level->cycle_number = $sponsored_level->cycle_number;
					$level->cycle_period = $sponsored_level->cycle_period;
				}
				else
				{				
					$level->billing_amount += $pmprosm_values['seat_cost'] * $seats;
					
					if(!empty($pmprosm_values['seat_cost_cycle_number']))
						$level->cycle_number = $pmprosm_values['seat_cost_cycle_number'];
					if(!empty($pmprosm_values['seat_cost_cycle_period']))
						$level->cycle_period = $pmprosm_values['seat_cost_cycle_period'];
				}
			}
		}
	}
	
	return $level;
}
add_filter("pmpro_checkout_level", "pmprosm_pmpro_checkout_levels");

//save seats at checkout
function pmprosm_pmpro_after_checkout($user_id)
{
	global $current_user, $pmprosm_sponsored_account_levels;
	//get seats from submit
	if(isset($_REQUEST['seats']))
		$seats = intval($_REQUEST['seats']);
	else
		$seats = "";

	update_user_meta($user_id, "pmprosm_seats", $seats);
	
	$parent_level = pmprosm_getValuesByMainLevel($_REQUEST['level']);
	
	if(!empty($parent_level['sponsored_accounts_at_checkout']))
	{
		//Create additional child member here
		if(!empty($_REQUEST['add_sub_accounts_username']))
			$child_username = $_REQUEST['add_sub_accounts_username'];
		else
			$child_username = array();
			
		if(!empty($_REQUEST['add_sub_accounts_first_name']))
			$child_first_name = $_REQUEST['add_sub_accounts_first_name'];
		else
			$child_first_name = array();
		
		if(!empty($_REQUEST['add_sub_accounts_last_name']))
			$child_last_name = $_REQUEST['add_sub_accounts_last_name'];
		else
			$child_last_name = array();
			
		$child_password = $_REQUEST['add_sub_accounts_password'];
		$child_email = $_REQUEST['add_sub_accounts_email'];
					
		$sponsored_code = pmprosm_getCodeByUserID($user_id);
		
		if($parent_level)
		{
			if(is_array($parent_level['sponsored_level_id']))
				$child_level_id = $parent_level['sponsored_level_id'][0];
			else
				$child_level_id = $parent_level['sponsored_level_id'];						
					
			//create new child accounts
			for($i = 0; $i < count($child_email); $i++)
			{
				//if a blank entry is find, skip it
				if(empty($child_email[$i]))
					   continue;
					   
				$child_user_id = wp_create_user( $child_username[$i], $child_password[$i], $child_email[$i]);
							
				//update first/last
				if(!empty($child_first_name[$i]))
					update_user_meta($child_user_id, "first_name", $child_first_name[$i]);
				if(!empty($child_last_name[$i]))
					update_user_meta($child_user_id, "last_name", $child_last_name[$i]);															

				if(pmprosm_changeMembershipLevelWithCode($child_level_id, $child_user_id, $sponsored_code))
				{
					pmprosm_addDiscountCodeUse($child_user_id, $child_level_id, $sponsored_code);		
				}
				
				//action after the child account is setup, user_id here is the parent user id
				do_action('pmprosm_after_child_created', $child_user_id, $user_id, $i);
			}		
		}
	}
}
add_action("pmpro_after_checkout", "pmprosm_pmpro_after_checkout");

//change a user's level and also set the code_id
function pmprosm_changeMembershipLevelWithCode($level_id, $user_id, $code_id)
{
	$child_level = pmpro_getLevel($level_id);
	
	//set the start date to NOW() but allow filters
	$startdate = apply_filters("pmpro_checkout_start_date", "NOW()", $user_id, $child_level);	
	
	$custom_level = array(
		'user_id' => $user_id,
		'membership_id' => $level_id,
		'code_id' => $code_id,
		'initial_payment' => $child_level->initial_payment,
		'billing_amount' => $child_level->billing_amount,
		'cycle_number' => $child_level->cycle_number,
		'cycle_period' => $child_level->cycle_period,
		'billing_limit' => $child_level->billing_limit,
		'trial_amount' => $child_level->trial_amount,
		'trial_limit' => $child_level->trial_limit,
		'startdate' => $startdate,
		'enddate' => ''
		);
		
	return pmpro_changeMembershipLevel($custom_level, $user_id);
}

//add a row to pmpro_discount_codes_uses with a blank order
function pmprosm_addDiscountCodeUse($user_id, $level_id, $code_id)
{
	global $wpdb;
	
	$user = get_userdata($user_id);
	
	//add blank order
	$morder = new MemberOrder();						
	$morder->InitialPayment = 0;	
	$morder->Email = $user->user_email;
	$morder->gateway = "free";	//sponsored

	$morder->user_id = $user_id;
	$morder->membership_id = $level_id;					
	$morder->saveOrder();

	if(!empty($morder->id))
		$code_order_id = $morder->id;
	else
		$code_order_id = "";

	global $wpdb;
	$wpdb->query("INSERT INTO $wpdb->pmpro_discount_codes_uses (code_id, user_id, order_id, timestamp) VALUES('" . esc_sql($code_id) . "', '" . esc_sql($user_id) . "', '" . intval($code_order_id) . "', now())");
}

//remove a row from pmpro_discount_code_uses
function pmprosm_removeDiscountCodeUse($user_id, $code_id)
{
	global $wpdb;
	
	$wpdb->query("DELETE FROM $wpdb->pmpro_discount_codes_uses WHERE user_id = '" . esc_sql($user_id) . "' AND code_id = '" . esc_sql($code_id) . "'");
}

function pmprosm_pmpro_registration_checks_sponsored_accounts($okay)
{
	global $pmpro_msg, $pmpro_msgt;
	
	//only if we're adding accounts at checkout
	$pmprosm_values = pmprosm_getValuesByMainLevel($_REQUEST['level']);	
	if(empty($pmprosm_values['sponsored_accounts_at_checkout']))
		return $okay;
	
	//get number of old accounts to test later
	if(!empty($_REQUEST['old_sub_accounts_active']))
		$num_old_accounts = count($_REQUEST['old_sub_accounts_active']);
	else
		$num_old_accounts = 0;
	
	//get seats
	if(!empty($_REQUEST['seats']))
		$seats = intval($_REQUEST['seats']);
	else
		$seats = 0;
	
	//how many new accounts?
	$num_new_accounts = $seats - $num_old_accounts;
	
	//get account values
	if(!empty($_REQUEST['add_sub_accounts_username']))
		$child_usernames = $_REQUEST['add_sub_accounts_username'];
	else
		$child_usernames = array();
	
	if(!empty($_REQUEST['add_sub_accounts_first_name']))
		$child_first_names = $_REQUEST['add_sub_accounts_first_name'];
	else
		$child_first_names = array();
		
	if(!empty($_REQUEST['add_sub_accounts_last_name']))
		$child_last_names = $_REQUEST['add_sub_accounts_last_name'];
	else
		$child_last_names = array();
	
	if(!empty($_REQUEST['add_sub_accounts_email']))
		$child_emails = $_REQUEST['add_sub_accounts_email'];
	else
		$child_emails = array();
		
	if(!empty($_REQUEST['add_sub_accounts_password']))
		$child_passwords = $_REQUEST['add_sub_accounts_password'];
	else
		$child_passwords = array();
	
	//check that these emails and usernames are unique
	$unique_usernames = array_unique(array_filter($child_usernames));
	$unique_emails = array_unique(array_filter($child_emails));
	$passwords = array_filter($child_passwords);
	
	if($num_new_accounts > 0 && (count($unique_usernames) < $num_new_accounts || count($unique_emails) < $num_new_accounts || count($passwords) < $num_new_accounts))
	{
		pmpro_setMessage(__("Please enter details for each new sponsored account."),"pmpro_error");
		$okay = false;
	}
	elseif(count($unique_usernames) != count($child_usernames) || count($unique_emails) != count($child_emails))
	{		
		pmpro_setMessage(__("Each sponsored account must have a unique username and email address."),"pmpro_error");
		$okay = false;
	}
	elseif(count($child_emails) + $num_old_accounts > $seats)
	{
		pmpro_setMessage(__("You have more accounts checked than you are purchasing seats. Increase the number of seats or deactivate some accounts."),"pmpro_error");
		$okay = false;
	}
	else
	{	
		foreach($child_usernames as $child_username)
		{
			//if registering child username or email already exisits the create an error.
			if(username_exists($child_username))
			{
					$pmpro_msg = "The username <b>".$child_username."</b> already exists. Please select a different username";
					$pmpro_msgt = "pmpro_error";
					pmpro_setMessage($pmpro_msg,"pmpro_error");
					$okay = false;
			}
		}
		
		foreach($child_emails as $child_email)
		{
			if(email_exists($child_email))
			{
				$pmpro_msg = "That email <b>".$child_email."</b> already exists. Please select a different email";
				$pmpro_msgt = "pmpro_error";
				pmpro_setMessage($pmpro_msg,"pmpro_error");
				
				$okay = false;	
			}
			elseif(!is_email($child_email))
			{
				$pmpro_msg = "<b>".$child_email."</b> is not a valid email address. Please select a different email";
				$pmpro_msgt = "pmpro_error";
				pmpro_setMessage($pmpro_msg,"pmpro_error");
				
				$okay = false;	
			}
		}
	}
	
	return $okay;
}
add_action('pmpro_registration_checks', 'pmprosm_pmpro_registration_checks_sponsored_accounts');

//add code and seats fields to profile for admins
function pmprosm_profile_fields_seats($user)
{
	global $wpdb;

	if(current_user_can("manage_options"))
	{
	?>
		<h3><?php _e("Sponsored Seats"); ?></h3>
		<table class="form-table">
		<?php
			$sponsor_code_id = pmprosm_getCodeByUserID($user->ID);
			if(!empty($sponsor_code_id))
			{
		?>
		<tr>
			<th><label for="sponsor_code"><?php _e("Sponsor Code"); ?></label></th>
			<td>
				<?php echo $wpdb->get_var("SELECT code FROM $wpdb->pmpro_discount_codes WHERE id = '" . $sponsor_code_id . "' LIMIT 1");?>
			</td>
		</tr>
		<?php
			}
		?>
		<tr>
			<th><label for="seats"><?php _e("Seats"); ?></label></th>
			<td>
				<?php
					$seats = intval(get_user_meta($user->ID, "pmprosm_seats", true));					
				?>
				<input type="text" id="seats" name="seats" size="5" value="<?php echo esc_attr($seats);?>" />

				
			</td>
		</tr>
		</table>
	<?php
	}
}
add_action('show_user_profile', 'pmprosm_profile_fields_seats');
add_action('edit_user_profile', 'pmprosm_profile_fields_seats');

//save seats on profile save
function pmprosm_profile_update_seats($user_id)
{
	//make sure they can edit
	if ( !current_user_can( 'edit_user', $user_id ) )
		return false;

	//only let admin's edit the seats
	if(current_user_can("manage_options") && isset($_POST['seats']))	
	{
		//update user meta
		update_user_meta( $user_id, "pmprosm_seats", intval($_POST['seats']) );			
		
		//update code
		global $wpdb;
		$code_id = pmprosm_getCodeByUserID($user_id);
		$sqlQuery = "UPDATE $wpdb->pmpro_discount_codes SET uses = '" . intval($_POST['seats']) . "' WHERE id = '" . $code_id . "' LIMIT 1";
		$wpdb->query($sqlQuery);
	}
}
add_action('profile_update', 'pmprosm_profile_update_seats');

/*
	Show seats on the account page and show if they have been claimed.
*/
function pmprosm_the_content_account_page($content)
{
	global $post, $pmpro_pages, $current_user, $wpdb;
			
	if(!is_admin() && $post->ID == $pmpro_pages['account'])
	{
		//what's their code?
		$code_id = pmprosm_getCodeByUserID($current_user->ID);
		$pmprosm_values = pmprosm_getValuesByMainLevel($current_user->membership_level->ID);
		
		if(!empty($code_id) && !empty($pmprosm_values))
		{			
			$code = $wpdb->get_row("SELECT * FROM $wpdb->pmpro_discount_codes WHERE id = '" . esc_sql($code_id) . "' LIMIT 1");
			
			if(!is_array($pmprosm_values['sponsored_level_id']))
				$sponsored_level_ids = array($pmprosm_values['sponsored_level_id']);
			else
				$sponsored_level_ids = $pmprosm_values['sponsored_level_id'];	
			
			//no sponsored levels to use codes for
			if(empty($sponsored_level_ids) || empty($sponsored_level_ids[0]))
				return $content;
			
			//no uses for this code
			if(empty($code->uses))
				return $content;
			
			$code_urls = array();
			$pmpro_levels = pmpro_getAllLevels(false, true);
			foreach($sponsored_level_ids as $sponsored_level_id)
			{
				$level_name = $pmpro_levels[$sponsored_level_id]->name;
				$code_urls[] = array("name"=>$level_name, "url"=>pmpro_url("checkout", "?level=" . $sponsored_level_id . "&discount_code=" . $code->code));
			}
					
			ob_start();
			
			$limit = 1;
			if(isset($pmprosm_values['max_seats']))
				$limit = $pmprosm_values['max_seats'];
			
			//get members
			$member_ids = pmprosm_getChildren($current_user->ID);
			?>
			<div id="pmpro_account-sponsored" class="pmpro_box">	
				 
				<h3><?php _e("Sponsored Members", "pmpro_sponsored_members");?></h3>
				
				<p><?php printf(__("Give this code to your sponsored members to use at checkout: <strong>%s</strong></p>", "pmpro_sponsored_members"), $code->code);?>
				<?php if(count($code_urls) > 1) { ?>
					<p><?php _e("Or provide one of these direct links to register:", "pmpro_sponsored_members");?></p>
				<?php } else { ?>
					<p><?php _e("Or provide this direct link to register:", "pmpro_sponsored_members");?></p>
				<?php } ?>
				
				<ul>
					<?php foreach($code_urls as $code_url) { ?>
						<li><?php echo $code_url['name'];?>: <strong><a target="_blank" href="<?php echo $code_url['url'];?>"><?php echo $code_url['url'];?></a></strong></li>
					<?php } ?>
				</ul>
				
				<p>
					<?php if(empty($code->uses)) { ?>
						<?php _e("This code has unlimited uses.", "pmpro_sponsored_members");?>
					<?php } else { ?>
						<?php printf(__("%s/%s uses.", "pmpro_sponsored_members"), count($member_ids), $code->uses);?>
					<?php } ?>
				</p>
				
				<?php if(!empty($member_ids)) { ?>
				<p><strong><?php __("Your Sponsored Members", "pmpro_sponsored_members");?></strong></p>
				<ul>
				<?php
					
					foreach($member_ids as $member_id)
					{
						$member = get_userdata($member_id);
						if(empty($member))
							continue;
						?>
						<li><?php echo $member->display_name;?></li>
						<?php
					}
				?>
				</ul>
				<?php } ?>
			</div> <!-- end pmpro_account-sponsored -->
			<?php
			
			$temp_content = ob_get_contents();
			ob_end_clean();
					
			$content = str_replace('<!-- end pmpro_account-profile -->', '<!-- end pmpro_account-profile -->' . $temp_content, $content);
		}			
	}
	
	return $content;
}
add_filter("the_content", "pmprosm_the_content_account_page", 30);

/*
	Get a user's sponsoring member.
*/
global $pmprosm_user_sponsors;
function pmprosm_getSponsor($user_id, $force = false)
{
	global $wpdb, $pmprosm_user_sponsors;
	
	if(!empty($pmprosm_user_sponsors[$user_id]) && !$force)
		return $pmprosm_user_sponsors[$user_id];
	
	//make sure this user has one of the sponsored levels
	$user_level = pmpro_getMembershipLevelForUser($user_id);	
	if(!pmprosm_isSponsoredLevel($user_level->id))
	{
		$pmprosm_user_sponsors[$user_id] = false;
		return $pmprosm_user_sponsors[$user_id];
	}
	
	//what code did this user_id sign up for?
	$sqlQuery = "SELECT code_id FROM $wpdb->pmpro_discount_codes_uses WHERE user_id = '" . $user_id . "' ORDER BY id DESC";
	$code_id = $wpdb->get_var($sqlQuery);
	
	//found a code?
	if(empty($code_id))
	{
		$pmprosm_user_sponsors[$user_id] = false;
		return $pmprosm_user_sponsors[$user_id];
	}
		
	//okay find sponsor
	$sponsor_user_id = pmprosm_getUserByCodeID($code_id);
	
	$pmprosm_user_sponsors[$user_id] = get_userdata($sponsor_user_id);
	return $pmprosm_user_sponsors[$user_id];
}

/*
	Add code to confirmation email.
*/
function pmprosm_pmpro_email_body($body, $pmpro_email)
{
	global $wpdb, $pmprosm_sponsored_account_levels;
 
	//only checkout emails, not admins
	if(strpos($pmpro_email->template, "checkout") !== false && strpos($pmpro_email->template, "admin") == false)
	{ 
		//get the user_id from the email
		$user_id = $wpdb->get_var("SELECT ID FROM $wpdb->users WHERE user_email = '" . $pmpro_email->data['user_email'] . "' LIMIT 1");
		$level_id = $pmpro_email->data['membership_id'];
		$code_id = pmprosm_getCodeByUserID($user_id);		
		
		if(!empty($user_id) && !empty($code_id) && pmprosm_isMainLevel($level_id))
		{
			//get code
			$code = $wpdb->get_row("SELECT * FROM $wpdb->pmpro_discount_codes WHERE id = '" . esc_sql($code_id) . "' LIMIT 1");
			
			//get sponsored levels
			$pmprosm_values = pmprosm_getValuesByMainLevel($level_id);
			if(!is_array($pmprosm_values['sponsored_level_id']))
				$sponsored_level_ids = array($pmprosm_values['sponsored_level_id']);
			else
				$sponsored_level_ids = $pmprosm_values['sponsored_level_id'];	
			
			//no sponsored levels to use codes for
			if(empty($sponsored_level_ids) || empty($sponsored_level_ids[0]))
				return $body;
			
			//no uses for this code
			if(empty($code->uses))
				return $body;
			
			//check if we should update confirmation email
			if(isset($pmprosm_values['add_code_to_confirmation_email']) && $pmprosm_values['add_code_to_confirmation_email'] === false)
				return $body;
			
			//figure out urls for code
			$code_urls = array();
			$pmpro_levels = pmpro_getAllLevels(true, true);
			foreach($sponsored_level_ids as $sponsored_level_id)
			{
				$level_name = $pmpro_levels[$sponsored_level_id]->name;
				$code_urls[] = array("name"=>$level_name, "url"=>pmpro_url("checkout", "?level=" . $sponsored_level_id . "&discount_code=" . $code->code));
			}

			//build message
			$message = "<p>" . sprintf(__("Give this code to your sponsored members to use at checkout: %s", "pmpro_sponsored_members"), $code->code) . "<br />";
			
			if(count($code_urls) > 1) 
				$message .= __("Or provide one of these direct links to register:", "pmpro_sponsored_members") . "</p>";
			else
				$message .= __("Or provide this direct link to register:", "pmpro_sponsored_members") . "</p>";
				
			$message .= "<ul>";
			foreach($code_urls as $code_url) { 
				$message .= "<li>" . $code_url['name'] . ": <strong>" . $code_url['url'] . "</strong></li>";
			}
			$message .= "</ul>";
			
			$body = $message . "<hr />" . $body;
		}
	}
 
	return $body;
}
add_filter("pmpro_email_body", "pmprosm_pmpro_email_body", 10, 2);
