<?php

/*
 * This file is part of the EoSetefi package.
 *
 * (c) 2014 Eymen Gunay <eymen@egunay.com>
 */

namespace Eo\SetefiBundle;

use Eo\SetefiBundle\Payment\PaymentRequestInterface;
use Guzzle\Http\Client as Guzzle;
use Symfony\Component\HttpFoundation\Response;

/**
 * Setefi client
 */
class Client
{
    protected $endpoint;
    protected $id;
    protected $password;

    /**
     * Class constructor
     * 
     * @param string $endpoint
     * @param string $id
     * @param string $password
     */
    public function __construct($endpoint, $id, $password)
    {
        $this->endpoint = $endpoint;
        $this->id = $id;
        $this->password = $password;
    }

    /**
     * Create payment url
     *
     * @param  PaymentRequestInterface $paymentRequest
     * @return string
     */
    public function createPaymentUrl(PaymentRequestInterface $paymentRequest)
    {
        $client = new Guzzle();

        $parameters = array(
            'id' => $this->id,
            'password' => $this->password,
            'operationType' => 'initialize',
            'amount' => $paymentRequest->getAmount(),
            'currencyCode' => $paymentRequest->getCurrencyCode(),
            'language' => $paymentRequest->getLanguage(),
            'responseToMerchantUrl' => $paymentRequest->getReturnUrl(),
            'recoveryUrl' => $paymentRequest->getRecoveryUrl(),
            'merchantOrderId' => $paymentRequest->getCartId(),
            'cardHolderName' => $paymentRequest->getFullName(),
            'cardHolderEmail'  => $paymentRequest->getEmail(),
            'description' => $paymentRequest->getDescription()
        );

        $request = $client->post($this->endpoint, array(), $parameters);
        $response = $request->send();

        $response = new \SimpleXMLElement($response->getBody());
        $paymentId = $response->paymentid;
        $paymentUrl = $response->hostedpageurl;
        $securityToken = $response->securitytoken;

        return "$paymentUrl?PaymentID=$paymentId";
    }

    /**
     * Send cancel request
     *
     * @param  string $paymentId
     * @return string
     */
    public function cancel($paymentId)
    {
        $client = new Guzzle();

        $parameters = array(
            'id' => $this->id,
            'password' => $this->password,
            'operationType' => 'voidauthorization',
            'paymentId' => $paymentId
        );

        $request  = $client->post($this->endpoint, array(), $parameters);
        $response = $request->send();
        $response = new \SimpleXMLElement($response->getBody());
        
        return array(
            'result' => strval($response->result),
            'authorizationcode' => strval($response->authorizationcode),
            'paymentid' => strval($response->paymentid),
            'merchantorderid' => strval($response->merchantorderid),
            'responsecode' => strval($response->responsecode),
            'customfield' => strval($response->customfield),
            'description' => strval($response->description)
        );
    }

    /**
     * Send confirm request
     *
     * @param  string                  $paymentId
     * @param  PaymentRequestInterface $paymentRequest
     * @return string
     */
    public function confirm($paymentId, PaymentRequestInterface $paymentRequest)
    {
        $client = new Guzzle();

        $parameters = array(
            'id' => $this->id,
            'password' => $this->password,
            'operationType' => 'confirm',
            'amount' => $paymentRequest->getAmount(),
            'currencyCode' => $paymentRequest->getCurrencyCode(),
            'merchantOrderId' => $paymentRequest->getCartId(),
            'description' => $paymentRequest->getDescription(),
            'paymentid' => $paymentId
        );

        $request  = $client->post($this->endpoint, array(), $parameters);
        $response = $request->send();
        $response = new \SimpleXMLElement($response->getBody());
        
        return array(
            'result' => strval($response->result),
            'authorizationcode' => strval($response->authorizationcode),
            'paymentid' => strval($response->paymentid),
            'merchantorderid' => strval($response->merchantorderid),
            'responsecode' => strval($response->responsecode),
            'customfield' => strval($response->customfield),
            'description' => strval($response->description),
        );
    }

    /**
     * Resolve notification parameters
     *
     * @param  array $parameters
     * @return array
     */
    public function resolveNotification($parameters)
    {
        $required = array(
            "authorizationcode",
            "cardcountry",
            "cardexpirydate",
            "customfield",
            "maskedpan",
            "merchantorderid",
            "paymentid",
            "responsecode",
            "result",
            "rrn",
            "securitytoken",
            "threedsecure"
        );

        $missing = array();
        foreach ($required as $key) {
            if (array_key_exists($key, $parameters) === false) {
                $missing[] = $key;
            }
        }
        if (empty($missing) === false) {
            throw new \Exception('Required fields are missing: ' . implode(', ', $missing));
        }

        return $parameters;
    }

    /**
     * Generate notification response
     *
     * @param  string   $url
     * @return Response
     */
    public function generateNotificationResponse($url)
    {
        return new Response($url);
    }
}