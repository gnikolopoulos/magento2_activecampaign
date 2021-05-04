<?php

namespace ID\ActiveCampaign\Observer;

use ID\ActiveCampaign\Helper\Data;
use GuzzleHttp\Client;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ResponseFactory;

class Customer implements \Magento\Framework\Event\ObserverInterface
{

  protected $_logger;
  protected $_helper;
  protected $_path = '/api/3/ecomCustomers';
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
    $customer = $observer->getEvent()->getCustomer();

    if( $this->_helper->getLoginConfig('integration_id') && !$customer->getAcCustomerId() ) {
      $params = array(
        'headers' => array('Api-Token' => $this->_helper->getLoginConfig('key')),
        'json' => array( 'ecomCustomer' => array(
          'connectionid' => $this->_helper->getLoginConfig('integration_id'),
          'externalid' => $customer->getId(),
          'email' => $customer->getEmail(),
          'acceptsMarketing' => 1
        )),
      );

      try {
        $response = $this->_client->request('POST', $this->_path, $params);

        if( $response->getStatusCode() == 201 ) {
          $response_data = json_decode($response->getBody());
          $this->_logger->info($response_data->ecomCustomer->id);
          $customer->setAcCustomerId($response_data->ecomCustomer->id);
          $customer->save();
        }
      } catch (GuzzleException $exception) {
        $this->_logger->info('exception');
        $this->_logger->info($exception->getMessage());
      }
    }

    return $this;
  }

}