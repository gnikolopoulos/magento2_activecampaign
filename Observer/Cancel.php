<?php

namespace ID\ActiveCampaign\Observer;

use ID\ActiveCampaign\Helper\Data;
use GuzzleHttp\Client;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ResponseFactory;

class Cancel implements \Magento\Framework\Event\ObserverInterface
{

  protected $_logger;
  protected $_helper;
  protected $_path = '/api/3/ecomOrders';
  protected $_clientFactory;
  protected $_responseFactory;
  protected $_client;

  public function __construct(
    \Psr\Log\LoggerInterface $logger,
    Data $helper,
    ClientFactory $clientFactory,
    ResponseFactory $responseFactory
  )
  {
    $this->_logger = $logger;
    $this->_helper = $helper;
    $this->_clientFactory = $clientFactory;
    $this->_responseFactory = $responseFactory;

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

    if( !$order->getAcOrderId() ) {
      return $this;
    }

    $params = array(
      'headers' => array('Api-Token' => $this->_helper->getLoginConfig('key')),
    );

    try {
      $response = $this->_client->request('DELETE', $this->_path . '/' . $order->getAcOrderId(), $params);

      if( $response->getStatusCode() == 200 ) {
        $order->setAcOrderId();
        $order->save();
      }
    } catch (GuzzleException $exception) {
      $this->_logger->info('exception');
      $this->_logger->info($exception->getMessage());
    }

    return $this;
  }

}