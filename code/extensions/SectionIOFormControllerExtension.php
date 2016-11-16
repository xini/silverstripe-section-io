<?php
class SectionIOFormControllerExtension extends Extension {
    
    public function onAfterInit() {
        $response = $this->owner->getResponse();
        $response->addHeader("X-SS-Form", "1");
    }
}