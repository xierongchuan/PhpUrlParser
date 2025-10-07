# PhpUrlParser
Выполнение тестового задания #4
Время выполнения ~1ч

## Задание #4
Напишите парсер урлов, который должен выдавать те же или больше данных что и parse_url.

## Test
```bash
composer test
```

## Using
```php
use Xierongchuan\UrlParser\UrlParser;

$url = 'https://user:pass@api.example.com:8080/path/page?id=1&name=test#section';
$parser = new UrlParser($url);

echo $parser->getScheme();      // https
echo $parser->getHost();        // api.example.com
echo $parser->getPort();        // 8080
echo $parser->getUser();        // user
echo $parser->getPass();        // pass
echo $parser->getPath();        // /path/page
echo $parser->getQueryString(); // id=1&name=test
echo $parser->getFragment();    // section

echo $parser->getSubdomain();   // api
echo $parser->getDomain();      // example.com
echo $parser->getTld();         // com

print_r($parser->getQueryParams()); // ['id' => '1', 'name' => 'test']
```
