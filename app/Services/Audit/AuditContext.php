<?php

namespace App\Services\Audit;

use Illuminate\Support\Str;

class AuditContext
{
    private string $requestId;

    private string $source;

    private string $platform;

    private ?string $appVersion = null;

    private ?string $ipAddress = null;

    private ?string $userAgent = null;

    private ?string $route = null;

    private ?string $httpMethod = null;

    private ?int $userId = null;

    public function __construct()
    {
        $this->requestId = (string) Str::uuid();
        $this->source = app()->runningInConsole() ? 'console' : 'web';
        $this->platform = app()->runningInConsole() ? 'cli' : 'browser';
    }

    public function setFromRequest(
        string $requestId,
        string $source,
        string $platform,
        ?string $appVersion = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $route = null,
        ?string $httpMethod = null,
        ?int $userId = null,
    ): void {
        $this->requestId = $requestId;
        $this->source = $source;
        $this->platform = $platform;
        $this->appVersion = $appVersion;
        $this->ipAddress = $ipAddress;
        $this->userAgent = $userAgent;
        $this->route = $route;
        $this->httpMethod = $httpMethod;
        $this->userId = $userId;
    }

    public function setUserId(?int $userId): void
    {
        $this->userId = $userId;
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getPlatform(): string
    {
        return $this->platform;
    }

    public function getAppVersion(): ?string
    {
        return $this->appVersion;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }

    public function getHttpMethod(): ?string
    {
        return $this->httpMethod;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'request_id' => $this->requestId,
            'source' => $this->source,
            'platform' => $this->platform,
            'app_version' => $this->appVersion,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'route' => $this->route,
            'http_method' => $this->httpMethod,
            'user_id' => $this->userId,
        ];
    }
}
