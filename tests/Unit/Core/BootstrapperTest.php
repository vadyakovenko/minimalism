<?php
namespace CarloNicora\Minimalism\Tests\Unit\Core;

use CarloNicora\Minimalism\Core\Bootstrapper;
use CarloNicora\Minimalism\Core\Modules\ErrorController;
use CarloNicora\Minimalism\Core\Response;
use CarloNicora\Minimalism\Core\Services\Factories\ServicesFactory;
use CarloNicora\Minimalism\Services\Logger\Logger;
use CarloNicora\Minimalism\Tests\Unit\Abstracts\AbstractTestCase;
use Exception;

class BootstrapperTest extends AbstractTestCase
{
    /** @var string|null  */
    private ?string $cacheFile=null;

    /** @var string  */
    private string $serialisedService = 'O:62:"CarloNicora\Minimalism\Core\Services\Factories\ServicesFactory":1:{s:72:" CarloNicora\Minimalism\Core\Services\Factories\ServicesFactory services";a:3:{s:43:"CarloNicora\Minimalism\Services\Paths\Paths";O:43:"CarloNicora\Minimalism\Services\Paths\Paths":5:{s:55:" CarloNicora\Minimalism\Services\Paths\Paths configData";O:72:"CarloNicora\Minimalism\Services\Paths\Configurations\PathsConfigurations":1:{s:10:"logFolders";a:1:{i:0;s:35:"/opt/project/data/logs/minimalism//";}}s:49:" CarloNicora\Minimalism\Services\Paths\Paths root";s:12:"/opt/project";s:48:" CarloNicora\Minimalism\Services\Paths\Paths url";s:8:"http:///";s:48:" CarloNicora\Minimalism\Services\Paths\Paths log";s:33:"/opt/project/data/logs/minimalism";s:11:" * services";r:1;}s:69:"CarloNicora\Minimalism\Services\ParameterValidator\ParameterValidator";O:69:"CarloNicora\Minimalism\Services\ParameterValidator\ParameterValidator":3:{s:81:" CarloNicora\Minimalism\Services\ParameterValidator\ParameterValidator configData";O:98:"CarloNicora\Minimalism\Services\ParameterValidator\Configurations\ParameterValidatorConfigurations":0:{}s:78:" CarloNicora\Minimalism\Services\ParameterValidator\ParameterValidator factory";O:86:"CarloNicora\Minimalism\Services\ParameterValidator\Factories\ParameterValidatorFactory":0:{}s:11:" * services";r:1;}s:45:"CarloNicora\Minimalism\Services\Logger\Logger";O:45:"CarloNicora\Minimalism\Services\Logger\Logger":5:{s:57:" CarloNicora\Minimalism\Services\Logger\Logger configData";O:74:"CarloNicora\Minimalism\Services\Logger\Configurations\LoggerConfigurations":2:{s:14:"saveSystemOnly";b:0;s:15:" * dependencies";a:1:{i:0;s:43:"CarloNicora\Minimalism\Services\Paths\Paths";}}s:53:" CarloNicora\Minimalism\Services\Logger\Logger events";a:0:{}s:63:" CarloNicora\Minimalism\Services\Logger\Logger systemEventsOnly";b:1;s:11:" * services";r:1;s:13:" * logFolders";a:1:{i:0;s:35:"/opt/project/data/logs/minimalism//";}}}}';

    /****************************************************************************/

    public function setUp(): void
    {
        parent::setUp();

        $this->cacheFile = realpath('.') . DIRECTORY_SEPARATOR
            . 'data' . DIRECTORY_SEPARATOR
            . 'cache' . DIRECTORY_SEPARATOR
            . 'services.cache';

        $this->resetCacheFile();
        unset($_COOKIE['minimalismServices'], $_SERVER['REQUEST_URI']);

        if(isset($_SESSION)) {
            unset($_SESSION);
        }
    }

    /****************************************************************************/

    private function resetCacheFile() : void
    {
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
    }

    /**
     * @param bool $markAsExpired
     */
    private function writeCacheFile(bool $markAsExpired=false): void
    {
        $this->resetCacheFile();
        file_put_contents($this->cacheFile, $this->serialisedService);

        if ($markAsExpired) {
            touch($this->cacheFile, (time() - 100 * 60));
        }
    }

    /**
     * @param bool $mockFailingServicesFactoryInitialiser
     * @return Bootstrapper
     */
    private function generateBootstrapper(bool $mockFailingServicesFactoryInitialiser=false) : Bootstrapper
    {
        $response = new Bootstrapper();

        if ($mockFailingServicesFactoryInitialiser) {
            $services = $this->mockFactory->createNonInitialisingServicesFactory();

            $this->setProperty($response, 'services', $services);
        }

        return $response->initialise();
    }

    /****************************************************************************/

    public function testBootstrapperInitialisation() : void
    {
        $_COOKIE['minimalismServices'] = '{"parameterName":"parameterValue"}';

        $this->assertEquals(
            unserialize($this->serialisedService),
            $this->getProperty($this->generateBootstrapper(), 'services'));
    }

    public function testBootstrapperFailsInitialisationBecauseOfIncorrectCookies() : void
    {
        $_COOKIE['minimalismServices'] = '{"parameterName":"parameterValue"}';

        $services = $this->getProperty($this->generateBootstrapper(), 'services');

        $this->assertEquals(
            unserialize($this->serialisedService),
            $services);
    }

    public function testBootstrapperInitialisationFromSession() : void
    {
        $_SESSION = ['minimalismServices' => unserialize($this->serialisedService)];

        $this->assertEquals(
            unserialize($this->serialisedService),
            $this->getProperty($this->generateBootstrapper(), 'services'));
    }

    public function testBootstrapperInitialisationFromCache() : void
    {
        $this->writeCacheFile();

        $_SESSION = null;

        /** @var ServicesFactory $loadedServices */
        $loadedServices = $this->getProperty($this->generateBootstrapper(), 'services');
        /** @var Logger $logger */
        $logger = $loadedServices->service(Logger::class);
        $logger->flush();

        $this->assertEquals(unserialize($this->serialisedService), $loadedServices);
    }

    public function testBootstrapperInitialisationFromExpiredCache() : void
    {
        $this->writeCacheFile(true);

        $this->assertEquals(
            unserialize($this->serialisedService),
            $this->getProperty($this->generateBootstrapper(), 'services'));
    }

    public function testDenyAccessToSpecificFileTypes() : void
    {
        $_SERVER['REQUEST_URI'] = 'image.jpg';

        $this->assertInstanceOf(ErrorController::class,
            $this->generateBootstrapper()->loadController()
        );
    }

    public function testFailServicesFactoryInitialisation() : void
    {
        $bootstrapper = $this->generateBootstrapper(true);

        $this->assertInstanceOf(ErrorController::class,
            $bootstrapper->loadController()
        );
    }

    public function testModelNameInitialisation() : void
    {
        $bootstrapper = $this->generateBootstrapper();
        $bootstrapper->setModel('modelName');

        $this->assertEquals(
            'modelName',
            $this->getProperty($bootstrapper, 'modelName'));
    }

    public function testLoadControllerFails() : void
    {
        $bootstrapper = $this->generateBootstrapper();

        $this->setProperty($bootstrapper,
            'controllerFactory',
            $this->mockFactory->createControllerFactoryWithoutModules());

        $this->assertInstanceOf(ErrorController::class, $bootstrapper->loadController('modelName'));
    }

    /**
     * @throws Exception
     */
    public function testLoadTestController() : void
    {
        $bootstrapper = $this->generateBootstrapper();


        $this->setProperty($bootstrapper,
            'controllerFactory',
            $this->mockFactory->createControllerFactory());

        $bootstrapper->loadController('modelName');

        $this->assertEquals(new Response(),
            $bootstrapper->loadController()->render()
        );
    }
}