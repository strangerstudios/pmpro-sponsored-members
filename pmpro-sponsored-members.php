<?php
/*
Plugin Name: Paid Memberships Pro - Sponsored Members Add On
Plugin URI: https://www.paidmembershipspro.com/add-ons/pmpro-sponsored-members/
Description: Generate discount code for a main account holder to distribute to sponsored members.
Version: .7
Author: Paid Memberships Pro
Author URI: https://www.paidmembershipspro.com
Text Domain: pmpro-sponsored-members
Domain Path: /languages
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

function pmprosm_load_textdomain() {
	load_plugin_textdomain( 'pmpro-sponsored-members', false, basename( dirname( __FILE__ ) ) . '/languages' ); 
  }
  add_action( 'init', 'pmprosm_load_textdomain' );

//includes
if(is_admin())
	require_once(dirname(__FILE__) . '/includes/import-users-from-csv.php');

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
function pmprosm_getValuesBySponsoredLevel($level_id, $first = true)
{
	global $pmprosm_sponsored_account_levels;
	
	$pmprosm_sponsored_account_values = array();
	
	foreach($pmprosm_sponsored_account_levels as $key => $values)
	{
		if(is_array($values['sponsored_level_id']))
		{
			if(in_array($level_id, $values['sponsored_level_id']) && $first)
				return $pmprosm_sponsored_account_levels[$key];

			else
			{
				$pmprosm_sponsored_account_values[] = $pmprosm_sponsored_account_levels[$key];
			}
		}
		else
		{
			if($values['sponsored_level_id'] == $level_id && $first)
				return $pmprosm_sponsored_account_levels[$key];
			
			else	
			{
				$pmprosm_sponsored_account_values[] = $pmprosm_sponsored_account_levels[$key];
				
			}
		}
	}
	
	return $pmprosm_sponsored_account_values;
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
		
		//make sure the code is still around
		if($code_id)
		{
			$code_exists = $wpdb->get_var("SELECT id FROM $wpdb->pmpro_discount_codes WHERE id = '" . $code_id . "' LIMIT 1");
			if(!$code_exists)
				$code_id = false;
		}
		
		//no code, make one
		if(empty($code_id))
		{
			//if seats cost money and there are no seats, just return
			if(!empty($pmprosm_values['seat_cost']) && empty($_REQUEST['seats']))
				return;
			
			//check for seats
			if(isset($_REQUEST['seats']))
				$uses = intval($_REQUEST['seats']);
			elseif(isset($_SESSION['seats']))
				$uses = intval($_SESSION['seats']);
			elseif(!empty($pmprosm_values['seats']))
				$uses = $pmprosm_values['seats'];
			elseif(!empty($pmprosm_values['min_seats']))
				$uses = $pmprosm_values['min_seats'];
			else
				$uses = "";

			//create a new code
			pmprosm_createSponsorCode($user_id, $level_id, $uses);
			
			//make sure seats is correct in user meta
			update_user_meta($user_id, "pmprosm_seats", $uses);
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
	Create a new sponsor discount code.
*/
function pmprosm_createSponsorCode($user_id, $level_id, $uses = "") {
	global $wpdb;
	
	//get values for this sponsorship
	$pmprosm_values = pmprosm_getValuesByMainLevel($level_id);
	
	//generate a new code. change these values if you want.
	if(version_compare(PMPRO_VERSION, "1.7.5") > 0)
		$code = "S" . pmpro_getDiscountCode($user_id); 	//seed parameter added in version 1.7.6
	else
		$code = "S" . pmpro_getDiscountCode();
	$starts = date("Y-m-d", current_time("timestamp"));
	$expires = date("Y-m-d", strtotime("+1 year", current_time("timestamp")));
			
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
			//default values for discount code; everything free
			$discount_code = array(
				'code_id'=>esc_sql($code_id),
				'level_id'=>esc_sql($sponsored_level),
				'initial_payment'=>'0',
				'billing_amount'=>'0',
				'cycle_number'=>'0',
				'cycle_period'=>"'Month'",
				'billing_limit'=>'0',
				'trial_amount'=>'0',
				'trial_limit'=>'0',
				'expiration_number'=>'0',
				'expiration_period' => "'Month'"
			);

			//allow override of the discount code values by setting it in the pmprosm_sponsored_account_levels array
			if(!empty($pmprosm_values['discount_code']))
				foreach($discount_code as $col => $value)
					if(isset($pmprosm_values['discount_code'][$col]))
						$discount_code[$col] = $pmprosm_values['discount_code'][$col];

			$sqlQuery = "INSERT INTO $wpdb->pmpro_discount_codes_levels (code_id, 
																		 level_id, 
																		 initial_payment, 
																		 billing_amount, 
																		 cycle_number, 
																		 cycle_period, 
																		 billing_limit, 
																		 trial_amount, 
																		 trial_limit, 
																		 expiration_number, 
																		 expiration_period) 
														VALUES(" . implode(",", $discount_code) . ")";
			$wpdb->query($sqlQuery);
		}
		
		//code created
		return $code_id;
	}
	
	//something went wrong
	return false;
}

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
	elseif(isset($_SESSION['seats']))
		$seats = intval($_SESSION['seats']);
	elseif(!empty($pmprosm_values['seats']))
		$seats = $pmprosm_values['seats'];
	else
		$seats = "";
			
	$sqlQuery = "UPDATE $wpdb->pmpro_discount_codes SET uses = '" . $seats . "' WHERE id = '" . $code_id . "' LIMIT 1";
	$wpdb->query($sqlQuery);
	
	//activate/deactivate old accounts
	if(!empty($pmprosm_values['sponsored_accounts_at_checkout']))
	{
		// cannot rely on old_sub_accounts_active to be set.  May have old accounts, but may only create new ones
		// so we need to deactivate old accounts.
		$children = pmprosm_getChildren($user_id);
		if($children)
		{		
			$old_sub_accounts_active = $_REQUEST['old_sub_accounts_active'];						

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

	// if old sub accounts cancelled above from user request and "sponsored_accounts_at_checkout"
	//   then old accounts to deactivate should be gone so ok to drop into this code.
	// if not sponsored_accounts_at_checkout then
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
						if(pmprosm_changeMembershipLevelWithCode( $last_level_id, $sub_user_id, $code_id ))
						{
							//  update code Use with new order
							pmprosm_removeDiscountCodeUse($sub_user_id, $code_id);
							pmprosm_addDiscountCodeUse($sub_user_id, $last_level_id, $code_id);
						}
				}
				else
					if(pmprosm_changeMembershipLevelWithCode( $pmprosm_values['sponsored_level_id'], $sub_user_id, $code_id ))
					{
						// update code Use with new order
						pmprosm_removeDiscountCodeUse($sub_user_id, $code_id);
						pmprosm_addDiscountCodeUse($sub_user_id, $pmprosm_values['sponsored_level_id'], $code_id);
					}
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
				set_transient("pmprosm_error", sprintf(__("This user has fewer seats than they had sponsored accounts. The sponsored accounts have been deactivated. The user must have his sponsored accounts checkout again using the code: %s.", "pmpro-sponsored-members"), $code));
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
	global $pmpro_level;
	
	//get level
	if(!empty($pmpro_level))
		$level_id = $pmpro_level->id;
	elseif(!empty($_REQUEST['level']))
		$level_id = intval($_REQUEST['level']);
	else
		$level_id = false;
	
	if(empty($level_id))
		return;

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
	
	$message .= "<p><strong>" . __( "Notice", "pmpro-sponsored-members" ) . ":</strong>" . __( "Your current membership has fewer seats than you had sponsored accounts", "pmpro-sponsored-members" ) . "." . __( "The accounts have been deactivated", "pmpro-sponsored-members" ) . "." .  __( "You must have your sponsored accounts checkout again using your code", "pmpro-sponsored-members" ) . ":" . $code . "</p>";
	
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

//function to get children of a sponsor
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

	// if sponsor account is expired or cancelled then children accounts are no longer active.
	//
	//  So we can get a list of old children accounts by getting all the uses of the discount code

	if (empty($children)) {
		$sqlQuery = "SELECT user_id FROM $wpdb->pmpro_discount_codes_uses WHERE code_id = '" . $code_id . "'";
		$children = $wpdb->get_col($sqlQuery);
	}
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

//get a discount code object from a code_id
function pmprosm_getDiscountCodeByCodeID( $code_id ) {
	static $discount_codes;

	if( !isset( $discount_codes[$code_id] ) ) {
		global $wpdb;
		$discount_codes[$code_id] = $wpdb->get_row( "SELECT * FROM $wpdb->pmpro_discount_codes WHERE id = '" . esc_sql( $code_id ) . "' LIMIT 1");
	}

	return $discount_codes[$code_id];
}

//show a user's discount code on the confirmation page
function pmprosm_pmpro_confirmation_message($message)
{
	global $current_user, $wpdb;
	
	$code_id = pmprosm_getCodeByUserID($current_user->ID);	
	
	if(!empty($code_id))
	{
		$pmprosm_values = pmprosm_getValuesByMainLevel($current_user->membership_level->ID);
		$code = pmprosm_getDiscountCodeByCodeID( $code_id );
		
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
		if (empty($pmprosm_values['hide_display_discount_code']) || $pmprosm_values['hide_display_discount_code'] === false ) {

			if ( count( $code_urls ) > 1 ) {
				$message .= "<div class=\"pmpro_content_message\"><p>" . __( "Give this code to your sponsored members to use at checkout:", "pmpro-sponsored-members" ) . " <strong>" . $code->code . "</strong></p><p>" . __( "Or provide one of these direct links to register:", "pmpro-sponsored-members" ) . "<br /></p>";
			} else {
				$message .= "<div class=\"pmpro_content_message\"><p>" . __( "Give this code to your sponsored members to use at checkout:", "pmpro-sponsored-members" ) . " <strong>" . $code->code . "</strong></p><p>" . __( "Or provide this direct link to register:", "pmpro-sponsored-members" ) . "<br /></p>";
			}

			$message .= "<ul>";
			foreach ( $code_urls as $code_url ) {
				$message .= "<li>" . $code_url['name'] . ":<strong> " . $code_url['url'] . "</strong></li>";
			}
			$message .= "</ul>";

			if ( empty( $code->uses ) ) {
				$message .= __( "This code has unlimited uses.", "pmpro-sponsored-members" );
			} else {
				$message .= sprintf( __( "This code has %d uses.", "pmpro-sponsored-members" ), $code->uses );
			}

			$message .= "</div>";
		}
        $member_ids = pmprosm_getChildren( $current_user->ID );
		if (isset($pmprosm_values['list_sponsored_accounts']) && $pmprosm_values['list_sponsored_accounts'] === true) {
			if ( ! empty( $member_ids ) ) {
				$message .= "<hr />";
				$message .= pmprosm_display_sponsored_accounts( $member_ids );
			}
		}
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
		$pmprosm_values = pmprosm_getValuesBySponsoredLevel($pmpro_level->id, false);
		$continue_reg = true;
		
		foreach($pmprosm_values as $pmprosm_value)
		{
			$check_sponsored_level = false;
			
			//check if array
			if(is_array($pmprosm_value['sponsored_level_id']))
			{
				$check_sponsored_level = in_array($pmpro_level->id, $pmprosm_value);
			}
			elseif($pmprosm_value['sponsored_level_id'] == $pmpro_level->id)
			{
				$check_sponsored_level = true;
			}
			
			if($continue_reg && isset($pmprosm_value['discount_code_required']) && !empty($pmprosm_value['discount_code_required']) && $check_sponsored_level)
			{	
				$continue_reg = false;
				break;
			}
		}
		
		if(!$continue_reg)
		{
			pmpro_setMessage(__("You must use a valid discount code to register for this level.", "pmpro-sponsored-members"), "pmpro_error");
			return false;
		}
	}
		
	//if a discount code is being used, check that the main account is active
	if(pmprosm_isSponsoredLevel($pmpro_level->id) && !empty($discount_code))
	{
		$pmprosm_values = pmprosm_getValuesBySponsoredLevel($pmpro_level->id, false);
		
		$code_id = $wpdb->get_var("SELECT id FROM $wpdb->pmpro_discount_codes WHERE code = '" . esc_sql($discount_code) . "' LIMIT 1");
		if(!empty($code_id))
		{
			$code_user_id = pmprosm_getCodeUserID($code_id);
		
			$continue_reg = false; 

			foreach($pmprosm_values as $pmprosm_value)
			{
				if(!$continue_reg && !empty($code_user_id) && !pmpro_hasMembershipLevel($pmprosm_value['main_level_id'], $code_user_id))
					$continue_reg = false;
				
				else
					$continue_reg = true;
			}
			
			if(!$continue_reg)
			{
				pmpro_setMessage(__("The sponsor for this code is inactive. Ask them to renew their account.", "pmpro-sponsored-members"), "pmpro_error");
				return false;
			}
		}
	}
	
	//if the level has max or min seats, check them
	if(pmprosm_isMainLevel($pmpro_level->id))
	{
		$pmprosm_values = pmprosm_getValuesBySponsoredLevel($pmpro_level->id, false);
		if(isset($pmprosm_values['max_seats']) && intval($_REQUEST['seats']) > intval($pmprosm_values['max_seats']))
		{
			pmpro_setMessage(__("The maximum number of seats allowed is " . intval($pmprosm_values['max_seats']) . ".", "pmpro-sponsored-members"), "pmpro_error");
			return false;
		}
		elseif(isset($pmprosm_values['min_seats']) && intval($_REQUEST['seats']) < intval($pmprosm_values['min_seats']))
		{
			pmpro_setMessage(__("The minimum number of seats allowed is " . intval($pmprosm_values['min_seats']) . ".", "pmpro-sponsored-members"), "pmpro_error");
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
	<th><?php _e("Parent Account", "pmpro-sponsored-members");?></th>
	<?php
}
add_action("pmpro_discountcodes_extra_cols_header", "pmprosm_pmpro_discountcodes_extra_cols_header");

function pmprosm_pmpro_discountcodes_extra_cols_body($code)
{
	$code_user_id = pmprosm_getCodeUserID($code->id);
	$code_user = get_userdata($code_user_id);
	?>
	<th><?php if(!empty($code_user_id) && !empty($code_user)) { ?><a href="<?php echo get_edit_user_link($code_user_id); ?>"><?php echo $code_user->user_login; ?></a><?php } elseif(!empty($code_user_id) && empty($code_user)) { ?><em><?php echo __('Missing User', 'pmpro-sponsored-members'); ?></em><?php } else { ?><?php } ?></th>
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
<h3><?php _e("For Sponsored Accounts", "pmpro-sponsored-members");?></h3>
<table class="form-table">
<tbody>
<tr>
    <th scope="row" valign="top"><label for="user_id"><?php _e("User ID:", "pmpro-sponsored-members");?></label></th>
    <td>
		<input name="user_id" type="text" size="10" value="<?php if(!empty($code_user_id)) echo esc_attr($code_user_id);?>" />
		<small class="pmpro_lite"><?php _e("The user ID of the main account holder.", "pmpro-sponsored-members");?></small>
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

	$seat_cost = $pmprosm_values['seat_cost'];
	$max_seats = $pmprosm_values['max_seats'];

	//get seats from submit
	if(isset($_REQUEST['seats']))
		$seats = intval($_REQUEST['seats']);
	elseif(!empty($current_user->ID))
		$seats = get_user_meta($current_user->ID, "pmprosm_seats", true);
    else
        $seats = "";

    if(isset($pmprosm_values['seats']) && $seats == "")
        $seats = $pmprosm_values['seats'];

	if ($seats == "") $seats = 0; 	// leaving blank ('') causes this to be unlimited.

	$sponsored_level = pmpro_getLevel($pmprosm_values['sponsored_level_id']);
	?>
	<div id="pmpro_extra_seats" class="pmpro_checkout">
		<hr />
		<h3>
			<span class="pmpro_checkout-h3-name">
                <?php if ($seat_cost > 0) {
					_e("Would you like to purchase extra seat(s)?", "pmpro-sponsored-members");
				} else {
					_e("Would you like to create extra account(s)?", "pmpro-sponsored-members");
				}
				?>
            </span>
		</h3>
		<div class="pmpro_checkout-fields">
			<div class="pmpro_checkout-field pmpro_checkout-field-seats">
				<label for="seats"><?php echo __('Number of Seats', 'pmpro-sponsored-members');?></label>
				<input type="text" id="seats" name="seats" value="<?php echo esc_attr($seats);?>" size="10" />
				<p class="pmpro_small">
					<?php
                        //min seats defaults to 1
                        if(!empty($pmprosm_values['min_seats']))
                            $min_seats = $pmprosm_values['min_seats'];
                        else
                            $min_seats = 1;

                        if ($max_seats > 1) {
                            if ( isset( $pmprosm_values['seat_cost_text'] ) ) {
                                printf( __( "Enter a number from %d to %d. %s", "pmpro-sponsored-members" ), $min_seats, $pmprosm_values['max_seats'], $pmprosm_values['seat_cost_text'] );
                            } else {
                                printf( __( "Enter a number from %d to %d. +%s per extra seat.", "pmpro-sponsored-members" ), $min_seats, $pmprosm_values['max_seats'], $pmpro_currency_symbol . $pmprosm_values['seat_cost'] );
                            }
                        }
					?>						
				</p>					

				<?php
				//adding sub accounts at checkout?
				if(!empty($pmprosm_values['sponsored_accounts_at_checkout']))
				{
					// add extra_seat_prompt_text
					if(!empty($pmprosm_values['extra_seat_prompt_text'])) {
						echo '<div id="pmpro_extra_seat_prompt">';
						echo  $pmprosm_values['extra_seat_prompt_text'];
						echo '</div>';
					}
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
                                if ($child === false)
                                   continue;  //user does not exist, so skip it.

							//acive?
								if(pmpro_hasMembershipLevel(NULL, $child_id))
									$active = true;
								else
									$active = false;

								//checked?
                            // can't assume order of old_sub_accounts_active
							if(in_array($child_id, $old_sub_accounts_active))
									$checked = true;
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
                        <div><h3><?php echo $sponsored_level->name; _e(' account information.', 'pmprosm'); ?> </h3>
                            <h4><?php if (isset($pmprosm_values['sponsored_header_text']))
									echo $pmprosm_values['sponsored_header_text'];
								else
									_e('Please fill in following information and account(s) will be created.', 'pmprosm');
								?></h4>
                        </div>
                        <?php if(!empty($pmprosm_values['children_get_name'])) { ?>
							<label><?php echo __("First Name", "pmpro-sponsored-members");?></label>
							<input type="text" name="add_sub_accounts_first_name[]" value="<?php echo esc_attr($child_first_name);?>" size="20" />
							<br>
							<label><?php echo __("Last Name", "pmpro-sponsored-members");?></label>
							<input type="text" name="add_sub_accounts_last_name[]" value="<?php echo esc_attr($child_last_name);?>" size="20" />
							<br>
						<?php } ?>
						<?php if(empty($pmprosm_values['children_hide_username'])) { ?>
							<label><?php echo __("Username", "pmpro-sponsored-members");?></label>
							<input type="text" name="add_sub_accounts_username[]" value="<?php echo esc_attr($child_username);?>" size="20" />
							<br>
						<?php } ?>
						<?php if(empty($pmprosm_values['children_hide_email'])) { ?>
							<label><?php echo __("Email", "pmpro-sponsored-members");?></label>
							<input type="text" name="add_sub_accounts_email[]" value="<?php echo esc_attr($child_email);?>" size="20" />
							<br>
						<?php } ?>
						<?php if(empty($pmprosm_values['children_hide_password'])) { ?>
							<label><?php echo __("Password", "pmpro-sponsored-members");?></label>
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
                            if( seats > 0) {
                                jQuery('#pmpro_extra_seat_prompt').show();
                            } else {
                                jQuery('#pmpro_extra_seat_prompt').hide();
                            }
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
                                        var div = '<div id="sponsored_account_'+i+'"><hr /><div><h3><?php echo $sponsored_level->name; _e(" account information # XXXX", "pmprosm"); ?> </h3><h4><?php if (isset($pmprosm_values["sponsored_header_text"]))echo $pmprosm_values["sponsored_header_text"];else _e("Please fill in following information and account(s) will be created.", "pmprosm");?></h4></div><?php if(!empty($pmprosm_values["children_get_name"])) { ?><label><?php echo __("First Name", "pmpro-sponsored-members"); ?></label><input type="text" name="add_sub_accounts_first_name[]" value="" size="20" /><br><label><?php echo __("Last Name", "pmpro-sponsored-members"); ?></label><input type="text" name="add_sub_accounts_last_name[]" value="" size="20" /><br><?php } ?><?php if(empty($pmprosm_values["children_hide_username"])){ ?><label><?php echo __("Username", "pmpro-sponsored-members"); ?></label><input type="text" name="add_sub_accounts_username[]" value="" size="20" /><br><?php } ?><label><?php echo __("Email", "pmpro-sponsored-members"); ?></label><input type="text" name="add_sub_accounts_email[]" value"" size="20" /><br><label><?php echo __("Password", "pmpro-sponsored-members"); ?></label><input type="password" name="add_sub_accounts_password[]" value="" size="20" /><?php echo $empty_child_fields;?></div>';
                                        newdiv = div.replace(/XXXX/g,i);
                                        jQuery('#sponsored_accounts').append(newdiv);										i++;
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
			</div> <!-- end pmpro_checkout-field-seats -->
		</div> <!-- end pmpro_checkout-fields -->
	</div> <!-- end pmpro_extra_seats -->
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
	global $current_user, $pmprosm_sponsored_account_levels, $pmpro_level;
	
	if(!empty($pmpro_level))
		$level_id = $pmpro_level->id;
	elseif(!empty($_REQUEST['level']))
		$level_id = intval($_REQUEST['level']);
	else
		$level_id = false;
	
	if(empty($level_id))
		return;

	$parent_level = pmprosm_getValuesByMainLevel($level_id);
	
	//get seats from submit
	if(!empty($parent_level['seats']))
		$seats = $parent_level['seats'];
	elseif(isset($_REQUEST['seats']))
		$seats = intval($_REQUEST['seats']);
	else
		$seats = "";

	update_user_meta($user_id, "pmprosm_seats", $seats);
			
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

				if( is_wp_error($child_user_id) ) {

					$error_code = $child_user_id->get_error_code();
					$existing_user = null;

					// check the error code & look up user if it's a duplicate email.
					if ( $error_code == 'existing_user_email' )
					{
						$existing_user = get_user_by( 'email', $child_email[ $i ] );
					}
					else
					{
						// skip this user (quietly). -- TODO: Should probably return message to admin?
						$existing_user = null;
						continue;
					}

					// have an actual WP_User object & is user already a member?
					if( ! is_null($existing_user) && ! pmpro_getMembershipLevelForUser( $existing_user->ID ) )
					{
						$child_user_id = $existing_user->ID;
					}
					else {
						continue;
					}
				}

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
function pmprosm_after_checkout_children_updated($user_id) {
	global $current_user, $pmprosm_sponsored_account_levels, $pmpro_level;

	if(!empty($pmpro_level))
		$level_id = $pmpro_level->id;
    elseif(!empty($_REQUEST['level']))
		$level_id = intval($_REQUEST['level']);
	else
		$level_id = false;

	if(empty($level_id))
		return;

	$parent_level = pmprosm_getValuesByMainLevel($level_id);

	if(!empty($parent_level['sponsored_accounts_at_checkout'])) {
		$children = pmprosm_getChildren($user_id);
		if ($children) {
			//get last order
			$parentOrder = new MemberOrder();
			$parentOrder->getLastMemberOrder( $user_id, "success" );

			$sponsor_code_id = pmprosm_getCodeByUserID( $user_id );
			foreach ($children as $child_user_id) {

				$order_id = pmprosm_getOrderByCodeUser( $sponsor_code_id, $child_user_id );

				if ( ! empty( $order_id ) ) {
					$invoice = new MemberOrder( $order_id );

					//set some child order fields to match parents
					$invoice->billing->name    = $parentOrder->billing->name;
					$invoice->billing->street  = $parentOrder->billing->street;
					$invoice->billing->city    = $parentOrder->billing->city;
					$invoice->billing->state   = $parentOrder->billing->state;
					$invoice->billing->zip     = $parentOrder->billing->zip;
					$invoice->billing->country = $parentOrder->billing->country;
					$invoice->billing->phone   = $parentOrder->billing->phone;
					$invoice->status           = $parentOrder->status;
					$invoice->saveOrder();
				} else {
					$invoice = null;
				}

				$sendemails = apply_filters( "pmpro_send_checkout_emails", true );

				if ( $sendemails ) { // Send the e-mails only if the flag is set to true
					$child_user                   = get_userdata( $child_user_id );
					$child_user->membership_level = pmpro_getMembershipLevelForUser( $child_user_id );

					//send email to member
					$pmproemail = new PMProEmail();
					$pmproemail->sendCheckoutEmail( $child_user, $invoice );

					//send email to admin
					$pmproemail = new PMProEmail();
					$pmproemail->sendCheckoutAdminEmail( $child_user, $invoice );
				}
			}
		}
	}
}
add_action("pmpro_after_checkout", "pmprosm_after_checkout_children_updated",30,1);

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
// get order_id from code_id & user_id
function pmprosm_getOrderByCodeUser($code_id, $user_id) {
	global $wpdb;

	$sqlQuery = "SELECT order_id FROM $wpdb->pmpro_discount_codes_uses WHERE user_id = '" . $user_id . "' AND code_id = '".$code_id."' ";
	$order_id = $wpdb->get_var($sqlQuery);
	return $order_id;

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
    elseif(isset($_REQUEST['username']) && in_array($_REQUEST['username'],$child_usernames))
	{
		pmpro_setMessage(__("A sponsored account must have a different username than the main account."),"pmpro_error");
		$okay = false;
	}
    elseif(isset($_REQUEST['bemail']) && in_array($_REQUEST['bemail'],$child_emails))
	{
		pmpro_setMessage(__("A sponsored account must have a different email than the main account."),"pmpro_error");
		$okay = false;
	}
	else
	{	
		foreach($child_usernames as $child_username)
		{
			//if registering child username or email already exisits the create an error.
			if(username_exists($child_username))
			{
					$pmpro_msg = sprintf( __('The username <b>%s</b> already exists. Please select a different username', 'pmpro-sponsored-members'), $child_username);
					$pmpro_msgt = "pmpro_error";
					pmpro_setMessage($pmpro_msg,"pmpro_error");
					$okay = false;
			}
		}
		
		foreach($child_emails as $child_email)
		{
			if(email_exists($child_email))
			{
				$pmpro_msg = sprintf( __( 'That email <b>%s</b> already exists. Please select a different email', 'pmpro-sponsored-members' ), $child_email );
				$pmpro_msgt = "pmpro_error";
				pmpro_setMessage($pmpro_msg,"pmpro_error");
				
				$okay = false;	
			}
			elseif(!is_email($child_email))
			{
				$pmpro_msg = sprintf( __( '<b>%s</b> is not a valid email address. Please select a different email', 'pmpro-sponsored-members' ), $child_email );
				$pmpro_msgt = "pmpro_error";
				pmpro_setMessage($pmpro_msg,"pmpro_error");
				
				$okay = false;	
			}
		}
	}
	
	return $okay;
}
add_action('pmpro_registration_checks', 'pmprosm_pmpro_registration_checks_sponsored_accounts');

//save fields in session for PayPal Express/etc
function pmprosm_pmpro_paypalexpress_session_vars()
{
	if(!empty($_REQUEST['seats']))
		$_SESSION['seats'] = $_REQUEST['seats'];
	else
		$_SESSION['seats'] = "";
	if(!empty($_REQUEST['add_sub_accounts_username']))
		$_SESSION['add_sub_accounts_username'] = $_REQUEST['add_sub_accounts_username'];
	else
		$_SESSION['add_sub_accounts_username'] = "";
	if(!empty($_REQUEST['add_sub_accounts_password']))
		$_SESSION['add_sub_accounts_password'] = $_REQUEST['add_sub_accounts_password'];
	else
		$_SESSION['add_sub_accounts_password'] = "";
	if(!empty($_REQUEST['add_sub_accounts_email']))
		$_SESSION['add_sub_accounts_email'] = $_REQUEST['add_sub_accounts_email'];
	else
		$_SESSION['add_sub_accounts_password'] = "";
	if(!empty($_REQUEST['add_sub_accounts_first_name']))
		$_SESSION['add_sub_accounts_first_name'] = $_REQUEST['add_sub_accounts_first_name'];
	else
		$_SESSION['add_sub_accounts_first_name'] = "";
	if(!empty($_REQUEST['add_sub_accounts_last_name']))
		$_SESSION['add_sub_accounts_last_name'] = $_REQUEST['add_sub_accounts_last_name'];
	else
		$_SESSION['add_sub_accounts_last_name'] = "";
	if(!empty($_REQUEST['old_sub_accounts_active']))
		$_SESSION['old_sub_accounts_active'] = $_REQUEST['old_sub_accounts_active'];
	else
		$_SESSION['old_sub_accounts_active'] = "";
}
add_action("pmpro_paypalexpress_session_vars", "pmprosm_pmpro_paypalexpress_session_vars");
add_action("pmpro_before_send_to_twocheckout", "pmprosm_pmpro_paypalexpress_session_vars", 10, 2);

//Load fields from session if available.
function pmprosm_init_load_session_vars($param)
{
	if(empty($_REQUEST['seats']) && !empty($_SESSION['seats']))
	{
		$_REQUEST['seats'] = $_SESSION['seats'];
		unset($_SESSION['seats']);
	}
	if(empty($_REQUEST['add_sub_accounts_username']) && !empty($_SESSION['add_sub_accounts_username']))
	{
		$_REQUEST['add_sub_accounts_username'] = $_SESSION['add_sub_accounts_username'];
		unset($_SESSION['add_sub_accounts_username']);
	}
	if(empty($_REQUEST['add_sub_accounts_password']) && !empty($_SESSION['add_sub_accounts_password']))
	{
		$_REQUEST['add_sub_accounts_password'] = $_SESSION['add_sub_accounts_password'];
		unset($_SESSION['add_sub_accounts_password']);
	}
	if(empty($_REQUEST['add_sub_accounts_email']) && !empty($_SESSION['add_sub_accounts_email']))
	{
		$_REQUEST['add_sub_accounts_email'] = $_SESSION['add_sub_accounts_email'];
		unset($_SESSION['add_sub_accounts_email']);
	}
	if(empty($_REQUEST['add_sub_accounts_first_name']) && !empty($_SESSION['add_sub_accounts_first_name']))
	{
		$_REQUEST['add_sub_accounts_first_name'] = $_SESSION['add_sub_accounts_first_name'];
		unset($_SESSION['add_sub_accounts_first_name']);
	}
	if(empty($_REQUEST['add_sub_accounts_last_name']) && !empty($_SESSION['add_sub_accounts_last_name']))
	{
		$_REQUEST['add_sub_accounts_last_name'] = $_SESSION['add_sub_accounts_last_name'];
		unset($_SESSION['add_sub_accounts_last_name']);
	}
	if(empty($_REQUEST['old_sub_accounts_active']) && !empty($_SESSION['old_sub_accounts_active']))
	{
		$_REQUEST['old_sub_accounts_active'] = $_SESSION['old_sub_accounts_active'];
		unset($_SESSION['old_sub_accounts_active']);
	}

	return $param;
}
add_action('init', 'pmprosm_init_load_session_vars', 5);

//add code and seats fields to profile for admins
function pmprosm_profile_fields_seats($user)
{
	global $wpdb;
	if( current_user_can("edit_users") && !empty($user->membership_level) )
	{
		?>
		<hr />
		<h3><?php _e("Sponsored Seats", "pmpro-sponsored-members"); ?></h3>
		<table class="form-table">
			<?php
				//get the user's sponsor code
				$sponsor_code_id = pmprosm_getCodeByUserID($user->ID);
				$code = pmprosm_getDiscountCodeByCodeID( $sponsor_code_id );
				if(!empty($code)) {
					?>
					<tr>
						<th><label for="sponsor_code"><?php _e('Sponsor Code', 'pmpro-sponsored-members'); ?></label></th>
						<td>
							<?php echo $code->code; ?>
						</td>
					</tr>
					<tr>
						<th><label for="seats"><?php _e('Seats', 'pmpro-sponsored-members'); ?></label></th>
						<td>
							<?php 
								$seats = intval(get_user_meta($user->ID, "pmprosm_seats", true)); 
							?>
							<input type="text" id="seats" name="seats" size="5" value="<?php echo esc_attr($seats);?>" />
						</td>
					</tr>
					<?php
					} else { ?>
					<tr>
						<th><label for="sponsor_code"><?php _e('Sponsor Code', 'pmpro-sponsored-members'); ?></label></th>
						<td><em class="muted"><?php _e('This membership level does not include a sponsor code.', 'pmpro-sponsored-members'); ?></em></td>
					</tr>
					<?php } ?>
					
				<?php
					//get the user's parent account
					$parent = pmprosm_getSponsor($user->ID);
					if( !empty( $parent ) ) 
					{
						?>
						<tr>
							<th><label for="parent"><?php _e('Parent', 'pmpro-sponsored-members'); ?></label></th>
							<td><a href="<?php echo get_edit_user_link($parent->ID); ?>"><?php echo $parent->display_name; ?></a></td>
						</tr>		
						<?php 
					} 
				?>
		</table>
		<?php
			//get members
			$member_ids = pmprosm_getChildren($user->ID);
			if ( !empty( $member_ids) ) {
            // this was already in profile so don't restrict by 'list_sponsored_accounts' - Keep backward compatability
                echo "<hr />";
                echo pmprosm_display_sponsored_accounts($member_ids);
			}
	}
}
add_action('show_user_profile', 'pmprosm_profile_fields_seats');
add_action('edit_user_profile', 'pmprosm_profile_fields_seats');

function pmprosm_display_sponsored_accounts($member_ids) {

    //make sure we have something to display
	if ( empty( $member_ids) ) return '';
	$count = 0;
	ob_start();
    ?>

    <h3><?php _e("Sponsored Members", "pmpro-sponsored-members");?></h3>
    <div class="pmpro-sponsored-members_children" <?php if( count( $member_ids ) > 4 ) { ?>style="height: 150px; overflow: auto;"<?php } ?>>
        <table class="wp-list-table widefat fixed" width="100%" cellpadding="0" cellspacing="0" border="0">
            <thead>
            <tr>
                <th><?php _e('Date', 'pmpro-sponsored-members'); ?></th>
                <th><?php _e('Name', 'pmpro-sponsored-members'); ?></th>
                <th><?php _e('Email', 'pmpro-sponsored-members'); ?></th>
                <th><?php _e('Membership Level', 'pmpro-sponsored-members'); ?></th>
            </tr>
            </thead>
            <tbody>
			<?php
			foreach($member_ids as $member_id)
			{
				$member = get_userdata($member_id);
				$member->membership_level = pmpro_getMembershipLevelForUser($member_id);
				if(empty($member)) {
					continue;
				}
				?>
                <tr<?php if($count++ % 2 == 1) { ?> class="alternate"<?php } ?>>
                    <td><?php echo date(get_option("date_format"), $member->membership_level->startdate); ?></td>
                    <td><a href="<?php echo get_edit_user_link($member_id); ?>"><?php echo $member->display_name; ?></a></td>
                    <td><?php echo $member->user_email; ?></td>
                    <td><?php echo $member->membership_level->name; ?></td>
                </tr>
				<?php
			}
			?>
            </tbody>
        </table>
    </div>
    <?php
	$content = ob_get_contents();
	ob_end_clean();
	return $content;
}
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
			$code = pmprosm_getDiscountCodeByCodeID( $code_id );

		
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
			

			$code_urls = pmprosm_get_checkout_urls( $code );
				
			ob_start();
			
			$limit = 1;
			if(isset($pmprosm_values['max_seats']))
				$limit = $pmprosm_values['max_seats'];
			
			//get members
			$member_ids = pmprosm_getChildren($current_user->ID);
			?>
			<div id="pmpro_account-sponsored" class="pmpro_box">	
				 
				<h3><?php _e("Sponsored Members", "pmpro-sponsored-members");?></h3>
                <?php if (empty($pmprosm_values['hide_display_discount_code']) || $pmprosm_values['hide_display_discount_code'] === false ) { ?>
                    <p><?php printf(__("Give this code to your sponsored members to use at checkout: <strong>%s</strong></p>", "pmpro-sponsored-members"), $code->code);?>
                    <?php if(count($code_urls) > 1) { ?>
                        <p><?php _e("Or provide one of these direct links to register:", "pmpro-sponsored-members");?></p>
                    <?php } else { ?>
                        <p><?php _e("Or provide this direct link to register:", "pmpro-sponsored-members");?></p>
                    <?php } ?>

                    <ul>
                        <?php foreach($code_urls as $code_url) { ?>
                            <li><?php echo $code_url['name'];?>: <strong><a target="_blank" href="<?php echo $code_url['url'];?>"><?php echo $code_url['url'];?></a></strong></li>
                        <?php } ?>
                    </ul>
                <?php } // hide_display_discount_code ?>

                <div class="pmpro_message pmpro_default">
					<?php if(empty($code->uses)) { ?>
						<?php _e("This code has unlimited uses.", "pmpro-sponsored-members");?>
					<?php } else { ?>
						<?php printf(__("%s/%s uses.", "pmpro-sponsored-members"), count($member_ids), $code->uses);?>
					<?php } ?>
				</div>
				<?php
                    // use same account display as in admin
                        if ( ! empty( $member_ids ) ) {
                            echo "<hr />";
                            echo pmprosm_display_sponsored_accounts( $member_ids );
                        }
                ?>
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
	if( !pmprosm_isSponsoredLevel($user_level->id) )
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
	if(strpos($pmpro_email->template, "checkout") !== false && strpos($pmpro_email->template, "admin") === false && strpos($pmpro_email->template, "debug") === false)
	{ 
		//get the user_id from the email
		$user_id = $wpdb->get_var("SELECT ID FROM $wpdb->users WHERE user_email = '" . $pmpro_email->data['user_email'] . "' LIMIT 1");
		$level_id = $pmpro_email->data['membership_id'];
		$code_id = pmprosm_getCodeByUserID($user_id);		
		
		if(!empty($user_id) && !empty($code_id) && pmprosm_isMainLevel($level_id))
		{
			//get code
			$code = pmprosm_getDiscountCodeByCodeID( $code_id );
			
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

			if(isset($pmprosm_values['add_created_accounts_to_confirmation_email']) && $pmprosm_values['add_created_accounts_to_confirmation_email'] === true) {
				$children = pmprosm_getChildren($user_id);
				if(!empty($children)) {
					$message = "<p>" . __( "Accounts created at checkout:", "pmpro-sponsored-members" ) . "<br />";
					$message .= "<ul>";
					foreach ( $children as $child_id ) {
						$child = get_userdata($child_id);
						$message .= "<li>" . $child->display_name . " ( " . $child->user_email . " ) </li>";
					}
					$message .= "</ul>";

					$body = $message . "<hr />" . $body;
				}
			}

			//check if we should update confirmation email
			if (isset($pmprosm_values['hide_display_discount_code']) && $pmprosm_values['hide_display_discount_code'] === true )
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
			$message = "<p>" . sprintf(__("Give this code to your sponsored members to use at checkout: %s", "pmpro-sponsored-members"), $code->code) . "<br />";
			
			if(count($code_urls) > 1) 
				$message .= __("Or provide one of these direct links to register:", "pmpro-sponsored-members") . "</p>";
			else
				$message .= __("Or provide this direct link to register:", "pmpro-sponsored-members") . "</p>";
				
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

/*
Function to add links to the plugin row meta
*/
function pmprosm_plugin_row_meta($links, $file) {
	if(strpos($file, 'pmpro-sponsored-members.php') !== false)
	{
		$new_links = array(
			'<a href="' . esc_url('https://www.paidmembershipspro.com/add-ons/pmpro-sponsored-members/')  . '" title="' . esc_attr( __( 'View Documentation', 'pmpro-sponsored-members' ) ) . '">' . __( 'Docs', 'pmpro-sponsored-members' ) . '</a>',
			'<a href="' . esc_url('https://paidmembershipspro.com/support/') . '" title="' . esc_attr( __( 'Visit Customer Support Forum', 'pmpro-sponsored-members' ) ) . '">' . __( 'Support', 'pmpro-sponsored-members' ) . '</a>',
		);
		$links = array_merge($links, $new_links);
	}
	return $links;
}
add_filter('plugin_row_meta', 'pmprosm_plugin_row_meta', 10, 2);

/**
 * This function generates direct checkout links from a discount code for future uses.
 * @param object $code This is the discount code object.
 * @since
 * @return array.
 */
function pmprosm_get_checkout_urls( $code ) {
	global $wpdb;

	$checkout_urls = array();

	if ( ! empty( $code ) && is_object( $code ) ) {
		$code_id = $code->id;
	} else {
		return;
	}

	$sql_code = "SELECT level_id, name FROM $wpdb->pmpro_discount_codes_levels c INNER JOIN $wpdb->pmpro_membership_levels l ON c.level_id = l.id WHERE c.code_id =" . esc_sql( $code_id );

	$levels_id = $wpdb->get_results( $sql_code );
		
	foreach ( $levels_id as $value ) {
		$checkout_urls[] = array( "name" => $value->name, "url" => pmpro_url( "checkout", "?level=" . $value->level_id . "&discount_code=" . $code->code ) );
	}

	return $checkout_urls;
}

/**
 * Add sponsor's code to memberslist CSV export
 */
function pmprosm_pmpro_members_list_csv_extra_columns( $columns ) {
	$columns['sponsorcode'] = 'pmprosm_pmpro_members_list_csv_sponsorcode';
	
	return $columns;
}
add_filter( 'pmpro_members_list_csv_extra_columns', 'pmprosm_pmpro_members_list_csv_extra_columns' );

/**
 * Call back to get the sponsor code
 */
function pmprosm_pmpro_members_list_csv_sponsorcode( $user ) {
	$sponsor_code_id = pmprosm_getCodeByUserID( $user->ID );
	$sponsor_code = pmprosm_getDiscountCodeByCodeID( $sponsor_code_id );
	if( !empty( $sponsor_code ) ) {
		return $sponsor_code->code;
	} else {
		return '';
	}
}

/**
 * Add Sponsor or Sponsor Code to the Member's List Table
 * Add Header
 */
function pmprosm_pmpro_memberslist_extra_cols_header() {
?>
<th><?php _e( 'Sponsor/Code', 'pmprosm' ); ?></th>
<?php
}
add_action( 'pmpro_memberslist_extra_cols_header', 'pmprosm_pmpro_memberslist_extra_cols_header' );

/**
 * Add Sponsor or Sponsor Code to the Member's List Table
 * Add Column Content
 */
function pmprosm_pmpro_memberslist_extra_cols_body( $theuser ) {
	$sponsor_code_id = pmprosm_getCodeByUserID( $theuser->ID );
	$sponsor_code = pmprosm_getDiscountCodeByCodeID( $sponsor_code_id );
	$sponsor = pmprosm_getSponsor( $theuser->ID );
?>
<td>
	<?php
		if( !empty( $sponsor) ) {
			$user_link = '<a href="' . add_query_arg('user_id', $sponsor->ID, admin_url('user-edit.php') ) . '">' . $sponsor->user_login . '</a>';
			printf( __( 'Sponsored by %s', 'pmprosm' ), $user_link );
		}
		if( !empty( $sponsor_code ) ) {
			echo '<a href="' . add_query_arg( array( 'page' => 'pmpro-discountcodes', 'edit' => $sponsor_code_id ), admin_url( 'admin.php' ) ) . '">' . $sponsor_code->code . '</a>';
		}
	?>
</td>
<?php
}
add_action( 'pmpro_memberslist_extra_cols_body', 'pmprosm_pmpro_memberslist_extra_cols_body' );