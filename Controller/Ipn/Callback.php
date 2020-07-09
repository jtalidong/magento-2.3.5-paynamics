<?php

namespace Paynamics\Gateway\Controller\Ipn;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;

class Callback extends Action implements \Magento\Framework\App\CsrfAwareActionInterface
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
        \Psr\Log\LoggerInterface $logger
    )
    {
        $this->_paymentMethod = $paymentMethod;
        $this->_orderFactory = $orderFactory;
        $this->_orderSender = $orderSender;
        $this->_logger = $logger;
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(
        RequestInterface $request
    ): ?InvalidRequestException {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * Handle POST request to PAYNAMICS callback endpoint.
     */

    public function execute()
    {
        //echo $request = file_get_contents("php://input");
        //echo $_POST['paymentresponse'];
        //echo $request = $this->getRequest();

        $response = "";

        if (empty($_POST['paymentresponse']))
        {
            if (!$this->getRequest()->isPost()) {
                return;
            }

            $data = $this->getRequest()->getPostValue();

            if (!empty($data['paymentresponse'])) {
                echo "data['paymentresponse']: <br/>";
                echo $response = $data['paymentresponse'];
            }
            else
            {
                echo "print_r: <br/>";
                //print_r($data);
                $response = file_get_contents('php://input');

                $request = ' ' . $response;
                $ini = strpos($request, '"paymentresponse"');
                if ($ini == 0) return '';
                $ini += strlen('"paymentresponse"');
                $len = strpos($request, '------WebKit', $ini) - $ini;
                echo $response = substr($request, $ini, $len);
            }
        }
        else
        {
            echo "POST: <br/>";
            echo $response = $_POST['paymentresponse'];
        }

        try {
            $base64 = str_replace(" ", "+", $response);
            $response = base64_decode($base64); // this will be the actual xml
            $data = simplexml_load_string($response);

            $mode = $this->_paymentMethod->getMerchantConfig('modes');

            $order_id = $data->application->request_id;
            $mkey = '';

            if ($mode == 'Test') {
                $order_id = substr($order_id, 5);
                $mkey = $this->_paymentMethod->getMerchantConfig('test_mkey');
            } elseif ($mode == 'Live') {
                $mkey = $this->_paymentMethod->getMerchantConfig('live_mkey');
            }

            $this->_order = $this->_loadOrder($order_id);
            $forSign = $data->application->merchantid . $data->application->request_id . $data->application->response_id . $data->responseStatus->response_code . $data->responseStatus->response_message . $data->responseStatus->response_advise . $data->application->timestamp . $data->application->rebill_id;
            $cert = $mkey; //<-- your merchant key
            $_sign = hash("sha512", $forSign . $cert);
            //if signature verified and equal, update database
            //query and update here
            if ($data->application->signature == $_sign) {
                $comment = $data->responseStatus->response_code . " : " . $data->responseStatus->response_message . " : " . $data->application->response_id;
				
				//check if order status is already success/paid before update
                $state = $this->_order->getState();
                echo "<br/><br/>TO UPDATE STATUS FROM ". strtoupper($state);
				
				if ($state == 'processing')
                {
                    echo "<br/><br/>Order already processing. Do not update.";
                }
				else
				{
					// check if successful payment
					if ($data->responseStatus->response_code == 'GR001' || $data->responseStatus->response_code == 'GR002') {
						//update here
						$this->_handlePaymentReceived($comment);
						echo "<br/><br/>SUCCESSFUL";
					} // check if pending payment
					else if ($data->responseStatus->response_code == 'GR033') {
						//update here
						$this->_handlePaymentWaiting($comment);
						echo "<br/><br/>PENDING";
					} // check if payment was cancelled
					else if ($data->responseStatus->response_code == 'GR053') {
						//update here
						$this->_handlePaymentCancelled($comment);
						echo "<br/><br/>CANCELLED";
					} //check if failed payment
					else {
						//update here
						$this->_handlePaymentFailed($comment);
						echo "<br/><br/>FAILED";
					}
				}
            }
            else
            {
                echo "<br/><br/>Signatures do not match.";
            }

            $this->_success();

        } catch (\Exception $e) {
            $this->_logger->addError("Paynamics: error processing callback");
            $this->_logger->addError($e->getMessage());
            return $this->_failure();
        }
    }

    protected function _handlePaymentReceived($comment)
    {
        $msg = $this->_makeComment($comment);
        $this->_changeOrderState(\Magento\Sales\Model\Order::STATE_PROCESSING, $msg);
    }

    protected function _handlePaymentWaiting($comment)
    {
        $msg = $this->_makeComment($comment);
        $this->_changeOrderState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT, $msg);
    }

    protected function _handlePaymentCancelled($comment)
    {
        $msg = $this->_makeComment($comment);
        $this->_changeOrderState(\Magento\Sales\Model\Order::STATE_CANCELED, $msg);
    }

    protected function _handlePaymentFailed($comment)
    {
        $msg = $this->_makeComment($comment);
        $this->_changeOrderState(\Magento\Sales\Model\Order::STATE_HOLDED, $msg);
    }

    protected function _changeOrderState($state, $message)
    {
        $this->_order->setState($state, true, $message, 1)->save();
        $this->_order->setStatus($state);
        $hist = $this->_order->addStatusHistoryComment($message);
        //$hist->setIsCustomerNotified(true);
        $this->_order->save();

        $this->_orderSender->send($this->_order);
        /// $this->_order->sendOrderUpdateEmail(true, $message);   /// FIX?
    }

    protected function _loadOrder($ref)
    {
        $order = $this->_orderFactory->create()->loadByIncrementId($ref);

        if (!($order && $order->getId())) {
            throw new \Exception('Could not find Magento order with id $order_id');
        }

        return $order;

    }

    protected function _success()
    {
        $this->getResponse()
            ->setStatusHeader(200);
    }

    protected function _failure()
    {
        $this->getResponse()
            ->setStatusHeader(400);
    }

    protected function _makeComment($comment, $suffix = '')
    {
        $fullComment = __($comment) . $suffix;
        return $fullComment;
    }
}
