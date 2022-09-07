<?php

namespace Local\API;

use Bitrix\Main\HttpRequest;
use CustomCache\CacheHandler;
use Exception;

/**
 * Base class for handling requests and making responses
 *
 * Class supports 2 events:
 * - 'OnBefore' + API_MODULE_NAME + 'Request($request, $requestBody)
 * - 'OnAfter' + API_MODULE_NAME + 'Request($request, $requestBody, $response)
 */
class RequestHandler
{
    /** @var string Module name */
    public const API_MODULE_NAME = 'CustomRestFulApi';
    /** @var string Class must extend this base class */
    public const BASE_CLASS_NAME = '\Local\API\Methods\BaseMethod';
    /** @var string Class must implement this interface */
    public const IMPLEMENTATION_INTERFACE_NAME = 'Local\\API\\Methods\\IAPIMethodImplementation';
    /** @var string Method to generate response defined in interface */
    public const IMPLEMENTATION_METHOD_NAME = 'execute';

    /** @var string Path to api response cache directory */
    private const CACHE_PATH = '/api/1c/';
    /** @var int Cache time in seconds */
    private const CACHE_TIME = 60;
    /** @var string Ignore cache header name */
    private const IGNORE_CACHE_HEADER_NAME = 'ignore-cache';

    /** @var string Class name to create object */
    private string $className;
    /** @var object \Bitrix\Main\HttpRequest object */
    private object $request;
    /** @var string Request body for POST requests */
    private string $requestBody;
    /** @var bool Need auth to perform request? */
    private bool $needAuth;
    /** @var bool Ignore cache result (force method execution) */
    private bool $ignoreCache;
    /** @var string Requested method */
    private string $requestedMethod;


    /**
     * RequestHandler constructor
     *
     * @param string $className classname to make an object
     * @param HttpRequest $request HttpRequest object
     * @param string $requestBody Request body for POST requests
     * @param bool $needAuth Need auth to perform request?
     */
    public function __construct(string $className, HttpRequest $request, string $requestBody = '', bool $needAuth = true)
    {
        Logger::logRequest($request, $requestBody);

        $this->setClassName($className);
        $this->checkClassExistence();
        $this->checkAndSetRequestObject($request);
        $this->requestedMethod = $this->request->getRequestMethod();
        $this->checkAndSetRequestBody($requestBody);
        $this->setAuth($needAuth);
        $this->setIgnoreCache();
    }

    /**
     * Method checks array with class and method name and sets private variables
     *
     * @param string $className
     */
    private function setClassName(string $className): void
    {
        if (0 >= strlen($className)) {
            $data = array(
                'status' => 'error',
                'description' => 'Class name is empty',
                'data' => ''
            );
            $this->makeResponse('500', $data, true);
        }

        $this->className = $className;
    }

    /**
     * @param string $httpCode
     * @param array $data
     * @param bool $stopProcessing
     */
    private function makeResponse(string $httpCode, array $data, bool $stopProcessing = false): void
    {
        Logger::logResponse($httpCode, $data);

        header('Content-Type: application/json');

        http_response_code($httpCode);

        echo json_encode($data);

        if ($stopProcessing) {
            die();
        }
    }

    /**
     * Method checks class existence
     */
    private function checkClassExistence(): void
    {
        if (!class_exists(__NAMESPACE__ . '\\Methods\\' . $this->className)) {
            $data = array(
                'status' => 'error',
                'description' => 'Class ' . $this->className . " doesn't exist",
                'data' => ''
            );
            $this->makeResponse('500', $data, true);
        }
        $this->className = __NAMESPACE__ . '\\Methods\\' . $this->className;
    }

    /**
     * Method checks and sets request object
     *
     * @param object $request \Bitrix\Main\HttpRequest
     */
    private function checkAndSetRequestObject(object $request): void
    {
        if (!is_object($request) || !($request instanceof HttpRequest)) {
            $data = array(
                'status' => 'error',
                'description' => 'Error in $request object',
                'data' => ''
            );
            $this->makeResponse('500', $data, true);
        }

        $this->request = $request;
    }


    /**
     * Method checks and sets request body
     *
     * @param string $requestBody Request body
     */
    private function checkAndSetRequestBody(string $requestBody): void
    {
        if ('POST' === $this->requestedMethod) {
            $failedPostBody = true;
            if (0 < strlen($requestBody)) {
                $failedPostBody = false;
            }
            if ($failedPostBody && $this->checkRequestBodyAsPartOfRequest()) {
                $failedPostBody = false;
            }

            if ($failedPostBody) {
                $data = array(
                    'status' => 'error',
                    'description' => 'Error in POST request body (missing or empty)',
                    'data' => ''
                );
                $this->makeResponse('500', $data, true);
            }
        }

        if ('GET' === $this->requestedMethod) {
            $requestBody = '';
        }

        $this->requestBody = $requestBody;
    }


    /**
     * Method checks post request body as part of request array
     *
     * @return bool
     */
    private function checkRequestBodyAsPartOfRequest(): bool
    {
        $dictPost = $this->request->getPostList();
        $arPostVars = array();
        foreach ($dictPost as $postVarName => $postVarValue) {
            if ($postVarName !== AuthHandler::API_KEY_HEADER_NAME) {
                $arPostVars[] = $postVarValue;
            }
        }

        return (0 < count($arPostVars));
    }


    /**
     * Method sets $needAuth variable
     *
     * @param $needAuth
     */
    private function setAuth($needAuth): void
    {
        if (!is_bool($needAuth) || !$needAuth) {
            $this->needAuth = false;
        } else {
            $this->needAuth = true;
        }
    }


    /**
     * Method sets $ignoreCache variable
     */
    private function setIgnoreCache(): void
    {
        $ignoreCache = $this->request->getHeaders()->get(self::IGNORE_CACHE_HEADER_NAME);
        if (isset($ignoreCache) && ('true' === strval($ignoreCache))) {
            $this->ignoreCache = true;
        } else {
            $this->ignoreCache = false;
        }
    }

    /**
     * Method creates requested class object and executes requested method
     */
    public function handleRequest(): void
    {
        if ($this->needAuth) {
            $this->checkAuth();
        }

        $this->executeRequestedMethod();
    }

    /**
     * Method handles auth process
     */
    private function checkAuth(): void
    {
        $authHandler = new AuthHandler($this->request);
        if (!$authHandler->checkAuth()) {
            $data = array(
                'status' => 'error',
                'description' => 'Authentication failed',
                'data' => ''
            );
            $this->makeResponse('401', $data, true);
        }
    }


    /**
     *  Method instantiate class and executes requested method
     */
    private function executeRequestedMethod(): void
    {
        $requestedClass = new $this->className($this->request, $this->requestBody);
        $this->checkCreatedClass($requestedClass);

        try {
            // Cache GET requests only
            if ('GET' === $this->requestedMethod) {
                $objCustomCache = new CacheHandler('File');
                $cacheId = $this->makeCachedId();
                $cacheResult = $objCustomCache->getCache($cacheId, self::CACHE_PATH, self::CACHE_TIME);

                if ($this->ignoreCache || empty($cacheResult) || !is_array($cacheResult) || !count($cacheResult)) {
                    $this->request->custom->APIClassName = $this->className;
                    $this->callOnBeforeRequest();
                    $requestedClass->{self::IMPLEMENTATION_METHOD_NAME}();
                    $this->callOnAfterRequest($requestedClass->data);
                    $objCustomCache->saveCache(
                        $cacheId,
                        self::CACHE_PATH,
                        array($requestedClass->httpStatus, $requestedClass->data, $requestedClass->stopProcessing),
                        self::CACHE_TIME
                    );
                } else {
                    [$requestedClass->httpStatus, $requestedClass->data, $requestedClass->stopProcessing] = $cacheResult;
                }
            } else {
                $this->request->custom->APIClassName = $this->className;
                $this->callOnBeforeRequest();
                $requestedClass->{self::IMPLEMENTATION_METHOD_NAME}();
                $this->callOnAfterRequest($requestedClass->data);
            }
        } catch (Exception $e) {
            $data = array(
                'status' => 'error',
                'description' => 'Error in method execution. Description: ' . $e->getMessage(),
                'data' => ''
            );
            $this->makeResponse('500', $data, true);
        }

        $this->makeResponse(...$requestedClass->prepareResult());
    }

    /**
     * Method checks class's parent
     *
     * @param object $requestedClass Class object
     */
    private function checkCreatedClass(object $requestedClass): void
    {
        if (!(is_subclass_of($requestedClass, self::BASE_CLASS_NAME))) {
            $data = array(
                'status' => 'error',
                'description' => 'Object is not a child of ' . self:: BASE_CLASS_NAME . ' class',
                'data' => ''
            );
            $this->makeResponse('500', $data, true);
        }

        $arImplementedInterfaces = class_implements($requestedClass);
        foreach ($arImplementedInterfaces as $implementedInterface) {
            if (self::IMPLEMENTATION_INTERFACE_NAME === $implementedInterface) {
                return;
            }
        }

        $data = array(
            'status' => 'error',
            'description' => 'Object class does not implement ' . self::IMPLEMENTATION_INTERFACE_NAME .
                'interface',
            'data' => ''
        );
        $this->makeResponse('500', $data, true);
    }

    /**
     * Method returns unique cache id for requested class and method and request params
     *
     * @return string
     */
    private function makeCachedId(): string
    {
        $GetArray = $this->request->getQueryList()->toArray();
        $PostArray = $this->request->getPostList()->toArray();

        return md5(implode('|', array_merge(
                $this->className, array_values($GetArray), array_values($PostArray))
        ));
    }

    /**
     * Method executes before request listener's functions
     *
     * @throws Exception
     */
    private function callOnBeforeRequest(): void
    {
        $eventName = 'OnBefore' . self::API_MODULE_NAME . 'Request';
        $arEvents = GetModuleEvents(self::API_MODULE_NAME, $eventName);

        while ($oEvent = $arEvents->Fetch()) {
            if (ExecuteModuleEventEx($oEvent, array($this->request, $this->requestBody)) === false) {
                throw new \Exception('Error in one of event listeners of ' . $eventName);
            }
        }
    }

    /**
     * Method executes after request listener's functions
     *
     * @throws Exception
     */
    private function callOnAfterRequest($requestData): void
    {
        $eventName = 'OnAfter' . self::API_MODULE_NAME . 'Request';
        $arEvents = GetModuleEvents(self::API_MODULE_NAME, $eventName);

        while ($oEvent = $arEvents->Fetch()) {
            if (ExecuteModuleEventEx($oEvent, array($this->request, $this->requestBody, $requestData)) === false) {
                throw new \Exception('Error in one of event listeners of ' . $eventName);
            }
        }
    }
}
