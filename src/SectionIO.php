<?php

namespace Innoweb\SectionIO;

use GuzzleHttp\Client as GuzzleClient;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Flushable;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;

class SectionIO implements Flushable
{
    
    use Configurable;
    use Injectable;
    
    private static $flush_on_dev_build = true;

    private static $sitetree_flush_strategy = 'smart';

    private static $api_url = 'https://aperture.section.io/api/v1';
    private static $account_id = null;
    private static $application_id = null;
    private static $environment_name = null;
    private static $proxy_name = null;
    private static $username = null;
    private static $password = null;
    
    private static $verify_ssl = true;
    private static $async = true;
    
    const SITETREE_STRATEGY_SINGLE = 'single';
    const SITETREE_STRATEGY_PARENTS = 'parents';
    const SITETREE_STRATEGY_ALL = 'all';
    const SITETREE_STRATEGY_SMART = 'smart';
    const SITETREE_STRATEGY_EVERYTING = 'everything';
    
    /**
     * Implementation of Flushable::flush()
     * Is triggered on dev/build and ?flush=1.
     */
    public static function flush()
    {
        if (Config::inst()->get(self::class, 'flush_on_dev_build')) {
            return static::flushAll();
        }
        return;
    }

    public static function flushAll()
    {
        $exp = 'obj.http.x-url ~ /';
        return static::performFlush($exp);
    }

    public static function flushImage($imageID)
    {
        $image = Image::get()->byID($imageID);
        if ($image && $image->exists()) {
            // build file paths
            $url = $image->getURL();
            $filename = $image->Name;
            $extension = $image->getExtension();
            $nameWithoutExtension = substr($filename, 0, strlen($filename) - strlen($extension) - 1);
            $path = substr($url, 0, strrpos($url, "/") + 1);
            // collect variants
            $parts = [];
            // original image
            $parts[] = 'obj.http.x-url ~ "^'.preg_quote($url).'$"';
            // resampled variants
            $parts[] = 'obj.http.x-url ~ "^'.preg_quote($path).preg_quote($nameWithoutExtension)
                .'__[a-zA-Z0-9_]*\.'.preg_quote($extension).'$"'; // variants
            return static::performFlush(implode(' || ', $parts));
        }
        return false;
    }

    public static function flushFile($fileID)
    {
        $file = File::get()->byID($fileID);
        if ($file && $file->exists()) {
            $exp = 'obj.http.x-url ~ "^'.preg_quote($file->getURL()).'$"';
            return static::performFlush($exp);
        }
        return false;
    }
    
    public static function flushSiteTree($sitetreeID, $smartStrategy = null)
    {
        $sitetree = SiteTree::get()->byID($sitetreeID);
        if ($sitetree && $sitetree->exists()) {
            // get strategy config
            $strategy = Config::inst()->get(self::class, 'sitetree_flush_strategy');
            // set smart strategy if set
            if ($strategy == SectionIO::SITETREE_STRATEGY_SMART && $smartStrategy) {
                $strategy = $smartStrategy;
            }
            switch ($strategy) {

                case SectionIO::SITETREE_STRATEGY_SINGLE:
                    $exp = 'obj.http.content-type ~ "'.preg_quote('text/html').'"';
                    $exp .= ' && obj.http.x-url ~ "^'.preg_quote($sitetree->Link()).'$"';
                    break;

                case SectionIO::SITETREE_STRATEGY_PARENTS:
                    $exp = 'obj.http.content-type ~ "'.preg_quote('text/html').'"';
                    $exp .= ' && (obj.http.x-url ~ "^'.preg_quote($sitetree->Link()).'$"';
                    $parent = $sitetree->getParent();
                    while ($parent && $parent->exists()) {
                        $exp .= ' || obj.http.x-url ~ "^'.preg_quote($parent->Link()).'$"';
                        $parent = $parent->getParent();
                    }
                    $exp .= ')';
                    break;

                case SectionIO::SITETREE_STRATEGY_ALL:
                    $exp = 'obj.http.content-type ~ "'.preg_quote('text/html').'"';
                    break;

                case 'everyting': // compatibility, old typo
                case SectionIO::SITETREE_STRATEGY_EVERYTING:
                default:
                    $exp = 'obj.http.x-url ~ /';
                    break;

            }
            return static::performFlush($exp);
        }
        return false;
    }
    
    public static function flushURL($url) {
        if ($url) {
            $exp = 'obj.http.x-url ~ "^'.preg_quote($url).'$"';
            return static::performFlush($exp);
        }
        return false;
    }

    protected static function performFlush($banExpression)
    {
        $success = true;
        $urls = static::getUrls();
        if (count($urls) > 0) {
            foreach ($urls as $url) {

                $client = new GuzzleClient();
                
                $response = $client->request('POST', $url, [
                    'query' => [
                        'banExpression' => $banExpression,
                        'async' => Config::inst()->get(self::class, 'async') ? 'true' : 'false',
                    ],
                    'auth' => [
                        Config::inst()->get(self::class, 'username'),
                        Config::inst()->get(self::class, 'password'),
                    ],
                    'verify' => Config::inst()->get(self::class, 'verify_ssl'),
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                    'http_errors' => false,
//                    'debug' => fopen('d:\\workspace\\cairns-visitor-centre\\.log\\guzzle.log', "w+")
                ]);
                
                if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 400) {
                    user_error('SectionIO::performFlush :: '.$response->getStatusCode().': '.$response->getBody(), E_USER_WARNING);
                    $success = $success && false;
                }
            }
        } else {
            user_error('SectionIO::performFlush :: no URLs loaded for ban.', E_USER_WARNING);
        }
        return $success;
    }

    protected static function getUrls()
    {
        $urls = [];

        if (static::checkConfig()) {
            $api_url = Config::inst()->get(self::class, 'api_url');
            $account_id = Config::inst()->get(self::class, 'account_id');
            $application_id = Config::inst()->get(self::class, 'application_id');
            $application_ids = [];
            if (is_string($application_id)) {
                $application_ids = preg_split("/[\s,]+/", $application_id);
            } elseif (is_array($application_id)) {
                $application_ids = $application_id;
            }
            $environment_name = Config::inst()->get(self::class, 'environment_name');
            $proxy_name = Config::inst()->get(self::class, 'proxy_name');

            foreach ($application_ids as $appid) {
                // build API URL: /account/{accountId}/application/{applicationId}/environment/{environmentName}/proxy/{proxyName}/state
                $urls[] = Controller::join_links(
                    $api_url,
                    'account',
                    $account_id,
                    'application',
                    $appid,
                    'environment',
                    $environment_name,
                    'proxy',
                    $proxy_name,
                    'state'
                );
            }
        }

        return $urls;
    }

    protected static function checkConfig()
    {
        $missing = [];
        // check config
        $api_url = Config::inst()->get(self::class, 'api_url');
        if (!isset($api_url) || strlen($api_url) < 1) {
            $missing[] = 'api_url';
        }
        $account_id = Config::inst()->get(self::class, 'account_id');
        if (!isset($account_id) || strlen($account_id) < 1) {
            $missing[] = 'account_id';
        }
        $application_id = Config::inst()->get(self::class, 'application_id');
        if (!isset($application_id) || (!is_array($application_id) && strlen((string) $application_id) < 1)) {
            $missing[] = 'application_id';
        }
        $environment_name = Config::inst()->get(self::class, 'environment_name');
        if (!isset($environment_name) || strlen($environment_name) < 1) {
            $missing[] = 'environment_name';
        }
        $proxy_name = Config::inst()->get(self::class, 'proxy_name');
        if (!isset($proxy_name) || strlen($proxy_name) < 1) {
            $missing[] = 'proxy_name';
        }
        $username = Config::inst()->get(self::class, 'username');
        if (!isset($username) || strlen($username) < 1) {
            $missing[] = 'username';
        }
        $password = Config::inst()->get(self::class, 'password');
        if (!isset($password) || strlen($password) < 1) {
            $missing[] = 'password';
        }
        
        if (count($missing) > 0) {
            user_error('SectionIO:: config parameters missing: ' . implode(', ', $missing), E_USER_WARNING);
            return false;
        }
        return true;
    }
}
