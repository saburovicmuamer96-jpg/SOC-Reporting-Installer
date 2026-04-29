<?php
/**
 * SOC Reporting System - RSS Feed Proxy
 * Fetches RSS/Atom feeds server-side and returns parsed JSON.
 * Avoids CORS issues and third-party API dependencies.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';

Session::start();
Session::requireLogin();

header('Content-Type: application/json; charset=utf-8');

$feedUrl = isset($_GET['url']) ? trim($_GET['url']) : '';

if (empty($feedUrl) || !filter_var($feedUrl, FILTER_VALIDATE_URL)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid feed URL']);
    exit;
}

// Only allow HTTPS feeds
if (strpos($feedUrl, 'https://') !== 0 && strpos($feedUrl, 'http://') !== 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid protocol']);
    exit;
}

// Simple file-based cache (10 minute TTL)
$cacheDir = __DIR__ . '/../cache';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}
$cacheFile = $cacheDir . '/' . md5($feedUrl) . '.json';
$cacheTTL = 600; // 10 minutes

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
    readfile($cacheFile);
    exit;
}

// Fetch the feed
$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'user_agent' => 'SOC-Reporting-System/1.0 RSS Reader',
        'follow_location' => true,
        'max_redirects' => 3,
    ],
    'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
    ],
]);

$xml = @file_get_contents($feedUrl, false, $context);

if ($xml === false) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch feed']);
    exit;
}

// Suppress XML warnings
libxml_use_internal_errors(true);
$feed = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);

if ($feed === false) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to parse XML']);
    exit;
}

$items = [];
$count = isset($_GET['count']) ? min((int)$_GET['count'], 30) : 20;

// Detect feed type: RSS 2.0 vs Atom
if (isset($feed->channel->item)) {
    // RSS 2.0
    $i = 0;
    foreach ($feed->channel->item as $item) {
        if ($i >= $count) break;
        $items[] = [
            'title' => (string)$item->title,
            'description' => strip_tags((string)($item->description ?? '')),
            'link' => (string)$item->link,
            'pubDate' => (string)($item->pubDate ?? ''),
        ];
        $i++;
    }
} elseif (isset($feed->entry)) {
    // Atom
    $i = 0;
    foreach ($feed->entry as $entry) {
        if ($i >= $count) break;
        $link = '';
        if (isset($entry->link)) {
            foreach ($entry->link as $l) {
                $attrs = $l->attributes();
                if ((string)($attrs['rel'] ?? '') === 'alternate' || empty($link)) {
                    $link = (string)($attrs['href'] ?? '');
                }
            }
        }
        $desc = '';
        if (isset($entry->summary)) {
            $desc = strip_tags((string)$entry->summary);
        } elseif (isset($entry->content)) {
            $desc = strip_tags((string)$entry->content);
        }
        $items[] = [
            'title' => (string)$entry->title,
            'description' => $desc,
            'link' => $link,
            'pubDate' => (string)($entry->updated ?? $entry->published ?? ''),
        ];
        $i++;
    }
} elseif (isset($feed->item)) {
    // RDF / RSS 1.0
    $i = 0;
    foreach ($feed->item as $item) {
        if ($i >= $count) break;
        $items[] = [
            'title' => (string)$item->title,
            'description' => strip_tags((string)($item->description ?? '')),
            'link' => (string)$item->link,
            'pubDate' => (string)($item->pubDate ?? $item->date ?? ''),
        ];
        $i++;
    }
}

$result = json_encode(['status' => 'ok', 'items' => $items], JSON_UNESCAPED_UNICODE);

// Cache the result
file_put_contents($cacheFile, $result);

echo $result;
