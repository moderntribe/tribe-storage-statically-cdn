# Tribe Storage Plugin: Statically.io

[![PHPCS + Unit Tests](https://github.com/moderntribe/tribe-storage-statically-cdn/actions/workflows/pull-request.yml/badge.svg)](https://github.com/moderntribe/tribe-storage-statically-cdn/actions/workflows/pull-request.yml)
![php 7.3+](https://img.shields.io/badge/php-min%207.3-red.svg)

Provides dynamic image sizing and optimization via [statically.io](https://statically.io/) and only creates WordPress thumbnails 
for images that require hard cropping.

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
accessible URL and it will be replaced to use Statically's CDN for images only:

> **NOTE:** Image URLs are cached, ensure your flush your object cache if you make any changes to the following
> defines.

```php
// Azure example
define( 'TRIBE_STORAGE_URL', 'https://account.blob.core.windows.net/container' );
```

URL rewriting would look as follows:

- Original: `https://account.blob.core.windows.net/container/sites/4/2021/06/image.jpg`
- Rewritten: `https://cdn.statically.io/img/account.blob.core.windows.net/f=auto,w=1024,h=1024/container/sites/4/2021/06/image.jpg`

### Use statically.io as a proxy via Nginx

Ensure you **do not** have `TRIBE_STORAGE_URL` defined, and define the following in `wp-config.php`:

```php
define( 'TRIBE_STORAGE_STATICALLY_PROXY', true );
```

**Sample Nginx Proxy**

> Replace `account.blob.core.windows.net` below with your cloud provider's hostname. Using the example from 
> above it would be: `account.blob.core.windows.net/container`

```nginx
# Root site and sub sites
location ~* ^/(.+)?wp-content/uploads {
    try_files $uri $uri/ @statically;
}

# Check statically first
location @statically {
    # adjust the /container below to your actual container name
    rewrite "^/(.+)?wp-content/uploads/(.*=.*?[\/])?(.+)$" /$2container/$3 break;

    proxy_http_version 1.1;
    resolver 1.1.1.1;

    proxy_set_header Connection '';
    proxy_set_header Authorization '';
    proxy_set_header Host cdn.statically.io;

    proxy_hide_header Set-Cookie;
    proxy_ignore_headers Set-Cookie;

    proxy_intercept_errors on;
    recursive_error_pages on;
    error_page 400 404 500 = @uploads;

    add_header X-Image-Path "$uri" always;
    
    proxy_pass https://cdn.statically.io/img/account.blob.core.windows.net$uri;
}

# Fallback to check Azure directly
location @uploads {
    # remove any statically.io params, e.g f=auto,w=518,h=291/
    rewrite ^/(.*=.*?[\/])?(.+)$ /$2 break;
    proxy_http_version 1.1;
    resolver 1.1.1.1;

    proxy_set_header Connection '';
    proxy_set_header Authorization '';
    proxy_set_header Host account.blob.core.windows.net;

    proxy_hide_header x-ms-blob-type;
    proxy_hide_header x-ms-lease-status;
    proxy_hide_header x-ms-request-id;
    proxy_hide_header x-ms-version;
    proxy_hide_header Set-Cookie;
    proxy_ignore_headers Set-Cookie;
    
    proxy_intercept_errors on;
    recursive_error_pages on;
    error_page 400 404 500 = @imageerror;

    add_header X-Image-Path "$uri" always;
    add_header Cache-Control max-age=31536000;

    proxy_pass https://account.blob.core.windows.net$uri;
}

# If both the above fail, show the default Nginx 404 error page
location @imageerror {
    add_header X-Error-Uri "$uri" always;
    return 404;
}
```

URL rewriting would look as follows, and proxied to Statically behind the scenes:

- Original: `https://example.com/wp-content/uploads/sites/4/2021/06/image.jpg`
- Rewritten: `https://example.com/wp-content/uploads/f=auto,w=1024,h=1024/sites/4/2021/06/image.jpg`
- Proxied URL: `https://cdn.statically.io/img/account.blob.core.windows.net/f=auto,w=1024,h=1024/container/sites/4/2021/06/image.jpg`

## Disable WordPress thumbnail creation

If you're not concerned with exact cropping, you can let statically.io resize your image based with keeping the same
dimension ratio and disable thumbnail creation to see a large performance boost when uploading new images. 

For this you have two options:

Option 1: Add this define to `wp-config.php`
```php
define( 'TRIBE_STORAGE_STATICALLY_CREATE_THUMBNAILS', false );
```

Option 2: Make the `tribe/storage/plugin/statically/create_thumbnails` filter return false, e.g.

```php
add_filter( 'tribe/storage/plugin/statically/create_thumbnails', '__return_false' );
```

> NOTE: Don't forget to clear object caching and regenerate thumbnails each time this option is swaped.

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
