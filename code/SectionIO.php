<?php

class SectionIO extends SS_Object implements Flushable
{
    private static $flush_on_dev_build = true;

    private static $sitetree_flush_strategy = 'single';

    private static $api_url = 'https://aperture.section.io/api/v1';
    private static $account_id = '';
    private static $application_id = '';
    private static $environment_name = '';
    private static $proxy_name = '';
    private static $username = '';
    private static $password = '';
    private static $verify_ssl = true;
    private static $async = true;

    /**
     * Implementation of Flushable::flush()
     * Is triggered on dev/build and ?flush=1.
     */
    public static function flush()
    {
        if (Config::inst()->get('SectionIO', 'flush_on_dev_build')) {
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
            $exp = 'obj.http.x-url ~ "^/'.preg_quote($image->getFilename()).'$"'; // image itself
            $exp    .= ' || obj.http.x-url ~ "^/'.preg_quote($image->Parent()->getFilename())
                    .'_resampled/(.*)\-'.preg_quote($image->Name).'$"'; // resampled versions
            return static::performFlush($exp);
        }

        return false;
    }

    public static function flushFile($fileID)
    {
        $file = File::get()->byID($fileID);
        if ($file && $file->exists()) {
            $exp = 'obj.http.x-url ~ "^/'.preg_quote($file->getFilename()).'$"';
            return static::performFlush($exp);
        }

        return false;
    }

    public static function flushSiteTree($sitetreeID)
    {
        $sitetree = SiteTree::get()->byID($sitetreeID);
        if ($sitetree && $sitetree->exists()) {
            $strategy = Config::inst()->get('SectionIO', 'sitetree_flush_strategy');
            switch ($strategy) {

                case 'single':
                    $exp = 'obj.http.content-type ~ "'.preg_quote('text/html').'"';
                    $exp .= ' && obj.http.x-url ~ "^'.preg_quote($sitetree->Link()).'$"';
                    break;

                case 'parents':
                    $exp = 'obj.http.content-type ~ "'.preg_quote('text/html').'"';
                    $exp .= ' && (obj.http.x-url ~ "^'.preg_quote($sitetree->Link()).'$"';
                    $parent = $sitetree->getParent();
                    while ($parent && $parent->exists()) {
                        $exp .= ' || obj.http.x-url ~ "^'.preg_quote($parent->Link()).'$"';
                        $parent = $parent->getParent();
                    }
                    $exp .= ')';
                    break;

                case 'all':
                    $exp = 'obj.http.content-type ~ "'.preg_quote('text/html').'"';
                    break;

                case 'everyting': // compatibility, old typo
                case 'everything':
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
        // config loaded successfully
		if (static::checkConfig()) {
			if (count($urls) > 0) {
				foreach ($urls as $url) {

					// get restful service object
					$service = static::getService($url, $banExpression);

					// prepare headers
					$headers = static::getHeaders();

					// prepare curl options
					$options = static::getOptions();

					// call API
					$conn = $service->request(null, 'POST', null, $headers, $options);

					if ($conn->isError()) {
						SS_Log::log('SectionIO::performFlush :: '.$conn->getStatusCode().' : '.$conn->getStatusDescription().' : '.$url, SS_Log::ERR);
						$success = $success && false;
					} else {
						SS_Log::log('SectionIO::performFlush :: ban successful. url: '.$url."; ban expression: '".$banExpression."'", SS_Log::NOTICE);
					}
				}
			} else {
				SS_Log::log('SectionIO::performFlush :: no URLs loaded for ban.', SS_Log::ERR);
			}
		}
		
        return $success;
    }

    protected static function getService($url, $banExpression)
    {
        // prepare API call
        $service = new RestfulService(
            $url,
            0 // expiry time 0: do not cache the API call
        );
        // set basic auth
        $username = Config::inst()->get('SectionIO', 'username');
        $password = Config::inst()->get('SectionIO', 'password');
        $service->basicAuth($username, $password);
        // set query string (ban expression)
        $service->setQueryString(array(
            'banExpression' => $banExpression,
            'async' => Config::inst()->get('SectionIO', 'async') ? 'true' : 'false',
        ));

        return $service;
    }

    protected static function getOptions()
    {
        // prepare curl options for ssl verification
        if (Config::inst()->get('SectionIO', 'verify_ssl')) {
            return array(
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_SSL_VERIFYHOST => 0,
            );
        }
        return array();
    }

    protected static function getHeaders()
    {
        $headers = array(
            'Content-Type: application/json',
            'Accept: application/json',
        );

        return $headers;
    }

    protected static function getUrls()
    {
        $urls = array();

        if (static::checkConfig()) {
            $api_url = Config::inst()->get('SectionIO', 'api_url');
            $account_id = Config::inst()->get('SectionIO', 'account_id');
            $application_id = Config::inst()->get('SectionIO', 'application_id');
            $application_ids = array();
            if (is_string($application_id)) {
                $application_ids = preg_split("/[\s,]+/", $application_id);
            } elseif (is_array($application_id)) {
                $application_ids = $application_id;
            }
            $environment_name = Config::inst()->get('SectionIO', 'environment_name');
            $proxy_name = Config::inst()->get('SectionIO', 'proxy_name');

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
        $missing = array();
        // check config
        $api_url = Config::inst()->get('SectionIO', 'api_url');
        if (!isset($api_url) || strlen($api_url) < 1) {
            $missing[] = 'SectionIO.api_url';
        }
        $account_id = Config::inst()->get('SectionIO', 'account_id');
        if (!isset($account_id) || strlen($account_id) < 1) {
            $missing[] = 'SectionIO.account_id';
        }
        $application_id = Config::inst()->get('SectionIO', 'application_id');
        if (!isset($application_id) || (!is_array($application_id) && strlen((string) $application_id) < 1)) {
            $missing[] = 'SectionIO.application_id';
        }
        $environment_name = Config::inst()->get('SectionIO', 'environment_name');
        if (!isset($environment_name) || strlen($environment_name) < 1) {
            $missing[] = 'SectionIO.environment_name';
        }
        $proxy_name = Config::inst()->get('SectionIO', 'proxy_name');
        if (!isset($proxy_name) || strlen($proxy_name) < 1) {
            $missing[] = 'SectionIO.proxy_name';
        }
        $username = Config::inst()->get('SectionIO', 'username');
        if (!isset($username) || strlen($username) < 1) {
            $missing[] = 'SectionIO.username';
        }
        $password = Config::inst()->get('SectionIO', 'password');
        if (!isset($password) || strlen($password) < 1) {
            $missing[] = 'SectionIO.password';
        }
        
        if (count($missing) > 0) {
			if (!Director::isDev()) {
				SS_Log::log('SectionIO:: config parameters missing: ' . implode(', ', $missing), SS_Log::WARN);
			}
            return false;
        }
        return true;
    }
}
