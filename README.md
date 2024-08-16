## WordPress PayPal IPN (Instant Payment Notification) Listener Plugin
**Description**
This plugin was built specifically for businesses that have multiple websites, each with their own PayPal buttons, and need to differentiate which site had the transaction. When a transaction occurs on a site with this plugin installed, the PHP code receives the `txn_id` from your PayPal account and checks whether the ID already exists in the database table created by the plugin (to avoid repetitive notifications). It then runs a security check with PayPal.

For the security check, it sends a cURL verification POST request to the endpoint for PayPal's Instant Payment Notification (IPN) service. If the message returns with a verified status, it means the transaction was legitimate. You can then receive many details about the transaction (amount, currency, quantity, contributor's email, or a custom detail such as the website the payment came from, set up through the PayPal account). The plugin then compiles an email, sent using the WP Mail SMTP Plugin's `wp_mail()`, to notify the relevant parties. This saves time by eliminating the need to log in to PayPal directly to determine the transaction details.

To make things more convenient, the plugin comes with a settings page that allows administators to change the receiver email for the transaction notification. NOTE: in the IPN listener, the PayPal account's email is compared with the email address you have configured in your plugin settings to ensure that the payment is indeed intended for your account. If the emails do not match, the script doesn't do anything to prevent processing fraudulent or incorrect payments.

ANOTHER NOTE: make sure that you have the WP Mail SMTP plugin configured so that you are able to send emails.

**Technologies Used:**
- PHP
- SQL
- [PayPal's Instant Payment Notification (IPN) service](https://developer.paypal.com/api/nvp-soap/ipn/)
- [WordPress Developer Resources Functions](https://developer.wordpress.org/reference/functions/)
- [WP Mail SMTP Plugin](https://wordpress.org/plugins/wp-mail-smtp/)


