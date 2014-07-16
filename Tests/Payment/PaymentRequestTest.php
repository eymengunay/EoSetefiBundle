<?php

namespace Eo\SetefiBundle\Tests\Payment;

use Eo\SetefiBundle\Payment\PaymentRequest;

/**
 * PaymentRequestTest
 */
class PaymentRequestTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var PaymentRequest
     */
    protected $request;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->request = new PaymentRequest(1, 123, 'http://example.com/completed', 'http://example.com/canceled');
    }

    /**
     * Test toArray
     */
    public function testGetterSetter()
    {
        // Amount
        $this->assertEquals($this->request->getAmount(), 1);
        $this->request->setAmount(2);
        $this->assertEquals($this->request->getAmount(), 2);

        // Currency code
        $this->assertEquals($this->request->getCurrencyCode(), null);
        $this->request->setCurrencyCode('EUR');
        $this->assertEquals($this->request->getCurrencyCode(), 'EUR');

        // Cart id
        $this->assertEquals($this->request->getCartId(), 123);
        $this->request->setCartId(456);
        $this->assertEquals($this->request->getCartId(), 456);

        // Language
        $this->assertEquals($this->request->getLanguage(), 'ITA');
        $this->request->setLanguage('ENG');
        $this->assertEquals($this->request->getLanguage(), 'ENG');

        // Full name
        $this->assertEquals($this->request->getFullName(), null);
        $this->request->setFullName('John Doe');
        $this->assertEquals($this->request->getFullName(), 'John Doe');

        // Email
        $this->assertEquals($this->request->getEmail(), null);
        $this->request->setEmail('john@doe.com');
        $this->assertEquals($this->request->getEmail(), 'john@doe.com');

        // Description
        $this->assertEquals($this->request->getDescription(), null);
        $this->request->setDescription('Hello world');
        $this->assertEquals($this->request->getDescription(), 'Hello world');
    }
}