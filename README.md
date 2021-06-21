# Tribe Storage Plugin: Statically.io

[![PHPCS + Unit Tests](https://github.com/moderntribe/tribe-storage-statically-cdn/actions/workflows/pull-request.yml/badge.svg)](https://github.com/moderntribe/tribe-storage-statically-cdn/actions/workflows/pull-request.yml)
![php 7.3+](https://img.shields.io/badge/php-min%207.3-red.svg)

Provides dynamic image sizing via [statically.io](https://statically.io/) and disables WordPress's automatic 
thumbnail creation.

## Installation Composer v1

Add the following to the composer.json `repositories` object:

```json
  "repositories": [
      {
        "type": "vcs",
        "url": "git@github.com:moderntribe/tribe-storage-statically-cdn.git"
      }
  ]
```
Then run:

```bash
composer require moderntribe/tribe-storage-statically-cdn
```

## Configuration

There are two ways to configure this plugin, either directly using the `cdn.statically.io` directly, or proxying
to that domain via Nginx.

### Use statically.io URLs directly

Ensure you have defined the `TRIBE_STORAGE_URL` constant in `wp-config.php` to your cloud provider's publicly
accessible URL and it will be replaced to use Statically's CDN:

> **NOTE:** Image URLs are cached, ensure your flush your object cache if you make any changes to the following
> defines.

```php
// Azure example
define( 'TRIBE_STORAGE_URL', 'https://account.blob.core.windows.net/container' );
```

URL rewriting would look as follows:

- Original: `https://example.com/wp-content/uploads/sites/4/2021/06/image.jpg`
- Rewritten: `https://cdn.statically.io/img/account.blob.core.windows.net/container/f=auto,w=1024,h=1024/wp-content/uploads/sites/4/2021/06/image.jpg`

### Use statically.io as a proxy via Nginx

Ensure you **do not** have `TRIBE_STORAGE_URL` defined, and define the following in `wp-config.php`:

```php
define( 'TRIBE_STORAGE_STATICALLY_PROXY', true );
```

**Sample Nginx Proxy**

> Replace <PUBLIC CLOUD PROVIDER HOST + PATH> below with your cloud provider's URL. Using the example from 
> above it would be: `account.blob.core.windows.net/container`

```nginx
# Root site
location ~* ^/wp-content/uploads(.+)\.(?:gif|jpg|png|jpeg|pdf) {
    add_header X-Image-Path "$uri" always;
    try_files $uri $uri/ @uploads;
}

# Multisite sub directory
location ~* ^/(.+)/wp-content/uploads(.+)\.(?:gif|jpg|png|jpeg|pdf) {
    add_header X-Image-Path "$uri" always;
    try_files $uri $uri/ @uploads;
}

# Sub sites with locale, e.g /en-us/wp-content/uploads...
location ~* "^/[a-z]{2}-[a-z]{2}/wp-content/uploads" {
    try_files $uri $uri/ @uploads;
}

location @uploads {
    rewrite "^/[a-z]{2}-[a-z]{2}/wp-content/uploads(.*)$" $1 break;
    rewrite ^/(.*)/wp-content/uploads(.*)$ $2 break;
    rewrite ^/wp-content/uploads(.*)$ $1 break;
    proxy_http_version 1.1;
    resolver 1.1.1.1;

    proxy_set_header Connection '';
    proxy_set_header Authorization '';
    proxy_set_header Host cdn.statically.io;

    proxy_hide_header Set-Cookie;
    proxy_ignore_headers Set-Cookie;
    proxy_intercept_errors on;

    add_header X-Image-Path "$uri" always;

    proxy_pass https://cdn.statically.io/img/<PUBLIC CLOUD PROVIDER HOST + PATH>$uri;
}
```

URL rewriting would look as follows, and proxied to Statically behind the scenes:

- Original: `https://example.com/wp-content/uploads/sites/4/2021/06/image.jpg`
- Rewritten: `https://example.com/wp-content/uploads/f=auto,w=1024,h=1024/sites/4/2021/06/image.jpg`

## Automated Testing

Testing provided via [PHPUnit](https://phpunit.de/) and the [Brain Monkey](https://brain-wp.github.io/BrainMonkey/)
testing suite.

#### Run Unit Tests

```bash
$ composer install
$ ./vendor/bin/phpunit
```

## More Resources:

- [Tribe Storage Documentation](https://github.com/moderntribe/tribe-storage)
- [Modern Tribe](https://tri.be/)
