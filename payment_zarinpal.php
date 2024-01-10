<?php
/**
 * @package     Zarinpal payment gateway for j2store.
 * @subpackage  com_j2store
 * @subpackage 	Zarinpal 
 * @copyright   Ali Bahadori => https://bahadori.dev
 * @copyright   Copyright (C) 2024 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

defined('_JEXEC') or die('Restricted access');
require_once (JPATH_ADMINISTRATOR.'/components/com_j2store/library/plugins/payment.php');
class plgJ2StorePayment_zarinpal extends J2StorePaymentPlugin
{
	var $_element = 'payment_zarinpal';


    function _renderForm( $data )
    {
        $html = $this->_getLayout('form', $data);  
        return $html;
    }

    function _prePayment( $data )
    {
        $vars = new stdClass();

        $order_id = $data['order_id'];
        $orderpayment_id = $data['orderpayment_id'];
        
        $vars->callback_url = JUri::root() . "index.php?option=com_j2store&view=checkout&task=confirmPayment&orderpayment_id=$orderpayment_id$&orderpayment_type=$this->_element&paction=callback";

        $merchant_id = $this->params->get('zarinpal_merchant_id');

        if (! is_null($merchant_id)) {
            $params = array(
                "merchant_id" => $merchant_id,
                "amount" => $data['orderpayment_amount'],
                "callback_url" => $vars->callback_url,
                "currency" =>  $this->params->get('zarinpal_currency'),
                "description" => ' پرداخت برای سفارش :  ' . $order_id,
                "metadata" => [
                    "email" => "0",
                    "mobile"=>"0",
                    "order_id" => $order_id
                ],
            );

            $jsonData = json_encode($params);
            $ch = curl_init('https://api.zarinpal.com/pg/v4/payment/request.json');
            curl_setopt($ch, CURLOPT_USERAGENT, 'Zarinpal Rest Api v4');
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonData)
            ));

            $result = curl_exec($ch);
            $err = curl_error($ch);
            $result = json_decode($result, true, JSON_PRETTY_PRINT);
            curl_close($ch);

            if (! $err) {

                if ($result['data']['code'] === 100) {
                    $vars->zarinpal = 'https://www.zarinpal.com/pg/StartPay/' . $result['data']['authority'];
                } else {
                    $vars->errors = ['message' => self::error_message($result['errors']['code'])];
                }
            } else {
                $vars->errors = ['message' => 'خطا در اتصال به درگاه  : ' . $err];
            }
			
        } else {
            $vars->errors = ['message' => "مرچنت کد درگاه وارد نشده، لطفا از قسمت تنظیمات مربوط به افزونه مرچنت کد را وارد کنید."];
        }
      
        $html = $this->_getLayout('prepayment', $vars);    
        return $html;
    }


    function _postPayment( $data )
    {
        // Process the payment
        $app = JFactory::getApplication();
        $vars = new JObject();
        $merchant_id = $this->params->get('zarinpal_merchant_id');
        $status = $app->input->getString('Status');
        $order_id = (int)$app->input->getString('orderpayment_id');
        $order = F0FTable::getInstance('Order', 'J2StoreTable')->getClone();

        if ($order->load($order_id)) {
            if ($status === 'OK') {

                $params =[
                    "merchant_id" => $merchant_id,
                    "amount" => (int) $order->order_total,
                    "authority" => $app->input->getString('Authority'),
                ];  
        
                $jsonData = json_encode($params);
                $ch = curl_init('https://api.zarinpal.com/pg/v4/payment/verify.json');
                curl_setopt($ch, CURLOPT_USERAGENT, 'Zarinpal Rest Api v4');
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($jsonData)
                ));
        
                $result = curl_exec($ch);
                $err = curl_error($ch);
                $result = json_decode($result, true, JSON_PRETTY_PRINT);
                curl_close($ch);
    
                if (! $err) {
                    $zarinpal_status_code = $result['data']['code'];

                    if ($zarinpal_status_code === 100) {
                        $ref_id = $result['data']['ref_id'];
                        $this->confirmOrder($order, $ref_id);
                        $vars->message = 'تراکنش موفق.';
                        $vars->ref_id = $ref_id;
                    } elseif ($zarinpal_status_code === 101) {
                        $ref_id = $result['data']['ref_id'];

                        if ($this->getPaymentStatus($order->order_state_id) == JText::_('J2STORE_PENDING')) {
                            $this->confirmOrder($order, $ref_id);
                            $vars->message = 'تراکنش موفق.';
                            $vars->ref_id = $ref_id;
                        } else {
                            $vars->message = 'تراکنش موفق بوده و قبلا یکبار تایید شده است.';
                            $vars->ref_id = $ref_id;
                        }
                    } else {
                        $vars->message = self::error_message($result['errors']['code']);
                    }
                } else {
                    $vars->message = 'خطا در اتصال به درگاه برای تایید تراکنش : <br> ';
                    $vars->message .= $err;
                }
            } elseif ($status === 'NOK') {
                $vars->message = "پرداخت توسط کاربر لغو شد.";
            } else {
                $vars->message = "اطلاعات بازگشت از درگاه نادرست می باشند";
            }
        } else {
            $vars->message = "سفارش پیدا نشد.";
        }
        
        $html = $this->_getLayout('message', $vars);

        return $html;
    }

    function getPaymentStatus($payment_status) {
    	$status = '';
    	switch($payment_status) {
			case '1': $status = JText::_('J2STORE_CONFIRMED'); break;
			case '2': $status = JText::_('J2STORE_PROCESSED'); break;
			case '3': $status = JText::_('J2STORE_FAILED'); break;
			case '4': $status = JText::_('J2STORE_PENDING'); break;
			case '5': $status = JText::_('J2STORE_INCOMPLETE'); break;
			default: $status = JText::_('J2STORE_PENDING'); break;	
    	}
    	return $status;
    }

    public function confirmOrder($order, $ref_id)
    {
        $order->transaction_id = $ref_id;
        $order->transaction_status = 'Paid';
        $order->payment_complete();
        $order->empty_cart();
        $order->store();    
    }

    /**
     * Zarinpal error message.
     * 
     * @param int $code
     * @return string
     */
    public static function error_message($code)
    {
        $message = null;

        switch ($code) {
            case $code == -9:
                $message = ('اطلاعات ارسال شده نادرست می باشد.');
                $message .= "<br>" . ('1- مرچنت کد داخل تنظیمات ثبت نشده یا صحیح نمی باشد');
                $message .= "<br>" . ('2- مبلغ پرداختی کمتر یا بیشتر از حد مجاز می باشد');
            break; 
            case $code == -10:
                $message = ('ای پی یا مرچنت كد پذیرنده صحیح نیست.');
            break; 
            case $code == -11:
                $message = ('مرچنت کد فعال نیست، پذیرنده مشکل خود را به امور مشتریان زرین‌پال ارجاع دهد.');
            break; 
            case $code == -12:
                $message = ('تلاش بیش از دفعات مجاز در یک بازه زمانی کوتاه به امور مشتریان زرین پال اطلاع دهید');
            break; 
            case $code == -15:
                $message = ('درگاه پرداخت به حالت تعلیق در آمده است، پذیرنده مشکل خود را به امور مشتریان زرین‌پال ارجاع دهد.');
            break; 
            case $code == -16:
                $message = ('سطح تایید پذیرنده پایین تر از سطح نقره ای است.');
            break; 
            case $code == -17:
                $message = ('محدودیت پذیرنده در سطح آبی');
            break; 
            case $code == -30:
                $message = ('پذیرنده اجازه دسترسی به سرویس تسویه اشتراکی شناور را ندارد.');
            break; 
            case $code == -31:
                $message = ('حساب بانکی تسویه را به پنل اضافه کنید. مقادیر وارد شده برای تسهیم درست نیست. پذیرنده جهت استفاده از خدمات سرویس تسویه اشتراکی شناور، باید حساب بانکی معتبری به پنل کاربری خود اضافه نماید.');
            break; 
            case $code == -32:
                $message = ('مبلغ وارد شده از مبلغ کل تراکنش بیشتر است.');
            break; 
            case $code == -33:
                $message = ('درصدهای وارد شده صحیح نیست.');
            break; 
            case $code == -34:
                $message = ('مبلغ وارد شده از مبلغ کل تراکنش بیشتر است.');
            break; 
            case $code == -35:
                $message = ('تعداد افراد دریافت کننده تسهیم بیش از حد مجاز است.');
            break; 
            case $code == -36:
                $message = ('حداقل مبلغ جهت تسهیم باید 10000 ریال باشد');
            break; 
            case $code == -37:
                $message = ('یک یا چند شماره شبای وارد شده برای تسهیم از سمت بانک غیر فعال است.');
            break; 
            case $code == -38:
                $message = ('خط،عدم تعریف صحیح شبا،لطفا دقایقی دیگر تلاش کنید.');
            break; 
            case $code == -39:
                $message = ('خطایی رخ داده است به امور مشتریان زرین پال اطلاع دهید');
            break; 
            case $code == -50:
                $message = ('مبلغ پرداخت شده با مقدار مبلغ ارسالی در متد وریفای متفاوت است.');
            break; 
            case $code == -51:
                $message = ('پرداخت ناموفق');
            break; 
            case $code == -52:
                $message = ('خطای غیر منتظره‌ای رخ داده است. پذیرنده مشکل خود را به امور مشتریان زرین‌پال ارجاع دهد.');
            break; 
            case $code == -53:
                $message = ('پرداخت متعلق به این مرچنت کد نیست.');
            break; 
            case $code == -54:
                $message = ('اتوریتی نامعتبر است.');
            break;
        }

        return $message;
    }
}