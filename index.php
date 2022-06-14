<?php

const CRAWLER_USER_AGENT_PATTERNS = [
    'Google' => 'Googlebot',
    'Bing' => 'Bingbot',
    'Baidu' => 'Baiduspider',
    'Yandex' => 'YandexBot',
];

class AccessInfoDto
{
    public string $requestPath;
    public int $statusCode;
    public int $bytesSent;
    public string $userAgent;
}

class LogInfoDto
{
    public int $views = 0;
    public int $urls = 0;
    public int $traffic = 0;
    public array $crawlers = [];
    public array $statusCodes = [];
}

function getAccessInfo(string $line): AccessInfoDto
{
    if (preg_match('/"\S+ (\S+) \S+" (\d+) (\d+) "[^"]+" "([^"]+)"/', $line, $matches, 0, strpos($line, ']', 40) + 2) === false) {
        exit("Line parse error: $line");
    }

    $accessInfoDto = new AccessInfoDto();
    $accessInfoDto->requestPath = $matches[1];
    $accessInfoDto->statusCode = (int)$matches[2];
    $accessInfoDto->bytesSent = (int)$matches[3];
    $accessInfoDto->userAgent = $matches[4];

    return $accessInfoDto;
}

function findCrawlerName(string $userAgent): ?string
{
    foreach (CRAWLER_USER_AGENT_PATTERNS as $crawlerName => $userAgentPattern) {
        if (strpos($userAgent, $userAgentPattern) !== false) {
            return $crawlerName;
        }
    }

    return null;
}

function parseAccessLog(string $filename): LogInfoDto
{
    $file = fopen($filename, 'r');
    $urls = [];
    $logInfoDto = new LogInfoDto();
    $logInfoDto->crawlers = array_fill_keys(array_keys(CRAWLER_USER_AGENT_PATTERNS), 0);
    try {
        $line = fgets($file);
        while ($line) {
            $accessInfoDto = getAccessInfo($line);
            $urls[$accessInfoDto->requestPath] = null;
            $logInfoDto->views++;
            $logInfoDto->traffic += $accessInfoDto->bytesSent;
            $logInfoDto->statusCodes[$accessInfoDto->statusCode] = ($logInfoDto->statusCodes[$accessInfoDto->statusCode] ?? 0) + 1;
            $crawlerName = findCrawlerName($accessInfoDto->userAgent);
            if ($crawlerName) {
                $logInfoDto->crawlers[$crawlerName]++;
            }

            $line = fgets($file);
        }
    } finally {
        fclose($file);
    }

    $logInfoDto->urls = count($urls);

    return $logInfoDto;
}

$logInfoDto = parseAccessLog($argv[1] ?? 'access.log');

echo json_encode($logInfoDto, JSON_PRETTY_PRINT);

exit(0);
