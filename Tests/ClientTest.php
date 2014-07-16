<?php

/*
 * This file is part of the EoSetefi package.
 *
 * (c) 2014 Eymen Gunay <eymen@egunay.com>
 */

namespace Eo\SetefiBundle\Tests;

use Eo\SetefiBundle\Client;
use Eo\SetefiBundle\Payment\PaymentRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * Client test
 */
class ClientTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $endpoint = 'https://test.monetaonline.it/monetaweb/payment/2/xml';
        $id       = getenv('SETEFI_ID') ?: '99999999';
        $password = getenv('SETEFI_PASSWORD') ?: '99999999';

        $this->client = new Client($endpoint, $id, $password);
    }

    /**
     * Test payment url
     */
    public function testPaymentUrl()
    {
        $code = uniqid();
        $paymentRequest = new PaymentRequest(50, $code, 'http://eymen.ngrok.com/test.php', 'http://example.com/canceled');
        $paymentRequest->setFullName('John Doe');
        $paymentRequest->setEmail('john@doe.com');
        $url = $this->client->createPaymentUrl($paymentRequest);
        
        $contents = file_get_contents($url);
        $this->assertNotEquals(strpos($contents, '<td id="amount">EUR 50,00</td>'), false);
        $this->assertNotEquals(strpos($contents, '<td id="trackid">'.$code.'</td>'), false);
        $this->assertNotEquals(strpos($contents, 'name="member" value="John Doe"'), false);
        $this->assertNotEquals(strpos($contents, 'name="cardHolderEmail" value="john@doe.com"'), false);
    }

    /**
     * Test resolve notification
     */
    public function testResolveNotification()
    {
        $parameters = array(
            "authorizationcode" => "fakews",
            "cardcountry"       => "ITALY",
            "cardexpirydate"    => "0718",
            "customfield"       => "",
            "maskedpan"         => "491620******5140",
            "merchantorderid"   => "1234567890",
            "paymentid"         => "1234567890",
            "responsecode"      => "000",
            "result"            => "APPROVED",
            "rrn"               => "999999999999",
            "securitytoken"     => "1234567890",
            "threedsecure"      => "H"
        );
        $this->assertEquals($parameters, $this->client->resolveNotification($parameters));
    }

    /**
     * Test payment url
     */
    public function testConfirm()
    {
        $code = uniqid();
        $paymentRequest = new PaymentRequest(50, $code, 'http://eymen.ngrok.com/test.php', 'http://example.com/canceled');
        $paymentRequest->setFullName('John Doe');
        $paymentRequest->setEmail('john@doe.com');
        $data = $this->client->sendConfirm(1234, $paymentRequest);
        
            print_r($data);
        die;
        $contents = file_get_contents($url);
        $this->assertNotEquals(strpos($contents, '<td id="amount">EUR 50,00</td>'), false);
        $this->assertNotEquals(strpos($contents, '<td id="trackid">'.$code.'</td>'), false);
        $this->assertNotEquals(strpos($contents, 'name="member" value="John Doe"'), false);
        $this->assertNotEquals(strpos($contents, 'name="cardHolderEmail" value="john@doe.com"'), false);
    }

    /**
     * Test generate notification response
     */
    public function testGenerateNotificationResponse()
    {
        $response = $this->client->generateNotificationResponse('http://example.com');
        $this->assertTrue($response instanceof Response);
    }
}