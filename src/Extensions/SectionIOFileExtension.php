<?php

namespace Innoweb\SectionIO\Extensions;

use Innoweb\SectionIO\SectionIO;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataExtension;

class SectionIOFileExtension extends DataExtension
{
    public function onAfterPublish(&$original)
    {
        if (is_a($this->owner, Image::class)) {
            SectionIO::flushImage($this->owner->ID);
        } else {
            SectionIO::flushFile($this->owner->ID);
        }
    }
    
    public function onAfterUnpublish()
    {
        SectionIO::flushAll();
    }
}
