<?php

namespace Innoweb\SectionIO\Tests\SectionIOTest;

use Innoweb\SectionIO\SectionIO;

class CustomSectionIO extends SectionIO
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
