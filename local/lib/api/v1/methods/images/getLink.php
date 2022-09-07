<?php

namespace Local\API\Methods\Images;

use Bitrix\Iblock\ElementTable;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use CFile;
use Local\API\Methods\BaseMethod;
use Local\API\Methods\IAPIMethodImplementation;

/**
 * Class handles images/link/{image_xml_id}/ GET request
 * It returns image path to image with given xml id
 *
 * Income: image_xml_id as part of url
 *
 * Outcome: Path to image
 */
class GetLink extends BaseMethod implements IAPIMethodImplementation
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

        $xmlId = $this->getXmlIdParam();
        if (0 >= strlen($xmlId)) {
            return;
        }

        $this->httpStatus = '200';
        $imageLink = $this->getImageLinkByXmlId($xmlId);
        if (0 >= strlen($imageLink)) {
            $this->data = array(
                'status' => 'error',
                'description' => 'Can\'t obtain image link by given XML_ID',
                'data' => ''
            );
        } else {
            $this->data = array(
                'status' => 'success',
                'description' => '',
                'data' => $imageLink
            );
        }
    }

    /**
     * Method obtain XML_ID from get param
     *
     * @return string image xml id
     */
    private function getXmlIdParam(): string
    {
        $imageXmlId = $this->request->get('image_xml_id');
        if (!is_string($imageXmlId) || 0 >= mb_strlen($imageXmlId)) {
            $this->httpStatus = '400';
            $this->data = array(
                'status' => 'error',
                'description' => 'Can\'t obtain XML_ID from request',
                'data' => ''
            );
            $this->stopProcessing = true;
            return '';
        } else {
            return $imageXmlId;
        }
    }


    /**
     * Method perform bitrix API request to obtain image link
     *
     * @return bool|string
     */
    private function getImageLinkByXmlId(string $xmlId): string
    {
        try {
            $dbImageResult = ElementTable::getList(array(
                'select' => array('ID', 'IBLOCK_ID', 'DETAIL_PICTURE', 'PREVIEW_PICTURE'),
                'filter' => array(
                    'IBLOCK_ID' => array(self::IBLOCK_GOODS, self::IBLOCK_OFFERS),
                    'XML_ID' => $xmlId
                )
            ));
        } catch (ObjectPropertyException | ArgumentException | SystemException $e) {
            return '';
        }

        $imageId = '';
        while ($productResult = $dbImageResult->fetch()) {
            $imageId = (isset($productResult['DETAIL_PICTURE']) && 0 < mb_strlen($productResult['DETAIL_PICTURE'])) ?
                $productResult['DETAIL_PICTURE'] : $productResult['PREVIEW_PICTURE'];
        }

        if (0 >= strlen($imageId)) {
            return '';
        }

        $filePath = CFile::GetPath($imageId);
        if (0 >= strlen($filePath)) {
            return '';
        } else {
            return self::BASE_HOST . $filePath;
        }
    }
}
