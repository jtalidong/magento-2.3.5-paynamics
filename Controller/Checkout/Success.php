<?php

namespace Paynamics\Gateway\Controller\Checkout;

use Magento\Framework\Controller\ResultFactory;

class Success extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $_urlBuilder;

    protected $_paymentMethod;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Paynamics\Gateway\Model\PaymentMethod $paymentMethod
    )
    {
        $this->_checkoutSession = $checkoutSession;
        $this->_storeManager = $storeManager;
        $this->_urlBuilder = $context->getUrl();
        parent::__construct($context);
    }

    /**
     * Unset the quote and redirect to checkout success.
     */
    public function execute()
    {
        if (!empty($_GET["responseid"]) && !empty($_GET["requestid"])) {
            $order_id = base64_decode($_GET["requestid"]);
            $response_id = base64_decode($_GET["responseid"]);

            $mode = $this->_paymentMethod->getMerchantConfig('modes');

            if ($mode == 'Test') {
                $mid = $this->_paymentMethod->getMerchantConfig('test_mid');
                $mkey = $this->_paymentMethod->getMerchantConfig('test_mkey');
                $client = new SoapClient("https://testpti.payserv.net/Paygate/ccservice.asmx?WSDL");
            } elseif ($mode == 'Live') {
                $mid = $this->_paymentMethod->getMerchantConfig('live_mid');
                $mkey = $this->_paymentMethod->getMerchantConfig('live_mkey');
                $client = new SoapClient("https://ptipaygate.paynamics.net/ccservice/ccservice.asmx?WSDL");
            }

            $request_id = '';
            $length = 8;
            $characters = '0123456789';
            $charactersLength = strlen($characters);
            $randomString = '';
            for ($i = 0; $i < $length; $i++) {
                $request_id .= $characters[rand(0, $charactersLength - 1)];
            }

            $merchantid = $mid;
            $requestid = $request_id;
            $org_trxid = $response_id;
            $org_trxid2 = "";
            $cert = $mkey;
            $data = $merchantid . $requestid . $org_trxid . $org_trxid2;
            $data = utf8_encode($data . $cert);

            // create signature
            $sign = hash("sha512", $data);

            $params = array("merchantid" => $merchantid,
                "request_id" => $requestid,
                "org_trxid" => $org_trxid,
                "org_trxid2" => $org_trxid2,
                "signature" => $sign);

            $result = $client->query($params);
            $response_code = $result->queryResult->txns->ServiceResponse->responseStatus->response_code;
            $response_message = $result->queryResult->txns->ServiceResponse->responseStatus->response_message;

            switch ($response_code) {
                case 'GR001':
                case 'GR002':
                case 'GR033':
                    $this->getResponse()->setRedirect(
                        $this->_getUrl('checkout/onepage/success')
                    );
                    break;
                default:
                    $this->messageManager->addErrorMessage(
                        __($response_message)
                    );
                    /** @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
                    $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
                    return $resultRedirect->setPath('checkout/cart');
                    break;
            }

//            $this->getResponse()->setRedirect(
//                $this->_getUrl('checkout/onepage/success')
//            );
        }
    }

    /**
     * Build URL for store.
     *
     * @param string $path
     * @param int $storeId
     * @param bool|null $secure
     *
     * @return string
     */
    protected function _getUrl($path, $secure = null)
    {
        $store = $this->_storeManager->getStore(null);

        return $this->_urlBuilder->getUrl(
            $path,
            ['_store' => $store, '_secure' => $secure === null ? $store->isCurrentlySecure() : $secure]
        );
    }
}
