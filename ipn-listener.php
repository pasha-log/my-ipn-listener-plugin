<?php
function handle_ipn() {
    // Send an empty HTTP 200 response to PayPal
    header('HTTP/1.1 200 OK');

    error_log("Handling IPN");
    
    // Log the original, unaltered IPN message
    error_log('Original IPN: ' . print_r($_POST, true));
    
    $raw_post_data = file_get_contents('php://input');
    $raw_post_array = explode('&', $raw_post_data);
    $myPost = array();
    foreach ($raw_post_array as $keyval) {
        $keyval = explode('=', $keyval);
        if (count($keyval) == 2) {
            // Since we do not want the plus in the datetime string to be encoded to a space, we manually encode it.
            if ($keyval[0] === 'payment_date') {
                if (substr_count($keyval[1], '+') === 1) {
                    $keyval[1] = str_replace('+', '%2B', $keyval[1]);
                }
            }
            $myPost[$keyval[0]] = urldecode($keyval[1]);
        }
    }

    // Build the body of the verification post request, adding the _notify-validate command.
    $req = 'cmd=_notify-validate';
    $get_magic_quotes_exists = false;
    if (function_exists('get_magic_quotes_gpc')) {
        $get_magic_quotes_exists = true;
    }
    foreach ($myPost as $key => $value) {
        if ($get_magic_quotes_exists == true && get_magic_quotes_gpc() == 1) {
            $value = urlencode(stripslashes($value));
        } else {
            $value = urlencode($value);
        }
        $req .= "&$key=$value";
    }
    
    error_log('Request: ' . $req);
    
    // Verify the IPN data
    $verified = verifyIPN($req);

    global $wpdb;
    $table_name = $wpdb->prefix . 'paypal_transactions';
    
    if ($verified) {
        // IPN data is valid, process the payment here
        error_log("IPN verified");

        // Check the payment_status is Completed
        if ($myPost['payment_status'] != 'Completed') {
            error_log("Payment is not completed");
            exit;
        }

        // Check that txn_id has not been previously processed
        $txn_id = $myPost['txn_id'];
        $prepared_statement = $wpdb->prepare("SELECT * FROM $table_name WHERE txn_id = %s", $txn_id);
        $exists = $wpdb->get_row($prepared_statement);

        if ($exists) {
            error_log("Transaction ID already exists");
            // txn_id already exists, exit or handle accordingly
            exit;
        } else {
            error_log("Inserting new transaction ID");
            // txn_id does not exist, insert it into the table
            $wpdb->insert($table_name, array('txn_id' => $txn_id));
        }
    
        // Check that receiver_email is your Primary PayPal email
        $receiver_email = get_option('my_ipn_plugin_receiver_email');
        if ($myPost['receiver_email'] != $receiver_email) {
            error_log("Receiver email does not match: " . $myPost['receiver_email']);
            exit;
        }
            
        $home_url = parse_url(home_url())['host'];
    
        if ($myPost['custom'] == $home_url) {
            $email = $receiver_email; 
            $subject = 'Payment received from' . $home_url . '';
        
            // Format the message body
            $message = "Hello,\n\n";
            $message .= "Donation Received\n\n";
            $message .= "This email confirms that you have received a donation of " . $myPost['mc_gross'] . " " . $myPost['mc_currency'] . " from " . $myPost['payer_email'] . ".\n\n";
            $message .= "You can view the transaction details online.\n\n";
            $message .= "Donation Details\n\n";
            $message .= "Total amount:\t" . $myPost['mc_gross'] . " " . $myPost['mc_currency'] . "\n";
            $message .= "Currency:\t" . $myPost['mc_currency'] . "\n";
            $message .= "Confirmation number:\t" . $myPost['txn_id'] . "\n";
            $message .= "Quantity:\t" . $myPost['quantity'] . "\n";
            $message .= "Contributor:\t" . $myPost['payer_email'] . "\n";
            $message .= "Website:\t" . $home_url . "\n";

            // Retrieve WP Mail SMTP options
            $from_email = get_option('wp_mail_smtp')['mail']['from_email'];

            // Use the retrieved email address in the headers
            $headers = 'From: ' . $from_email . "\r\n" .
                'Reply-To: ' . $from_email . "\r\n" .
                'X-Mailer: PHP/' . phpversion();
        
            if(wp_mail($email, $subject, $message, $headers)) {
                error_log("Mail sent successfully");
            } else {
                error_log("Mail failed to send");
            }
        }
    } else {
        error_log("IPN not verified");
    }
}

function verifyIPN($req) {
    // Change the URL based on whether you're in sandbox or live mode
    $paypal_url = 'https://ipnpb.paypal.com/cgi-bin/webscr';  // Live mode
    // $paypal_url = 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr';  // Sandbox mode
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $paypal_url);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
    curl_setopt($ch, CURLOPT_SSLVERSION, 6);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'User-Agent: PHP-IPN-Verification-Script',
        'Connection: Close',
    ));

    // Execute the cURL request and get the response data
    $res = curl_exec($ch);
    error_log('Response: ' . $res);
    
    // Get the HTTP response code
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    
    // Log the HTTP response code
    error_log("HTTP response code: " . $http_code);
    
    // Check the IPN message
    if (strcmp ($res, "VERIFIED") == 0) {
        error_log("IPN message verified");
        return true;
    } else if (strcmp ($res, "INVALID") == 0) {
        error_log("IPN message not verified");
        return false;
    }
}

add_action('handle_ipn', 'handle_ipn');
?>