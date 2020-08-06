<?php

return [

    /**
     * This value decides to log or not to log requests.
     */
    'debug' => env('BOG_PAYMENT_DEBUG', false),

    /**
     * Payment url from Bank of Georgia
     */
    'url' => env('BOG_PAYMENT_URL', 'https://3dacq.georgiancard.ge/payment/start.wsm'),

    /**
     * Merchant ID from Bank of Georgia
     */
    'merchant_id' => env('BOG_PAYMENT_MERCHANT_ID'),

    /**
     * Page ID from Bank of Georgia
     */
    'page_id' => env('BOG_PAYMENT_PAGE_ID'),

    /**
     * Account ID from Bank of Georgia
     */
    'account_id' => env('BOG_PAYMENT_ACCOUNT_ID'),

    /**
     * Shop Name for Bank of Georgia payment
     */
    'shop_name' => env('BOG_PAYMENT_SHOP_NAME', env('APP_NAME')),

    /**
     * Success callback url for Bank of Georgia
     */
    'success_url' => env('BOG_PAYMENT_SUCCESS_URL', '/payments/success'),

    /**
     * Fail callback url for Bank of Georgia
     */
    'fail_url' => env('BOG_PAYMENT_FAIL_URL', '/payments/fail'),

    /**
     * Default currency for Bank of Georgia payment
     */
    'currency' => env('BOG_PAYMENT_CURRENCY', 981),

    /**
     * Default language for Bank of Georgia payment
     */
    'language' => env('BOG_PAYMENT_LANGUAGE', 'KA'),

    /**
     * HTTP Authentication username for Bank of Georgia payment
     */
    'http_auth_user' => env('BOG_PAYMENT_HTTP_AUTH_USER'),

    /**
     * HTTP Authentication password for Bank of Georgia payment
     */
    'http_auth_pass' => env('BOG_PAYMENT_HTTP_AUTH_PASS'),

    /**
     * List of allowed ips to access your system from Bank of Georgia
     */
    'allowed_ips' => env('BOG_PAYMENT_ALLOWED_IPS', '213.131.36.62'),

    /**
     * Bank of Georgia certificate path from storage
     */
    'cert_path' => env('BOG_PAYMENT_CERTIFICATE_PATH', 'app/bog.cer'),

    /**
     * Bank of Georgia api password for refund operation
     */
    'refund_api_pass' => env('BOG_PAYMENT_REFUND_API_PASS'),
];
