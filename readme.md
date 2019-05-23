# SilverStripe Section.io integration (Varnish Cache)

[![Build Status](https://travis-ci.org/innoweb/silverstripe-section-io.svg?branch=master)](https://travis-ci.org/innoweb/silverstripe-section-io)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/innoweb/silverstripe-section-io/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/innoweb/silverstripe-section-io/?branch=master)
[![codecov](https://codecov.io/gh/innoweb/silverstripe-section-io/branch/master/graph/badge.svg)](https://codecov.io/gh/innoweb/silverstripe-section-io)
[![Version](http://img.shields.io/packagist/v/innoweb/silverstripe-section-io.svg?style=flat-square)](https://packagist.org/packages/innoweb/silverstripe-section-io)
[![License](http://img.shields.io/packagist/l/innoweb/silverstripe-section-io.svg?style=flat-square)](license.md)

## Overview

Integrates a SilverStripe installation with [section.io] (https://www.section.io/) varnish cache. section.io is a cloud installation of varnish running on AWS.

It uses varnish bans for flushing, which bans the objects from being delivered from cache (and are therefor re-loaded into the cache from the origin server). 

The module currently has the following functionality:
* flush SiteTree objects from the varnish cache onAfterPublish(). The ban allows different strategies, see configuration section below.
* flush files (i.e. PDF, DOC, etc) from the cache onAfterWrite(). 
* flush images and all resampled versions of those images onAfterWrite(). 

## Requirements

* SilverStripe CMS ^4.0

## Installation

Install the module using composer:
```
composer require innoweb/silverstripe-section-io dev-master
```

Then run dev/build.

See [documentation](docs/en/index.md) for further details.

## License

BSD 3-Clause License, see [License](license.md)

## Documentation

See [documentation](docs/en/index.md)

