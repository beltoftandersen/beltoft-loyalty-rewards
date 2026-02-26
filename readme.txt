=== Beltoft Loyalty Rewards for WooCommerce ===
Contributors: christian198521, beltoft.net
Tags: loyalty, points, rewards, discount, woocommerce
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.2.9
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Earn points on purchases and redeem them for cart discounts.

== Description ==

A points-based loyalty system for WooCommerce. Customers earn points on orders and redeem them for discounts at cart or checkout.

=== How It Works ===

1. **Earn** — Customers get points when their order is completed (or processing). You set the earn rate, e.g. 1 point per $1 spent.
2. **Redeem** — On the cart or checkout page, customers apply points for a discount. No coupon codes involved.
3. **Expire** — Optionally expire unused points after a set number of days.

=== Features ===

* Configurable earn rate (points per currency unit)
* Points redemption with configurable redeem rate, minimum points, and max discount percentage
* "My Points" tab in My Account with balance and transaction history
* Points expiry with configurable days
* Admin points ledger with filtering and CSV export
* Manual point adjustments from user profiles
* Points earned shown on product pages and order admin
* Automatic reversal on cancellation or refund
* Works with classic cart and checkout
* HPOS compatible
* Shortcodes: `[blr_points_message]` and `[blr_redeem_form]`

=== Pro Add-on ===

Need referrals, campaigns, tiers, or bulk tools? The **[Beltoft Loyalty Rewards for WooCommerce - Pro](https://chimkins.com/customer-loyalty-pro)** add-on adds:

* Referral program with shareable links and anti-fraud hold
* Bonus points for signup, reviews, and daily login
* Email notifications (points earned, expiry warning, monthly summary)
* Multiplier campaigns (double/triple points events)
* VIP tiers (Bronze, Silver, Gold, Platinum) with earn-rate multipliers
* Bulk credit/debit, CSV import, and balance export
* Full redeem UI, tier badges, and campaign banners
* Compatible with WooCommerce block-based Cart and Checkout

== Installation ==

1. Upload the `beltoft-loyalty-rewards` folder to `/wp-content/plugins/` or install from the Plugins screen.
2. Activate through the Plugins menu.
3. Go to WooCommerce > Loyalty Rewards to configure.

== Frequently Asked Questions ==

= How do customers redeem points? =
On the cart or checkout page, logged-in customers see their balance and can enter how many points to apply. The discount shows as a line item.

= What happens when an order is cancelled or refunded? =
Points earned from that order are automatically reversed.

= Can I adjust a customer's points manually? =
Yes. Go to Users > Edit User and scroll to the Loyalty Points section.

= Does it work with WooCommerce block-based Cart and Checkout? =
The Pro add-on adds full support for block-based Cart and Checkout with a redeem form, tier badges, and campaign banners.

= Is it compatible with HPOS? =
Yes.

== Screenshots ==

1. Settings screen with earn rate, redeem controls, and expiry settings.
2. Cart page with redeem form.
3. My Account "My Points" tab.
4. Admin points ledger.

== Changelog ==

= 1.2.9 =
* Fixed: Order points could be under-awarded when discounts were subtracted twice.
* Changed: Shortcodes now use slug-based names: `[blr_points_message]` and `[blr_redeem_form]`.

= 1.2.8 =
* Improved: Redeem form syncs when coupon is removed via WooCommerce cart totals.
* Improved: Earn estimate shown on cart/checkout even when balance is zero.
* Fixed: WC "Coupon has been removed" notice flash suppressed on page-builder pages.
* Added: WC_Logger debug logging with toggle in Advanced settings.
* Added: Portuguese (Portugal) translation.

= 1.2.3 =
* Improved: Redeem form now compatible with page builders (Bricks, Elementor, etc.).
* Improved: Apply button shows loading spinner while processing.
* Fixed: Expiry cron stopping after first run (uncorrelated NOT EXISTS subquery).
* Fixed: Customers earning fewer points than intended when redeeming on the same order (double coupon subtraction).
* Fixed: Redeemed points now restored when order status changes to failed.
* Fixed: Admin deductions larger than balance no longer desync ledger from stored balance.
* Fixed: Store API apply/remove now checks redeem_enabled setting.
* Fixed: Ledger list cache invalidation on insert.
* Fixed: Form state updates directly from AJAX response instead of relying on checkout refresh.
* Fixed: WooCommerce coupon notices no longer pile up on page-builder checkouts.

= 1.2.2 =
* Improved: Redeem form redesigned to follow WooCommerce native styling.
* Improved: My Points page design consistency.
* Fixed: Escape all translatable strings per WordPress coding standards.
* Fixed: Sanitize POST data with wp_unslash in admin profile handler.

= 1.2.1 =
* Improved: Clearer earn and redeem messaging on cart and checkout.
* Fixed: Inline notices replace JavaScript alerts on redeem errors.

= 1.2.0 =
* Added: Points-to-earn estimate on cart and checkout.
* Added: Configurable redeem rate, minimum points, and max discount percentage.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.2.0 =
Adds new redeem settings. Review the redeem options under WooCommerce > Loyalty Rewards.
