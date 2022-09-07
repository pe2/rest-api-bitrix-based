<?php

namespace Local\API\Methods\Sections;

use Bitrix\Main\HttpRequest;
use Local\API\Methods\BaseMethod;
use Local\API\Methods\IAPIMethodImplementation;

/**
 * Class handles sections/tree/ GET request
 *
 * Income: -
 *
 * Stub method demo
 *
 * Outcome: 200/success
 */
class GetTree extends BaseMethod implements IAPIMethodImplementation
{
    /**
     * GetTree constructor
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
        $this->stub();
    }
}