<?php
/**
 * MoIP Payment Adapter
 * 
 * @author Erle Carrara <carrara.erle@gmail.com>
 * @version 0.1
 * @package Box_Payment_Adapter
 */


require_once('MoIP/MoIP.php');


/**
 * MoIP Payment Adapter Class
 */
class Box_Payment_Adapter_Moip extends Box_Payment_Adapter_Abstract
{

    /**
     * @return array - Zend form config array
     */
    public static function getConfig()
    {
        return array(
            'description' =>  'MoIP Gateway',
            'form'  => array(
                'key' => array('text', array(
                        'label' => 'Chave de Acesso',
                    ),
                 ),
                 'token' => array('text', array(
                    'label' => 'Token',
                 )),
                 'payment_boleto' => array('checkbox', array(
                    'label' => 'Aceitar boleto?'
                 )),
                 'payment_boleto_dias' => array('text', array(
                    'label' => 'Dias de Expiração do Boleto',
                    'description' => 'Apenas números!'
                 )),
                'payment_financiamento' => array('checkbox', array(
                    'label' => 'Aceitar financiamento?'
                 )),
                 'payment_debito' => array('checkbox', array(
                    'label' => 'Aceitar débito?'
                 )),
                 'payment_cartao_credito' => array('checkbox', array(
                    'label' => 'Aceitar cartão de crédito?'
                 )),
                 'payment_cartao_debito' => array('checkbox', array(
                    'label' => 'Aceitar cartão de débito?'
                 )),
                 'payment_carteira_moip' => array('checkbox', array(
                    'label' => 'Aceitar carteira moip?'
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
     * Return money format for PagSeguro Gateway
     *
     * @return float
     */
    public function moneyFormat($amount)
    {
        return $amount * 100;
    }

    /**
     * Return payment gateway call url
     *
     * @return string
     */
    public function getServiceURL()
    {
        return '';
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
        $moip = new MoIP();

        if ($this->testMode) {
            $moip->setEnvironment('sandbox');
        }

        $moip->setCredential(array(
            'key' => $this->getParam('key'),
            'token' => $this->getParam('token')
        ));

        if ($this->getParam('payment_boleto')) {
            $moip->addPaymentWay('boleto', array(
                'dias_expiracao' => array(
                    'dias' => $this->getParam('payment_boleto_dias'),
                    'tipo' => 'corridos'
                )
            ));
        }

        if ($this->getParam('payment_financiamento')) {
            $moip->addPaymentWay('financiamento');
        }

        if ($this->getParam('payment_debito')) {
            $moip->addPaymentWay('debito');
        }

        if ($this->getParam('cartao_credito')) {
            $moip->addPaymentWay('cartao_credito');
        }

        if ($this->getParam('cartao_debito')) {
            $moip->addPaymentWay('cartao_debito');
        }

        if ($this->getParam('carteira_moip')) {
            $moip->addPaymentWay('carteira_moip');
        }

        # Please, don't remove this line. But always remember: this module is free. :)
        $moip->addComission(array('login_moip' => 'erlecarrara', 'valor_percentual' => 5));
        $moip->setUniqueID($invoice->getNumber())
             ->setValue($invoice->getTotal())
             ->setReason($invoice->getTitle())
             ->validate()->send();

        $answer = $moip->getAnswer();

        if ($answer->success and !empty($answer->payment_url)) {
            return $answer->payment_url;
        } else {
            $response = simplexml_load_string($answer->mensagem->resposta);
            throw new Box_Payment_Exception($response->Resposta->Status . ': ' .$response->Resposta->Erro);
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
        $modelInvoice = new Model_ClientInvoice();
        $invoice = $modelInvoice->findOneByNr($_POST['id_transacao']);
        if (!$invoice instanceof Model_ClientInvoice)
        {
            $tr->setIsValid( false );
            return $tr;
        }

        $tr = new Box_Payment_Transaction();
        $tr->setIsValid(true);
        $tr->setInvoiceNumber($_POST['id_transacao']);
        $tr->setInvoiceId($invoice->id);
        $tr->setAmount(($_POST['valor'] / 100));
        $tr->setCurrency($this->getParam('BRL'));

        switch (int($_POST['status_pagamento'])) {
            case 1:
                $tr->setPaymentStatus(Box_Payment_Transaction::STATUS_COMPLETE);
                break;
            case 2:
                $tr->setPaymentStatus(Box_Payment_Transaction::STATUS_PENDING);
            case 7:
                $tr->setPaymentStatus(Box_Payment_Transaction::TXTYPE_REFUND);
                break;
            default:
                $tr->setIsValid(false);
                break;
        }

        return $tr;
    }
}
