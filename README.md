# Beltoft Loyalty Rewards for WooCommerce

Points-based loyalty system for WooCommerce. Customers earn points on orders and redeem them for discounts at cart or checkout.

- Stable version: 1.2.3
- Requires: WordPress 6.2+, WooCommerce 7.0+, PHP 7.4+
- Author: beltoft.net
- License: GPLv2 or later

## Overview

Customers earn points when their order is completed (or processing). On the cart or checkout page they can apply points for an instant discount. The discount is handled as a virtual coupon behind the scenes — no coupon codes needed.

Works with classic shortcode-based cart and checkout.

## Features

- Configurable earn rate (points per currency unit spent)
- Points redemption with configurable redeem rate, minimum points, and max discount percentage
- "My Points" tab in My Account with balance and transaction history
- Points expiry with configurable days
- Admin points ledger with type filtering, user search, and CSV export
- Manual point adjustments from user profiles
- Points earned shown on product pages and order admin
- Automatic reversal on cancellation or refund
- HPOS (High-Performance Order Storage) compatible
- Shortcodes: `[wclr_points_message]` and `[wclr_redeem_form]`

## Installation

1. Upload the `beltoft-loyalty-rewards` folder to `wp-content/plugins/` or install from a ZIP.
2. Activate through the Plugins menu.
3. Go to WooCommerce > Loyalty Rewards to configure.

## Configuration

### Earn

- **Earn rate** — Points per currency unit (e.g. 1 point per $1).
- **Earn status** — Order status that triggers earning (completed or processing).
- **Exclude tax/shipping** — Calculate points on the subtotal only.

### Redeem

- **Redeem rate** — How many points equal a currency unit (e.g. 100 points = $1).
- **Minimum points** — Minimum balance required to redeem.
- **Maximum discount** — Maximum percentage of the cart that can be discounted.

### Expiry

- **Expiry days** — Number of days before unused points expire. A daily cron handles expirations.

### Display

- **Product message** — Show "Earn X points" on single product pages.
- **My Account tab** — Show or hide the "My Points" tab.

## Pro Add-on

The optional [Beltoft Loyalty Rewards for WooCommerce - Pro](https://chimkins.com/customer-loyalty-pro) add-on adds:

- Referral program with shareable links and anti-fraud hold
- Bonus points for signup, reviews, and daily login
- Email notifications (points earned, expiry warning, monthly summary)
- Multiplier campaigns (double/triple points events)
- VIP tiers (Bronze, Silver, Gold, Platinum) with earn-rate multipliers
- Bulk credit/debit, CSV import, and balance export
- Full redeem UI, tier badges, and campaign banners
- Compatible with WooCommerce block-based Cart and Checkout

## Development

- PSR-4 autoloaded classes under `src/`.
- No Composer dependencies.
- Text domain: `beltoft-loyalty-rewards`

## Changelog

### 1.2.3

- Added WC_Logger debug logging with admin toggle (WooCommerce > Status > Logs).
- Fixed redemption timing: points are now debited after payment confirmation, not at checkout submission.
- Fixed Store API REST routes not checking the "Redeem Enabled" setting.
- Fixed SQL LIKE pattern in LedgerRepository for Plugin Check compliance.

### 1.2.2

- Redeem form redesigned to follow WooCommerce native styling.
- My Points page design consistency.
- Escape and sanitize fixes per WordPress coding standards.

### 1.2.0

- Points-to-earn estimate on cart and checkout.
- Configurable redeem rate, minimum points, and max discount percentage.

### 1.0.0

- Initial release.

## About the Author

Beltoft Loyalty Rewards for WooCommerce is built and maintained by [Chimkins IT](https://chimkins.com), a team specializing in WooCommerce and Odoo ERP integrations. Check out our [Odoo WooCommerce Connector](https://chimkins.com) for real-time sync between your WooCommerce store and Odoo.

## License

GPLv2 or later. See https://www.gnu.org/licenses/gpl-2.0.html.
