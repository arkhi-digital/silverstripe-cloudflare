<?php

/**
 * Class CloudFlareTest
 *
 * @todo
 * @coversDefaultClass CloudFlare_Purge
 */
class CloudFlare_PurgeTest extends SapphireTest {

    /**
     * @covers ::getUrlVariants
     */
    public function testGetUrlVariants()
    {
        $urls = array(
            'http://www.example.com',
        );

        $this->assertEquals(
            CloudFlare_Purge::singleton()->getUrlVariants($urls),
            array(
                'http://www.example.com',
                'https://www.example.com'
            )
        );
    }

}
