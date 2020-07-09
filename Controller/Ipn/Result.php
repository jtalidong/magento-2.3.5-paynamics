<?php
/**
 * Created by PhpStorm.
 * User: Janna
 * Date: 4/11/18
 * Time: 12:01 PM
 */


namespace Paynamics\Gateway\Controller\Ipn;

use Magento\Framework\App\Action\Action as AppAction;

class Result extends AppAction
{

    const STATUS_RECEIVED = '00';
    const STATUS_WAITCSP = '02';
    const STATUS_FAILED = '99';

    /**
     * @var \Paynamics\Gateway\Model\PaymentMethod
     */
    protected $_paymentMethod;

    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $_order;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;

    /**
     * @var Magento\Sales\Model\Order\Email\Sender\OrderSender
     */
    protected $_orderSender;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Paynamics\Gateway\Model\PaymentMethod $paymentMethod
     * @param Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
     * @param  \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Paynamics\Gateway\Model\PaymentMethod $paymentMethod,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->_paymentMethod = $paymentMethod;
        $this->_orderFactory = $orderFactory;
        $this->_orderSender = $orderSender;
        $this->_logger = $logger;
        $this->_checkoutSession = $checkoutSession;
        $this->_storeManager = $storeManager;
        $this->_urlBuilder = $context->getUrl();
        parent::__construct($context);
    }

    /**
     * Handle POST request to PAYNAMICS callback endpoint.
     */
    public function execute()
    {
        if (!empty($_GET["responseid"]) && !empty($_GET["requestid"])) {
            $order_id = base64_decode($_GET["requestid"]);
            $response_id = base64_decode($_GET["responseid"]);

            $mode = $this->_paymentMethod->getMerchantConfig('modes');

            if ($mode == 'Test') {
                $order_id = substr($order_id, 5);
            }

            $this->_order = $this->_loadOrder($order_id);
            $statusHistoryItem = $this->_order->getStatusHistoryCollection()->getFirstItem();


            $comment = $statusHistoryItem->getComment();

            if (!empty($comment))
            {
                $comment_parts = explode(" : ", $comment);

                if (strpos($comment_parts[0], 'Awaiting Paynamics payment') !== false)
                {
                    $this->messageManager->addWarningMessage(
                        __('Transaction is still pending. Awaiting Paynamics payment.')
                    );
                    $this->getResponse()->setRedirect(
                        $this->_getUrl('checkout/cart')
                    );
                } else {
                    $response_code = $comment_parts[0];
                    $response_message = $comment_parts[1];

                    switch ($response_code) {
                        case 'GR001':
                        case 'GR002':
                        case 'GR033':
                        $this->getResponse()->setRedirect(
                            $this->_getUrl('checkout/onepage/success')
                        );
                        break;

                        case 'GR053':
                        $this->messageManager->addErrorMessage(
                            __('Payment has been cancelled.')
                        );
                        $this->getResponse()->setRedirect(
                            $this->_getUrl('checkout/cart')
                        );
                        break;

                        default:
                        $this->messageManager->addErrorMessage(
                            __($response_message)
                        );
                        $this->getResponse()->setRedirect(
                            $this->_getUrl('checkout/cart')
                        );
                        break;
                    }
                }
            }
            else
            {
                $this->messageManager->addWarningMessage(
                    __('Transaction is still pending. Awaiting Paynamics payment.')
                );
                $this->getResponse()->setRedirect(
                    $this->_getUrl('checkout/cart')
                );
            }

//            $this->getResponse()->setRedirect(
//                $this->_getUrl('checkout/onepage/success')
//            );
        }
    }

    protected function _loadOrder($ref)
    {
        $order = $this->_orderFactory->create()->loadByIncrementId($ref);

        if (!($order && $order->getId())) {
            throw new \Exception('Could not find Magento order with id $order_id');
        }

        return $order;

    }

    protected function _getUrl($path, $secure = null)
    {
        $store = $this->_storeManager->getStore(null);

        return $this->_urlBuilder->getUrl(
            $path,
            ['_store' => $store, '_secure' => $secure === null ? $store->isCurrentlySecure() : $secure]
        );
    }
}
