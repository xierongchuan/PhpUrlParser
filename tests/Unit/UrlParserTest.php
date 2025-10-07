<?php

declare(strict_types=1);

namespace Xierongchuan\UrlParser\Tests\Unit;

use Xierongchuan\UrlParser\UrlParser;
use PHPUnit\Framework\TestCase;

class UrlParserTest extends TestCase
{
    public function test_can_parse_full_url_correctly(): void
    {
        $url = 'https://user:pass@api.example.com:8080/path/page?id=1&name=test#section';
        $parser = new UrlParser($url);

        $this->assertSame('https', $parser->getScheme());
        $this->assertSame('api.example.com', $parser->getHost());
        $this->assertSame(8080, $parser->getPort());
        $this->assertSame('user', $parser->getUser());
        $this->assertSame('pass', $parser->getPass());
        $this->assertSame('/path/page', $parser->getPath());
        $this->assertSame('id=1&name=test', $parser->getQueryString());
        $this->assertSame('section', $parser->getFragment());

        // Проверяем наши улучшения
        $this->assertSame('api', $parser->getSubdomain());
        $this->assertSame('example.com', $parser->getDomain());
        $this->assertSame('com', $parser->getTld());

        $expectedQueryParams = ['id' => '1', 'name' => 'test'];
        $this->assertEquals($expectedQueryParams, $parser->getQueryParams());
    }

    public function test_can_parse_simple_url(): void
    {
        $url = 'http://example.com';
        $parser = new UrlParser($url);

        $this->assertSame('http', $parser->getScheme());
        $this->assertSame('example.com', $parser->getHost());
        $this->assertNull($parser->getSubdomain());
        $this->assertSame('example.com', $parser->getDomain());
        $this->assertSame('com', $parser->getTld());
        $this->assertEmpty($parser->getQueryParams());
        $this->assertNull($parser->getPort());
    }

    public function test_can_parse_ipv4_address(): void
    {
        $url = 'http://192.168.0.1:8080/status';
        $parser = new UrlParser($url);

        $this->assertSame('http', $parser->getScheme());
        $this->assertSame('192.168.0.1', $parser->getHost());
        $this->assertSame(8080, $parser->getPort());
        $this->assertSame('/status', $parser->getPath());

        // IPv4 — domain should equal host, subdomain and tld null
        $this->assertSame('192.168.0.1', $parser->getDomain());
        $this->assertNull($parser->getSubdomain());
        $this->assertNull($parser->getTld());
    }

    public function test_can_parse_ipv6_with_brackets_and_port(): void
    {
        $url = 'https://[2001:db8::1]:8443/health';
        $parser = new UrlParser($url);

        $this->assertSame('https', $parser->getScheme());
        // getHost() возвращает IPv6 без скобок
        $this->assertSame('2001:db8::1', $parser->getHost());
        $this->assertSame(8443, $parser->getPort());
        $this->assertSame('/health', $parser->getPath());

        // Для IP нет разбора domain/tld/subdomain
        $this->assertSame('2001:db8::1', $parser->getDomain());
        $this->assertNull($parser->getSubdomain());
        $this->assertNull($parser->getTld());
    }

    public function test_localhost_and_single_label_host(): void
    {
        $url = 'http://localhost:3000/';
        $parser = new UrlParser($url);

        $this->assertSame('http', $parser->getScheme());
        $this->assertSame('localhost', $parser->getHost());
        $this->assertSame(3000, $parser->getPort());
        $this->assertSame('/', $parser->getPath());

        // single-label host -> domain == host, tld == null
        $this->assertSame('localhost', $parser->getDomain());
        $this->assertNull($parser->getSubdomain());
        $this->assertNull($parser->getTld());
    }

    public function test_multi_level_subdomain_and_co_uk_behavior(): void
    {
        // Демонстрирует текущее (наивное) поведение разбора domain/tld без PSL:
        // для example.co.uk parser вернёт domain = "co.uk", tld = "uk"
        $url = 'https://api.v2.example.co.uk/path';
        $parser = new UrlParser($url);

        $this->assertSame('https', $parser->getScheme());
        $this->assertSame('api.v2.example.co.uk', $parser->getHost());
        $this->assertSame('/path', $parser->getPath());

        // Ожидаемое поведение без Public Suffix List
        $this->assertSame('co.uk', $parser->getDomain());
        $this->assertSame('uk', $parser->getTld());
        $this->assertSame('api.v2.example', $parser->getSubdomain());
    }

    public function test_query_array_parsing(): void
    {
        $url = 'https://example.com/search?tags[]=php&tags[]=unit&foo=bar';
        $parser = new UrlParser($url);

        $this->assertSame('https', $parser->getScheme());
        $this->assertSame('example.com', $parser->getHost());

        $expected = [
            'tags' => ['php', 'unit'],
            'foo' => 'bar',
        ];

        $this->assertEquals($expected, $parser->getQueryParams());
    }
}
