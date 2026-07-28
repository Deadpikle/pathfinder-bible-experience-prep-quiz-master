<?php

namespace App\Helpers;

/**
 * Deliberately small CommonMark-style renderer for administrator-authored home
 * content. All input is escaped first; only a conservative Markdown subset is
 * emitted. Raw HTML and unsafe URI schemes are never passed through.
 */
final class SafeMarkdown
{
    public static function render(string $markdown): string
    {
        $lines = preg_split('/\R/u', str_replace("\0", '', $markdown)) ?: [];
        $html = [];
        $listType = null;

        foreach ($lines as $line) {
            $line = rtrim($line);
            if (trim($line) === '') {
                self::closeList($html, $listType);
                continue;
            }

            if (preg_match('/^(#{1,3})\s+(.+)$/u', $line, $match)) {
                self::closeList($html, $listType);
                $level = strlen($match[1]);
                $html[] = '<h' . $level . '>' . self::inline($match[2]) . '</h' . $level . '>';
                continue;
            }

            if (preg_match('/^\s*[-*]\s+(.+)$/u', $line, $match)) {
                self::switchList($html, $listType, 'ul');
                $html[] = '<li>' . self::inline($match[1]) . '</li>';
                continue;
            }

            if (preg_match('/^\s*\d+[.)]\s+(.+)$/u', $line, $match)) {
                self::switchList($html, $listType, 'ol');
                $html[] = '<li>' . self::inline($match[1]) . '</li>';
                continue;
            }

            self::closeList($html, $listType);
            if (preg_match('/^>\s*(.*)$/u', $line, $match)) {
                $html[] = '<blockquote><p>' . self::inline($match[1]) . '</p></blockquote>';
            } else {
                $html[] = '<p>' . self::inline(trim($line)) . '</p>';
            }
        }

        self::closeList($html, $listType);
        return implode("\n", $html);
    }

    private static function switchList(array &$html, ?string &$listType, string $nextType): void
    {
        if ($listType === $nextType) {
            return;
        }
        self::closeList($html, $listType);
        $html[] = '<' . $nextType . '>';
        $listType = $nextType;
    }

    private static function closeList(array &$html, ?string &$listType): void
    {
        if ($listType !== null) {
            $html[] = '</' . $listType . '>';
            $listType = null;
        }
    }

    private static function inline(string $text): string
    {
        $tokens = [];
        $text = preg_replace_callback('/`([^`]+)`/u', function (array $match) use (&$tokens): string {
            return self::token($tokens, '<code>' . self::escape($match[1]) . '</code>');
        }, $text) ?? $text;

        $text = preg_replace_callback('/\[([^\]]+)\]\(([^\s)]+)(?:\s+"[^"]*")?\)/u', function (array $match) use (&$tokens): string {
            $label = self::escape($match[1]);
            $url = trim($match[2]);
            if (!self::isSafeUrl($url)) {
                return self::token($tokens, $label);
            }
            $external = preg_match('/^https?:\/\//i', $url) === 1;
            $attributes = $external ? ' target="_blank" rel="noopener noreferrer"' : '';
            return self::token(
                $tokens,
                '<a href="' . self::escape($url) . '"' . $attributes . '>' . $label . '</a>'
            );
        }, $text) ?? $text;

        $text = self::escape($text);
        $text = preg_replace('/\*\*([^*]+)\*\*/u', '<strong>$1</strong>', $text) ?? $text;
        $text = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/u', '<em>$1</em>', $text) ?? $text;

        foreach ($tokens as $token => $html) {
            $text = str_replace(self::escape($token), $html, $text);
        }
        return $text;
    }

    /** @param array<string, string> $tokens */
    private static function token(array &$tokens, string $html): string
    {
        $token = 'PBEMARKDOWNTOKEN' . count($tokens) . 'END';
        $tokens[$token] = $html;
        return $token;
    }

    private static function isSafeUrl(string $url): bool
    {
        if ($url === '' || str_starts_with($url, '//')) {
            return false;
        }
        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return true;
        }
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https', 'mailto'], true);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
