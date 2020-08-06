<?php

namespace Zorb\BOGPayment\Contracts;

use Symfony\Component\HttpFoundation\Response;

class XMLResponse
{
    //
    protected $response;

    //
    public function __construct()
    {
        $this->response = new Response('', Response::HTTP_OK, ['content-type' => 'text/xml']);
    }

    //
    function checkSuccess($data = [])
    {
        $trx_id = $this->clean($data['trx_id'], 50);
        $short_desc = $this->clean($data['short_desc'], 30);
        $long_desc = $this->clean($data['long_desc'], 125);
        $account_id = $this->clean($data['account_id'], 32);
        $amount = intval($data['amount']);
        $currency = $this->clean($data['currency']);
        $primary_trx_id = $this->clean($data['primary_trx_id']);

        $repeat_txn = $primary_trx_id ? "<primaryTrxPcid>{$primary_trx_id}</primaryTrxPcid>" : '';

        $content = <<<XML
<payment-avail-response>
    <result>
        <code>1</code>
        <desc>OK</desc>
    </result>
    <merchant-trx>{$trx_id}</merchant-trx>
    {$repeat_txn}
    <purchase>
        <shortDesc>{$short_desc}</shortDesc>
        <longDesc>{$long_desc}</longDesc>
        <account-amount>
            <id>{$account_id}</id>
            <amount>{$amount}</amount>
            <currency>{$currency}</currency>
            <exponent>2</exponent>
        </account-amount>
    </purchase>
</payment-avail-response>
XML;

        $this->send($content);
    }

    //
    function checkError($desc = 'Unable to accept this payment')
    {
        $desc = $this->clean($desc, 125);

        $content = <<<XML
<payment-avail-response>
    <result>
        <code>2</code>
        <desc>{$desc}</desc>
    </result>
</payment-avail-response>
XML;

        $this->send($content);
    }

    //
    function registerSuccess()
    {
        $content = <<<XML
<register-payment-response>
    <result>
        <code>1</code>
        <desc>OK</desc>
    </result>
</register-payment-response>
XML;

        $this->send($content);
    }

    //
    function registerError($desc = 'Unable to accept this payment')
    {
        $desc = $this->clean($desc, 125);

        $content = <<<XML
<register-payment-response>
    <result>
        <code>2</code>
        <desc>{$desc}</desc>
    </result>
</register-payment-response>
XML;

        $this->send($content);
    }

    //
    public function send($content)
    {
        $this->response->setContent($content);
        $this->response->send();

        exit;
    }

    //
    protected function clean($var, $substr = null)
    {
        $var = htmlspecialchars($var);

        if ($substr) {
            $var = mb_substr($var, 0, $substr, 'UTF-8');
        }

        return $var;
    }
}
