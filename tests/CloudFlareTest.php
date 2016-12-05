<?php
/**
 * Class CloudFlareTest
 *
 * @todo
 * @coversDefaultClass CloudFlare
 */
class CloudFlareTest extends SapphireTest
{
    /**
     * @covers ::getUrlVariants
     */
    public function testGetUrlVariants()
    {
        $urls = array(
            'http://www.example.com',
        );

        $this->assertEquals(
            CloudFlare::inst()->getUrlVariants($urls),
            array(
                'http://www.example.com',
                'https://www.example.com',
                'http://www.example.com?stage=Stage',
                'https://www.example.com?stage=Stage'
            )
        );
    }

    /**
     * Ensures that the server name can be retrieved as expected from environment variables or an extension attached
     * @covers ::getServerName
     */
    public function testGetServerName()
    {
        // Ensures the CI environment can be factored in
        putenv('TRAVIS=1');
        putenv('CLOUDFLARE_DUMMY_SITE=https://www.sometest.dev');
        $this->assertSame('sometest.dev', CloudFlare::inst()->getServerName());

        // Apply a test extension, get a new instance of the CF class and test again to ensure the hook works
        CloudFlare::add_extension('CloudFlareTest_Extension');
        $this->assertSame('extended.dev', CloudFlare::create()->getServerName());
    }
}

/**
 * A stub extension applied to CloudFlare as needed to test extension hooks
 */
class CloudFlareTest_Extension extends Extension implements TestOnly
{
    /**
     * Set a dummy server name
     *
     * @see CloudFlare::getServerName
     * @var string $serverName
     */
    public function updateCloudFlareServerName(&$serverName)
    {
        $serverName = 'extended.dev';
    }
}
