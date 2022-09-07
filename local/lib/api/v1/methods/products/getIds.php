<?php

namespace Local\API\Methods\Products;

use Bitrix\Main\HttpRequest;
use CIBlockElement;
use Local\API\Methods\BaseMethod;
use Local\API\Methods\IAPIMethodImplementation;

/**
 * Class handles 'products/ids/' POST request
 * It returns by given 1c codes bitrix id for products
 *
 * Income: json array with '1c-codes' key
 *
 * Outcome: assoc. array of 1c codes and bitrix ids
 */
class GetIds extends BaseMethod implements IAPIMethodImplementation
{
    /** @var string 1C code prop name */
    private const _1C_CODE_PROPERTY_NAME = 'PRODUCT_CODE_IN_1C';

    /**
     * RemoveDeliveryScheme constructor
     *
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
        if (!$this->loadModules(array('iblock')))
            return;

        $arIds = $this->getIdsFromRequestBody();
        if (!count($arIds))
            return;

        $this->httpStatus = '200';
        $this->data = array(
            'status' => 'success',
            'description' => '',
            'data' => json_encode($this->getBitrixIds($arIds))
        );
    }

    /**
     * Method extracts 1c codes array from request body
     *
     * @return array
     */
    private function getIdsFromRequestBody(): array
    {
        $arRequestBody = json_decode($this->requestBody, true);
        if (!array_key_exists('1c-codes', $arRequestBody) || !count($arRequestBody['1c-codes'])) {
            $this->httpStatus = '400';
            $this->data = array(
                'status' => 'error',
                'description' => '1C codes array missing or empty or failed to decode',
                'data' => ''
            );
            $this->stopProcessing = true;
            return array();
        } elseif (is_string($arRequestBody['1c-codes'])) {
            return array($arRequestBody['1c-codes']);
        } elseif (is_array($arRequestBody['1c-codes'])) {
            return $arRequestBody['1c-codes'];
        } else {
            return array();
        }
    }


    /**
     * Method obtains bitrix ids by given 1c codes
     *
     * @param array $arIds
     *
     * @return array
     */
    private function getBitrixIds(array $arIds): array
    {
        $arSelect = array('ID', 'PROPERTY_' . self::_1C_CODE_PROPERTY_NAME);
        $arFilter = array(array('LOGIC' => 'OR'));
        foreach ($arIds as $product1CId) {
            $arFilter[0][] = array('=PROPERTY_' . self::_1C_CODE_PROPERTY_NAME => trim($product1CId));
        }

        $dbIdsResult = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
        $arMapping = array();
        while ($arElement = $dbIdsResult->GetNext()) {
            $arMapping[trim($arElement['PROPERTY_' . self::_1C_CODE_PROPERTY_NAME . '_VALUE'])] = $arElement['ID'];
        }

        return $arMapping;
    }
}