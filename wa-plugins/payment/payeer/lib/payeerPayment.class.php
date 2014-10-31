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
		$email_error = $this->email_error;
		
		$ip_filter = $this->ip_filter;
		
        $order = waOrder::factory($order_data);

		$m_url = $this->m_url;
		
        $m_shop = $this->m_shop;
		
		$m_key = $this->m_key;

        $m_orderid = $order->id;

        $m_amount = number_format($order->total, 2, '.', '');

        $m_curr = $order->currency;

        $m_desc = base64_encode($this->m_desc . ' (' . $this->app_id . '-' . $this->merchant_id . ')');
		
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
        $app_payment_method = null;
			
        switch ($action) 
		{
            case 'status':
			
				if (isset($request['m_operation_id']) && isset($request['m_sign']))
				{
					$m_key = $this->m_key;
					$arHash = array($request['m_operation_id'],
							$request['m_operation_ps'],
							$request['m_operation_date'],
							$request['m_operation_pay_date'],
							$request['m_shop'],
							$request['m_orderid'],
							$request['m_amount'],
							$request['m_curr'],
							$request['m_desc'],
							$request['m_status'],
							$m_key);
					$sign_hash = strtoupper(hash('sha256', implode(':', $arHash)));
					
					// проверка принадлежности ip списку доверенных ip
					
					$list_ip_str = str_replace(' ', '', $this->ip_filter);
					
					if (!empty($list_ip_str)) 
					{
						$list_ip = explode(',', $list_ip_str);
						$this_ip = $_SERVER['REMOTE_ADDR'];
						$this_ip_field = explode('.', $this_ip);
						$list_ip_field = array();
						$i = 0;
						$valid_ip = FALSE;
						foreach ($list_ip as $ip)
						{
							$ip_field[$i] = explode('.', $ip);
							if ((($this_ip_field[0] ==  $ip_field[$i][0]) or ($ip_field[$i][0] == '*')) and
								(($this_ip_field[1] ==  $ip_field[$i][1]) or ($ip_field[$i][1] == '*')) and
								(($this_ip_field[2] ==  $ip_field[$i][2]) or ($ip_field[$i][2] == '*')) and
								(($this_ip_field[3] ==  $ip_field[$i][3]) or ($ip_field[$i][3] == '*')))
								{
									$valid_ip = TRUE;
									break;
								}
							$i++;
						}
					}
					else
					{
						$valid_ip = TRUE;
					}
					
					$log_text = 
						"--------------------------------------------------------\n".
						"operation id		" . $request["m_operation_id"] . "\n".
						"operation ps		" . $request["m_operation_ps"] . "\n".
						"operation date		" . $request["m_operation_date"] . "\n".
						"operation pay date	" . $request["m_operation_pay_date"] . "\n".
						"shop				" . $request["m_shop"] . "\n".
						"order id			" . $request["m_orderid"] . "\n".
						"amount				" . $request["m_amount"] . "\n".
						"currency			" . $request["m_curr"] . "\n".
						"description		" . base64_decode($request["m_desc"]) . "\n".
						"status				" . $request["m_status"] . "\n".
						"sign				" . $request["m_sign"] . "\n\n";
						
					if (!empty($this->log_file))
					{
						file_put_contents($_SERVER['DOCUMENT_ROOT'] . $this->log_file, $log_text, FILE_APPEND);
					}
		
					if ($request['m_sign'] == $sign_hash && $request['m_status'] == 'success' && $valid_ip)
					{
						$callback_method = self::CALLBACK_PAYMENT;
						$transaction_data = $this->saveTransaction($transaction_data, $request);
						$callback = $this->execAppCallback($callback_method, $transaction_data);
						self::addTransactionData($transaction_data['id'], $callback);
				
						exit ($request['m_orderid'] . '|success');
					}
					else
					{
						$callback_method = self::CALLBACK_DECLINE;
						$transaction_data = $this->saveTransaction($transaction_data, $request);
						$callback = $this->execAppCallback($callback_method, $transaction_data);
						self::addTransactionData($transaction_data['id'], $callback);
				
						$to = $this->email_error;
						$subject = "Ошибка оплаты";
						$message = "Не удалось провести платёж через систему Payeer по следующим причинам:\n\n";
						
						if ($request["m_sign"] != $sign_hash)
						{
							$message .= " - Не совпадают цифровые подписи\n";
						}
						
						if ($request['m_status'] != "success")
						{
							$message .= " - Cтатус платежа не является success\n";
						}
						
						if (!$valid_ip)
						{
							$message .= " - ip-адрес сервера не является доверенным\n";
							$message .= "   доверенные ip: " . $this->ip_filter . "\n";
							$message .= "   ip текущего сервера: " . $_SERVER['REMOTE_ADDR'] . "\n";
						}
						
						$message .= "\n" . $log_text;
						$headers = "From: no-reply@" . $_SERVER['HTTP_SERVER'] . "\r\nContent-type: text/plain; charset=utf-8 \r\n";
						mail($to, $subject, $message, $headers);
						
						exit ($request['m_orderid'] . '|error');
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