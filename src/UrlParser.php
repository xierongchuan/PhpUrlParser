<?php

declare(strict_types=1);

namespace Xierongchuan\UrlParser;

/**
 * Класс UrlParser
 *
 * Разбирает URL на компоненты без использования parse_url().
 * Поддерживает:
 *  - схема (scheme)
 *  - учетные данные (user / pass)
 *  - хост (включая IPv6 в квадратных скобках)
 *  - порт
 *  - путь
 *  - query + разбор query в ассоциативный массив
 *  - фрагмент (hash)
 *  - выделение subdomain / domain / tld (наивный разбор по точкам;
 *    для публичных суффиксов нужен PSL)
 *
 * Пример:
 * <code>
 * $p = new UrlParser('https://user:pass@api.sub.example.com:8080/path?a=1#frag');
 * $p->getDomain(); // example.com
 * $p->getSubdomain(); // api.sub
 * </code>
 *
 * @package Xierongchuan\UrlParser
 */
final class UrlParser
{
    /**
     * Массив разобранных частей URL.
     *
     * Ключи: scheme, host, port, user, pass, path, query, queryParams, fragment,
     * subdomain, domain, tld
     *
     * @var array<string, mixed>
     */
    private array $parts = [];

    /**
     * Конструктор.
     *
     * @param string $url URL для разбора
     */
    public function __construct(private readonly string $url)
    {
        $this->parse();
    }

    /**
     * Парсит URL регулярным выражением и дополняет данные.
     *
     * Не использует parse_url().
     *
     * @return void
     */
    private function parse(): void
    {
        // Регекс покрывает: [scheme:][//[user[:pass]@]host[:port]][/path][?query][#fragment]
        $pattern = '/^(?:(?P<scheme>[a-z][a-z0-9+\-.]*):)?' . // схема
            '(?:\/\/' .
                '(?:(?P<user>[^:@\/?#]+)(?::(?P<pass>[^@\/?#]*))?@)?' . // учётные данные
                '(?P<host>\[[^\]]+\]|[^:\/?#]+)?' . // хост (включая [IPv6])
                '(?::(?P<port>\d+))?' . // порт
            ')?' .
            '(?P<path>\/[^?#]*)?' . // путь
            '(?:\?(?P<query>[^#]*))?' . // query
            '(?:#(?P<fragment>.*))?' . // fragment
        '$/i';

        $matches = [];
        preg_match($pattern, $this->url, $matches);

        // Заполняем базовые части (null если не найдено)
        $this->parts['scheme'] = $matches['scheme'] ?? null;
        $this->parts['user'] = $matches['user'] ?? null;
        $this->parts['pass'] = $matches['pass'] ?? null;

        // Host может быть в квадратных скобках (IPv6) — убираем скобки при необходимости
        $rawHost = $matches['host'] ?? null;
        if ($rawHost !== null && mb_strlen($rawHost) > 0) {
            $host = $rawHost;
            if ($host[0] === '[' && str_ends_with($host, ']')) {
                $host = substr($host, 1, -1); // убираем квадратные скобки для IP
            }
            $this->parts['host'] = $host;
        } else {
            $this->parts['host'] = null;
        }

        // Порт — приводим к int если задан
        $this->parts['port'] = isset($matches['port']) && $matches['port'] !== '' ? (int)$matches['port'] : null;

        // Путь / query / fragment
        $this->parts['path'] = $matches['path'] ?? null;
        $this->parts['query'] = $matches['query'] ?? null;
        $this->parts['fragment'] = $matches['fragment'] ?? null;

        // Разбор query в массив (без использования глобальных переменных)
        if (!empty($this->parts['query'])) {
            parse_str($this->parts['query'], $queryParams);
            $this->parts['queryParams'] = $queryParams;
        } else {
            $this->parts['queryParams'] = [];
        }

        // Разбор host на subdomain / domain / tld
        $this->parts['subdomain'] = null;
        $this->parts['domain'] = null;
        $this->parts['tld'] = null;

        if (!empty($this->parts['host'])) {
            $host = $this->parts['host'];

            // Если это IP (v4 или v6) — не пытаемся резать на домен/tld
            if (filter_var($host, FILTER_VALIDATE_IP)) {
                $this->parts['domain'] = $host;
                // subdomain и tld останутся null
            } else {
                // Обычный домен — разбиваем по точкам
                $labels = explode('.', $host);
                $labelsCount = count($labels);

                if ($labelsCount === 1) {
                    // localhost или single-label host
                    $this->parts['domain'] = $host;
                } elseif ($labelsCount >= 2) {
                    // TLD — последняя метка
                    $tld = array_pop($labels);
                    $second = array_pop($labels);
                    $this->parts['tld'] = $tld;
                    $this->parts['domain'] = $second . '.' . $tld;

                    // Если остались метки — это поддомен(ы)
                    if (count($labels) > 0) {
                        $this->parts['subdomain'] = implode('.', $labels);
                    } else {
                        $this->parts['subdomain'] = null;
                    }
                }
            }
        }
    }

    /**
     * Возвращает исходный URL.
     *
     * @return string
     */
    public function getOriginalUrl(): string
    {
        return $this->url;
    }

    /**
     * Возвращает схему (protocol), например "https".
     *
     * @return string|null
     */
    public function getScheme(): ?string
    {
        return $this->parts['scheme'] ?? null;
    }

    /**
     * Возвращает полный хост (например "sub.example.com" или "2001:db8::1").
     *
     * @return string|null
     */
    public function getHost(): ?string
    {
        return $this->parts['host'] ?? null;
    }

    /**
     * Возвращает порт как int, либо null.
     *
     * @return int|null
     */
    public function getPort(): ?int
    {
        return $this->parts['port'] ?? null;
    }

    /**
     * Возвращает имя пользователя из URL, если оно есть.
     *
     * @return string|null
     */
    public function getUser(): ?string
    {
        return $this->parts['user'] ?? null;
    }

    /**
     * Возвращает пароль из URL, если он есть.
     *
     * @return string|null
     */
    public function getPass(): ?string
    {
        return $this->parts['pass'] ?? null;
    }

    /**
     * Возвращает путь (начинается с '/'), либо null.
     *
     * @return string|null
     */
    public function getPath(): ?string
    {
        return $this->parts['path'] ?? null;
    }

    /**
     * Возвращает query-строку (без '?'), либо null.
     *
     * @return string|null
     */
    public function getQueryString(): ?string
    {
        return $this->parts['query'] ?? null;
    }

    /**
     * Возвращает разобранные параметры запроса как ассоциативный массив.
     *
     * @return array<string, mixed>
     */
    public function getQueryParams(): array
    {
        return $this->parts['queryParams'] ?? [];
    }

    /**
     * Возвращает фрагмент (часть после '#'), либо null.
     *
     * @return string|null
     */
    public function getFragment(): ?string
    {
        return $this->parts['fragment'] ?? null;
    }

    /**
     * Возвращает поддомен (все метки до второго с конца), например "api.v2".
     *
     * @return string|null
     */
    public function getSubdomain(): ?string
    {
        return $this->parts['subdomain'] ?? null;
    }

    /**
     * Возвращает основной домен без поддомена, например "example.com".
     *
     * @return string|null
     */
    public function getDomain(): ?string
    {
        return $this->parts['domain'] ?? null;
    }

    /**
     * Возвращает TLD (последняя метка), например "com".
     *
     * @return string|null
     */
    public function getTld(): ?string
    {
        return $this->parts['tld'] ?? null;
    }

    /**
     * Возвращает весь массив разобранных частей.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->parts;
    }
}
