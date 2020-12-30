<?php
/*
	Code to import sponsored member associations when using the Import Users From CSV plugin.
	
	Step 0. Have your $pmprosm_sponsored_account_levels global setup already with sponsored level relationships.
	
	Step 1. Add the following columns to your import CSV:
	* pmprosm_seats: This will set the user meta field that stores the number of seats available to a sponsoring/parent account. Leave blank or 0 for unlimited.
	* pmprosm_sponsor: set to the ID, user login, or email address of the sponsoring parent account and the relationship will be setup.
	
	IMPORTANT: Make sure that sponsors are imported BEFORE their child accounts. i.e. sponsors should come earlier/higher up in the CSV	
*/
function pmprosm_is_iu_post_user_import($user_id) {
	global $wpdb;
		
	//get user and make sure they have a membership level
	$user = get_userdata($user_id);
	$user->membership_level = pmpro_getMembershipLevelForUser($user_id);
	
	//find sponsor and setup attachment
	$sponsor = $user->pmprosm_sponsor;		
	if(!empty($sponsor)) {
		//find sponsoring member
		if(is_numeric($sponsor))
			$sponsor = get_userdata($sponsor);
		elseif(strpos($sponsor, "@") !== false)
			$sponsor = get_user_by('email', $sponsor);
		else
			$sponsor = get_user_by('login', $sponsor);
				
		//found a sponsor, set it up
		if(!empty($sponsor)) {			
			//get membership level for sponsor
			$sponsor->membership_level = pmpro_getMembershipLevelForUser($sponsor->ID);
			
			//check if this user already has a discount code
			$code_id = pmprosm_getCodeByUserID($sponsor->ID);
						
			//make sure the code is still around
			if($code_id)
			{
				$code_exists = $wpdb->get_var("SELECT id FROM $wpdb->pmpro_discount_codes WHERE id = '" . $code_id . "' LIMIT 1");
				if(!$code_exists)
					$code_id = false;
			}
						
			//get seats for this sponsor is saved in user meta
			$seats = $sponsor->pmprosm_seats;
			if(!empty($seats))
				$uses = $seats;
			else
				$uses = "";
			
			//if no code, create a new one
			if(empty($code_id))
				$code_id = pmprosm_createSponsorCode($sponsor->ID, $sponsor->membership_level->id, $uses);
						
			//update code for sponsored user
			$wpdb->query("UPDATE $wpdb->pmpro_memberships_users SET code_id = '" . $code_id . "' WHERE user_id = '" . $user_id . "' AND membership_id = '" . $user->membership_level->ID . "' AND status = 'active' LIMIT 1");
			pmprosm_addDiscountCodeUse($user_id, $user->membership_level->ID, $code_id);
		}
		
		//delete user meta
		delete_user_meta($user_id, 'pmprosm_sponsor');
	}
}
add_action("is_iu_post_user_import", "pmprosm_is_iu_post_user_import", 20);