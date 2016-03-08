# SilverStripe Section.io integration (Varnish Cache)

## Overview

Integrates a SilverStripe installation with [section.io] (https://www.section.io/) varnish cache. section.io is a cloud installation of varnish running on AWS.

CAUTION: This is early days. This module currently contains a default vcl file for Silverstripe doing the following:
* clean up accept-encoding
* remove cookies for static content
* pass requests for admin, Security and dev requests
* pass requests for multistep forms
* remove most common tracking cookies
* remove adwords gclid parameters
* set default cache time for static content and pages
* set a grace period of one day (if content is expired it is still delivered from the cache for 1 day and reloaded in the background for subsequent requests)

Also see ToDo's below.

## Requirements

* SilverStripe ~3.1

## Installation

1. Download or git clone the 'section-io' directory to your webroot, or;
2. Using composer run the following in the command line: composer require xini/silverstripe-section-io dev-master
3. Run dev/build (http://www.mysite.com/dev/build?flush=all)

Caution:

* HTTP::cache_age needs to be 0 (= default), otherwise the Vary header will be set to "Cookie, X-Forwarded-Protocol, User-Agent, Accept" which pretty much disables caching alltogether

## Known issues

* When a form page is requested and the security token is activated, a cookie is set and all subsequent requests for that user will not be cached because of the cookie. I was thinking about adding something like https://gist.github.com/owindsor/21b289d480d931d457c3, but I haven't tried that yet.

## ToDo

* build an extension to automatically flush the cache when pages are published, see http://www.silverstripe.org/community/forums/customising-the-cms/show/25052?start=8 (for a local varnish installation), https://lassekarstensen.wordpress.com/2014/06/03/what-happened-to-ban-url-in-varnish-4-0/ , https://www.smashingmagazine.com/2014/04/cache-invalidation-strategies-with-varnish-cache/ , http://stackoverflow.com/questions/11119786/varnish-purge-using-http-and-regex, http://foshttpcache.readthedocs.org/en/stable/varnish-configuration.html and https://www.varnish-cache.org/docs/4.0/users-guide/purging.html 
