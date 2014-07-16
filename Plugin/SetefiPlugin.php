<?php

/*
 * This file is part of the EoSetefi package.
 *
 * (c) 2014 Eymen Gunay <eymen@egunay.com>
 */

namespace Eo\SetefiBundle\Plugin;

use Eo\SetefiBundle\Client;
use Eo\SetefiBundle\Payment\PaymentRequest;
use Eo\JmsPaymentExtraBundle\Plugin\Exception\Action\ResponseAction;
use JMS\Payment\CoreBundle\Model\ExtendedDataInterface;
use JMS\Payment\CoreBundle\Model\FinancialTransactionInterface;
use JMS\Payment\CoreBundle\Plugin\AbstractPlugin;
use JMS\Payment\CoreBundle\Plugin\PluginInterface;
use JMS\Payment\CoreBundle\Plugin\Exception\FinancialException;
use JMS\Payment\CoreBundle\Plugin\Exception\PaymentPendingException;
use JMS\Payment\CoreBundle\Plugin\Exception\Action\VisitUrl;
use JMS\Payment\CoreBundle\Plugin\Exception\ActionRequiredException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;

class SetefiPlugin extends AbstractPlugin
{
    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var string
     */
    protected $returnUrl;

    /**
     * @var string
     */
    protected $cancelUrl;

    /**
     * Class constructor
     *
     * @param RequestStack $requestStack
     * @param Client       $client
     */
    public function __construct(RequestStack $requestStack, Client $client)
    {
        $this->requestStack = $requestStack;
        $this->client = $client;
    }

    /**
     * {@inheritdoc}
     */
    public function processes($name)
    {
        return 'setefi' === $name;
    }

    /**
     * Create payment request
     * 
     * @param  FinancialTransactionInterface $transaction
     * @param  ExtendedDataInterface         $data
     * @return PaymentRequest
     */
    public function createPaymentRequest(FinancialTransactionInterface $transaction, ExtendedDataInterface $data)
    {
        $paymentRequest = new PaymentRequest(
            $transaction->getRequestedAmount(),
            $this->getCartId($data),
            $this->getReturnUrl($data),
            $this->getRecoveryUrl($data)
        );

        if ($fullName = $this->getFullName($data)) {
            $paymentRequest->setFullName($fullName);
        }
        if ($email = $this->getEmail($data)) {
            $paymentRequest->setEmail($email);
        }

        return $paymentRequest;
    }

    /**
     * {@inheritdoc}
     */
    public function approve(FinancialTransactionInterface $transaction, $retry)
    {
        return $this->approveAndDeposit($transaction, $retry);
    }

    /**
     * {@inheritdoc}
     */
    public function deposit(FinancialTransactionInterface $transaction, $retry)
    {
        $data = $transaction->getExtendedData();
        if ($data->has('payment_id') === false) {
            throw new \RuntimeException('No payment_id found');
        }

        $paymentRequest = $this->createPaymentRequest($transaction, $data);
        $response = $this->client->confirm($data->get('payment_id'), $paymentRequest);

        $data->set('result', $response['result']);
        $data->set('authorizationcode', $response['authorizationcode']);
        $data->set('responsecode', $response['responsecode']);
        $data->set('merchantorderid', $response['merchantorderid']);

        $transaction->setReferenceNumber($data->get('payment_id'));
        $transaction->setProcessedAmount($paymentRequest->getAmount());
        $transaction->setResponseCode(PluginInterface::RESPONSE_CODE_SUCCESS);
        $transaction->setReasonCode(PluginInterface::REASON_CODE_SUCCESS);
    }

    /**
     * {@inheritdoc}
     */
    public function approveAndDeposit(FinancialTransactionInterface $transaction, $retry)
    {
        $data = $transaction->getExtendedData();
        $paymentRequest = $this->createPaymentRequest($transaction, $data);

        // Redirect new transactions to payment page
        if ($transaction->getState() === FinancialTransactionInterface::STATE_NEW) {
            $redirectUrl   = $this->client->createPaymentUrl($paymentRequest);
            $actionRequest = new ActionRequiredException('User must authorize the transaction.');
            $actionRequest->setFinancialTransaction($transaction);
            $actionRequest->setAction(new VisitUrl($redirectUrl));

            throw $actionRequest;  
        }

        if ($data->has('payment_id') === false) {
            try {
                $request    = $this->requestStack->getCurrentRequest();
                $parameters = $this->client->resolveNotification($request->request->all());
                $response   = $this->client->generateNotificationResponse($this->getReturnUrl($data));
            } catch (MissingOptionsException $e) {
                $ex = new FinancialException($e->getMessage());
                $ex->setFinancialTransaction($transaction);
                $transaction->setResponseCode('Failed');
                $transaction->setReasonCode('Invalid setefi notification request');

                throw $ex;
            }

            $id = $parameters['paymentid'];
            $data->set('payment_id', $id);

            $actionRequest = new ActionRequiredException('Server must authorize the transaction.');
            $actionRequest->setFinancialTransaction($transaction);
            $actionRequest->setAction(new ResponseAction($response));

            throw $actionRequest;  
        }

        $transaction->setReferenceNumber($data->get('payment_id'));
        $transaction->setProcessedAmount($paymentRequest->getAmount());
        $transaction->setResponseCode(PluginInterface::RESPONSE_CODE_SUCCESS);
        $transaction->setReasonCode(PluginInterface::REASON_CODE_SUCCESS);
    }

    /**
     * Get returnUrl
     *
     * @param  ExtendedDataInterface $data
     * @return string
     */
    protected function getReturnUrl(ExtendedDataInterface $data)
    {
        if ($data->has('return_url')) {
            return $data->get('return_url');
        }
        else if (0 !== strlen($this->returnUrl)) {
            return $this->returnUrl;
        }

        throw new \RuntimeException('You must configure a return url.');
    }

    /**
     * Get recoveryUrl
     *
     * @param  ExtendedDataInterface $data
     * @return string
     */
    protected function getRecoveryUrl(ExtendedDataInterface $data)
    {
        if ($data->has('recovery_url')) {
            return $data->get('recovery_url');
        }
        else if (0 !== strlen($this->cancelUrl)) {
            return $this->cancelUrl;
        }

        throw new \RuntimeException('You must configure a recovery url.');
    }

    /**
     * Get cartId
     * 
     * @param  ExtendedDataInterface $data
     * @return string
     */
    protected function getCartId(ExtendedDataInterface $data)
    {
        if ($data->has('cart_id')) {
            return $data->get('cart_id');
        }

        throw new \RuntimeException('You must configure a cart id.');
    }

    /**
     * Get cartId
     * 
     * @param  ExtendedDataInterface $data
     * @return string
     */
    protected function getFullName(ExtendedDataInterface $data)
    {
        if ($data->has('full_name')) {
            return $data->get('full_name');
        }
    }

    /**
     * Get cartId
     * 
     * @param  ExtendedDataInterface $data
     * @return string
     */
    protected function getEmail(ExtendedDataInterface $data)
    {
        if ($data->has('email')) {
            return $data->get('email');
        }
    }
}