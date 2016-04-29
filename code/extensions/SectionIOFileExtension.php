<?php

class SectionIOFileExtension extends DataExtension
{
    public function onAfterWrite()
    {
        if (is_a($this->owner, 'Image')) {
            SectionIO::flushImage($this->owner->ID);
        } else {
            SectionIO::flushFile($this->owner->ID);
        }
    }
}
