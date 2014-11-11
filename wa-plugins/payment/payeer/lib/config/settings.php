<?php

return array(
	'm_url' => array(
        'value' => '//payeer.com/merchant/',
        'title' => 'URL мерчанта',
        'description' => 'url для оплаты в системе Payeer',
        'control_type' => waHtmlControl::INPUT,
    ),
    'm_shop' => array(
        'value' => '',
        'title' => 'Идентификатор магазина',
        'description' => 'Идентификатор магазина, зарегистрированного в системе "PAYEER".<br/>Узнать его можно в <a href="http://www.payeer.com/account/">аккаунте Payeer</a>: "Аккаунт -> Мой магазин -> Изменить".',
        'control_type' => waHtmlControl::INPUT,
    ),
    'm_key' => array(
        'value' => '',
        'title' => 'Секретный ключ',
        'description' => 'Секретный ключ оповещения о выполнении платежа,<br/>который используется для проверки целостности полученной информации<br/>и однозначной идентификации отправителя.<br/>Должен совпадать с секретным ключем, указанным в <a href="http://www.payeer.com/account/">аккаунте Payeer</a>: "Аккаунт -> Мой магазин -> Изменить".',
        'control_type' => waHtmlControl::INPUT,
    ),
	'm_desc' => array(
        'value' => '',
        'title' => 'Комментарий к оплате',
        'description' => 'Пояснение оплаты заказа',
        'control_type' => waHtmlControl::INPUT,
    ),
	'ip_filter' => array(
        'value' => '',
        'title' => 'IP фильтр',
        'description' => 'Список доверенных ip адресов, можно указать маску',
        'control_type' => waHtmlControl::INPUT,
    ),
	'email_error' => array(
        'value' => '',
        'title' => 'Email для ошибок',
        'description' => 'Email для отправки ошибок оплаты',
        'control_type' => waHtmlControl::INPUT,
    ),
    'log_file' => array(
        'value' => true,
        'title' => 'Путь до файла для журнала оплат через Payeer (например, /payeer_orders.log)',
        'description' => 'Если путь не указан, то журнал не записывается',
        'control_type' => waHtmlControl::INPUT,
    )
);
