# Changelog

All notable changes to this project will be documented in this file.

This project adheres to [Semantic Versioning](http://semver.org/).

## [1.5.0]

* add section.io geo location header in default vcl
* clean up default.vcl

## [1.4.3]

* update root certificate

## [1.4.2]

* change cookie exclusion regex in default.vcl for case where file name is in URL parameter (e.g. fileexists test in UploadField)

## [1.4.1]

* fix typo

## [1.4.0]

* add support for [DMS documents] (https://github.com/silverstripe/silverstripe-dms).

## [1.3.2]

* update root certificate

## [1.3.1]

* update travis config
* fix form detection in default.vcl

## [1.3.0]

* add X-SS-Form header to user forms via extension that can be used for custom forms as well
* add https redirect option to default.vcl (needs to be uncommented to be active)
* improve handling of tracking cookies in default.vcl
* update readme

Please read the updated [documentation](docs/en/index.md).

## [1.2.9]

* update root certificate

## [1.2.8]

* update root certificate

## [1.2.7]

* update root certificate

## [1.2.6]

* fix config warning

## [1.2.5]

* remove config warning if in dev mode

## [1.2.4]

* add statcounter cookie to default exclusion list (vcl file)
* fix logging

## [1.2.3]

* fix typo in SectionIO file name
* fix logging
* add include of default section features in defualt vcl file

## [1.2.2]

* Use SS_Log::log() instead of user_error() in case config is not set or API calls fail


## [1.2.1]

Fix version number in changelog (1.2.0 instead of 1.1.3)


## [1.2.0]

Change license and updates for SS module standard.

* change license to BSD 3-Clause
* update readme and extract docs
* refactor code for testing
* add tests
* change to PSR-2
* add contributing file
* add standard code of conduct
* add standard editor config
* add standard git attributes
* add standard travis config
* add standard scrutinizer config
* add changelog
