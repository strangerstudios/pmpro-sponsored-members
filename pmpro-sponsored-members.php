<?php
/*
Plugin Name: PMPro Sponsored Members
Plugin URI: http://www.paidmembershipspro.com/add-ons/pmpro-sponsored-members/
Description: Generate discount code for a main account holder to distribute to sponsored members.
Version: .1
Author: Stranger Studios
Author URI: http://www.strangerstudios.com
*/

/*
	Set these to the ids of your main and sponsored levels. 	
*/
define('PMPROSM_MAIN_ACCOUNT_LEVEL', 1);
define('PMPROSM_SPONSORED_ACCOUNT_LEVEL', 2);

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
	}
	elseif($level_id == PMPROSM_MAIN_ACCOUNT_LEVEL)
	{
		//check if this user already has a discount code
		$code_id = pmprosm_getCodeByUserID($user_id);
		
		if(empty($code_id))
		{
			//generate a new code. change these values if you want.
			$code = "S" . pmpro_getDiscountCode();
			$starts = date("Y-m-d");
			$expires = date("Y-m-d", strtotime("+1 year"));
			$uses = "";
			$sqlQuery = "INSERT INTO $wpdb->pmpro_discount_codes (code, starts, expires, uses) VALUES('" . $wpdb->escape($code) . "', '" . $starts . "', '" . $expires . "', '" . intval($uses) . "')";
			
			if($wpdb->query($sqlQuery) !== false)
			{
				$code_id = $wpdb->insert_id;
				
				//okay update level
				if(PMPROSM_SPONSORED_ACCOUNT_LEVEL > 0)
				{
					$sqlQuery = "INSERT INTO $wpdb->pmpro_discount_codes_levels (code_id, level_id, initial_payment, billing_amount, cycle_number, cycle_period, billing_limit, trial_amount, trial_limit, expiration_number, expiration_period) VALUES('" . $wpdb->escape($code_id) . "', '" . $wpdb->escape(PMPROSM_SPONSORED_ACCOUNT_LEVEL) . "', '0', '0', '0', 'Month', '0', '0', '0', '0', 'Month')";
					$wpdb->query($sqlQuery);
					
					pmprosm_setCodeUserID($code_id, $user_id);
				}
			}
		}	
		elseif(PMPROSM_SPONSORED_ACCOUNT_LEVEL > 0)
		{
			//see if we should enable some accounts
			$sqlQuery = "SELECT user_id FROM $wpdb->pmpro_discount_codes_uses WHERE code_id = '" . $code_id . "'";
			$sub_user_ids = $wpdb->get_col($sqlQuery);
			if(!empty($sub_user_ids))
			{
				foreach($sub_user_ids as $sub_user_id)
				{
					//change their membership
					pmpro_changeMembershipLevel(PMPROSM_SPONSORED_ACCOUNT_LEVEL, $sub_user_id);
				}
			}
		}
	}
}
add_action("pmpro_after_change_membership_level", "pmprosm_pmpro_after_change_membership_level", 10, 2);

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

//show a user's discount code on the account page
function pmprosm_the_content_account_page($content)
{
	global $post, $pmpro_pages, $current_user, $wpdb;
			
	if(!is_admin() && $post->ID == $pmpro_pages['account'])
	{
		$code_id = pmprosm_getCodeByUserID($current_user->ID);
				
		if(!empty($code_id))
			$code = $wpdb->get_var("SELECT code FROM $wpdb->pmpro_discount_codes WHERE id = '" . $wpdb->escape($code_id) . "' LIMIT 1");
				
		if(!empty($code))
			$content = "<p>Give this code for your sponsors to use at checkout: <strong>" . $code . "</strong></p>" . $content;
	}
	
	return $content;
}
add_filter("the_content", "pmprosm_the_content_account_page");

//show a user's discount code on the confirmation page
function pmprosm_pmpro_confirmation_message($message)
{
	global $current_user, $wpdb;
	
	$code_id = pmprosm_getCodeByUserID($current_user->ID);
				
	if(!empty($code_id))
		$code = $wpdb->get_var("SELECT code FROM $wpdb->pmpro_discount_codes WHERE id = '" . $wpdb->escape($code_id) . "' LIMIT 1");
			
	if(!empty($code))
		$message .= "<p>Give this code for your sponsors to use at checkout: <strong>" . $code . "</strong></p>";
	
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
	if($pmpro_level->id == PMPROSM_SPONSORED_ACCOUNT_LEVEL && empty($discount_code))
	{
		pmpro_setMessage("You must use a valid discount code to register for this level.", "pmpro_error");
		return false;
	}
		
	//if a discount code is being used, check that the main account is active
	if($pmpro_level->id == PMPROSM_SPONSORED_ACCOUNT_LEVEL && !empty($discount_code))
	{
		$code_id = $wpdb->get_var("SELECT id FROM $wpdb->pmpro_discount_codes WHERE code = '" . $wpdb->escape($discount_code) . "' LIMIT 1");
		if(!empty($code_id))
		{
			$code_user_id = pmprosm_getCodeUserID($code_id);
						
			if(!pmpro_hasMembershipLevel(PMPROSM_MAIN_ACCOUNT_LEVEL, $code_user_id))
			{
				pmpro_setMessage("The sponsor for this code is inactive. Ask them to renew their account.", "pmpro_error");
				return false;
			}
		}
	}
	
	return $pmpro_continue_registration;
}
add_filter("pmpro_registration_checks", "pmprosm_pmpro_registration_checks");

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