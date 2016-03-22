<?php
class SectionIOSiteTreeExtension extends DataExtension {
	
	public function onAfterPublish() {
		SectionIO::flushSiteTree($this->owner->ID);
	}
	
	
}