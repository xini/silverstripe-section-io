<?php

class SectionIOTest extends SapphireTest
{
    protected static $fixture_file = 'SectionIOTest.yml';
    
    public function setUpOnce() {
        parent::setUpOnce();
        
        // add config values
        Config::inst()->update('SectionIO', 'flush_on_dev_build', true);
        Config::inst()->update('SectionIO', 'api_url', 'https://example.com');
        Config::inst()->update('SectionIO', 'account_id', '123456');
        Config::inst()->update('SectionIO', 'application_id', '987654');
        Config::inst()->update('SectionIO', 'environment_name', 'Production');
        Config::inst()->update('SectionIO', 'proxy_name', 'myproxy');
        Config::inst()->update('SectionIO', 'username', 'someuser');
        Config::inst()->update('SectionIO', 'password', 'MySafePassword');
        
        // remove extensions otherwise the fixtures will break the tests (by calling the live flush)
        File::remove_extension('SectionIOFileExtension');
        SiteTree::remove_extension('SectionIOSiteTreeExtension');
        
    }
    
    public function setUp() 
    {
        parent::setUp();
        
        if(!file_exists(ASSETS_PATH)) mkdir(ASSETS_PATH);
        
        // Create a test folders for each of the fixture references
        $folderIDs = $this->allFixtureIDs('Folder');
        foreach($folderIDs as $folderID) {
            $folder = DataObject::get_by_id('Folder', $folderID);
            if(!file_exists(BASE_PATH."/$folder->Filename")) mkdir(BASE_PATH."/$folder->Filename");
        }
        
        // Copy test images for each of the fixture references
        $imageIDs = $this->allFixtureIDs('Image');
        foreach($imageIDs as $imageID) {
            $image = DataObject::get_by_id('Image', $imageID);
            $filePath = BASE_PATH."/$image->Filename";
            $sourcePath = str_replace('assets/SectionTest/', 'section-io/tests/testfiles/', $filePath);
            if(!file_exists($filePath)) {
                if (!copy($sourcePath, $filePath)) user_error('Failed to copy test images', E_USER_ERROR);
            }
        }
        
        // Copy test files for each of the fixture references
        $fileIDs = $this->allFixtureIDs('File');
        foreach($fileIDs as $fileID) {
            $file = DataObject::get_by_id('File', $fileID);
            $filePath = BASE_PATH."/$file->Filename";
            $sourcePath = str_replace('assets/SectionTest/', 'section-io/tests/testfiles/', $filePath);
            if(!file_exists($filePath)) {
                if (!copy($sourcePath, $filePath)) user_error('Failed to copy test files', E_USER_ERROR);
            }
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
        // Remove the test images that we've created
        $imageIDs = $this->allFixtureIDs('Image');
        foreach($imageIDs as $imageID) {
            $image = DataObject::get_by_id('Image', $imageID);
            if($image && file_exists(BASE_PATH."/$image->Filename")) unlink(BASE_PATH."/$image->Filename");
        }
        
        // Remove the test files that we've created
        $fileIDs = $this->allFixtureIDs('File');
        foreach($fileIDs as $fileID) {
            $file = DataObject::get_by_id('File', $fileID);
            if($file && file_exists(BASE_PATH."/$file->Filename")) unlink(BASE_PATH."/$file->Filename");
        }
        
        // Remove the test folders that we've created
        $folderIDs = $this->allFixtureIDs('Folder');
        foreach($folderIDs as $folderID) {
            $folder = DataObject::get_by_id('Folder', $folderID);
            if($folder && file_exists(BASE_PATH."/".$folder->Filename."_resampled")) {
                Filesystem::removeFolder(BASE_PATH."/".$folder->Filename."_resampled");
            }
            if($folder && file_exists(BASE_PATH."/$folder->Filename")) {
                Filesystem::removeFolder(BASE_PATH."/$folder->Filename");
            }
        }
        
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
        
        // headers
        $this->assertContains(
            'Content-Type: application/json',
            $result[0]['headers'],
            'content type header is correct'
        );
        $this->assertContains(
            'Accept: application/json',
            $result[0]['headers'],
            'accept header is correct'
        );
   
        // options
        $this->assertArrayHasKey(
            CURLOPT_SSL_VERIFYPEER,
            $result[0]['options'],
            'ssl verify is set'
        );
        $this->assertEquals(
            1,
            $result[0]['options'][CURLOPT_SSL_VERIFYPEER],
            'ssl verfify is activated'
        );
        $this->assertArrayHasKey(
            CURLOPT_SSL_VERIFYHOST,
            $result[0]['options'],
            'ssl verfi host os set'
        );
        $this->assertEquals(
            2,
            $result[0]['options'][CURLOPT_SSL_VERIFYHOST],
            'ssl verfify host is set to 2'
        );
        $this->assertArrayHasKey(
            CURLOPT_CAINFO,
            $result[0]['options'],
            'ca info is set'
        );
        $this->assertNotEmpty(
            $result[0]['options'][CURLOPT_CAINFO],
            'ca info is not empty'
        );
        
        // service
        $this->assertInstanceOf(
            'RestfulService',
            $result[0]['service'],
            'service is of type RestfulService'
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
        Config::inst()->update('SectionIO', 'flush_on_dev_build', false);
        $result = SectionIOTest_MySectionIO::flush();
        $this->assertNull(
            $result,
            'null returned if flush on build deactivated'
        );
        
    }
    
    public function testMultipleApplicationIDs()
    {
        // add second application to config
        Config::inst()->update('SectionIO', 'application_id', '2546987,856954');
        
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
        Config::inst()->update('SectionIO', 'application_id', '741852, 369258');
        
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
                .' || obj.http.x-url ~ "^/assets/SectionTest/_resampled/(.*)\-test_image\.png$"',
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
        Config::inst()->update('SectionIO', 'sitetree_flush_strategy', 'single');
        $result = SectionIOTest_MySectionIO::flushSiteTree($pageId);
        $this->assertEquals(
            'obj.http.content-type ~ "text/html"'
            .' && obj.http.x-url ~ "^/about\-us/my\-staff/ceo/$"',
            $result[0]['banExpression'],
            'ban expression is correct'
        );
        
        // test parents flush
        Config::inst()->update('SectionIO', 'sitetree_flush_strategy', 'parents');
        $result = SectionIOTest_MySectionIO::flushSiteTree($pageId);
        $this->assertEquals(
            'obj.http.content-type ~ "text/html"'
            .' && (obj.http.x-url ~ "^/about\-us/my\-staff/ceo/$" || obj.http.x-url ~ "^/about\-us/my\-staff/$" || obj.http.x-url ~ "^/about\-us/$")',
            $result[0]['banExpression'],
            'ban expression is correct'
        );
    
        // test all pages flush
        Config::inst()->update('SectionIO', 'sitetree_flush_strategy', 'all');
        $result = SectionIOTest_MySectionIO::flushSiteTree($pageId);
        $this->assertEquals(
            'obj.http.content-type ~ "text/html"',
            $result[0]['banExpression'],
            'ban expression is correct'
        );
        
        // test whole site flush
        Config::inst()->update('SectionIO', 'sitetree_flush_strategy', 'everything');
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
        // config loaded successfully
        if ($urls) {
            foreach ($urls as $url) {
        
                // get restful service object
                $service = static::getService($url, $banExpression);
        
                // prepare headers
                $headers = static::getHeaders();
        
                // prepare curl options
                $options = static::getOptions();
        
                // store data for return
                $data = array();
                $data['url'] = $url;
                $data['banExpression'] = $banExpression;
                $data['headers'] = $headers;
                $data['options'] = $options;
                $data['service'] = $service;
                $result[] = $data;
        
            }
        } else {
            user_error('SectionIOTest_MySectionIO::performFlush :: no URLs loaded for ban.', E_USER_WARNING);
        }
        return $result;
    }
    
}