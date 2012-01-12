<?php
class Box_Payment_Adapter_PagamentoDigital extends Box_Payment_Adapter_Abstract
{
    /**
     * @return array - Zend form config array
     */
    public static function getConfig()
    {
        return array(
            'description'     =>  'PagamentoDigital gateway',
            'form'  => array(
                'email' => array('text', array(
                            'label' => 'PagamentoDigital Email',
                            'description' => 'Your account email in PagamentoDigital.',
                    ),
                 ),
                 'token' => array('text', array(
                    'label' => 'PagamentoDigital Token',
                    'description' => 'Your token in PagamentoDigital.'
                 )),
                 'code' => array('text', array(
                    'label' => 'Store Code'
                 )),
                 'max_parcels' => array('text', array(
                    'label' => 'Max number of parcels'
                 ))
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
        return 'form';
    }

    /**
     * Return payment gateway call url
     *
     * @return string
     */
    public function getServiceURL()
    {
        return 'https://www.pagamentodigital.com.br/checkout/pay/';
    }

    /**
     * Return money format for Custom Gateway
     *
     * @return string
     */
    public function moneyFormat($amount)
    {
        return $amount * 100;
    }

    /**
     * Return form params
     *
     * @return array - key value pairs of data
     */
    public function singlePayment(Box_Payment_Invoice $invoice)
    {
        $buyer = $invoice->buyer();

        $ret = array(
            'email_loja' => $this->getParam('email'),
            'tipo_integracao' => 'PAD',
            'frete' => 0,
            'cod_loja' => $this->getParam('code'),
            'nome' => $buyer->getFullName(),
            'email' => $buyer->getEmail(),
            'parcela_maxima' => $this->getParam('max_parcels')
            'id_pedido' => $invoice->getNumber()
        );

        if (!empty($buyer->zip))
            $ret['cep'] = $buyer->zip;

        if (!empty($buyer->phone))
            $ret['telefone'] = $buyer->phone;

        if (!empty($buyer->city))
            $ret['cidade'] = $buyer->city;

        if (!empty($buyer->state))
            $ret['estado'] = $buyer->state;

        if (!empty($buyer->address))
            $ret['endereco'] = $buyer->address;

        $items = $invoice->items();
        for ($i = 1; $i < (count($items)+1); $i++) {
            $item = $items[$i-1];

            $ret = array_merge($ret, array(
                "produto_codigo_$i" => $item->id,
                "produto_descricao_$i" => $item->title,
                "produto_qtde_$i" => $item->quantity,
                "produto_valor_$i" => $item->price
            ));
        }

        ksort($ret);
        $token = $this->getParam('token');
        if (!empty($token)) {
            $querystring = http_build_query($ret) . $token;

            $hash = md5($querystring);
            $ret['hash'] = $hash;
        }

        return $ret;
    }

    /**
     * Perform recurent payment
     *
     * @param Box_Payment_Invoice $invoice
     */
    public function recurrentPayment(Box_Payment_Invoice $invoice)
    {
        return array();
    }

    /**
     * Handle IPN and return response object
     *
     * @return Box_Payment_Transaction
     */
    public function ipn()
    {
        $token = $this->getParam('token');

        $id_transacao = $_POST['id_transacao'];
        $valor_original = $_POST['valor_original'];
        $valor_loja = $_POST['valor_loja'];
        $status = $_POST['status'];
        $cod_status = int($_POST['cod_status']);
        $id_pedido = $_POST['id_pedido'];

        $qtde_produtos = $_POST['qtde_produtos'];

        $post = "transacao=$id_transacao" .
            "&status=$status" .
            "&cod_status=$cod_status" .
            "&valor_original=$valor_original" .
            "&valor_loja=$valor_loja" .
            "&token=$token";
        $enderecoPost = "https://www.pagamentodigital.com.br/checkout/verify/";

        ob_start();
        $ch = curl_init();
        curl_setopt ($ch, CURLOPT_URL, $enderecoPost);
        curl_setopt ($ch, CURLOPT_POST, 1);
        curl_setopt ($ch, CURLOPT_POSTFIELDS, $post);
        curl_exec ($ch);
        $resposta = ob_get_contents();
        ob_end_clean();

        $tr = new Box_Payment_Transaction();
        $tr->setIsValid(false);
        $tr->setInvoiceNumber($id_pedido);
        $tr->setAmount($valor_original);
        $tr->setCurrency('BRL');
        
        if( trim($resposta) == "VERIFICADO"){
            if ($cod_status == 0) {
                $tr->setIsValid(true);
                $tr->setPaymentStatus(Box_Payment_Transaction::PENDING);
            } else if ($cod_status == 1) {
                $tr->setIsValid(true);
                $tr->setPaymentStatus(Box_Payment_Transaction::STATUS_COMPLETE);
            }
        }

        return $tr;
    }
}

