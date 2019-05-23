<?php

namespace Innoweb\SectionIO\Tests;

use Innoweb\SectionIO\SectionIO;
use Innoweb\SectionIO\Extensions\SectionIOFileExtension;
use Innoweb\SectionIO\Extensions\SectionIOSiteTreeExtension;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Dev\TestAssetStore;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;

class SectionIOTest extends SapphireTest
{
    protected static $fixture_file = 'SectionIOTest.yml';

    public function setUpOnce()
    {
        parent::setUpOnce();

        // add config values
        Config::inst()->update(SectionIO::class, 'flush_on_dev_build', true);
        Config::inst()->update(SectionIO::class, 'api_url', 'https://example.com');
        Config::inst()->update(SectionIO::class, 'account_id', '123456');
        Config::inst()->update(SectionIO::class, 'application_id', '987654');
        Config::inst()->update(SectionIO::class, 'environment_name', 'Production');
        Config::inst()->update(SectionIO::class, 'proxy_name', 'myproxy');
        Config::inst()->update(SectionIO::class, 'username', 'someuser');
        Config::inst()->update(SectionIO::class, 'password', 'MySafePassword');
        Config::inst()->update(SectionIO::class, 'verify_ssl', false);

        // remove extensions otherwise the fixtures will break the tests (by calling the live flush)
        File::remove_extension(SectionIOFileExtension::class);
        SiteTree::remove_extension(SectionIOSiteTreeExtension::class);
    }

    public function setUp()
    {
        parent::setUp();
        
        // Set backend root to /ImageTest
        TestAssetStore::activate('FileTest');
        
        // Create a test files for each of the fixture references
        $fileIDs = array_merge(
            $this->allFixtureIDs(File::class),
            $this->allFixtureIDs(Image::class)
        );
        foreach ($fileIDs as $fileID) {
            /** @var File $file */
            $file = DataObject::get_by_id(File::class, $fileID);
            $file->setFromString(str_repeat('x', 1000000), $file->getFilename());
        }
        
    }

    public function tearDownOnce()
    {
        parent::tearDownOnce();
        
        // re-add extensions
        File::add_extension('SectionIOFileExtension');
        SiteTree::add_extension('SectionIOSiteTreeExtension');
    }

    public function tearDown()
    {
        TestAssetStore::reset();
        
        parent::tearDown();
    }

    public function testFlushAll()
    {
        $result = SectionIOTest_MySectionIO::flushAll();

        $this->assertCount(
            1,
            $result,
            'one url returned for one application id'
        );

        // url
        $this->assertEquals(
            'https://example.com/account/123456/application/987654/environment/Production/proxy/myproxy/state',
            $result[0]['url'],
            'URL is concatenated correctly'
        );

        // ban expression
        $this->assertEquals(
            'obj.http.x-url ~ /',
            $result[0]['banExpression'],
            'ban expression is correct'
        );

    }

    public function testFlush()
    {
        $result = SectionIOTest_MySectionIO::flush();

        $this->assertCount(
            1,
            $result,
            'one url returned for one application id'
        );

        // url
        $this->assertEquals(
            'https://example.com/account/123456/application/987654/environment/Production/proxy/myproxy/state',
            $result[0]['url'],
            'URL is concatenated correctly'
        );

        // ban expression
        $this->assertEquals(
            'obj.http.x-url ~ /',
            $result[0]['banExpression'],
            'ban expression is correct'
        );

        // test deactivated flush on build
        Config::inst()->update(SectionIO::class, 'flush_on_dev_build', false);
        $result = SectionIOTest_MySectionIO::flush();
        $this->assertNull(
            $result,
            'null returned if flush on build deactivated'
        );
    }

    public function testMultipleApplicationIDs()
    {
        // add second application to config
        Config::inst()->update(SectionIO::class, 'application_id', '2546987,856954');

        $result = SectionIOTest_MySectionIO::flushAll();

        $this->assertCount(
            2,
            $result,
            'two urls returned for two application id'
        );

        // url
        $this->assertEquals(
            'https://example.com/account/123456/application/2546987/environment/Production/proxy/myproxy/state',
            $result[0]['url'],
            'URL is concatenated correctly for app 1'
        );
        $this->assertEquals(
            'https://example.com/account/123456/application/856954/environment/Production/proxy/myproxy/state',
            $result[1]['url'],
            'URL is concatenated correctly for app 2'
        );

        // add second application to config with spaces in csv
        Config::inst()->update(SectionIO::class, 'application_id', '741852, 369258');

        $result = SectionIOTest_MySectionIO::flushAll();

        $this->assertCount(
            2,
            $result,
            'two urls returned for two application id'
        );

        // url
        $this->assertEquals(
            'https://example.com/account/123456/application/741852/environment/Production/proxy/myproxy/state',
            $result[0]['url'],
            'URL is concatenated correctly for app 1'
        );
        $this->assertEquals(
            'https://example.com/account/123456/application/369258/environment/Production/proxy/myproxy/state',
            $result[1]['url'],
            'URL is concatenated correctly for app 2'
        );
    }

    public function testFlushImage()
    {
        $imageId = $this->idFromFixture('Image', 'testImage');

        $result = SectionIOTest_MySectionIO::flushImage($imageId);

        // ban expression
        $this->assertEquals(
            'obj.http.x-url ~ "^/assets/SectionTest/test_image\.png$"'
                .' || obj.http.x-url ~ "^/assets/SectionTest/test_image__[a-zA-Z0-9_]*\.png$"',
            $result[0]['banExpression'],
            'ban expression is correct'
        );
    }

    public function testFlushFile()
    {
        $fileId = $this->idFromFixture('File', 'testFile');

        $result = SectionIOTest_MySectionIO::flushFile($fileId);

        // ban expression
        $this->assertEquals(
            'obj.http.x-url ~ "^/assets/SectionTest/test_document\.pdf$"',
            $result[0]['banExpression'],
            'ban expression is correct'
        );
    }

    public function testFlushSiteTree()
    {
        $pageId = $this->idFromFixture('Page', 'ceo');

        // test single page flush
        Config::inst()->update(SectionIO::class, 'sitetree_flush_strategy', 'single');
        $result = SectionIOTest_MySectionIO::flushSiteTree($pageId);
        $this->assertEquals(
            'obj.http.content-type ~ "text/html"'
            .' && obj.http.x-url ~ "^/about\-us/my\-staff/ceo/$"',
            $result[0]['banExpression'],
            'ban expression is correct'
        );

        // test parents flush
        Config::inst()->update(SectionIO::class, 'sitetree_flush_strategy', 'parents');
        $result = SectionIOTest_MySectionIO::flushSiteTree($pageId);
        $this->assertEquals(
            'obj.http.content-type ~ "text/html"'
            .' && (obj.http.x-url ~ "^/about\-us/my\-staff/ceo/$" || obj.http.x-url ~ "^/about\-us/my\-staff/$" || obj.http.x-url ~ "^/about\-us/$")',
            $result[0]['banExpression'],
            'ban expression is correct'
        );

        // test all pages flush
        Config::inst()->update(SectionIO::class, 'sitetree_flush_strategy', 'all');
        $result = SectionIOTest_MySectionIO::flushSiteTree($pageId);
        $this->assertEquals(
            'obj.http.content-type ~ "text/html"',
            $result[0]['banExpression'],
            'ban expression is correct'
        );

        // test whole site flush
        Config::inst()->update(SectionIO::class, 'sitetree_flush_strategy', 'everything');
        $result = SectionIOTest_MySectionIO::flushSiteTree($pageId);
        $this->assertEquals(
            'obj.http.x-url ~ /',
            $result[0]['banExpression'],
            'ban expression is correct'
        );
    }
}

class SectionIOTest_MySectionIO extends SectionIO
{
    protected static function performFlush($banExpression)
    {
        $result = array();
        $urls = static::getUrls();
        if (count($urls) > 0) {
            foreach ($urls as $url) {
                
                $data = array();
                $data['url'] = $url;
                $data['banExpression'] = $banExpression;
                $result[] = $data;
                
            }
        }
        return $result;
    }
}
