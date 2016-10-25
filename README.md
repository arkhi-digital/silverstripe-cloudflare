# silverstripe-cloudflare

[![Code Climate](https://codeclimate.com/github/steadlane/silverstripe-cloudflare/badges/gpa.svg)](https://codeclimate.com/github/steadlane/silverstripe-cloudflare) [![Issue Count](https://codeclimate.com/github/steadlane/silverstripe-cloudflare/badges/issue_count.svg)](https://codeclimate.com/github/steadlane/silverstripe-cloudflare)

# Introduction

The intention of this module is to relieve the double-handling required when updating any of your pages within the CMS of SilverStripe. When a page is _Published_ or _Unpublished_ a call will be made to the relevant CloudFlare endpoint to clear the cache of the URL/Page you just published/unpublished.

This allows you to see your changes instantly in the preview window without having to worry about logging into the Cloud Flare dashboard to purge the cache yourself.

## Installation

This module only supports installation via composer:

```
composer require steadlane/silverstripe-cloudflare
```

Run `/dev/build` afterwards for SilverStripe to become aware of this extension

## Configuration

Obtain your API key from CloudFlare and enter it into `code/_config/cloudflare.yml` like below:

```
---
Name: cloudflare
After:
  - 'framework/*'
  - 'cms/*'
---

CloudFlare:
  auth:
    email: you@example.com # aka X-Auth-Email
    key: ABCDEFGHIJKLMNOPQRXTUVWXYZ123456789 # aka X-Auth-Key
```

## Contributing

If you feel you can improve this module in any way, shape or form please do not hesitate to submit a PR for review.

## Bugs / Issues

To report a bug or an issue please use our [issue tracker](https://github.com/steadlane/silverstripe-cloudflare/issues).

## License

This module is distributed under the [BSD-3 Clause](https://github.com/steadlane/silverstripe-cloudflare/blob/master/LICENSE) license.