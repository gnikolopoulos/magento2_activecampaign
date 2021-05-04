<?php

namespace ID\ActiveCampaign\Observer;

use ID\ActiveCampaign\Helper\Data;
use GuzzleHttp\Client;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ResponseFactory;

class Order implements \Magento\Framework\Event\ObserverInterface
{

  protected $_logger;
  protected $_helper;
  protected $_path = '/api/3/ecomOrders';
  protected $_clientFactory;
  protected $_responseFactory;
  protected $_client;
  protected $_customerRepository;

  public function __construct(
    \Psr\Log\LoggerInterface $logger,
    Data $helper,
    ClientFactory $clientFactory,
    ResponseFactory $responseFactory,
    \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
  )
  {
    $this->_logger = $logger;
    $this->_helper = $helper;
    $this->_clientFactory = $clientFactory;
    $this->_responseFactory = $responseFactory;
    $this->_customerRepository = $customerRepository;

    $this->_client = $this->_clientFactory->create(['config' => [
      'base_uri' => $this->_helper->getLoginConfig('api_url')
    ]]);
  }

  public function execute(\Magento\Framework\Event\Observer $observer)
  {
    if( !$this->_helper->getLoginConfig('integration_id') ) {
      return $this;
    }

    $order = $observer->getEvent()->getOrder();

    if( $order->getCustomerIsGuest() ) {
      return $this;
    }

    $ac_id = $this->getCustomerId($order);

    if( !$ac_id ) {
      return $this;
    }

    $params = array(
      'headers' => array('Api-Token' => $this->_helper->getLoginConfig('key')),
      'json' => array( 'ecomOrder' => array(
        'externalid' => $order->getId(),
        'source' => 1,
        'email' => $order->getCustomerEmail() ?: $order->getBillingAddress()->getEmail(),
        'orderProducts' => $this->getOrderProducts($order),
        'totalPrice' => 100 * number_format($order->getGrandTotal(), 2, '.', ''),
        'externalCreatedDate' => $order->getCreatedAt(),
        'shippingAmount' => 100 * number_format($order->getShippingAmount(), 2, '.', ''),
        'taxAmount' => 100 * number_format($order->getBillingAddress()->getData('tax_amount'), 2, '.', ''),
        'discountAmount' => 100 * abs(number_format($order->getDiscountAmount(), 2, '.', '')),
        'currency' => $order->getOrderCurrencyCode(),
        'orderNumber' => $order->getIncrementId(),
        'connectionid' => $this->_helper->getLoginConfig('integration_id'),
        'customerid' => $ac_id
      )),
    );

    try {
      $response = $this->_client->request('POST', $this->_path, $params);

      if( $response->getStatusCode() == 201 ) {
        $response_data = json_decode($response->getBody());
        $order->setAcOrderId($response_data->ecomOrder->id);
        $order->save();
      }
    } catch (GuzzleException $exception) {
      $this->_logger->info('exception');
      $this->_logger->info($exception->getMessage());
    }

    return $this;
  }

  private function getCustomerId($order)
  {
    $attribute = $this->_customerRepository->getById($order->getCustomerId())->getCustomAttribute('ac_customer_id');
    if( $attribute ) {
      return $attribute->getValue();
    } else {
      return false;
    }
  }

  private function getOrderProducts($order)
  {
    $order_products = array();
    foreach( $order->getAllVisibleItems() as $item ) {
      $order_products[] = array(
        'externalid' => $item->getProductId(),
        'name' => $item->getData('name'),
        'price' => 100 * number_format($item->getData('price_incl_tax'), 2, '.', ''),
        'quantity' => intval( $item->getQtyOrdered() ),
        'category' => "",
        'sku' => $item->getData('sku'),
        'description' => $item->getData('description')
      );
    }

    return $order_products;
  }

}