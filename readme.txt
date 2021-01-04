=== Paid Memberships Pro - Sponsored Members Add On ===
Contributors: strangerstudios
Tags: pmpro, membership, user pages
Requires at least: 4.0
Tested up to: 5.6
Stable tag: 0.10

Generate a discount code for a main account holder to distribute to sponsored members.

== Description ==

This plugin currently requires Paid Memberships Pro. 

This plugin is meant as a functional demo on how to implement sponsored memberships with Paid Memberships Pro. Feel free to edit as needed for your project.

Once the plugin is activated with the PMPROSM_MAIN_ACCOUNT_LEVEL and PMPROSM_SPONSORED_ACCOUNT_LEVEL, your site will:
* When users checkout for a main account (or are assigned one by and admin), a discount code is generated to allow sponsored members to sign up for the sponsored level for free.
* If a user has a discount code assigned to them, it will show up on their membership account and confirmation pages.
* Only members using a sponsored discount code will be able to sign up for the sponsored level.
* Sponsored members will be linked to their sponsor through the pmpro_discount_codes_uses table.
* If a sponsor's account is cancelled, all of their sponsored members will have their accounts disabled as well.
* If a sponsor's account is reenabled at a later point, all of their sponsored members will have their accounts reenabled automatically.

== Installation ==

1. Upload the `pmpro-sponsored-members` directory to the `/wp-content/plugins/` directory of your site.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Create one level for paying account holders and one level for sponsored members.
1. Edit the PMPROSM_MAIN_ACCOUNT_LEVEL constant in the plugin file to match your main account level id.
1. Edit the PMPROSM_SPONSORED_ACCOUNT_LEVEL constant in the plugin file to match your sponsored level id.
1. Edit the second half of the pmprosm_pmpro_after_change_membership_level() function to change the values of the discount code given to paying members.

== Frequently Asked Questions ==

= I found a bug in the plugin. =

Please post it in the issues section of GitHub and we'll fix it as soon as we can. Thanks for helping. https://github.com/strangerstudios/pmpro-sponsored-members/issues

== Changelog ==
= 0.10 - 2021-01-03 =
* FEATURE: Admins and users can now remove sponsored child accounts to free up the spot.
* BUG FIX/ENHANCEMENT: We are not generating a username for child accounts at checkout if you use the children_hide_username option to hide the username field. Child accounts still need an email or name fields to generate a user account.
* BUG FIX/ENHANCEMENT: Now allowing a min_seats value of 0.
* BUG FIX/ENHANCEMENT: Now allowing seats set to a specific value, with max_seats and/or min_seats not defined.
* BUG FIX: Fixed an issue where 'normal' discount codes wouldn't work on child levels at checkout.
* Bug Fix: Fixed an issue where unlimited codes were not working as intended/hidden from the front-end.
* BUG FIX: Fixed some warnings when finding a sponsor of a user with no level and in some other cases.

= 0.9 - 2020-10-15 =
* BUG FIX: Fixed warning of undefined constant when creating child accounts at checkout.
* BUG FIX: Fixed incorrect escaping of discount code text on account/confirmation page.
* BUG FIX: Fixed issue of child start date being 1 January 1970 during account creation at checkout.
* BUG FIX: Fixed a warning during child account creation at checkout of duplicate order codes for each child account.
* BUG FIX: Updates the frontend list of a sponsor's child accounts to only show a link to edit user if they have that capability.
* BUG FIX: Fixes improper use of a `<th>` where it should be a `<td>` in the discount code table inside the WordPress dashboard.

= 0.8 - 2020-09-30 =
* BUG FIX: Fixed bug where PayPal Express wasn't saving the number of 'child' seats correctly.
* BUG FIX: Fixed a PHP warning when editing non-members in the WordPress dashboard. Thanks @cipriantepes
* BUG FIX: Fixed a warning when child level ID's was an array and tried to output a string value during checkout.
* BUG FIX: Fixed child account heading (numbering) when creating child accounts during checkout. This was out by 1.
* BUG FIX/ENHANCEMENT: Prevent sponsored members for signing up with their own discount codes.
* ENHANCEMENT: New filter added to allow dynamic changes done to code created - 'pmprosm_sponsored_code_settings'
* ENHANCEMENT: Localization and escaping of text on the front-end. Thanks @sebthesun

= .7 =
* BUG FIX: Fixed bug where discount codes were not being saved when using PayPal Express. Fixed other PayPal Express-related bugs.
* BUG FIX: Fixed bugs with the checkout URL generated for the sponsor code.
* BUG FIX: Added check in case you use the same email address for thes sponsor and a child account when creating child accounts at checkout. (Thanks, Bill Stoltz)
* BUG FIX/ENHANCEMENT: Fixed pmprosm_getChildren to work when the sponsoring account is expired. (Thanks, Bill Stoltz)
* ENHANCEMENT: Adding blank orders and sending confirmation emails to sponsored accounts created at checkout. (Thanks, Bill Stoltz)
* ENHANCEMENT: Added a new option hide_display_discount_code if you don't want sponsors to be able to see their sponsor code. (Thanks, Bill Stoltz)
* ENHANCEMENT: Tweaked seat text for cases where seats don't cost extra. (Thanks, Bill Stoltz)
* ENHANCEMENT: Improved display of sponsor or sponsored children on the edit user page in the WP dashboard. (Thanks, Bill Stoltz)
* ENHANCEMENT: Giving Membership Manager role access to view and edit # of seats.
* ENHANCEMENT: Added pmprosm_getDiscountCodeByCodeID( $code_id ) function to help with getting the code and other details from a code id.
* ENHANCEMENT: Added a "Sponsor/Code" column to the Members List showing a link to the sponsoring user or the sponsor code if applicable.
* ENHANCEMENT: Added a "sponsorcode" column to the Members List CSV export.

= .6.3 = 
* ENHANCEMENT: Improved fields display on membership checkout page to use no tables

= .6.2 =
* BUG/ENHANCEMENT: Now setting the number of seats to the default value when changing a user's level to a parent/main level in the admin.
* ENHANCEMENT: Added a link to Parent of the child to User Profile for Admins-only. 
* ENHANCEMENT: Added list of Sponsored Members (children) to User Profile for Admins-only. 

= .6.1 =
* BUG: Fixed bug where seats and other information weren't being saved after checking out at offsite gateway like PayPal Express.
* ENHANCEMENT: Moved some code from after checkout method into a pmprosm_createSponsorCode($user_id, $level_id, $uses = "") so it can be used elsewhere.
* ENHANCEMENT: Added integration with Import Users From CSV so you can set a pmprosm_sponsor column (to a user ID, user email, or user login). If set, a sponsor code will be created for the sponsoring user (if needed) and the sponsored user will be setup as a child account.

= .6 =
* BUG: Fixed bug where "seats" user meta was not updated sometimes at checkout.
* BUG: Fixed bug where no new discount code was created if a user's old code had been deleted and the id was still linked to the user.
* FEATURE: Can now have multiple parent levels pointing to the same child levels.

= .5.1 =
* BUG: Added current_time('timestamp') to the date() calls to fix off by one timestamp issues.
* BUG: No longer applying esc_sql to values in the discount_code settings. Escaping of quotes was breaking the SQL. Make sure your values in your settings are SQL safe.

= .5 =
* BUG: Fixed bug in pmprosm_getValuesBySponsoredLevel() where it was checking the main account level id instead of the $level_id parameter passed to the function. This would have kept the registration checks from working.
* FEATURE: Can now override the discount code that is generated for brand new main account users. Add a 'discount_code' element to the $pmprosm_sponsored_account_levels array element that is an array itself with values for any of the following discount code fields: code_id, level_id, initial_payment, billing_amount, cycle_number, cycle_period, billing_limit, trial_amount, trial_limit, expiration_number, expiration_period. Make sure that strings in the _period values are wrapped in single quotes, e.g. $discount_code['expiration_period']=>"'Year'"
* FEATURE: Added a "discount_code_required" property for sponsored levels, which will remove the registration check to make sure a discount code is used when checking out for a sponsored level. This is useful if users can purchase the sponsored levels directly.

= .4.3 =
* No longer showing the discount code links in confirmation messages if the code has 0 uses.
* Added checks to pmprosm_pmpro_registration_checks() to make sure seats purchased falls within min and max.

= .4.2.1 =
* Fixed bug when updating seats at checkout if both min_seats and max_seats is set.

= .4.2 =
* Fixed bug where message under seats field would say "from 1 to X" even if the min_seats was set to something other than 1. (Thanks, martinec)

= .4.1 =
* Fixed bug where billing address wouldn't show up if the base level was free, there was a seat cost, but the sponsored_accounts_at_checkout setting was not turned on.
* Fixed bug where apply_seat_cost_to_initial_payment and apply_seat_cost_to_billing_amount settings weren't working in some cases.
* Better error handling when using the sponsored_accounts_at_checkout setting. Will throw error if any of the details for new accounts is missing or invalid emails are given.
* The apply_seat_cost_to_initial_payment and apply_seat_cost_to_billing_amount settings can be set to 'sponsored_level' and it will grab the pricing from that level instead of setting a fixed amount in the code.

= .4 =
* Fixed issues with child account creation and form at checkout.
* Added 'sponsored_accounts_at_checkout' option for sponsored levels that tells the addon whether or not to show new user forms at checkout for sponsored members.
* Now showing previously activated accounts at checkout. You can uncheck to deactivate.
* Now use pmprosm_getChildren in all places where child accounts are used/shown. This function queries on the pmpro_memberships_users table by code_id. Other queries used to query the pmpro_discount_codes_uses table instead. This will ensure that used seats is always tracking active members.
* Added children_get_name, children_hide_username, and add_code_to_confirmation_email options for sponsored levels.

= .3.8 =
* Fixed issues with child account creation and form at checkout.
* Fixed bug in pmprosm_isSponsoredLevel. Wasn't working when sponsored level was a non-array value.
* Added pmprosm_after_child_created hook with $child_id, $parent_id as parameters.
* Added new properties for sponsored levels: apply_seat_cost_to_initial_payment, apply_seat_cost_to_billing_amount (these can be set to 'sponsored_level' to map to sponsored level values), children_get_name, children_hide_username, add_code_to_confirmation_email. Might try to find a way to make these properties easier to manage.

= .3.7 =
* Added the ability to have sponsored members create child accounts at checkout.

= .3.6 =
* Caching user sponsors into a global cache when using pmpro_getSponsor function.

= .3.5 =
* Wrapped strings for translation/gettext/etc.

= .3.4 =
* Added pmprosm_getUserByCodeID and pmprosm_getSponsor functions.
* Added the discount code and code URLs to the sponsored members checkout confirmation email.
* Added ability for a sponsored code to work on multiple levels. Just pass an array for the 'sponsored_level_id' value in the pmprosm_sponsored_account_levels global.

= .3.3 =
* Added Sponsor Code field to edit user profile page for admins.
* Using $seed parameter in calls to pmpro_getDiscountCode if running PMPro 1.7.6 or higher.

= .3.2 =
* Fixed pmprosm_isSponsoredLevel()

= .3.1 =
* Fixed bug where the seats value was not being honored and codes were being created with unlimited seats.
* Fixed bug where uses value for codes was not being updated if a user with an existing code checked out again.
* Changed language on account and confirmation page.

= .3 =
* All settings now defined in the $pmprosm_sponsored_account_levels global. This is an array that supports more than one "main" account level at a time.

= .2.1 =
* Fixed bug where non-sponsor discount codes were showing a "sponsor is inactive" error message at checkout.

= .2 =
* Added the ability to add a field to checkout for users to choose how many seats they are purchasing using the PMPROSM_SEAT_COST and PMPROSM_MAX_SEATS constants.

= .1 =
* This is the initial version of the plugin.
