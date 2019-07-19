# silverstripe-cloudflare

[![Build Status](https://travis-ci.org/steadlane/silverstripe-cloudflare.svg?branch=master)](https://travis-ci.org/steadlane/silverstripe-cloudflare) [![Latest Stable Version](https://poser.pugx.org/steadlane/silverstripe-cloudflare/v/stable)](https://packagist.org/packages/steadlane/silverstripe-cloudflare) [![Total Downloads](https://poser.pugx.org/steadlane/silverstripe-cloudflare/downloads)](https://packagist.org/packages/steadlane/silverstripe-cloudflare) [![License](https://poser.pugx.org/steadlane/silverstripe-cloudflare/license)](https://packagist.org/packages/steadlane/silverstripe-cloudflare) [![Monthly Downloads](https://poser.pugx.org/steadlane/silverstripe-cloudflare/d/monthly)](https://packagist.org/packages/steadlane/silverstripe-cloudflare) [![Code Climate](https://codeclimate.com/github/steadlane/silverstripe-cloudflare/badges/gpa.svg)](https://codeclimate.com/github/steadlane/silverstripe-cloudflare)

# Introduction

The intention of this module is to relieve the double-handling required when updating any of your pages within the CMS of SilverStripe while being behind CloudFlare. When a page is _Published_ or _Unpublished_ a call will be made to the relevant CloudFlare endpoint to clear the cache of the URL/Page you just published/unpublished.

This allows you to see your changes instantly in the preview window without having to worry about logging into the Cloud Flare dashboard to purge the cache yourself.

CloudFlare allows you to have multiple domains registered under a single account. This module is versatile in the sense that it will automatically detect which Zone ID is to be used alongside the domain that this module is installed on. Therefore beyond the two configuration settings required below there is no additional setup required. You can "plug and play" this module in as many locations as you want which means you don't have to worry about tracking down the relevant Zone ID (you can only get it via the API).

**Remember**: Always keep your API authentication details secure. If you are concerned with your credentials being on someone else's machine; have them set up their own CloudFlare account.

**Note**: The detected Zone ID will always be shown in the SilverStripe Administration panel whilst viewing the "CloudFlare" menu item

## Features

- Dynamic Zone ID Detection  
- Intelligent Purging
    - If you modify the title or URL of any page: All cache for the zone will be purged.
    - If you modify the contents of any page: Only the cache for that page will be purged.
    - If you modify any page that has a parent, the page you modified and all of it's parents will be purged too.
- Manual Purging
    - The administration area for this module allows you to either purge all css files, all javascript files, all image files or ... everything. 
    
## Installation

This module only supports installation via composer:

```
composer require steadlane/silverstripe-cloudflare
```

Run `/dev/build` afterwards and `?flush=1` for good measure for SilverStripe to become aware of this module

## Configuration

Configuration for this module is minimal, you need only define two constants in your .env file

```
CLOUDFLARE_AUTH_EMAIL="mycloudflare@example.com.au"
CLOUDFLARE_AUTH_KEY="ABCDEFGHIJKLMNOPQRSTUVWXYZ"
```

## Cache Rules
It is recommended that you add the below to your CloudFlare Cache Rules as `no-cache`

| Rule             	| Comments                                                                                                                                                	|
|------------------	|---------------------------------------------------------------------------------------------------------------------------------------------------------	|
| `example.com.au/*stage=Stage*` 	| It is outside the scope of this module to handle cache purging for drafts. Drafts should never need to be cached as they're not usable on the front end 	|
| `example.com.au/Security/*`   	| Prevents caching of the login page etc                                                                                                                  	|
| `example.com.au/admin/*`      	| Prevents caching of the Administrator Panel                                                                                                             	|
| `example.com.au/dev/*`      	| Prevents caching of the development tools                                                                                                             	|

![Bypass Cache Example](http://i.imgur.com/s37SJX4.png)

## Contributing

If you feel you can improve this module in any way, shape or form please do not hesitate to submit a PR for review.

## Troubleshooting and FAQ

Q. **The SS CloudFlare administrator section is blank!**  
A. If the CloudFlare administration panel isn't loading correctly, a quick `?flush=1` will resolve this issue.

Q. **The SS CloudFlare footer always says "Zone ID: UNABLE TO DETECT".**  
A. This module dynamically retrieves your Zone ID by using the domain you have accessed the website with. Ensure this domain is correctly registered under your CloudFlare account. If the issue persists, please open a ticket in our issue tracker and provide as much information you can.


## Bugs / Issues

To report a bug or an issue please use our [issue tracker](https://github.com/steadlane/silverstripe-cloudflare/issues).

## License

This module is distributed under the [BSD-3 Clause](https://github.com/steadlane/silverstripe-cloudflare/blob/master/LICENSE) license.
