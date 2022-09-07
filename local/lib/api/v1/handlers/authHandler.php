<?php

namespace Local\API;

use Bitrix\Main\HttpRequest;

/**
 * Class for authorization actions
 */
class AuthHandler
{
    /** @var string Variable name in headers array */
    public const API_KEY_HEADER_NAME = 'api-key-auth';
    /** @var string MD5-hash of 1C API v1 key */
    private const _1C_API_MD5_KEY_HASH = 'some_md5_hash_to_check';


    /** @var object $request \Bitrix\Main\Web\HttpHeaders */
    private object $request;

    /**
     * Method sets $headers variable
     *
     * @param object $request \Bitrix\Main\HttpRequest object
     */
    public function __construct(object $request)
    {
        $this->request = $request;
    }


    /**
     * Method-controller to select auth method
     *
     * @return bool
     */
    public function checkAuth(): bool
    {
        if (!($this->request instanceof HttpRequest)) {
            return false;
        }

        $requestedUri = $this->request->getRequestUri();

        if (false !== strpos($requestedUri, _1C_API_PREFIX_V1)) {
            return $this->checkAuth1CApi();
        }

        return false;
    }


    /**
     * Method checks 1C API authorization header
     *
     * @return bool
     */
    private function checkAuth1CApi(): bool
    {
        $arHeaders = $this->request->getHeaders();

        return self::_1C_API_MD5_KEY_HASH === md5(strval($arHeaders->get(self::API_KEY_HEADER_NAME)));
    }
}
