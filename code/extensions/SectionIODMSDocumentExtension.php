<?php

class SectionIODMSDocumentExtension extends DataExtension
{
    public function onAfterWrite()
    {
        SectionIO::flushURL($this->owner->getLink());
    }
}
