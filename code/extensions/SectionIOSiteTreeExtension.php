<?php

class SectionIOSiteTreeExtension extends SiteTreeExtension
{
    public function onAfterPublish(&$original)
    {
        $strategy = SectionIO::SITETREE_STRATEGY_SINGLE;
        if (
            $this->owner->URLSegment != $original->URLSegment || // the slug has been altered
            $this->owner->MenuTitle != $original->MenuTitle || // the navigation label has been altered
            $this->owner->Title != $original->Title // the title has been altered
        ) {
            $strategy = SectionIO::SITETREE_STRATEGY_ALL;
        } else if (
            $this->owner->getParent()
        ) {
            $strategy = SectionIO::SITETREE_STRATEGY_PARENTS;
        }
        
        SectionIO::flushSiteTree($this->owner->ID, $strategy);
        
        parent::onAfterPublish($original);
    }
    
    public function onAfterUnpublish()
    {
        SectionIO::flushAll();
        
        parent::onAfterUnpublish();
    }
}
