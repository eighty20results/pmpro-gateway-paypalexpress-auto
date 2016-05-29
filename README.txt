=== PMPro PayPal Express Gateway add-on (auto-confirm) ===
Contributors: strangerstudios, eighty20results
Tags: paid memberships pro, ecommerce, paypal express, payment gateway
Requires at least: 3.5
Tested up to: 4.5.2
Stable tag: 1.4

A custom PayPal Express Payment Gateway for Paid Memberships Pro

== Description ==
Adds configurable behavior for PayPal Express based payment plans in the Paid Memberships Pro membership plugin. Admin may choose whether to require confirmation of payment plan & instant charge on PayPal.com site, or after being returned to the originiating membership site and clicking the "Confirmation" button.

Paid Memberships Pro and this PayPal Express Payment Gateway for Paid Memberships Pro add-on is 100% GPL and available for free on our site at https://eighty20results.com/pmpro-paypalexpress-auto. The full version of the plugin is offered with no restrictions or additional licenses required.

== Installation ==

= Download, Install and Activate! =
In your WordPress admin:

1. Download the latest version of the plugin.
2. Unzip the downloaded file to your computer.
3. Upload the /pmpro-gateway-paypalexpress-auto/ directory to the /wp-content/plugins/ directory of your site.
4. Activate the plugin through the 'Plugins' menu in WordPress.

= Complete the Initial Plugin Setup =
Go to Memberships -> Payment Settings in the WordPress admin to select the "PayPal Express (Auto)" payment gateway and configure the required input fields.

== Changelog ==
== 1.4 ==
* ENHANCEMENT: Refactored for WP Coding Style compliance
* ENHANCEMENT: Set 'cancelled_at_payment' as order status when user clicks 'cancel' during PayPal.com payment processing.

== 1.3.1 ==
* FIX: Would sometimes clear the default PMPro 'Cancel Page' setting

== 1.3 ==
* FIX: Would sometimes cause 'Please configure your membership pages' warning to appear
* ENHANCEMENT: Refactoring & adding documentation

== 1.2.1 ==
* FIX: Didn't ensure consistent request number for PayPal transaction URL
* FIX: Only configure gateway info & IPN link if using this gateway
* FIX: Subscription plans would fail since subscription profiles do not support PAYMENTREQUEST_n_* fields

== 1.2 ==
* FIX: Refactored
* FIX: Didn't load plugin update files correctly
* FIX: Path to plugin-updates
* ENHANCEMENT: Add build environment
* ENHANCEMENT: Added one-click upgrade support

== 1.0 ==
* Initial release of the Payment Gateway add-on

