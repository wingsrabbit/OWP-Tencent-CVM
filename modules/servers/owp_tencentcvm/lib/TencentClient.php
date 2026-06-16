<?php

declare(strict_types=1);

namespace OwpTencentCvm;

final class TencentClient
{
    private const DEFAULT_ENDPOINT = 'cvm.tencentcloudapi.com';
    private const DEFAULT_SERVICE = 'cvm';
    private const DEFAULT_VERSION = '2017-03-12';
    private const ALGORITHM = 'TC3-HMAC-SHA256';

    private string $secretId;
    private string $secretKey;
    private string $endpoint;
    private string $service;
    private string $version;
    private string $language;
    private ?string $token;
    private int $timeoutSeconds;

    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(array $settings = [])
    {
        $this->secretId = trim((string) ($settings['secret_id'] ?? ''));
        $this->secretKey = trim((string) ($settings['secret_key'] ?? ''));
        $this->endpoint = trim((string) ($settings['endpoint'] ?? '')) ?: self::DEFAULT_ENDPOINT;
        $this->service = trim((string) ($settings['service'] ?? '')) ?: self::DEFAULT_SERVICE;
        $this->version = trim((string) ($settings['version'] ?? '')) ?: self::DEFAULT_VERSION;
        $this->language = trim((string) ($settings['language'] ?? '')) ?: 'en-US';
        $token = trim((string) ($settings['token'] ?? ''));
        $this->token = $token !== '' ? $token : null;
        $this->timeoutSeconds = max(1, (int) ($settings['timeout_seconds'] ?? 20));
    }

    public function isConfigured(): bool
    {
        return $this->secretId !== '' && $this->secretKey !== '';
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function request(string $action, array $payload = [], string $region = '', ?int $timestamp = null): TencentResponse
    {
        $this->assertConfigured();

        $action = trim($action);
        if ($action === '') {
            throw new TencentApiException('Tencent Cloud action must not be empty.');
        }

        $payloadJson = $this->encodePayload($payload);
        $timestamp = $timestamp ?? time();
        $headers = $this->buildHeaders($action, $region, $payloadJson, $timestamp);
        $raw = $this->send($payloadJson, $headers);

        return $this->normalizeResponse($action, $raw['status'], $raw['body']);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function describeInstances(string $region, array $filters = [], int $limit = 20, int $offset = 0): TencentResponse
    {
        $payload = [
            'Limit' => $limit,
            'Offset' => $offset,
        ];

        if ($filters !== []) {
            $payload['Filters'] = $filters;
        }

        return $this->request('DescribeInstances', $payload, $region);
    }

    /**
     * @param list<string> $instanceIds
     */
    public function describeInstancesStatus(string $region, array $instanceIds): TencentResponse
    {
        return $this->request('DescribeInstancesStatus', ['InstanceIds' => array_values($instanceIds)], $region);
    }

    public function describeZones(string $region): TencentResponse
    {
        return $this->request('DescribeZones', [], $region);
    }

    /**
     * @param list<string> $imageIds
     */
    public function describeImages(string $region, array $imageIds): TencentResponse
    {
        return $this->request('DescribeImages', ['ImageIds' => array_values($imageIds)], $region);
    }

    public function describeZoneInstanceConfigInfos(string $region): TencentResponse
    {
        return $this->request('DescribeZoneInstanceConfigInfos', [], $region);
    }

    /**
     * @param list<string> $subnetIds
     */
    public function describeSubnets(string $region, array $subnetIds): TencentResponse
    {
        return $this->request('DescribeSubnets', ['SubnetIds' => array_values($subnetIds)], $region);
    }

    /**
     * @param list<string> $securityGroupIds
     */
    public function describeSecurityGroups(string $region, array $securityGroupIds): TencentResponse
    {
        return $this->request('DescribeSecurityGroups', ['SecurityGroupIds' => array_values($securityGroupIds)], $region);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function runInstances(string $region, array $params, bool $dryRun = true): TencentResponse
    {
        if ($dryRun) {
            $params['DryRun'] = true;
        }

        return $this->request('RunInstances', $params, $region);
    }

    /**
     * @param list<string> $instanceIds
     */
    public function startInstances(string $region, array $instanceIds): TencentResponse
    {
        return $this->request('StartInstances', ['InstanceIds' => array_values($instanceIds)], $region);
    }

    /**
     * @param list<string> $instanceIds
     */
    public function stopInstances(string $region, array $instanceIds, bool $forceStop = false): TencentResponse
    {
        return $this->request('StopInstances', [
            'InstanceIds' => array_values($instanceIds),
            'ForceStop' => $forceStop,
        ], $region);
    }

    /**
     * @param list<string> $instanceIds
     */
    public function rebootInstances(string $region, array $instanceIds, bool $forceReboot = false): TencentResponse
    {
        return $this->request('RebootInstances', [
            'InstanceIds' => array_values($instanceIds),
            'ForceReboot' => $forceReboot,
        ], $region);
    }

    /**
     * @param list<string> $instanceIds
     */
    public function resetInstancesPassword(string $region, array $instanceIds, string $password): TencentResponse
    {
        return $this->request('ResetInstancesPassword', [
            'InstanceIds' => array_values($instanceIds),
            'Password' => $password,
        ], $region);
    }

    public function describeInstanceVncUrl(string $region, string $instanceId): TencentResponse
    {
        return $this->request('DescribeInstanceVncUrl', ['InstanceId' => $instanceId], $region);
    }

    /**
     * @param list<string> $instanceIds
     */
    public function terminateInstances(string $region, array $instanceIds): TencentResponse
    {
        return $this->request('TerminateInstances', ['InstanceIds' => array_values($instanceIds)], $region);
    }

    /**
     * @return array<string, string>
     */
    public function buildHeaders(string $action, string $region, string $payloadJson, int $timestamp): array
    {
        $date = gmdate('Y-m-d', $timestamp);
        $canonicalHeaders = 'content-type:application/json' . "\n"
            . 'host:' . $this->endpoint . "\n"
            . 'x-tc-action:' . strtolower($action) . "\n";
        $signedHeaders = 'content-type;host;x-tc-action';
        $hashedPayload = hash('sha256', $payloadJson);
        $canonicalRequest = implode("\n", [
            'POST',
            '/',
            '',
            $canonicalHeaders,
            $signedHeaders,
            $hashedPayload,
        ]);

        $credentialScope = $date . '/' . $this->service . '/tc3_request';
        $stringToSign = implode("\n", [
            self::ALGORITHM,
            (string) $timestamp,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        $secretDate = hash_hmac('sha256', $date, 'TC3' . $this->secretKey, true);
        $secretService = hash_hmac('sha256', $this->service, $secretDate, true);
        $secretSigning = hash_hmac('sha256', 'tc3_request', $secretService, true);
        $signature = hash_hmac('sha256', $stringToSign, $secretSigning);
        $authorization = self::ALGORITHM
            . ' Credential=' . $this->secretId . '/' . $credentialScope
            . ', SignedHeaders=' . $signedHeaders
            . ', Signature=' . $signature;

        $headers = [
            'Authorization' => $authorization,
            'Content-Type' => 'application/json',
            'Host' => $this->endpoint,
            'X-TC-Action' => $action,
            'X-TC-Version' => $this->version,
            'X-TC-Timestamp' => (string) $timestamp,
            'X-TC-Language' => $this->language,
        ];

        if ($region !== '') {
            $headers['X-TC-Region'] = $region;
        }

        if ($this->token !== null) {
            $headers['X-TC-Token'] = $this->token;
        }

        return $headers;
    }

    private function assertConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new TencentApiException('Tencent Cloud SecretId and SecretKey are required before making API calls.');
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function encodePayload(array $payload): string
    {
        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (!is_string($payloadJson)) {
            throw new TencentApiException('Failed to encode Tencent Cloud API payload.');
        }

        return $payloadJson;
    }

    /**
     * @param array<string, string> $headers
     * @return array{status:int, body:string}
     */
    private function send(string $payloadJson, array $headers): array
    {
        $url = 'https://' . $this->endpoint . '/';

        if (function_exists('curl_init')) {
            return $this->sendWithCurl($url, $payloadJson, $headers);
        }

        return $this->sendWithStreams($url, $payloadJson, $headers);
    }

    /**
     * @param array<string, string> $headers
     * @return array{status:int, body:string}
     */
    private function sendWithCurl(string $url, string $payloadJson, array $headers): array
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new TencentApiException('Failed to initialize cURL for Tencent Cloud API request.');
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payloadJson,
            CURLOPT_HTTPHEADER => $this->formatHeaders($headers),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->timeoutSeconds,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
        ]);

        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if (!is_string($body)) {
            throw new TencentApiException(
                'Tencent Cloud API request failed before receiving a response: ' . Redactor::redactString($error),
                '',
                '',
                '',
                $status
            );
        }

        return ['status' => $status, 'body' => $body];
    }

    /**
     * @param array<string, string> $headers
     * @return array{status:int, body:string}
     */
    private function sendWithStreams(string $url, string $payloadJson, array $headers): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $this->formatHeaders($headers)),
                'content' => $payloadJson,
                'timeout' => $this->timeoutSeconds,
                'ignore_errors' => true,
            ],
        ]);

        $body = file_get_contents($url, false, $context);
        $status = $this->statusFromResponseHeaders($http_response_header ?? []);

        if (!is_string($body)) {
            throw new TencentApiException('Tencent Cloud API request failed before receiving a response.', '', '', '', $status);
        }

        return ['status' => $status, 'body' => $body];
    }

    /**
     * @param array<string, string> $headers
     * @return list<string>
     */
    private function formatHeaders(array $headers): array
    {
        $formatted = [];
        foreach ($headers as $name => $value) {
            $formatted[] = $name . ': ' . $value;
        }

        return $formatted;
    }

    /**
     * @param list<string> $headers
     */
    private function statusFromResponseHeaders(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\\/\\S+\\s+(\\d{3})\\b/', $header, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return 0;
    }

    private function normalizeResponse(string $action, int $httpStatus, string $body): TencentResponse
    {
        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            throw new TencentApiException(
                'Tencent Cloud API returned a non-JSON response.',
                '',
                '',
                $action,
                $httpStatus
            );
        }

        $response = isset($decoded['Response']) && is_array($decoded['Response'])
            ? $decoded['Response']
            : $decoded;

        $requestId = isset($response['RequestId']) ? (string) $response['RequestId'] : '';

        if (isset($response['Error']) && is_array($response['Error'])) {
            $code = isset($response['Error']['Code']) ? (string) $response['Error']['Code'] : 'UnknownError';
            $message = isset($response['Error']['Message']) ? (string) $response['Error']['Message'] : 'Tencent Cloud API error.';
            throw new TencentApiException($message, $code, $requestId, $action, $httpStatus);
        }

        if ($httpStatus < 200 || $httpStatus >= 300) {
            throw new TencentApiException('Tencent Cloud API returned HTTP status ' . $httpStatus . '.', '', $requestId, $action, $httpStatus);
        }

        $data = $response;
        unset($data['RequestId']);

        return new TencentResponse($action, $requestId, $data, $httpStatus, $decoded);
    }
}
