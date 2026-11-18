<?php
declare(strict_types=1);

namespace Grav\Plugin\MetaFeed\Api;

use Grav\Plugin\Api\Controllers\AbstractApiController;
use Grav\Plugin\Api\Response\ApiResponse;
use Grav\Plugin\MetaFeed\ActivityLog;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ActivityApiController extends AbstractApiController
{
    /**
     * GET /meta-feed/activity - Most recent fetch and token refresh runs.
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $this->requirePermission($request, 'api.system.read');

        return ApiResponse::create((new ActivityLog($this->grav))->all());
    }
}
