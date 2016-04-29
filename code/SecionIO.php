<?php

class SectionIO extends Object implements Flushable
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

    /**
     * Implementation of Flushable::flush()
     * Is triggered on dev/build and ?flush=1.
     */
    public static function flush()
    {
        if (Config::inst()->get('SectionIO', 'flush_on_dev_build')) {
            self::flushAll();
        }
    }

    public static function flushAll()
    {
        $exp = 'obj.http.x-url ~ /';

        return self::performFlush($exp);
    }

    public static function flushImage($imageID)
    {
        $image = Image::get()->byID($imageID);
        if ($image && $image->exists()) {
            $exp = 'obj.http.x-url ~ "^/'.preg_quote($image->getFilename()).'$"'; // image itself
            $exp    .= ' || obj.http.x-url ~ "^/'.preg_quote($image->Parent()->getFilename())
                    .'_resampled/(.*)\-'.preg_quote($image->Name).'$"'; // resampled versions
            return self::performFlush($exp);
        }

        return false;
    }

    public static function flushFile($fileID)
    {
        $file = File::get()->byID($fileID);
        if ($file && $file->exists()) {
            $exp = 'obj.http.x-url ~ "^/'.preg_quote($file->getFilename()).'$"';

            return self::performFlush($exp);
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

                case 'everyting':
                default:
                    $exp = 'obj.http.x-url ~ /';
                    break;

            }

            return self::performFlush($exp);
        }

        return false;
    }

    private static function performFlush($banExpression)
    {
        $success = true;

        // check config
        $api_url = Config::inst()->get('SectionIO', 'api_url');
        if (!$api_url || strlen($api_url) < 1) {
            SS_Log::log('Value for SectionIO.api_url needs to be configured.', SS_Log::WARN);
            $success = false;
        }
        $account_id = Config::inst()->get('SectionIO', 'account_id');
        if (!$account_id || strlen($account_id) < 1) {
            SS_Log::log('Value for SectionIO.account_id needs to be configured.', SS_Log::WARN);
            $success = false;
        }
        $application_id = Config::inst()->get('SectionIO', 'application_id');
        $application_ids = array();
        if (!$application_id) {
            SS_Log::log('Value for SectionIO.application_id needs to be configured.', SS_Log::WARN);
            $success = false;
        } elseif (is_string($application_id)) {
            $application_ids = preg_split("/[\s,]+/", $application_id);
        } elseif (is_array($application_id)) {
            $application_ids = $application_id;
        }
        $environment_name = Config::inst()->get('SectionIO', 'environment_name');
        if (!$environment_name || strlen($environment_name) < 1) {
            SS_Log::log('Value for SectionIO.environment_name needs to be configured.', SS_Log::WARN);
            $success = false;
        }
        $proxy_name = Config::inst()->get('SectionIO', 'proxy_name');
        if (!$proxy_name || strlen($proxy_name) < 1) {
            SS_Log::log('Value for SectionIO.proxy_name needs to be configured.', SS_Log::WARN);
            $success = false;
        }
        $username = Config::inst()->get('SectionIO', 'username');
        if (!$username || strlen($username) < 1) {
            SS_Log::log('Value for SectionIO.username needs to be configured.', SS_Log::WARN);
            $success = false;
        }
        $password = Config::inst()->get('SectionIO', 'password');
        if (!$password || strlen($password) < 1) {
            SS_Log::log('Value for SectionIO.password needs to be configured.', SS_Log::WARN);
            $success = false;
        }

        // config loaded successfully
        if ($success) {
            foreach ($application_ids as $application_id) {

                // build API URL: /account/{accountId}/application/{applicationId}/environment/{environmentName}/proxy/{proxyName}/state
                $url = Controller::join_links(
                    $api_url,
                    'account',
                    $account_id,
                    'application',
                    $application_id,
                    'environment',
                    $environment_name,
                    'proxy',
                    $proxy_name,
                    'state'
                );

                // prepare API call
                $fetch = new RestfulService(
                    $url,
                    0 // expiry time 0: do not cache the API call
                );
                // set basic auth
                $fetch->basicAuth($username, $password);
                // set query string (ban expression)
                $fetch->setQueryString(array(
                    'banExpression' => $banExpression,
                ));
                // prepare headers
                $headers = array(
                    'Content-Type: application/json',
                    'Accept: application/json',
                );
                // prepare curl options for ssl verification
                $cert = ini_get('curl.cainfo');
                if (!$cert) {
                    $cert = BASE_PATH.'/'.SECTIONIO_BASE.'/cert/cacert.pem';
                }
                $options = array(
                    CURLOPT_SSL_VERIFYPEER => 1,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_CAINFO => $cert,
                );

                // call API
                $conn = $fetch->request(null, 'POST', null, $headers, $options);

                if ($conn->isError()) {
                    SS_Log::log('SectionIO::performFlush :: '.$conn->getStatusCode().' : '.$conn->getStatusDescription(), SS_Log::WARN);
                    $success = $success && false;
                } else {
                    SS_Log::log('SectionIO::performFlush :: ban successful. application ID: '.$application_id."; ban expression: '".$banExpression."'", SS_Log::NOTICE);
                }
            }
        }

        return $success;
    }
}
