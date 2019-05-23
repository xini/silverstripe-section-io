# SilverStripe Section.io integration (Varnish Cache)

[![Build Status](http://img.shields.io/travis/xini/silverstripe-section-io.svg?style=flat-square)](https://travis-ci.org/xini/silverstripe-section-io)
[![Code Quality](http://img.shields.io/scrutinizer/g/xini/silverstripe-section-io.svg?style=flat-square)](https://scrutinizer-ci.com/g/xini/silverstripe-section-io)
[![Code Coverage](http://img.shields.io/scrutinizer/coverage/g/xini/silverstripe-section-io.svg?style=flat-square)](https://scrutinizer-ci.com/g/xini/silverstripe-section-io)
[![Version](http://img.shields.io/packagist/v/xini/silverstripe-section-io.svg?style=flat-square)](https://packagist.org/packages/xini/silverstripe-section-io)
[![License](http://img.shields.io/packagist/l/xini/silverstripe-section-io.svg?style=flat-square)](license.md)

## Overview

Integrates a SilverStripe installation with [section.io](https://www.section.io/) varnish cache. section.io is a cloud installation of varnish running on AWS.

It uses varnish bans for flushing, which bans the objects from being delivered from cache (and are therefor re-loaded into the cache from the origin server). 

The module currently has the following functionality:
* flush SiteTree objects from the varnish cache onAfterPublish(). The ban allows different strategies, see configuration section below.
* flush files (i.e. PDF, DOC, etc) from the cache onAfterWrite(). 
* flush images and all resampled versions of those images onAfterWrite(). 

This is still early stages, PRs welcome!  

## Requirements

* SilverStripe CMS ~3.1

## Installation

Install the module using composer:
```
composer require xini/silverstripe-section-io dev-master
```

Then run dev/build.

See [documentation](docs/en/index.md) for further details.

## License

BSD 3-Clause License, see [License](license.md)

## Documentation

See [documentation](docs/en/index.md)

