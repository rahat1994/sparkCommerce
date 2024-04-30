# Sparkcommerce: Ecommerce plugin for filament

[![Latest Version on Packagist](https://img.shields.io/packagist/v/rahat1994/sparkcommerce.svg?style=flat-square)](https://packagist.org/packages/rahat1994/sparkcommerce)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/rahat1994/sparkcommerce/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/rahat1994/sparkcommerce/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/rahat1994/sparkcommerce/fix-php-code-styling.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/rahat1994/sparkcommerce/actions?query=workflow%3A"Fix+PHP+code+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/rahat1994/sparkcommerce.svg?style=flat-square)](https://packagist.org/packages/rahat1994/sparkcommerce)

Designed for developers, the Sparkcommerce Plugin offers a comprehensive solution for managing products, orders, and customer data within your Laravel applications. With a focus on simplicity and efficiency, Sparkcommerce streamlines your workflow and enhances productivity.

## Installation

You can install the package via composer:

```bash
composer require rahat1994/sparkcommerce
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="sparkcommerce-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="sparkcommerce-config"
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="sparkcommerce-views"
```

This is the contents of the published config file:

```php
return [
];
```

## Usage

```php
$sparkCommerce = new Rahat1994\SparkCommerce();
echo $sparkCommerce->echoPhrase('Hello, Rahat1994!');
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Rahat Baksh](https://github.com/rahat1994)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
