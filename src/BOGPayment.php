<?php

namespace Zorb\BOGPayment;

use Zorb\BOGPayment\Contracts\XMLResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Log;
use UnexpectedValueException;
use GuzzleHttp\Client;

class BOGPayment
{
    /**
     * @param array $additional_params
     * @return RedirectResponse
     */
    function redirect(array $additional_params = []): RedirectResponse
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
        ], $additional_params);

        $query_params = http_build_query($params);

        $url = config('bogpayment.url') . '?' . $query_params;

        return redirect($url);
    }

    //
    function checkAuth()
    {
        if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
            $shop_name = config('bogpayment.shop_name');

            header('WWW-Authenticate: Basic realm="' . $shop_name . '"');
            header('HTTP/1.0 401 Unauthorized');
            echo 'Access denied';
            exit;
        } else {
            if ($_SERVER['PHP_AUTH_USER'] != config('bogpayment.http_auth_user')
                || $_SERVER['PHP_AUTH_PW'] != config('bogpayment.http_auth_pass')
            ) {
                header('HTTP/1.0 401 Unauthorized');
                echo 'Access denied';
                exit;
            }
        }
    }

    //
    function checkIpAllowed()
    {
        $ip_list = explode(',', config('bogpayment.allowed_ips'));

        if ($ip_list) {
            $client_ip = request()->getClientIp();

            if (!in_array($client_ip, $ip_list)) {
                header('HTTP/1.0 403 Forbidden');
                echo 'Access denied for IP: ' . $client_ip;
                exit;
            }
        }
    }

    //
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

    //
    function getParam($name, $default = '')
    {
        $name = str_replace('.', '_', $name);
        return request($name, $default);
    }

    //
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

    //
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

    //
    function repeat(string $trx_id, array $additional_params = [])
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

    //
    function refund(string $trx_id, string $rrn, int $amount)
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

    //
    protected function parseXml($xml)
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
