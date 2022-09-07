<?php

namespace Local\API\Methods;

use Bitrix\Main\HttpRequest;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use CIBlockResult;
use CIBlockSection;

/**
 * This abstract class describes classes that handles real tasks
 */
abstract class BaseMethod
{
    /** @var int IBlock with goods */
    private const IBLOCK_GOODS = 2;

    /** @var string HTTP response status */
    public string $httpStatus = '';
    /** @var array Array with data ['status', 'description', 'data'] */
    public array $data = array();
    /** @var bool Stop request processing */
    public bool $stopProcessing = false;
    /** @var HttpRequest */
    public HttpRequest $request;
    /** @var string Request body */
    public string $requestBody;


    /**
     * Base method constructor
     *
     * @param HttpRequest $request
     * @param string $requestBody
     */
    public function __construct(HttpRequest $request, string $requestBody)
    {
        $this->request = $request;
        $this->requestBody = $requestBody;
    }


    /**
     * Method prepares result object
     *
     * @return array
     */
    public function prepareResult(): array
    {
        if (0 >= strlen($this->httpStatus)) {
            $this->httpStatus = 520;
        }

        if (!is_array($this->data) || !count($this->data)) {
            $this->data = array(
                'status' => 'error',
                'description' => "Variables \$httpCode or \$data don't set",
                'data' => ''
            );
        }

        return array(
            $this->httpStatus,
            $this->data,
            $this->stopProcessing
        );
    }

    /**
     * Method loads necessary modules
     *
     * @param array $arModules Array of module names to load
     *
     * @return bool
     */
    public function loadModules(array $arModules): bool
    {
        try {
            foreach ($arModules as $moduleName) {
                Loader::includeModule($moduleName);
            }
        } catch (LoaderException $e) {
            $this->httpStatus = '500';
            $this->data = array(
                'status' => 'error',
                'description' => "Can't load necessary module: " . $moduleName,
                'data' => ''
            );
            $this->stopProcessing = true;

            return false;
        }

        return true;
    }

    /**
     * Method returns bitrix sections tree
     *
     * @param array $arSelect Array with fields
     *
     * @return CIBlockResult
     */
    protected function getBitrixSectionsTree(array $arSelect = array()): object
    {
        return CIBlockSection::GetTreeList(
            array('IBLOCK_ID' => self::IBLOCK_GOODS),
            $arSelect
        );
    }

    /**
     * Method sets vars for unimplemented methods
     */
    public function stub(): void
    {
        $this->httpStatus = '501';
        $this->data = array(
            'status' => 'success',
            'description' => '',
            'data' => ''
        );
    }
}
