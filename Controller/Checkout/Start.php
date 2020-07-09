<?php

namespace Paynamics\Gateway\Controller\Checkout;

class Start extends \Magento\Framework\App\Action\Action
{
	/**
	* @var \Magento\Checkout\Model\Session
	*/
	protected $_checkoutSession;

	/**
	* @var \Paynamics\Gateway\Model\PaymentMethod
	*/
	protected $_paymentMethod;

	/**
	* @param \Magento\Framework\App\Action\Context $context
	* @param \Magento\Checkout\Model\Session $checkoutSession
	* @param \Paynamics\Gateway\Model\PaymentMethod $paymentMethod
	*/
	public function __construct(
	\Magento\Framework\App\Action\Context $context,
	\Magento\Checkout\Model\Session $checkoutSession,
	\Paynamics\Gateway\Model\PaymentMethod $paymentMethod
	) {
		$this->_paymentMethod = $paymentMethod;
		$this->_checkoutSession = $checkoutSession;
		$this->_resultRedirectFactory = $context->getResultRedirectFactory();
		parent::__construct($context);
	}

	/**
	* Start checkout by creating request data and redirect customer to PAYNAMICS.
	*/
	public function execute()
	{
		$order = $this->_getOrder();
        $msg = $this->_makeComment("Awaiting Paynamics payment.");
        $status = "awaiting_paynamics"; //$this->_paymentMethod->getMerchantConfig('order_status');
        $this->_changeOrderState($status, $msg);

        $billing = $order->getBillingAddress();

        $mode = $this->_paymentMethod->getMerchantConfig('modes');

        $orderid = $order->getIncrementId();
		$origOrderId = $order->getIncrementId();
        $mid = '';
        $mkey = '';
        $wpf_url = '';

        if ($mode == 'Test') {
            $mid = $this->_paymentMethod->getMerchantConfig('test_mid');
            $mkey = $this->_paymentMethod->getMerchantConfig('test_mkey');
            $wpf_url = $this->_paymentMethod->getMerchantConfig('test_url');

            $orderid = substr($mid, -4) . "-" . $orderid;
        } elseif ($mode == 'Live') {
            $mid = $this->_paymentMethod->getMerchantConfig('live_mid');
            $mkey = $this->_paymentMethod->getMerchantConfig('live_mkey');
            $wpf_url = $this->_paymentMethod->getMerchantConfig('live_url');
        }
		
        $notification_url = $this->_paymentMethod->getMerchantConfig('notification_url');
        if (empty($notification_url)) {
            $notification_url = $this->_paymentMethod->getNotifyUrl();
        }		

        $_mid = $mid; //<-- your merchant id
        $_requestid = $orderid;
        $_ipaddress = $this->_paymentMethod->getMerchantConfig('ip_address');
        $_noturl = $notification_url; //$this->_paymentMethod->getNotifyUrl();
        $_resurl =  $this->_paymentMethod->getSuccessUrl();
        $_cancelurl = $this->_paymentMethod->getSuccessUrl();
        $_fname = $billing->getFirstName();
        $_mname = '';
        $_lname = $billing->getLastName();
        $_addr1 = $billing->getStreetLine(1);
        $_addr2 = $billing->getStreetLine(2);
        $_city = $billing->getCity();
        $_state = $billing->getRegion();
        $_country = $billing->getCountryId();
        $_zip = $billing->getPostcode();
        $_sec3d = "try3d";
        $_email = $order->getCustomerEmail();
        $_phone = $billing->getTelephone();
        $_mobile = $billing->getTelephone();
        $_clientip = $_SERVER['REMOTE_ADDR'];
        $_amount = number_format(($order->getBaseGrandTotal()), 2, '.', $thousands_sep = '');
        $_currency = strtoupper($order->getBaseCurrencyCode());
        $_trxtype = strtolower($this->_paymentMethod->getMerchantConfig('trxtype'));

        if ($mode == 'Test') {
            if ($_trxtype == 'authorized') {
                $_trxtype = 'auth';
            }
        }
		
        //trim forSIgn
        $_mid = trim($_mid);
        $_requestid = trim($_requestid);
        $_ipaddress = trim($_ipaddress);
        $_noturl = trim($_noturl);
        $_resurl = trim($_resurl);
        $_fname = trim($_fname);
        $_lname = trim($_lname);
        $_mname = trim($_mname);
        $_addr1 = trim($_addr1);
        $_addr2 = trim($_addr2);
        $_city = trim($_city);
        $_state = trim($_state);
        $_country = trim($_country);
        $_zip = trim($_zip);
        $_email = trim($_email);
        $_phone = trim($_phone);
        $_clientip = trim($_clientip);
        $_amount = trim($_amount);
        $_currency = trim($_currency);
        $_sec3d = trim($_sec3d);		

        $forSign = $_mid . $_requestid . $_ipaddress . $_noturl . $_resurl . $_fname . $_lname . $_mname . $_addr1 . $_addr2 . $_city . $_state . $_country . $_zip . $_email . $_phone . $_clientip . number_format(($_amount), 2, '.', $thousands_sep = '') . $_currency . $_sec3d;
        $cert = $mkey; //<-- your merchant key

        $_sign = hash("sha512", $forSign . $cert);
        $xmlstr = "";

        $strxml = "";

        $strxml = $strxml . "<?xml version=\"1.0\" encoding=\"utf-8\" ?>";
        $strxml = $strxml . "<Request>";
        $strxml = $strxml . "<orders>";
        $strxml = $strxml . "<items>";

        /*foreach ($order->getAllVisibleItems() as $item) {
            $strxml = $strxml . "<Items>";
            $strxml = $strxml . "<itemname>" . $item->getName() . "</itemname><quantity>" . intval($item->getQtyOrdered()) . "</quantity><amount>" . number_format($item->getBasePrice(), 2, '.', $thousands_sep = '') . "</amount>";
            $strxml = $strxml . "</Items>";
        }

        if ($order->getShippingAmount() != "" || $order->getShippingAmount() != null) {
            $strxml = $strxml . "<Items>";
            $strxml = $strxml . "<itemname>Shipping Fee</itemname><quantity>1</quantity><amount>" . number_format($order->getShippingAmount(), 2, '.', $thousands_sep = '') . "</amount>";
            $strxml = $strxml . "</Items>";
        }

        if ($order->getDiscountAmount() != "" || $order->getDiscountAmount() != null) {
            $strxml = $strxml . "<Items>";
            $strxml = $strxml . "<itemname>Discount</itemname><quantity>1</quantity><amount>" . number_format($order->getDiscountAmount(), 2, '.', $thousands_sep = '') . "</amount>";
            $strxml = $strxml . "</Items>";
        }

        if ($order->getTaxAmount() != "" || $order->getTaxAmount() != null) {
            $strxml = $strxml . "<Items>";
            $strxml = $strxml . "<itemname>Tax</itemname><quantity>1</quantity><amount>" . number_format($order->getTaxAmount(), 2, '.', $thousands_sep = '') . "</amount>";
            $strxml = $strxml . "</Items>";
        }*/
		
		$strxml = $strxml . "<Items>";
		$strxml = $strxml . "<itemname>Payment for #" . $origOrderId . "</itemname><quantity>1</quantity><amount>" . number_format(($_amount), 2, '.', $thousands_sep = '') . "</amount>";
		$strxml = $strxml . "</Items>";

        $strxml = $strxml . "</items>";
        $strxml = $strxml . "</orders>";
        $strxml = $strxml . "<mid>" . $_mid . "</mid>";
        $strxml = $strxml . "<request_id>" . $_requestid . "</request_id>";
        $strxml = $strxml . "<ip_address>" . $_ipaddress . "</ip_address>";
        $strxml = $strxml . "<notification_url>" . $_noturl . "</notification_url>";
        $strxml = $strxml . "<response_url>" . $_resurl . "</response_url>";
        $strxml = $strxml . "<cancel_url>" . $_cancelurl . "</cancel_url>";
        $strxml = $strxml . "<mtac_url>" . $this->_paymentMethod->getMerchantConfig('mtac_url') . "</mtac_url>"; // pls set this to the url where your terms and conditions are hosted
        $strxml = $strxml . "<descriptor_note>" . $this->_paymentMethod->getMerchantConfig('descriptor') . "</descriptor_note>"; // pls set this to the descriptor of the merchant
        $strxml = $strxml . "<fname>" . $_fname . "</fname>";
        $strxml = $strxml . "<lname>" . $_lname . "</lname>";
        $strxml = $strxml . "<mname>" . $_mname . "</mname>";
        $strxml = $strxml . "<address1>" . $_addr1 . "</address1>";
        $strxml = $strxml . "<address2>" . $_addr2 . "</address2>";
        $strxml = $strxml . "<city>" . $_city . "</city>";
        $strxml = $strxml . "<state>" . $_state . "</state>";
        $strxml = $strxml . "<country>" . $_country . "</country>";
        $strxml = $strxml . "<zip>" . $_zip . "</zip>";
        $strxml = $strxml . "<secure3d>" . $_sec3d . "</secure3d>";
        $strxml = $strxml . "<trxtype>" . $_trxtype . "</trxtype>";
        $strxml = $strxml . "<email>" . $_email . "</email>";
        $strxml = $strxml . "<phone>" . $_phone . "</phone>";
        $strxml = $strxml . "<mobile>" . $_mobile . "</mobile>";
        $strxml = $strxml . "<client_ip>" . $_clientip . "</client_ip>";
        $strxml = $strxml . "<amount>" . number_format(($_amount), 2, '.', $thousands_sep = '') . "</amount>";
        $strxml = $strxml . "<currency>" . $_currency . "</currency>";
        $strxml = $strxml . "<mlogo_url>" . $this->_paymentMethod->getMerchantConfig('mlogo_url') . "</mlogo_url>"; // pls set this to the url where your logo is hosted
        $strxml = $strxml . "<pmethod></pmethod>";
        $strxml = $strxml . "<signature>" . $_sign . "</signature>";
        $strxml = $strxml . "</Request>";
        $b64string = base64_encode($strxml);

        echo '<form action="' . $wpf_url . '" method="post" id="paynamics_payment_form">
                <style type="text/css">
                    @import url(https://fonts.googleapis.com/css?family=Roboto);
                    .Absolute-Center {
                        font-family: "Roboto", Helvetica, Arial, sans-serif;
                        width: auto;
                        height: 100px;
                        position: absolute;
                        top:0;
                        bottom: 0;
                        left: 0;
                        right: 0;
                        margin: auto;
                        text-align: center;
                        font-size: 14px;
                    }
                </style>
                <div class="Absolute-Center">
                    <h3>Please wait while you are being redirected to Paynamics payment page.</h3>
                </div>
                <input type="hidden" name="paymentrequest" id="paymentrequest" value="' . $b64string . '" style="width:800px; padding: 20px;">
				<script type="text/javascript">
					window.onload=function(){
					    document.forms["paynamics_payment_form"].submit();
					}
				</script>
			</form>';
	}

	/**
	* Get order object.
	*
	* @return \Magento\Sales\Model\Order
	*/
	protected function _getOrder()
	{
		return $this->_checkoutSession->getLastRealOrder();
	}

    protected function _makeComment($comment, $suffix = '')
    {
        $fullComment = __($comment) . $suffix;
        return $fullComment;
    }

    protected function _changeOrderState($state, $message) {
        $this->_getOrder()->setState($state, true, $message, 1)->save();
        $this->_getOrder()->setStatus($state);
        $hist = $this->_getOrder()->addStatusHistoryComment($message);
        $hist->setIsCustomerNotified(true);
        $this->_getOrder()->save();

        //$this->_orderSender->send($this->_order);
        /// $this->_order->sendOrderUpdateEmail(true, $message);   /// FIX?
    }
}
