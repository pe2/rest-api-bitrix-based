<?php

namespace Local\API\Methods\Images;

use Bitrix\Main\HttpRequest;
use CFile;
use CIBlockElement;
use Local\API\Methods\BaseMethod;
use Local\API\Methods\IAPIMethodImplementation;

/**
 * Class handles images/links/ POST request
 * It returns image paths to image with given xml ids
 *
 * Income: xml ids array
 *
 * Outcome: Paths to image
 */
class GetLinks extends BaseMethod implements IAPIMethodImplementation
{
    /** @var string Base host */
    private const BASE_HOST = 'https://example.com';
    /** @var int IBlock goods id */
    private const IBLOCK_GOODS = 2;
    /** @var int IBlock offers id */
    private const IBLOCK_OFFERS = 3;

    /**
     * ImageGetLink constructor
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
        if (!$this->loadModules(array('iblock'))) {
            return;
        }

        $arXmlIds = $this->getXmlIds();
        if (!count($arXmlIds)) {
            return;
        }

        $this->httpStatus = '200';
        $arJsonImageLinks = $this->getImageLinksByXmlIds($arXmlIds);
        if (0 >= strlen($arJsonImageLinks)) {
            $this->data = array(
                'status' => 'error',
                'description' => 'Can\'t obtain image link by given XML_IDs',
                'data' => ''
            );
        } else {
            $this->data = array(
                'status' => 'success',
                'description' => '',
                'data' => $arJsonImageLinks
            );
        }
    }

    /**
     * Method obtain XML ids from post request body
     *
     * @return array Array of xml ids
     */
    private function getXmlIds(): array
    {
        $arXmlIds = json_decode($this->requestBody, true);
        if (!is_array($arXmlIds) || !count($arXmlIds)) {
            $this->httpStatus = '400';
            $this->data = array(
                'status' => 'error',
                'description' => 'Can\'t obtain XML_IDs from request',
                'data' => ''
            );
            $this->stopProcessing = true;
            return array();
        } else {
            return $arXmlIds;
        }
    }

    /**
     * Method perform bitrix API request to obtain image link
     *
     * @return bool|string
     */
    private function getImageLinksByXmlIds(array $arXmlIds): string
    {
        $arImagesInfo = array();

        $dbElementsResult = CIBlockElement::GetList(
            array(),
            array('IBLOCK_ID' => array(self::IBLOCK_GOODS, self::IBLOCK_OFFERS), 'XML_ID' => $arXmlIds),
            false,
            false,
            array('ID', 'IBLOCK_ID', 'XML_ID', 'DETAIL_PICTURE', 'PREVIEW_PICTURE', 'PROPERTY_MORE_PHOTO')
        );
        while ($arProductResult = $dbElementsResult->GetNext()) {
            $mainImageId = (isset($arProductResult['DETAIL_PICTURE']) && 0 < mb_strlen($arProductResult['DETAIL_PICTURE'])) ?
                $arProductResult['DETAIL_PICTURE'] : $arProductResult['PREVIEW_PICTURE'];
            $arImagesInfo[$arProductResult['XML_ID']]['main'] = $mainImageId;

            if (is_null($arProductResult['PROPERTY_MORE_PHOTO_VALUE'])) {
                $arImagesInfo[$arProductResult['XML_ID']]['other'] = array();
                continue;
            }

            if (!isset($arImagesInfo[$arProductResult['XML_ID']]['other']) || !is_array($arImagesInfo[$arProductResult['XML_ID']]['other'])) {
                $arImagesInfo[$arProductResult['XML_ID']]['other'] = array($arProductResult['PROPERTY_MORE_PHOTO_VALUE']);
            } else {
                $arImagesInfo[$arProductResult['XML_ID']]['other'][] = $arProductResult['PROPERTY_MORE_PHOTO_VALUE'];
            }
        }

        return $this->getLinksByFileIds($arImagesInfo);
    }

    /**
     * Method iterates array ids, prepare links array and encode it
     * First element is the product's main image
     *
     * @param array $arFileIds
     *
     * @return string
     */
    private function getLinksByFileIds(array $arFileIds): string
    {
        if (!count($arFileIds)) {
            return '';
        }

        $arImageLinks = array();
        foreach ($arFileIds as $xmlId => $arFilesInfo) {
            $arImageLinks[$xmlId][] = $this->getImagePath($arFilesInfo['main']);
            foreach ($arFilesInfo['other'] as $imageId) {
                $arImageLinks[$xmlId][] = $this->getImagePath($imageId);
            }
        }

        return json_encode($arImageLinks);
    }

    /**
     * Method returns path to image by given id
     *
     * @param int $imageId
     *
     * @return string
     */
    private function getImagePath(int $imageId): string
    {
        $filePath = CFile::GetPath($imageId);
        if (0 >= strlen($filePath)) {
            return '';
        } else {
            return self::BASE_HOST . $filePath;
        }
    }
}
