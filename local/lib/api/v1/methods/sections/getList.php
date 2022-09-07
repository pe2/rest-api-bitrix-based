<?php

namespace Local\API\Methods\Sections;

use Bitrix\Main\HttpRequest;
use Local\API\Methods\BaseMethod;
use Local\API\Methods\IAPIMethodImplementation;

/**
 * Class handles sections/list/ GET request
 *
 * Income: -
 *
 * Outcome: 200/success
 */
class GetList extends BaseMethod implements IAPIMethodImplementation
{
    /**
     * GetList constructor
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

        $dbResult = $this->getBitrixSectionsTree();
        $arSections = array();
        while ($arSection = $dbResult->GetNext()) {
            $arSections[$arSection['ID']] = array(
                'id' => $arSection['ID'],
                'name' => $arSection['NAME'],
                'code' => $arSection['CODE'],
                'xmlId' => (null === $arSection['XML_ID']) ? '' : $arSection['XML_ID'],
                'parentId' => (null === $arSection['IBLOCK_SECTION_ID']) ? '0' : $arSection['IBLOCK_SECTION_ID']
            );
        }

        $this->httpStatus = '200';
        $this->data = array(
            'status' => 'success',
            'description' => '',
            'data' => $arSections
        );
    }
}