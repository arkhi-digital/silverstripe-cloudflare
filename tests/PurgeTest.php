<?php
namespace Steadlane\CloudFlare\Tests;

use SilverStripe\Dev\SapphireTest;
use Steadlane\CloudFlare\Purge;

/**
 * Class CloudFlareTest
 *
 * @todo
 * @coversDefaultClass CloudFlare_Purge
 */
class PurgeTest extends SapphireTest
{

    /**
     * @covers Purge::getUrlVariants
     */
    public function testGetUrlVariants()
    {
        $urls = array(
            'http://www.example.com',
        );

        $this->assertEquals(
            Purge::singleton()->getUrlVariants($urls),
            array(
                'http://www.example.com',
                'https://www.example.com'
            )
        );
    }

    /**
     * @covers Purge::getFileTypes()
     */
    public function testGetFileTypes()
    {
        $this->assertTrue(is_array(Purge::singleton()->getFileTypes()));
    }

    /**
     * @covers Purge::pushFile()
     * @covers Purge::getFile()
     * @covers Purge::clearFiles()
     */
    public function testFileMethods()
    {
        $purger = Purge::create();

        // test string as file
        $purger->pushFile("somefile.ext");

        $this->assertEquals(
            $purger->getFiles(),
            array(
                "somefile.ext"
            )
        );

        // test array as file
        $purger->reset();
        $purger->pushFile(
            array(
                'somefile.ext',
                'someotherfile.ext'
            )
        );

        $this->assertEquals(
            $purger->getFiles(),
            array(
                'somefile.ext',
                'someotherfile.ext'
            )
        );

        // test set files without reset
        $purger->setFiles(
            array(
                'somemorefiles.ext',
                'andsomemorefiles.ext'
            )
        );

        $this->assertEquals(
            $purger->getFiles(),
            array(
                'somemorefiles.ext',
                'andsomemorefiles.ext'
            )
        );

        // assert that all files from above are cleared
        $this->assertTrue(is_null($purger->clearFiles()->getFiles()));
    }

    /**
     * @covers Purge::setPurgeEverything
     * @covers Purge::purge()
     * @covers Purge::isSuccessful()
     * @covers Purge::setTestOnly()
     */
    public function testPurgeEverything()
    {
        $purger = Purge::create();
        $purger
            ->setPurgeEverything(true)
            ->setTestOnly(true, true)
            ->purge();

        $this->assertTrue($purger->isSuccessful());

        $purger->reset();

        $purger
            ->setPurgeEverything(true)
            ->setTestOnly(true, false)
            ->purge();

        $this->assertFalse($purger->isSuccessful());
    }

}
