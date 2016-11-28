<?php

/**
 * @todo
 * Class CloudFlareTest
 */
class CloudFlareTest extends SapphireTest {

    /**
     * Tests CloudFlare::inst()->getUrlVariants()
     */
    public function testGetUrlVariants() {
        $urls = array(
            "http://www.example.com",
        );
        
        $this->assertEquals(
            CloudFlare::inst()->getUrlVariants($urls),
            array(
                "http://www.example.com",
                "https://www.example.com",
                "http://www.example.com?stage=Stage",
                "https://www.example.com?stage=Stage"
            )
        );
    }
    

}