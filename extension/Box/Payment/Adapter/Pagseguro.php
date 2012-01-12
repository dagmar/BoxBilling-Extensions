<?php
/**
 * Pagseguro Payment Adapter
 * 
 * @author Erle Carrara <carrara.erle@gmail.com>
 * @version 0.1
 * @package Ec_Payment_Adapter
 */


require_once('PagSeguroLibrary/PagSeguroLibrary.php');


/**
 * Pagseguro Payment Adapter Class
 */
class Ec_Payment_Adapter_Pagseguro extends Box_Payment_Adapter_Abstract
{

    /**
     * @return array - Zend form config array
     */
    public static function getConfig()
    {
        return array(
            'description' =>  'PagSeguro Gateway',
            'form'  => array(
                'email' => array('text', array(
                        'label' => 'PagSeguro Email',
                    ),
                 ),
                 'token' => array('text', array(
                    'label' => 'Token',
                 )),
                 'currency' => array('text', array(
                    'label' => 'Currency'
                 )),
            ),
        );
    }

    /**
     * Return payment gateway type
     *
     * @return string
     */
    public function getType()
    {
        return Box_Payment_Adapter_Abstract::TYPE_REDIRECT;
    }

    /**
     * Return payment gateway call url
     *
     * @return string
     */
    public function getServiceURL()
    {
        return $this->_url;
    }

    /**
     * Return money format for PagSeguro Gateway
     *
     * @return float
     */
    public function moneyFormat($amount)
    {
        return $amount * 100;
    }

    /**
     * Create and send new payment request to PagSeguro Gateway
     *
     * @param Box_Payment_Invoice $invoice
     * @return string - url to redirect
     */
    public function singlePayment(Box_Payment_Invoice $invoice)
    {
        $buyer = $invoice->buyer();

        $paymentRequest = new PaymentRequest();
        $paymentRequest->setCurrency($this->getParam('currency'));
        $paymentRequest->setSenderName($buyer->getFullName());
        $paymentRequest->setSenderEmail($buyer->email);
        $paymentRequest->setReference($invoice->getNumber());

        if (!empty($buyer->phone)) {
            $phone = preg_replace("/[^0-9]/","", $buyer->phone);
            $paymentRequest->setSenderPhone(substr($phone, 0, 2), substr($phone, 2));
        }

        foreach ( $invoice->items() as $i ) {
            $paymentRequest->addItem(array(
                'id' => $i->id,
                'description' => $i->getTitle(),
                'quantity' => $i->getQuantity(),
                'amount' => $i->getPrice(),
                'weight' => 0,
                'shippingCost' => 0.0
            ));
        }

        try {
            $credentials = new AccountCredentials($this->getParam('email'), $this->getParam('token'));
            $url = $paymentRequest->register($credentials);
            return $url;
        } catch (PagSeguroServiceException $e) {
            throw new Box_Payment_Exception( "PagSeguro API Gateway: "  . $e->getMessage() );
        }
    }


    /**
     * Perform recurent payment
     *
     * @param Box_Payment_Invoice $invoice
     * @return array
     */
    public function recurrentPayment(Box_Payment_Invoice $invoice)
    {
        return array(); // Not implemented
    }

    /**
     * Handle IPN and return response object
     *
     * @return Box_Payment_Transaction
     */
    public function ipn()
    {
        $credentials = new AccountCredentials($this->getParam('email'), $this->getParam('token'));

        $type = $_POST['notificationType'];  
        $code = $_POST['notificationCode'];  

        if ($type === 'transaction') {  
            $transaction = NotificationService::checkTransaction(  
                $credentials,
                $code
            );

            $tr = new Box_Payment_Transaction();
            $tr->setIsValid(true);
            $tr->setInvoiceNumber($transaction->getReference());
            $tr->setAmount($transaction->getGrossAmount());
            $tr->setCurrency($this->getParam('currency'));

            $status = $transaction->getStatus()->getValue();
            switch ($status) {
                case TransactionStatus::PAID:
                    $tr->setPaymentStatus(Box_Payment_Transaction::STATUS_COMPLETE);
                    break;
                case TransationStatus::REFUNDED:
                    $tr->setPaymentStatus(Box_Payment_Transaction::TXTYPE_REFUND)
                default:
                    $tr->setIsValid(false);
            }

            return $tr;
        }
    }
}
