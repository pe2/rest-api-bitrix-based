<?php

namespace Local\API\Methods;

use Bitrix\Main\HttpRequest;

/**
 * Interface for API method
 */
interface IAPIMethodImplementation
{
    public function __construct(HttpRequest $request, string $requestBody);

    public function execute(): void;
}