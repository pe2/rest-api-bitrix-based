<?php

namespace Local\API\Methods\Orders;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\ArgumentNullException;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\NotImplementedException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Bitrix\Sale\Order;
use Bitrix\Sale\OrderBase;
use Exception;
use Local\API\Methods\BaseMethod;
use Local\API\Methods\IAPIMethodImplementation;
use OrderPropsHandler;
use OzonIntegration\OzonSendShippingOrder;
use OzonIntegration\OzonShippingOrderAfterApi;

/**
 * Class handles orders/set-properties/ POST request
 * It updates some order properties
 *
 * Income: json array with data. "OrderId" => array("Property_name_1" => "Property_value_1", ...)
 *
 * Outcome: 200/success
 */
class SetOrderProperties extends BaseMethod implements IAPIMethodImplementation
{
    /** @var array Array of property codes available to change via request */
    private const AR_AVAILABLE_FIELDS_CODES = array(
        'COURIER_NAME', 'POSTING_ID'
    );

    /** @var Order Loader order */
    private OrderBase $oOrder;

    /**
     * SetOrderProperties constructor
     *
     * @param HttpRequest $request
     * @param string $requestBody
     */
    public function __construct(HttpRequest $request, string $requestBody)
    {
        parent::__construct($request, $requestBody);
    }

    /**
     * Request handling
     *
     * @throws Exception
     */
    public function execute(): void
    {
        if (!$this->loadModules(array('sale')))
            return;

        $arOrderData = $this->getOrderDataFromRequestBody();
        if (!count($arOrderData))
            return;

        $orderId = array_key_first($arOrderData);
        if (!$this->checkOrder(strval($orderId)))
            return;

        $arOrderProps = $this->normalizeOrderProperties(reset($arOrderData));
        if (!count($arOrderProps))
            return;

        if (!$this->updateOrderProps($arOrderProps))
            return;

        $this->httpStatus = '200';
        $this->data = array(
            'status' => 'success',
            'description' => 'Order properties successfully updated',
            'data' => ''
        );
    }

    /**
     * Method extracts data from request body
     *
     * @return array
     */
    private function getOrderDataFromRequestBody(): array
    {
        $arOrderData = json_decode($this->requestBody, true);
        if (!is_array($arOrderData) || !count($arOrderData)) {
            $this->httpStatus = '400';
            $this->data = array(
                'status' => 'error',
                'description' => 'Order information is missing or empty or failed to decode',
                'data' => ''
            );
            $this->stopProcessing = true;
            return array();
        } else {
            return $arOrderData;
        }
    }

    /**
     * Method validates given order id
     *
     * @param string $orderId Order id
     *
     * @return bool
     */
    private function checkOrder(string $orderId): bool
    {
        if (0 >= strlen($orderId)) {
            $this->httpStatus = '400';
            $this->data = array(
                'status' => 'error',
                'description' => 'Order information is missing or empty or failed to decode',
                'data' => ''
            );
            $this->stopProcessing = true;
            return false;
        }

        $normalizedNumber = $this->normalizeOrderNumber($orderId);
        if (!$this->checkOrderExistence($normalizedNumber)) {
            $this->httpStatus = '400';
            $this->data = array(
                'status' => 'error',
                'description' => 'Failed to get order information for order \'' . $normalizedNumber . '\'',
                'data' => ''
            );
            $this->stopProcessing = true;
            return false;
        }

        return true;
    }

    /**
     * Method normalizes order id
     *
     * @param string $orderId Order id
     *
     * @return string
     */
    public function normalizeOrderNumber(string $orderId): string
    {
        $orderId = trim($orderId);
        $firstSymbol = mb_substr($orderId, 0, 1);

        return ('H' === $firstSymbol || 'Н' === $firstSymbol) ? mb_substr($orderId, 1) : $orderId;
    }

    /**
     * Method checks order existence
     *
     * @param string $orderId Order id
     *
     * @return bool
     */
    private function checkOrderExistence(string $orderId): bool
    {
        try {
            $oOrder = Order::load($orderId);
            if (NULL !== $oOrder) {
                $this->oOrder = $oOrder;
                return true;
            } else {
                return false;
            }
        } catch (ArgumentNullException $e) {
            return false;
        }
    }

    /**
     * Method returns array of properties that can be set
     *
     * @param array $arOrderProperties Assoc. array of order properties
     *
     * @return array
     */
    public function normalizeOrderProperties(array $arOrderProperties): array
    {
        $arNormalizedOrderProperties = array();
        foreach ($arOrderProperties as $propertyName => $propertyValue) {
            if (in_array(trim($propertyName), self::AR_AVAILABLE_FIELDS_CODES)) {
                if (is_array($propertyValue) && count($propertyValue)) {
                    $arNormalizedOrderProperties[trim($propertyName)] = json_encode($propertyValue);
                } elseif (is_string($propertyValue) && 0 <= mb_strlen($propertyValue)) {
                    $arNormalizedOrderProperties[trim($propertyName)] = trim($propertyValue);
                }
            }
        }

        if (!count($arNormalizedOrderProperties)) {
            $this->httpStatus = '400';
            $this->data = array(
                'status' => 'error',
                'description' => 'None of the given order properties can be set',
                'data' => ''
            );
            $this->stopProcessing = true;
        }

        return $arNormalizedOrderProperties;
    }

    /**
     * Method sets/updates order properties
     *
     * @param array $arOrderProperties Order properties to update
     *
     * @return bool
     *
     * @throws Exception
     */
    private function updateOrderProps(array $arOrderProperties): bool
    {
        try {
            $oOrderProps = new OrderPropsHandler($this->oOrder);
            foreach ($arOrderProperties as $propertyName => $propertyValue) {
                $oOrderProps->updatePropObjectOrder($propertyName, $propertyValue);
            }
            $this->oOrder->save();
        } catch (ObjectPropertyException | ArgumentException | NotImplementedException | SystemException $e) {
            $this->httpStatus = '400';
            $this->data = array(
                'status' => 'error',
                'description' => 'Error on set fields values. ' . $e->getMessage(),
                'data' => ''
            );
            $this->stopProcessing = true;
            return false;
        }

        return true;
    }
}