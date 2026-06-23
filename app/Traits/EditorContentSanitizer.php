<?php

namespace App\Traits;

trait EditorContentSanitizer
{
    private function getRestrictedPatterns(): array
    {
        return [
            '/<a\b[^>]*>(.*?)<\/a>/si' => '$1',
            '/<img\b[^>]*>/si' => '',
            '/<iframe\b[^>]*>.*?<\/iframe>/si' => '',
            '/<(video|audio)\b[^>]*>.*?<\/\1>/si' => '',
            '/<pre\b[^>]*>.*?<\/pre>/si' => '',
            '/<code\b[^>]*>.*?<\/code>/si' => '',
            '/<blockquote\b[^>]*>.*?<\/blockquote>/si' => '',
            '/<table\b[^>]*>.*?<\/table>/si' => '',
            '/<thead\b[^>]*>.*?<\/thead>/si' => '',
            '/<tbody\b[^>]*>.*?<\/tbody>/si' => '',
            '/<tr\b[^>]*>.*?<\/tr>/si' => '',
            '/<td\b[^>]*>.*?<\/td>/si' => '',
            '/<th\b[^>]*>.*?<\/th>/si' => '',
            '/<colgroup\b[^>]*>.*?<\/colgroup>/si' => '',
            '/<col\b[^>]*>/si' => '',
            '/<mark\b[^>]*>(.*?)<\/mark>/si' => '$1',
            '/<span\b[^>]*style="[^"]*(?:color|background)[^"]*"[^>]*>(.*?)<\/span>/si' => '$1',
            '/<span\b[^>]*class="[^"]*(?:highlight|text-color)[^"]*"[^>]*>(.*?)<\/span>/si' => '$1',
            '/<hr\b[^>]*>/si' => '',
            '/<script\b[^>]*>.*?<\/script>/si' => '',
            '/<style\b[^>]*>.*?<\/style>/si' => '',
        ];
    }

    private function getAllowedTags(): array
    {
        return [
            'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'ul', 'ol', 'li',
            'blockquote',
            'div', 'span',
        ];
    }

    public function sanitizeBasicContent(string $content): string
    {
        if (empty($content)) {
            return '';
        }

        $allowed = '<'.implode('><', $this->getAllowedTags()).'>';
        $content = strip_tags($content, $allowed);
        $content = $this->stripAttributes($content);

        foreach ($this->getRestrictedPatterns() as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }

        $content = $this->cleanupSanitizedContent($content);

        return $content;
    }

    public function sanitizeCustomContent(string $content, array $restrictions = []): string
    {
        if (empty($content)) {
            return '';
        }

        $allowed = '<'.implode('><', $this->getAllowedTags()).'>';
        $content = strip_tags($content, $allowed);
        $content = $this->stripAttributes($content);

        $restrictionPatterns = [
            'links' => ['/<a\b[^>]*>(.*?)<\/a>/si' => '$1'],
            'images' => ['/<img\b[^>]*>/si' => ''],
            'code' => [
                '/<pre\b[^>]*>.*?<\/pre>/si' => '',
                '/<code\b[^>]*>.*?<\/code>/si' => '',
            ],
            'tables' => [
                '/<table\b[^>]*>.*?<\/table>/si' => '',
                '/<thead\b[^>]*>.*?<\/thead>/si' => '',
                '/<tbody\b[^>]*>.*?<\/tbody>/si' => '',
                '/<tr\b[^>]*>.*?<\/tr>/si' => '',
                '/<td\b[^>]*>.*?<\/td>/si' => '',
                '/<th\b[^>]*>.*?<\/th>/si' => '',
            ],
            'iframes' => ['/<iframe\b[^>]*>.*?<\/iframe>/si' => ''],
            'videos' => ['/<(video|audio)\b[^>]*>.*?<\/\1>/si' => ''],
            'blockquotes' => ['/<blockquote\b[^>]*>.*?<\/blockquote>/si' => ''],
        ];

        foreach ($restrictions as $restriction) {
            if (isset($restrictionPatterns[$restriction])) {
                foreach ($restrictionPatterns[$restriction] as $pattern => $replacement) {
                    $content = preg_replace($pattern, $replacement, $content);
                }
            }
        }

        $content = $this->cleanupSanitizedContent($content);

        return $content;
    }

    public function sanitizeFullContent(string $content): string
    {
        if (empty($content)) {
            return '';
        }

        return strip_tags($content);
    }

    public function checkRestrictedContent(string $content, array $types = []): array
    {
        $found = [];

        $checkPatterns = [
            'links' => '/<a\b[^>]*>/i',
            'images' => '/<img\b[^>]*>/i',
            'code' => '/<(pre|code)\b[^>]*>/i',
            'tables' => '/<table\b[^>]*>/i',
            'iframes' => '/<iframe\b[^>]*>/i',
            'videos' => '/<(video|audio)\b[^>]*>/i',
        ];

        if (empty($types)) {
            $types = array_keys($checkPatterns);
        }

        foreach ($types as $type) {
            if (isset($checkPatterns[$type])) {
                if (preg_match($checkPatterns[$type], $content)) {
                    $found[] = $type;
                }
            }
        }

        return $found;
    }

    private function stripAttributes(string $content): string
    {
        return preg_replace('/<(\w+)\s+[^>]*>/', '<$1>', $content);
    }

    private function cleanupSanitizedContent(string $content): string
    {
        $content = preg_replace('/<p>\s*<\/p>/', '', $content);
        $content = preg_replace('/<p>(\s|<br\s*\/?>)*<\/p>/', '', $content);
        $content = preg_replace('/(<br\s*\/?>\s*)+/', '<br>', $content);
        $content = trim($content);

        return $content;
    }
}
