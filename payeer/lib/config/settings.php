<?php

return array(
	'm_url' => array(
        'value' => '//payeer.com/merchant/',
        'title' => 'The URL of the merchant',
        'description' => 'url for payment in the system Payeer',
        'control_type' => waHtmlControl::INPUT,
    ),
    'm_shop' => array(
        'value' => '',
        'title' => 'ID store',
        'description' => 'The store identifier registered in the system "PAYEER".<br/>it can be found in <a href="http://www.payeer.com/account/">Payeer account</a>: "Account -> My store -> Edit".',
        'control_type' => waHtmlControl::INPUT,
    ),
    'm_key' => array(
        'value' => '',
        'title' => 'Secret key',
        'description' => 'The secret key notification about the payment,<br/>which is used to verify the integrity of the received information<br/>and unambiguous identification of the sender.<br/>Must match the secret key specified in the <a href="http://www.payeer.com/account/">Payeer account</a>: "Account -> My store -> Edit".',
        'control_type' => waHtmlControl::INPUT,
    ),
	'ip_filter' => array(
        'value' => '',
        'title' => 'IP filter',
        'description' => 'The list of trusted ip addresses, you can specify the mask',
        'control_type' => waHtmlControl::INPUT,
    ),
	'email_error' => array(
        'value' => '',
        'title' => 'Email for errors',
        'description' => 'Email to send payment errors',
        'control_type' => waHtmlControl::INPUT,
    ),
    'log_file' => array(
        'value' => true,
        'title' => 'Logging',
        'description' => 'The query log from Payeer is stored in the file: /payeer/orders.log',
        'control_type' => waHtmlControl::CHECKBOX,
    )
);
