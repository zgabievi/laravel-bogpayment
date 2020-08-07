<?php

namespace Zorb\BOGPayment;

use Zorb\BOGPayment\Contracts\XMLResponse;
use Psr\Http\Message\StreamInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Log;
use UnexpectedValueException;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use SimpleXMLElement;

class BOGPayment
{
    /**
     * This method is used to redirect user to card details page
     *
     * @param array $additional_params
     * @param bool $preAuth
     * @return RedirectResponse
     */
    function redirect(array $additional_params = [], bool $preAuth = false): RedirectResponse
    {
        $lang = config('bogpayment.language');
        $merchant_id = rawurlencode(config('bogpayment.merchant_id'));
        $page_id = rawurlencode(config('bogpayment.page_id'));
        $success_url = rawurlencode(URL::to(config('bogpayment.success_url')));
        $fail_url = rawurlencode(URL::to(config('bogpayment.fail_url')));

        $params = array_merge([
            'lang' => $lang,
            'page_id' => $page_id,
            'merch_id' => $merchant_id,
            'back_url_s' => $success_url,
            'back_url_f' => $fail_url,
            'preauth' => $preAuth ? 'Y' : 'N'
        ], $additional_params);

        $query_params = http_build_query($params);

        $url = config('bogpayment.url') . '?' . $query_params;

        return redirect($url);
    }

    /**
     * This method is used to check if basic authentication is correct
     */
    function checkAuth()
    {
        if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
            $shop_name = config('bogpayment.shop_name');

            header('WWW-Authenticate: Basic realm="' . $shop_name . '"');
        } else {
            if ($_SERVER['PHP_AUTH_USER'] != config('bogpayment.http_auth_user')
                || $_SERVER['PHP_AUTH_PW'] != config('bogpayment.http_auth_pass')
            ) {
                abort(401);
            }
        }
    }

    /**
     * This method is used to check if request is made from allowed ip
     */
    function checkIpAllowed()
    {
        $ip_list = explode(',', config('bogpayment.allowed_ips'));

        if ($ip_list) {
            $client_ip = request()->getClientIp();

            if (!in_array($client_ip, $ip_list)) {
                abort(403, 'Access denied for IP: ' . $client_ip);
            }
        }
    }

    /**
     * This method is used to compare signature parameter to certificate
     *
     * @param string $mode
     */
    function checkSignature($mode = 'check')
    {
        $signature = request('signature');

        if (!$signature) {
            $this->sendError($mode, 'Signature is missing!');
        }

        $url = request()->fullUrl();
        $url = preg_replace('#&signature=.*$#', '', $url);
        $url = rawurldecode($url);

        $cert_file = storage_path(config('bogpayment.cert_path'));
        $cert = file_get_contents($cert_file);
        $key = openssl_pkey_get_public($cert);

        $signature = base64_decode($signature);
        $valid = openssl_verify($url, $signature, $key, OPENSSL_ALGO_SHA1);

        if ($valid !== 1) {
            $this->sendError($mode, 'Signature is invalid!');
        }
    }

    /**
     * This method is used to get request parameters from payment process
     *
     * @param $name
     * @param string $default
     * @return Request
     */
    function getParam($name, $default = ''): Request
    {
        $name = str_replace('.', '_', $name);
        return request($name, $default);
    }

    /**
     * This method is used to send failed xml response
     *
     * @param string $mode
     * @param string $message
     */
    function sendError($mode = 'check', $message = '')
    {
        $response = new XMLResponse();

        if (config('bogpayment.debug')) {
            Log::debug('BOG Payment -> sendError', compact('mode', 'message'));
        }

        if ($mode === 'check') {
            $response->checkError($message);
        } else if ($mode === 'register') {
            $response->registerError($message);
        }
    }

    /**
     * This method is used to send success xml response
     *
     * @param string $mode
     * @param array $data
     */
    function sendSuccess($mode = 'check', $data = [])
    {
        $response = new XMLResponse();

        if (config('bogpayment.debug')) {
            Log::debug('BOG Payment -> sendSuccess', compact('mode', 'data'));
        }

        if ($mode === 'check') {
            $response->checkSuccess($data);
        } else if ($mode === 'register') {
            $response->registerSuccess();
        }
    }

    /**
     * This method is used for recurring process
     *
     * @param string $trx_id
     * @param array $additional_params
     * @return StreamInterface
     */
    function repeat(string $trx_id, array $additional_params = []): StreamInterface
    {
        $lang = config('bogpayment.language');
        $merchant_id = rawurlencode(config('bogpayment.merchant_id'));
        $page_id = rawurlencode(config('bogpayment.page_id'));
        $success_url = rawurlencode(URL::to(config('bogpayment.success_url')));
        $fail_url = rawurlencode(URL::to(config('bogpayment.fail_url')));

        $params = array_merge([
            'lang' => $lang,
            'page_id' => $page_id,
            'merch_id' => $merchant_id,
            'back_url_s' => $success_url,
            'back_url_f' => $fail_url,
            'o.trx_id' => $trx_id
        ], $additional_params);

        $query_params = http_build_query($params);

        $url = config('bogpayment.url') . '?' . $query_params;

        $client = new Client;
        $request = $client->get($url);
        return $request->getBody();
    }

    /**
     * This method is used to refund payment transaction
     *
     * @param string $trx_id
     * @param string $rrn
     * @param int $amount
     * @return Object
     * @throws \Exception
     */
    function refund(string $trx_id, string $rrn, int $amount): Object
    {
        $merchant_id = rawurlencode(config('bogpayment.merchant_id'));
        $api_pass = rawurlencode(config('bogpayment.refund_api_pass'));
        $params = [
            'trx_id' => $trx_id,
            'p.rrn' => $rrn,
            'amount' => $amount,
        ];

        $query_params = http_build_query($params);

        $url = "https://{$merchant_id}:{$api_pass}@3dacq.georgiancard.ge/merchantapi/refund?{$query_params}";

        $client = new Client;
        $response = $client->get($url);

        try {
            $xml = $this->parseXml($response->getBody()->getContents());
            return $xml->Message->RefundResponse->Result;
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }
    }

    /**
     * This method is used to parse xml string
     *
     * @param $xml
     * @return SimpleXMLElement
     */
    protected function parseXml($xml): SimpleXMLElement
    {
        libxml_use_internal_errors(true);
        $object = simplexml_load_string($xml);
        $errors = libxml_get_errors();
        libxml_clear_errors();

        if ($errors) {
            $err = [];

            foreach ($errors as $err_obj) {
                $err[] = $err_obj->message;
            }

            $err_str = implode(', ', $err);

            throw new UnexpectedValueException('XML string is invalid. libXML Errors: ' . $err_str);
        }

        return $object;
    }
}
