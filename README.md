# Bank of Georgia payment integration for Laravel

## Table of Contents
- [Installation](#installation)
- [Usage](#usage)
    - [Payment](#payment)
    - [Recurring](#recurring)
    - [Refund](#refund)
- [Additional Information](#additional-information)
- [Environment Variables](#environment-variables)
- [License](#license)

## Installation

To get started, you need to install package:

```shell script
composer require zgabievi/laravel-bogpayment
```

If your laravel version is older than 5.5, then add this to your service providers in config/app.php:

```php
'providers' => [
    ...
    Zorb\BOGPayment\BOGPaymentServiceProvider::class,
    ...
];
```

You can publish config file using this command:

```shell script
php artisan vendor:publish --provider="Zorb\BOGPayment\BOGPaymentServiceProvider"
```

This command will copy config file and create migrations for you. You should run php artisan migrate to get deliveries table.

## Usage

- [Payment](#payment)
- [Recurring](#recurring)
- [Refund](#refund)

### Payment

Default process has several to be completed:

1. Redirect to card details page
2. Bank will check payment details on your route
3. Bank will register payment details on your route

#### Step #1

On this step you should redirect user to card details page

```php
use Zorb\BOGPayment\BOGPayment;

class PaymentController extends Controller {
    //
    public function __invoke()
    {
        return BOGPayment::redirect([
            'order_id' => 1,
        ]);
    }
}
```

#### Step #2

On this step bank will check that you are ready to accept payment

```php
use Zorb\BOGPayment\BOGPayment;

class PaymentCheckController extends Controller {
    //
    public function __invoke()
    {
        // chek that http authentication is correct
        BOGPayment::checkAuth();

        // check if you are getting request from allowed ip
        BOGPayment::checkIpAllowed();

        // check if you can find order with provided id
        $order_id = BOGPayment::getParam('o.order_id');
        $order = Order::find($order_id);
    
        if (!$order) {
            BOGPayment::sendError('check', 'Order couldn\'t be found with provided id');
        }

        $trx_id = BOGPayment::getParam('trx_id');

        // send success response
        BOGPayment::sendSuccess('check', [
            'amount' => $order->amount,
            'short_desc' => $order->short_desc,
            'long_desc' => $order->long_desc,
            'trx_id' => $trx_id,
            'account_id' => config('bogpayment.account_id'),
            'currency' => config('bogpayment.currency'),
        ]);
    }
}
```

*Check [request parameters](#parameters-of-check-request) here*

#### Step #3

On this step bank will provide details of the payment

```php
use Zorb\BOGPayment\BOGPayment;

class PaymentRegisterController extends Controller {
    //
    public function __invoke()
    {
        // chek that http authentication is correct
        BOGPayment::checkAuth();

        // check if you are getting request from allowed ip
        BOGPayment::checkIpAllowed();

        // check if provided signature matches certificate
        BOGPayment::checkSignature('register');

        // check if you can find order with provided id
        $order_id = BOGPayment::getParam('o.order_id');
        $order = Order::find($order_id);
    
        if (!$order) {
            BOGPayment::sendError('check', 'Order couldn\'t be found with provided id');
        }

        $trx_id = BOGPayment::getParam('trx_id');
        $result_code = BOGPayment::getParam('result_code');

        if (empty($result_code)) {
            BOGPayment::sendError('register', 'Result code has not been provided');
        }
    
        if ((int)$result_code === 1) {
            // payment has been succeeded
        } else {
            // payment has been failed
        }

        // send success response
        BOGPayment::sendSuccess('register');
    }
}
```

*Check [request parameters](#parameters-of-register-request) here*

### Recurring

Recurring process is the same as default process. Difference is that user doesn't have to fill card details again.

1. Request will be sent to bank to start recurring process
2. Bank will check payment details on your route
3. Bank will register payment details on your route

```php
use Zorb\BOGPayment\BOGPayment;

class PaymentRecurringController extends Controller {
    //
    public function __invoke(string $trx_id)
    {
        return BOGPayment::repeat($trx_id, [
            'recurring' => true,
        ]);
    }
}
```

In your check and register controllers you can catch `BOGPayment::getParam('o.recurring')` parameter and now you will know that this process is from recurring request.

### Refund

In order to refund money you need to have trx_id of payment and rrn.

```php
use Zorb\BOGPayment\BOGPayment;

class PaymentRefundController extends Controller {
    //
    public function __invoke(string $trx_id, string $rrn)
    {
        $result = BOGPayment::refund($trx_id, $rrn);

        if ((int)$result->code === 1) {
            // refund process succeeded
        } else {
            // refund process failed
        }
    }
}
```

*Check [result parameters](#refund-result) here*

## Additional Information

### Parameters of check request

| Param | Meaning | 
| --- | --- |
| merch_id | Merchant ID of your shop *(length 32)* |
| trx_id | Transaction ID of current payment *(length 32)* |
| lang_code | ISO 639 language codes *(EN/KA/RU)* |
| o.* | Additional parameters provided by you on redirect |
| ts | Payment creation time *(yyyyMMdd HH:mm:ss)* |

### Parameters of register request

| Param | Meaning | 
| --- | --- |
| merch_id | Merchant ID of your shop *(length 32)* |
| trx_id | Transaction ID of current payment *(length 32)* |
| merchant_trx | Transaction ID, if it is provided by shop |
| result_code | Result code of the payment *(1 - Success, 2 - Fail)* |
| amount | Integer value of payment amount |
| p.rrn | RRN of payment |
| p.transmissionDateTime | Authorization request date and time *(MMddHHmmss)* |
| o.* | Additional parameters provided by you on redirect |
| m.* | Parameters provided on first phase of payment process |
| ts | Payment creation time *(yyyyMMdd HH:mm:ss)* |
| signature | Base64 encoded signature to compare with certificate |
| p.cardholder | Cardholder name |
| p.authcode | Authentication code from processing *(ISO 8583 Field 38)* |
| p.maskedPan | Masked card number |
| p.isFullyAuthenticated | Result of 3D authentication *(Y - Success, N - Fail)* |
| p.storage.card.ref | Parameters of the card |
| p.storage.card.expDt | Expiration date of card *(YYMM)* |
| p.storage.card.recurrent | Authorization status of recurring process *(Y - Recurring is possible, N - Reucrring is not possive)* |
| p.storage.card.registered | Card registration status *(Y - Card has been registered, N - Card was not registered)* |
| ext_result_code | Additional information about result code |

### Refund result

| Key | Meaning  | 
| --- | --- |
| code | Numeric value for result code |
| desc | Description of payment result | 

## Environment Variables

| Key | Meaning | Type | Default |
| --- | --- | --- | --- |
| BOG_PAYMENT_DEBUG | This value decides to log or not to log requests | bool | false |
| BOG_PAYMENT_URL | Payment url from Bank of Georgia | string | https://3dacq.georgiancard.ge/payment/start.wsm |
| BOG_PAYMENT_MERCHANT_ID | Merchant ID from Bank of Georgia | string |  |
| BOG_PAYMENT_PAGE_ID | Page ID from Bank of Georgia | string |  |
| BOG_PAYMENT_ACCOUNT_ID | Account ID from Bank of Georgia | string |  |
| BOG_PAYMENT_SHOP_NAME | Shop Name for Bank of Georgia payment | string | APP_NAME |
| BOG_PAYMENT_SUCCESS_URL | Success callback url for Bank of Georgia | string | /payments/success | 
| BOG_PAYMENT_FAIL_URL | Fail callback url for Bank of Georgia | string | /payments/fail | 
| BOG_PAYMENT_CURRENCY | Default currency for Bank of Georgia payment | int | 981 | 
| BOG_PAYMENT_LANGUAGE | Default language for Bank of Georgia payment | string | KA |
| BOG_PAYMENT_HTTP_AUTH_USER | HTTP Authentication username for Bank of Georgia payment | string |  |
| BOG_PAYMENT_HTTP_AUTH_PASS | HTTP Authentication password for Bank of Georgia payment | string |  |
| BOG_PAYMENT_ALLOWED_IPS | Comma separated list of allowed ips to access your system from Bank of Georgia | string | 213.131.36.62 |
| BOG_PAYMENT_CERTIFICATE_PATH | Bank of Georgia certificate path from storage | string | app/bog.cer |
| BOG_PAYMENT_REFUND_API_PASS | Bank of Georgia api password for refund operation | string |  |

## License

laravel-bogpayment is licensed under a [MIT License](https://github.com/zgabievi/laravel-bogpayment/blob/master/LICENSE).
