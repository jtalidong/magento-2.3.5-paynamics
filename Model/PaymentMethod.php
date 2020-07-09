<?php

namespace Paynamics\Gateway\Model;

use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction;

class PaymentMethod extends \Magento\Payment\Model\Method\AbstractMethod
{

    const DEFAULT_CURRENCY_TYPE = 'PHP';

    const URL_SUCCESS = 'pnx/ipn/result';
    const URL_CALLBACK = 'pnx/ipn/callback';

    protected $_code = 'pnx';

    // Currency code conversions
    static protected $_currTypes = [
        'THB' => 'TH',
        'AUD' => 'AU',
        'GBP' => 'GB',
        'EUR' => 'EU',
        'HKD' => 'HK',
        'JPY' => 'JP',
        'NZD' => 'NZ',
        'SGD' => 'SG',
        'CHF' => 'CH',
        'USD' => 'US',
        'PHP' => 'PH'
    ];


    /**
     * @var \Magento\Framework\Exception\LocalizedExceptionFactory
     */
    protected $_exception;

    /**
     * @var \Magento\Sales\Api\TransactionRepositoryInterface
     */
    protected $_transactionRepository;

    /**
     * @var Transaction\BuilderInterface
     */
    protected $_transactionBuilder;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $_urlBuilder;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Magento\Framework\Exception\LocalizedExceptionFactory $exception
     * @param \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository
     * @param Transaction\BuilderInterface $transactionBuilder
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Framework\Exception\LocalizedExceptionFactory $exception,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    )
    {
        $this->_urlBuilder = $urlBuilder;
        $this->_exception = $exception;
        $this->_transactionRepository = $transactionRepository;
        $this->_transactionBuilder = $transactionBuilder;
        $this->_orderFactory = $orderFactory;
        $this->_storeManager = $storeManager;

        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * Instantiate state and set it to state object.
     *
     * @param string $paymentAction
     * @param \Magento\Framework\DataObject $stateObject
     */
    public function initialize($paymentAction, $stateObject)
    {
        $payment = $this->getInfoInstance();
        $order = $payment->getOrder();
        $order->setCanSendNewEmailFlag(false);

        // Set Initial Order Status
        $state = \Magento\Sales\Model\Order::STATE_NEW;
        $stateObject->setState($state);
        $stateObject->setStatus($state);
        $stateObject->setIsNotified(false);
    }

    // public static function getCurrencyCode($alpha) {
    // 	return self::$_currCodes[isset(self::$_currCodes[$alpha]) ? $alpha : self::DEFAULT_CURRENCY];
    // }

    public static function getCurrencyType($alpha)
    {
        return self::$_currTypes[isset(self::$_currTypes[$alpha]) ? $alpha : self::DEFAULT_CURRENCY_TYPE];
    }

    /**
     * Get return URL.
     *
     * @param int|null $storeId
     *
     * @return string
     */
    public function getSuccessUrl($storeId = null)
    {
        return $this->_getUrl(self::URL_SUCCESS, $storeId);
    }

    /**
     * Get notify (IPN) URL.
     *
     * @param int|null $storeId
     *
     * @return string
     */
    public function getNotifyUrl($storeId = null)
    {
        return $this->_getUrl(self::URL_CALLBACK, $storeId, false);
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
    protected function _getUrl($path, $storeId, $secure = null)
    {
        $store = $this->_storeManager->getStore($storeId);

        return $this->_urlBuilder->getUrl(
            $path,
            ['_store' => $store, '_secure' => $secure === null ? $store->isCurrentlySecure() : $secure]
        );
    }

    /**
     * Get success state from config
     *
     * @return string
     */
    public function getSuccessState()
    {
        return $this->getConfigData('order_status');
    }

    public function getMerchantConfig($configname)
    {
        return $this->getConfigData($configname);
    }

}
