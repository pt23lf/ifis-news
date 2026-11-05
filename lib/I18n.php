<?php
declare(strict_types=1);

/**
 * One translation catalogue for news, editorial and private network views.
 * Caller must choose a permitted language: never load a file from raw user input.
 */
final class I18n
{
    private const SUPPORTED = ['de', 'en'];
    private string $language;
    private array $messages;
    private array $fallback;

    public function __construct(string $language = 'de')
    {
        $this->language = self::normalize($language);
        $this->fallback = require dirname(__DIR__) . '/lang/de.php';
        $this->messages = require dirname(__DIR__) . '/lang/' . $this->language . '.php';
    }

    public static function normalize(string $requested): string
    {
        $code = strtolower(substr(str_replace('_', '-', trim($requested)), 0, 2));
        return in_array($code, self::SUPPORTED, true) ? $code : 'de';
    }

    public function language(): string
    {
        return $this->language;
    }

    public function text(string $key, array $parameters = []): string
    {
        $template = $this->messages[$key] ?? $this->fallback[$key] ?? $key;
        foreach ($parameters as $parameter => $value) {
            $template = str_replace('{' . $parameter . '}', (string) $value, $template);
        }
        return $template; // Escape at the HTML output boundary, not in translation data.
    }
}
