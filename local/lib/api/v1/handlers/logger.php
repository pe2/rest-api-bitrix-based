<?php

namespace Local\API;

use Bitrix\Main\HttpRequest;
use Local\Init\ServiceHandler as SH;

/**
 * This class logs requests and responses
 */
class Logger
{
    /** @var string Log file path */
    private const LOG_FILE_PATH = '/local/logs/api-requests.log';
    /** @var int If response data too long, cut up to this number */
    private const CUT_DATA_UP_TO = 256;

    /**
     * Method logs request
     *
     * @param HttpRequest $request
     * @param string $requestBody
     */
    public static function logRequest(HttpRequest $request, string $requestBody): void
    {
        $arServer = $request->getServer();
        if ('' === $requestBody) {
            $arPosts = $request->getPostList();
            foreach ($arPosts as $postName => $postValue) {
                $requestBody .= $postName . ' => \'' . $postValue . '\'; ';
            }
        }

        $message = 'Request URI: ' . $request->getRequestUri() . "; ";
        $message .= 'From: ' . $arServer['SERVER_NAME'] . ' (' . $arServer['SERVER_ADDR'] . ':' .
            $arServer['SERVER_PORT'] . ')' . "; ";
        $message .= 'Method: ' . $arServer['REQUEST_METHOD'] . "; ";
        $message .= 'Content-type: ' . $arServer['CONTENT_TYPE'] . "; ";
        $message .= 'Auth header md5: ' . md5($request->getHeader(AuthHandler::API_KEY_HEADER_NAME)) . "\n";
        $message .= 'Request body: ' . $requestBody;

        SH::writeToLog($message, self::LOG_FILE_PATH, 'short_version');
    }

    /**
     * Method logs response
     *
     * @param string $httpCode
     * @param array $data
     */
    public static function logResponse(string $httpCode, array $data): void
    {
        $message = 'Response code: ' . $httpCode . "; ";
        $message .= 'Status: ' . $data['status'] . "\n";
        if (isset($data['description']) && 0 < mb_strlen($data['description'])) {
            $message .= 'Response description: ' . $data['description'];
        }
        if (isset($data['data']) && 0 < mb_strlen($data['data'])) {
            $message .= 'Response data: ' . ((self::CUT_DATA_UP_TO >= mb_strlen($data['data'])) ?
                    $data['data'] : substr($data['data'], 0, self::CUT_DATA_UP_TO) . ' ...');
        }

        SH::writeToLog($message, self::LOG_FILE_PATH, 'short_version');
    }
}