<?php
/*
Plugin Name: PMPro Sponsored Members
Plugin URI: http://www.paidmembershipspro.com/add-ons/pmpro-sponsored-members/
Description: Generate discount code for a main account holder to distribute to sponsored members.
Version: .3.4
Author: Stranger Studios
Author URI: http://www.strangerstudios.com
*/

/*
	Set these to the ids of your main and sponsored levels. 	
	
	Now using a global array so you can have multiple main and sponsored levels.	
	Array keys should be the main account level.
		
	array(
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
	)
*/
global $pmprosm_sponsored_account_levels;
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
		if(is_array($values['']))
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
	return $pmprosm_sponsored_account_levels[$level_id];
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
		
	//see if we should enable some accounts
	$sqlQuery = "SELECT user_id FROM $wpdb->pmpro_discount_codes_uses WHERE code_id = '" . $code_id . "'";
	$sub_user_ids = $wpdb->get_col($sqlQuery);
		
	if(!empty($sub_user_ids))
	{
		//check if they have enough seats				
		if($seats >= count($sub_user_ids))
		{				
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
						pmpro_changeMembershipLevel($last_level_id, $sub_user_id);
				}
				else				
					pmpro_changeMembershipLevel($pmprosm_values['sponsored_level_id'], $sub_user_id);
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
				set_transient("pmprosm_error", "This user has fewer seats than they had sponsored accounts. The sponsored accounts have been deactivated. The user must have his sponsored accounts checkout again using the code: " . $code . ".");
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
	
	$message .= "<p><strong>Notice:</strong>Your current membership has fewer seats than you had sponsored accounts. The accounts have been deactivated. You must have your sponsored accounts checkout again using your code: " . $code . ".</p>";
	
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
		
		$pmpro_levels = pmpro_getAllLevels();
		
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
			$message .= "<div class=\"pmpro_content_message\"><p>Give this code to your sponsored members to use at checkout: <strong>" . $code->code . "</strong></p><p>Or provide one of these direct links to register:<br /></p>";
		else
			$message .= "<div class=\"pmpro_content_message\"><p>Give this code to your sponsored members to use at checkout: <strong>" . $code->code . "</strong></p><p>Or provide this direct link to register:<br /></p>";
			
		$message .= "<ul>";
			foreach($code_urls as $code_url)
				$message .= "<li>" . $code_url['name'] . ":<strong> " . $code_url['url'] . "</strong></li>";
		$message .= "</ul>";
		
		if(empty($code->uses))
			$message .= "This code has unlimited uses.";
		else
			$message .= "This code has " . $code->uses . " uses.";
		
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
	if(pmprosm_isSponsoredLevel($pmpro_level->id) && empty($discount_code))
	{
		pmpro_setMessage("You must use a valid discount code to register for this level.", "pmpro_error");
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
				pmpro_setMessage("The sponsor for this code is inactive. Ask them to renew their account.", "pmpro_error");
				return false;
			}
		}
	}
	
	return $pmpro_continue_registration;
}
add_filter("pmpro_registration_checks", "pmprosm_pmpro_registration_checks");

// add parent account column to the discount codes table view
function pmprosm_pmpro_discountcodes_extra_cols_header()
{
	?>
	<th>Parent Account</th>
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
<h3>For Sponsored Accounts</h3>
<table class="form-table">
<tbody>
<tr>
    <th scope="row" valign="top"><label for="user_id">User ID:</label></th>
    <td>
		<input name="user_id" type="text" size="10" value="<?php if(!empty($code_user_id)) echo esc_attr($code_user_id);?>" />
		<small class="pmpro_lite">The user ID of the main account holder.</small>
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

//add a dropdown to choose number of seats on checkout page
function pmprosm_pmpro_checkout_boxes()
{
	global $current_user, $pmpro_level, $pmpro_currency_symbol;
		
	//only for PMPROSM_MAIN_ACCOUNT_LEVEL
	if(empty($pmpro_level) || !pmprosm_isMainLevel($pmpro_level->id))
		return;
		
	//make sure options are defined for this
	$pmprosm_values = pmprosm_getValuesByMainLevel($pmpro_level->id);
		
	if(empty($pmprosm_values['max_seats']) || empty($pmprosm_values['seat_cost']))
		return;
		
	//get seats from submit
	if(isset($_REQUEST['seats']))
		$seats = intval($_REQUEST['seats']);
	elseif(!empty($current_user->ID))
		$seats = get_user_meta($current_user->ID, "pmprosm_seats", true);
	else
		$seats = "";			
?>
<table id="pmpro_payment_method" class="pmpro_checkout top1em" width="100%" cellpadding="0" cellspacing="0" border="0">
	<thead>
		<tr>
			<th>Would you like to purchase extra seats?</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td>
				<div>
					<label for="seats">How many?</label>
					<input type="text" id="seats" name="seats" value="<?php echo esc_attr($seats);?>" size="10" />
					<small>Enter a number from 1 to <?php echo $pmprosm_values['max_seats'];?>. +<?php echo $pmpro_currency_symbol . $pmprosm_values['seat_cost'];?> per extra seat.</small>
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
			if($level->initial_payment > 0)
				$level->initial_payment += $pmprosm_values['seat_cost'] * $seats;
			if($level->billing_amount > 0)
				$level->billing_amount += $pmprosm_values['seat_cost'] * $seats;
		}
	}

	return $level;
}
add_filter("pmpro_checkout_level", "pmprosm_pmpro_checkout_levels");

//save seats at checkout
function pmprosm_pmpro_after_checkout($user_id)
{
	//get seats from submit
	if(isset($_REQUEST['seats']))
		$seats = intval($_REQUEST['seats']);
	else
		$seats = "";

	update_user_meta($user_id, "pmprosm_seats", $seats);
}
add_action("pmpro_after_checkout", "pmprosm_pmpro_after_checkout");

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
		$code_id = pmprosm_getCodeByUserID($current_user->id);
		$pmprosm_values = pmprosm_getValuesByMainLevel($current_user->membership_level->ID);
		
		if(!empty($code_id) && !empty($pmprosm_values))
		{			
			$code = $wpdb->get_row("SELECT * FROM $wpdb->pmpro_discount_codes WHERE id = '" . esc_sql($code_id) . "' LIMIT 1");
			
			if(!is_array($pmprosm_values['sponsored_level_id']))
				$sponsored_level_ids = array($pmprosm_values['sponsored_level_id']);
			else
				$sponsored_level_ids = $pmprosm_values['sponsored_level_id'];	
			
			$code_urls = array();
			$pmpro_levels = pmpro_getAllLevels();
			foreach($sponsored_level_ids as $sponsored_level_id)
			{
				$level_name = $pmpro_levels[$sponsored_level_id]->name;
				$code_urls[] = array("name"=>$level_name, "url"=>pmpro_url("checkout", "?level=" . $sponsored_level_id . "&discount_code=" . $code->code));
			}
					
			ob_start();
			//get members
			$member_ids = $wpdb->get_col("SELECT user_id FROM $wpdb->pmpro_discount_codes_uses WHERE code_id = '" . intval($code_id) . "' LIMIT 1");
			?>
			<div id="pmpro_account-sponsored" class="pmpro_box">	
				 
				<h3>Sponsored Members</h3>
				
				<p>Give this code to your sponsored members to use at checkout: <strong><?php echo $code->code;?></strong></p>
				<?php if(count($code_urls) > 1) { ?>
					<p>Or provide one of these direct links to register:</p>
				<?php } else { ?>
					<p>Or provide this direct link to register:</p>
				<?php } ?>
				
				<ul>
					<?php foreach($code_urls as $code_url) { ?>
						<li><?php echo $code_url['name'];?>: <strong><a target="_blank" href="<?php echo $code_url['url'];?>"><?php echo $code_url['url'];?></a></strong></li>
					<?php } ?>
				</ul>
				
				<p>
					<?php if(empty($code->uses)) { ?>
						This code has unlimited uses.
					<?php } else { ?>
						<?php echo count($member_ids);?>/<?php echo $code->uses;?> used.
					<?php } ?>
				</p>
				
				<?php if(!empty($member_ids)) { ?>
				<p><strong>Your Sponsored Members</strong></p>
				<ul>
				<?php
					
					foreach($member_ids as $member_id)
					{
						$member = get_userdata($member_id);
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
function pmprosm_getSponsor($user_id)
{
	global $wpdb;
	
	//make sure this user has one of the sponsored levels
	$user_level = pmpro_getMembershipLevelForUser($user_id);	
	if(!pmprosm_isSponsoredLevel($user_level->id))
		return false;
	
	//what code did this user_id sign up for?
	$sqlQuery = "SELECT code_id FROM $wpdb->pmpro_discount_codes_uses WHERE user_id = '" . $user_id . "' ORDER BY id DESC";
	$code_id = $wpdb->get_var($sqlQuery);
	
	//found a code?
	if(empty($code_id))
		return false;
		
	//okay find sponsor
	$sponsor_user_id = pmprosm_getUserByCodeID($code_id);
	
	return get_userdata($sponsor_user_id);
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
			$code = $wpdb->get_row("SELECT * FROM $wpdb->pmpro_discount_codes WHERE id = '" . $wpdb->escape($code_id) . "' LIMIT 1");
			
			//get sponsored levels
			$pmprosm_values = pmprosm_getValuesByMainLevel($level_id);
			if(!is_array($pmprosm_values['sponsored_level_id']))
				$sponsored_level_ids = array($pmprosm_values['sponsored_level_id']);
			else
				$sponsored_level_ids = $pmprosm_values['sponsored_level_id'];	
			
			//figure out urls for code
			$code_urls = array();
			$pmpro_levels = pmpro_getAllLevels();
			foreach($sponsored_level_ids as $sponsored_level_id)
			{
				$level_name = $pmpro_levels[$sponsored_level_id]->name;
				$code_urls[] = array("name"=>$level_name, "url"=>pmpro_url("checkout", "?level=" . $sponsored_level_id . "&discount_code=" . $code->code));
			}

			//build message
			$message = "<p>Give this code to your sponsored members to use at checkout: " . $code->code . "<br />";
			
			if(count($code_urls) > 1) 
				$message .= "Or provide one of these direct links to register:</p>";
			else
				$message .= "Or provide this direct link to register:</p>";
				
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
