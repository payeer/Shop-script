<?php

class payeerPayment extends waPayment implements waIPayment
{
    const VERSION = '1.0';

    protected function initControls(){}

    public function allowedCurrency()
	{
        return array(
            'UAH',
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

        $m_desc = base64_encode(htmlentities($order->description, ENT_QUOTES, 'utf-8') . ' (' . $this->app_id . '-' . $this->merchant_id . ')');
		
		$arHash = array(
			$m_shop,
			$m_orderid,
			$m_amount,
			$m_curr,
			$m_desc,
			$m_key
		);
		
		$m_sign = strtoupper(hash('sha256', implode(':', $arHash)));
		
		// проверка принадлежности ip списку доверенных ip
		$list_ip_str = str_replace(' ', '', $ip_filter);
		
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
		
		if (!$valid_ip)
		{
			$log_text = 
				"--------------------------------------------------------\n".
				"shop				" . $m_shop . "\n".
				"order id			" . $m_orderid . "\n".
				"amount				" . $m_amount . "\n".
				"currency			" . $m_curr . "\n".
				"sign				" . $m_sign . "\n\n";
			
			$to = $email_error;
			$subject = "Error payment";
			$message = "Failed to make the payment through the system Payeer for the following reasons:\n\n";
			$message.=" - the ip address of the server is not trusted\n";
			$message.="   trusted ip: " . $ip_filter . "\n";
			$message.="   ip of the current server: " . $_SERVER['REMOTE_ADDR'] . "\n";
			$message.="\n".$log_text;
			$headers = "From: no-reply@" . $_SERVER['HTTP_SERVER'] . "\r\nContent-type: text/plain; charset=utf-8 \r\n";
			mail($to, $subject, $message, $headers);
		}

        $view = wa()->getView();
		$view->assign('m_url', $m_url);
		$view->assign('m_shop', $m_shop);
        $view->assign('m_orderid', $m_orderid);
		$view->assign('m_amount', $m_amount);
		$view->assign('m_curr', $m_curr);
		$view->assign('m_desc', $m_desc);
        $view->assign('m_sign', $m_sign);
        $view->assign('auto_submit', $auto_submit);
		$view->assign('valid_ip', $valid_ip);
		
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
					
					if ($request['m_sign'] == $sign_hash && $request['m_status'] == 'success')
					{
						echo $request['m_orderid'].'|success';
					}
					else
					{
						echo $request['m_orderid'].'|error';
						
						$to = $this->email_error;
						$subject = "Error payment";
						$message = "Failed to make the payment through the system Payeer for the following reasons:\n\n";
						
						if ($request["m_sign"] != $sign_hash)
						{
							$message.=" - Do not match the digital signature\n";
						}
						
						if ($request['m_status'] != "success")
						{
							$message.=" - The payment status is not success\n";
						}
						
						$message .= "\n" . $log_text;
						$headers = "From: no-reply@" . $_SERVER['HTTP_SERVER'] . "\r\nContent-type: text/plain; charset=utf-8 \r\n";
						mail($to, $subject, $message, $headers);
					}
				}

				return array(
					'template' => false
				);
                break;

            case 'success':
                $url = $this->getAdapter()->getBackUrl(waAppPayment::URL_SUCCESS, $transaction_data);

				$callback_method = self::CALLBACK_PAYMENT;
				$transaction_data = $this->saveTransaction($transaction_data, $request);
				$callback = $this->execAppCallback($callback_method, $transaction_data);
				self::addTransactionData($transaction_data['id'], $callback);
						
				if ($this->log_file)
				{
					file_put_contents($_SERVER['DOCUMENT_ROOT'].'/payeer_orders.log', $log_text, FILE_APPEND);
				}
				
				return array(
                    'redirect' => $url
                );
                break;
				
            case 'fail':
				$url = $this->getAdapter()->getBackUrl(waAppPayment::URL_FAIL, $transaction_data);
				
				$callback_method = self::CALLBACK_DECLINE;
				$transaction_data = $this->saveTransaction($transaction_data, $request);
				$callback = $this->execAppCallback($callback_method, $transaction_data);
				self::addTransactionData($transaction_data['id'], $callback);
				
				if ($this->log_file)
				{
					file_put_contents($_SERVER['DOCUMENT_ROOT'].'/payeer_orders.log', $log_text, FILE_APPEND);
				}
				
				return array(
                    'redirect' => $url
                );
                break;
				
            default:
                $url = $this->getAdapter()->getBackUrl(waAppPayment::URL_FAIL, $transaction_data);
				
				$callback_method = self::CALLBACK_DECLINE;
				$transaction_data = $this->saveTransaction($transaction_data, $request);
				$callback = $this->execAppCallback($callback_method, $transaction_data);
				self::addTransactionData($transaction_data['id'], $callback);
				
				return array(
                    'redirect' => $url
                );
                break;
        }
    }

    protected function formalizeData($transaction_raw_data){

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