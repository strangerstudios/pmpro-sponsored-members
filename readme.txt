=== PMPro Sponsored Members ===
Contributors: strangerstudios
Tags: pmpro, membership, user pages
Requires at least: 3.5
Tested up to: 4.0
Stable tag: .4.3

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
