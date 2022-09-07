<?php

namespace Local\API\Methods\Orders;

use Bitrix\Main\HttpRequest;
use DateTime;
use DateTimeZone;
use Exception;
use Local\API\Methods\BaseMethod;
use Local\API\Methods\IAPIMethodImplementation;
use Local\Sale\OrderInformationHandler;

/**
 * Class handles orders/in-final-status/ GET request
 *
 * Income: -
 *
 * Outcome: 200/success/array of order ids in final status
 */
class InFinalStatus extends BaseMethod implements IAPIMethodImplementation
{

    /** @var string Final orders status to select orders */
    protected const FINAL_STATUS = 'F';

    /** @var string Time interval to select orders */
    protected const TIME_INTERVAL = '1 month ago';

    /**
     * @param HttpRequest $request
     * @param string $requestBody
     */
    public function __construct(HttpRequest $request, string $requestBody)
    {
        parent::__construct($request, $requestBody);
    }

    /**
     * Handle request
     */
    public function execute(): void
    {
        $arOrderIds = $this->selectOrdersInFinalStatus();

        if ('500' === $this->httpStatus) {
            return;
        }

        $this->httpStatus = '200';
        $this->data = array(
            'status' => 'success',
            'description' => '',
            'data' => $arOrderIds
        );
    }

    /**
     * Method returns array with filter for \OrderInformationHandler class
     *
     * @return string[][][]
     */
    protected function getFilterArrayForOrdersRequest(): array
    {
        return array('forFilter' => array('@STATUS_ID' => array(self::FINAL_STATUS)));
    }

    /**
     * Method returns orders list in final status
     *
     * @return array
     */
    private function selectOrdersInFinalStatus(): array
    {
        try {
            $oOrdersInfo = new OrderInformationHandler(
                $this->getStartDate(),
                $this->getEndDate(),
                array(),
                'asc',
                $this->getFilterArrayForOrdersRequest()
            );
            $oOrdersInfoResult = $oOrdersInfo->getOrdersBaseData();

            if (count($oOrdersInfoResult)) {
                $arOrderIds = array_keys($oOrdersInfoResult);
                return $this->modifyOrderIds($arOrderIds);
            }

            return array();
        } catch (Exception $e) {
            $this->httpStatus = '500';
            $this->data = array(
                'status' => 'error',
                'description' => $e->getMessage(),
                'data' => array()
            );
            $this->stopProcessing = true;

            return array();
        }
    }

    /**
     * Method returns start date interval
     *
     * @return string
     */
    private function getStartDate(): string
    {
        $oStartDate = new DateTime();
        $oStartDate->setTimestamp(strtotime(self::TIME_INTERVAL));
        $oStartDate->setTimezone(new DateTimeZone('Europe/Moscow'));
        return $oStartDate->format('d.m.Y H:i:s');
    }

    /**
     * Method returns end date interval
     *
     * @return string
     */
    private function getEndDate(): string
    {
        $oEndDate = new DateTime('now');
        $oEndDate->setTimezone(new DateTimeZone('Europe/Moscow'));
        return $oEndDate->format('d.m.Y H:i:s');
    }

    /**
     * Method modifies order ids for 1C
     *
     * @param $arOrderIds
     *
     * @return array
     */
    private function modifyOrderIds($arOrderIds): array
    {
        $arModifiedOrderIds = array();
        foreach ($arOrderIds as $orderId) {
            $arModifiedOrderIds[] = 'H' . $orderId;
        }

        return $arModifiedOrderIds;
    }
}