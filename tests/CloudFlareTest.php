<?php

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Core\Object;
use SilverStripe\Core\Extension;

/**
 * Class CloudFlareTest
 *
 * @todo
 * @coversDefaultClass CloudFlare
 */
class CloudFlareTest extends SapphireTest
{
    /**
     * Ensures that the server name can be retrieved as expected from environment variables or an extension attached
     * @covers ::getServerName
     */
    public function testGetServerName()
    {
        // remove any user defined extension that updates the server name
        // so that tests run locally in this scenario
        $this->removeExtensibleMethod('updateCloudFlareServerName');

        // Ensures the CI environment can be factored in
        putenv('TRAVIS=1');
        putenv('CLOUDFLARE_DUMMY_SITE=https://www.sometest.dev');
        $this->assertSame('sometest.dev', CloudFlare::singleton()->getServerName());

        // Apply a test extension, get a new instance of the CF class and test again to ensure the hook works
        CloudFlare::add_extension('CloudFlareTest_Extension');
        $this->assertSame('extended.dev', CloudFlare::create()->getServerName());
        CloudFlare::remove_extension('CloudFlareTest_Extension');
    }

    /**
     * @covers ::prependServerName
     */
    public function testPrependServerName() {
        $this->removeExtensibleMethod('updateCloudFlareServerName');
        CloudFlare::add_extension('CloudFlareTest_Extension');

        $this->assertEquals(
            CloudFlare::singleton()->prependServerName(
                array(
                    '/path/to/some/file.js',
                    'path/to/some/other/file.js',
                    'extended.dev/path/to/some/page',
                    'http://extended.dev/path/to/some/other/page'
                )
            ),
            array(
                'http://extended.dev/path/to/some/file.js',
                'http://extended.dev/path/to/some/other/file.js',
                'http://extended.dev/path/to/some/page',
                'http://extended.dev/path/to/some/other/page',
            )
        );
        CloudFlare::remove_extension('CloudFlareTest_Extension');
    }

    /**
     * Removes a user defined extension if it contains the provided method so that it cannot conflict with
     * our tests locally
     *
     * @param $method
     */
    public function removeExtensibleMethod($method) {
        $extensions = Object::get_extensions('CloudFlare');
        foreach ($extensions as $class) {
            $tmp = new $class();
            if (method_exists($tmp, $method)) {
                CloudFlare::remove_extension($class);
            }
            unset($tmp);
        }
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
