# SilverStripe Section.io integration (Varnish Cache)

## Overview

Integrates a SilverStripe installation with [section.io] (https://www.section.io/) varnish cache. section.io is a cloud installation of varnish running on AWS.

It uses varnish bans for flushing, which bans the objects from being delivered from cache (and are therefor re-loaded into the cache from the origin server). 

The module currently has the following functionality:
* flush SiteTree objects from the varnish cache onAfterPublish(). The ban allows different strategies, see configuration section below.
* flush files (i.e. PDF, DOC, etc) from the cache onAfterWrite(). 
* flush images and all resampled versions of those images onAfterWrite(). 

This is still early stages, PRs welcome!  

## Requirements

* SilverStripe CMS ~3.1

## Installation

1. composer require xini/silverstripe-section-io dev-master (or download or git clone the module into a ‘section-io’ directory in your webroot)
2. run dev/build (http://www.mysite.com/dev/build?flush=all)

**Caution:**

* HTTP::cache_age needs to be 0 (= default), otherwise the Vary header will be set to "Cookie, X-Forwarded-Protocol, User-Agent, Accept" which pretty much disables caching alltogether

### Default VCL

The module contains a default vcl (Varnish Configuration Language) file you can use with this module. The file does the following:
* clean up accept-encoding
* remove cookies for static content (based on /assets/.htaccess)
* pass requests for admin, Security and dev requests
* pass requests for multistep forms
* pass requests if the login marker cookie 'sslogin' is set
* remove most common tracking cookies
* remove adwords gclid parameters
* set default cache time for static content and pages
* set a grace period of one day (if content is expired it is still delivered from the cache for 1 day and reloaded in the background for subsequent requests)

It also saves the URL of an object to a temporary field `http.x-url` in `vcl_backend_response` and removes it again before delivering the object to the client in `vcl_deliver`. If you edit the file, these are the sections you shouldn't change for the module to work!

## Configuration

### section.io API data

You need to add the following to your config.yml: 

```
SectionIO:
  account_id: '{your section.io account ID}'
  application_id: '{your section.io application ID}'
  environment_name: '{your section.io environment name}'
  proxy_name: '{your section.io proxy name}'
  username: '{your section.io username}'
  password: '{your section.io password}'
```

Go to the API section in your https://aperture.section.io/ application account. You'll find the details (apart from your username and password) within the API URLs displayed on that page.

Unfortunately the section.io API doesn't allow any auth method other than username and password. You can create a seperate user for your API calls, but that user would still have the same permissions as your main account. I have requested the introduction of something similar to deploy keys. (Status 03/2016) 

To configure different behaviour for different environments please use the default SS config options for [environment specific settings] (https://docs.silverstripe.org/en/3.3/developer_guides/configuration/configuration/#exclusionary-rules) or your mysite/_config.php file to set the config for a specific environment only. 
If one of the settings is missing for an environment, the API will not be called and a warning will be logged. 


### SiteTree flush strategy

SiteTree objects are banned from being delivered ("flushed") `onAfterPublish`. You can change the strategy used for this ban:  

```
SectionIO:
  sitetree_flush_strategy: '{single|parents|all|everything}'
```

* `single` (default) only bans the SiteTree object currently published.
* `parents` bans the current page as well as all its parents.
* `all` bans all pages on the site.
* `everyting` bans the whole site.

### flush on /dev/build

You can configure whether the whole site should be banned from delivery from cache ("flushed") on dev/build: 

```
SectionIO:
  flush_on_dev_build: {true|false}
```

Default is `true` and it flushes the whole site.

### Front-end login

If you want the cache to be disabled for logged in users, you can add the following to your config.yml:

```
Member:
  login_marker_cookie: sslogin
```

If this is set, a session cookie called "sslogin" will be set to "1" whenever a user logs in. This cookie is checked in the default vcl included in this module and all requests passed if the cookie is set. 

### Varnish node locations

When you setup your section.io account and application the varnish nodes will be set up in your "home" AWS region only by default. If you need cache nodes in other regions, please ask section.io support to put your application on a network with nodes in those regions. 

## Known issues / ToDo

* When a form page is requested and the security token is activated, a cookie is set and all subsequent requests for that user will not be cached because of the cookie. I was thinking about adding something like https://gist.github.com/owindsor/21b289d480d931d457c3, but I haven't tried that yet.
