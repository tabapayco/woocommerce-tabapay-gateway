=== TabaPay Gateway ===
Contributors: TabaPay
Tags: woocommerce, payment gateway, tabapay, iran, shaparak
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.3.2
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A secure payment gateway for WooCommerce integrating Tabapay.

== Description ==
This plugin allows you to easily integrate the Tabapay payment gateway into your WooCommerce store, offering a secure and fast payment experience for your customers.

== Features ==
– Full WooCommerce Support: Seamless integration with WooCommerce payment system.
– Secure Transactions: Powered by Tabapay.
– Customizable: Adjustable gateway title and description for the checkout page.
– Multi-Currency Handling: Supports Iranian Rial (IRR), Toman (IRT), and other variations (IRHT, IRHR).
– Detailed Transaction Logs: Logs payment details like tracking code, card number, IP, and date.

== Installation ==
1. Upload the plugin folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to WooCommerce > Settings > Payments, enable "Tabapay," and configure your Merchant Key.

== Frequently Asked Questions ==
= Where do I get my Merchant Key? =
You can obtain your Merchant Key by signing up at [Tabapay](https://tabapay.ir).

= Does it support multiple currencies? =
Yes, it supports IRR, IRT, IRHT, and IRHR with automatic conversion.

== Changelog ==
= 1.3.2 =
* Initial release with full payment and callback functionality.

== Upgrade Notice ==
= 1.3.2 =
Initial release – no upgrade notices yet.

== Screenshots ==
1. Payment gateway option on the checkout page.
2. Admin settings for configuring the Tabapay gateway.

== License ==
This plugin is licensed under the GNU General Public License version 2 or later. See the License URI for more details.

== External services ==
This plugin connects to the Tabapay API to process payments through Shaparak.

When a customer proceeds to checkout and initiates a payment, the following data is sent to the Tabapay API:
– Order amount
– Order ID
– Callback URL

These requests are made to securely create and verify payment transactions on the Tabapay servers.

All communications with the Tabapay API are done over HTTPS and follow secure protocols.

Service Provider: [Tabapay.ir](https://tabapay.ir)  
- [Terms and Conditions](https://tabapay.ir/terms-and-conditions)  
- [Privacy Policy](https://tabapay.ir/privacy-policy)
