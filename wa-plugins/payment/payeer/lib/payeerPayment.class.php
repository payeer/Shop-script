<?php

class payeerPayment extends waPayment implements waIPayment
{
    const VERSION = '1.0';

    protected function initControls(){}

    public function allowedCurrency()
	{
        return array(
            'RUB',
			'RUR',
            'USD',
            'EUR',
        ); 
    }

    public function payment($payment_form_data, $order_data, $auto_submit = false)
	{
        $order = waOrder::factory($order_data);
		$m_url = $this->m_url;
        $m_shop = $this->m_shop;
		$m_key = $this->m_key;
        $m_orderid = $order->id;
        $m_amount = number_format($order->total, 2, '.', '');
        $m_curr = $order->currency == 'RUR' ? 'RUB' : $order->currency;
        $m_desc = base64_encode('Оплата заказа №' . $m_orderid . ' (' . $this->app_id . '-' . $this->merchant_id . ')');
		
		$arHash = array(
			$m_shop,
			$m_orderid,
			$m_amount,
			$m_curr,
			$m_desc,
			$m_key
		);
		
		$m_sign = strtoupper(hash('sha256', implode(':', $arHash)));
		
        $view = wa()->getView();
		$view->assign('m_url', $m_url);
		$view->assign('m_shop', $m_shop);
        $view->assign('m_orderid', $m_orderid);
		$view->assign('m_amount', $m_amount);
		$view->assign('m_curr', $m_curr);
		$view->assign('m_desc', $m_desc);
        $view->assign('m_sign', $m_sign);
        $view->assign('auto_submit', $auto_submit);
		
		return $view->fetch($this->path . '/templates/payment.html');
    }

    protected function callbackInit($request)
	{
		$desc = base64_decode($request['m_desc']);
		preg_match_all("/\(+.+\-+[0-9]+\)+/", $desc, $matches);
		preg_match('/\((.+)\)/', $matches[0][0], $m);
		$opt = explode('-', $m[1]);
		
		$this->app_id = $opt[0]; 
		$this->merchant_id = $opt[1];
        return parent::callbackInit($request);
    }

    public function callbackHandler($request)
	{	
		$transaction_data = $this->formalizeData($request);
        $action = $request['action'];
        $url = null;

        switch ($action) 
		{
            case 'status':
			
				if (isset($request['m_operation_id']) && isset($request['m_sign']))
				{
					$err = false;
					$message = '';
					
					// запись логов
			
					$log_text = 
					"--------------------------------------------------------\n" .
					"operation id		" . $request['m_operation_id'] . "\n" .
					"operation ps		" . $request['m_operation_ps'] . "\n" .
					"operation date		" . $request['m_operation_date'] . "\n" .
					"operation pay date	" . $request['m_operation_pay_date'] . "\n" .
					"shop				" . $request['m_shop'] . "\n" .
					"order id			" . $request['m_orderid'] . "\n" .
					"amount				" . $request['m_amount'] . "\n" .
					"currency			" . $request['m_curr'] . "\n" .
					"description		" . base64_decode($request['m_desc']) . "\n" .
					"status				" . $request['m_status'] . "\n" .
					"sign				" . $request['m_sign'] . "\n\n";
					
					$log_file = $this->log_file;
					
					if (!empty($log_file))
					{
						file_put_contents($_SERVER['DOCUMENT_ROOT'] . $log_file, $log_text, FILE_APPEND);
					}
					
					// проверка цифровой подписи и ip

					$sign_hash = strtoupper(hash('sha256', implode(":", array(
						$request['m_operation_id'],
						$request['m_operation_ps'],
						$request['m_operation_date'],
						$request['m_operation_pay_date'],
						$request['m_shop'],
						$request['m_orderid'],
						$request['m_amount'],
						$request['m_curr'],
						$request['m_desc'],
						$request['m_status'],
						$this->m_key
					))));
					
					$valid_ip = true;
					$sIP = str_replace(' ', '', $this->ip_filter);
					
					if (!empty($sIP))
					{
						$arrIP = explode('.', $_SERVER['REMOTE_ADDR']);
						if (!preg_match('/(^|,)(' . $arrIP[0] . '|\*{1})(\.)' .
						'(' . $arrIP[1] . '|\*{1})(\.)' .
						'(' . $arrIP[2] . '|\*{1})(\.)' .
						'(' . $arrIP[3] . '|\*{1})($|,)/', $sIP))
						{
							$valid_ip = false;
						}
					}
					
					if (!$valid_ip)
					{
						$message .= " - ip-адрес сервера не является доверенным\n" .
						"   доверенные ip: " . $sIP . "\n" .
						"   ip текущего сервера: " . $_SERVER['REMOTE_ADDR'] . "\n";
						$err = true;
					}

					if ($request['m_sign'] != $sign_hash)
					{
						$message .= " - не совпадают цифровые подписи\n";
						$err = true;
					}
			
					if (!$err)
					{
						// проверка статуса
						
						switch ($request['m_status'])
						{
							case 'success':
								$callback_method = self::CALLBACK_PAYMENT;
								$transaction_data = $this->saveTransaction($transaction_data, $request);
								$callback = $this->execAppCallback($callback_method, $transaction_data);
								self::addTransactionData($transaction_data['id'], $callback);
								break;
								
							default:
								$message .= " - статус платежа не является success\n";
								$callback_method = self::CALLBACK_DECLINE;
								$transaction_data = $this->saveTransaction($transaction_data, $request);
								$callback = $this->execAppCallback($callback_method, $transaction_data);
								self::addTransactionData($transaction_data['id'], $callback);
								$err = true;
								break;
						}
					}
					
					if ($err)
					{
						$to = $this->email_error;

						if (!empty($to))
						{
							$message = "Не удалось провести платёж через систему Payeer по следующим причинам:\n\n" . $message . "\n" . $log_text;
							$headers = "From: no-reply@" . $_SERVER['HTTP_HOST'] . "\r\n" . 
							"Content-type: text/plain; charset=utf-8 \r\n";
							mail($to, 'Ошибка оплаты', $message, $headers);
						}
						
						exit($request['m_orderid'] . '|error');
					}
					else
					{
						exit($request['m_orderid'] . '|success');
					}
				}
                break;

            case 'success':
				$url = $this->getAdapter()->getBackUrl(waAppPayment::URL_SUCCESS, $transaction_data);
				return array('redirect' => $url);
				break;
				
            case 'fail':
				$url = $this->getAdapter()->getBackUrl(waAppPayment::URL_FAIL, $transaction_data);
				return array('redirect' => $url);
				break;
        }
    }

    protected function formalizeData($transaction_raw_data)
	{
        $transaction_data = parent::formalizeData($transaction_raw_data);
        $transaction_data['native_id'] = $transaction_raw_data['m_operation_id'];
        $transaction_data['amount'] = $transaction_raw_data['m_amount'];
        $transaction_data['currency_id'] = $transaction_raw_data['m_curr'];
        $transaction_data['order_id'] = $transaction_raw_data['m_orderid'];
		
        switch ($transaction_raw_data['action'])
		{
            case 'success':
                $transaction_data['state'] = self::STATE_CAPTURED;
                $transaction_data['type'] = self::OPERATION_AUTH_CAPTURE;
                $transaction_data['result'] = 1;
                break;
				
            case 'fail':
                $transaction_data['state'] = self::STATE_DECLINED;
                $transaction_data['type'] = self::OPERATION_CANCEL;
                $transaction_data['result'] = 1;
                break;
        }
		
        return $transaction_data;
    }
	
}