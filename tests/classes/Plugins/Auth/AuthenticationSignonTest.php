<?php

declare(strict_types=1);

namespace PhpMyAdmin\Tests\Plugins\Auth;

use PhpMyAdmin\Config;
use PhpMyAdmin\Config\Settings\Server;
use PhpMyAdmin\Current;
use PhpMyAdmin\DatabaseInterface;
use PhpMyAdmin\Exceptions\ExitException;
use PhpMyAdmin\Plugins\Auth\AuthenticationSignon;
use PhpMyAdmin\ResponseRenderer;
use PhpMyAdmin\Tests\AbstractTestCase;
use PhpMyAdmin\Tests\Stubs\ResponseRenderer as ResponseRendererStub;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use ReflectionProperty;
use Throwable;

use function ob_get_clean;
use function ob_start;
use function session_get_cookie_params;
use function session_id;
use function session_name;

#[CoversClass(AuthenticationSignon::class)]
class AuthenticationSignonTest extends AbstractTestCase
{
    protected AuthenticationSignon $object;

    /**
     * Configures global environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        parent::setLanguage();

        parent::setGlobalConfig();

        DatabaseInterface::$instance = $this->createDatabaseInterface();
        Current::$database = 'db';
        Current::$table = 'table';
        $this->object = new AuthenticationSignon();
    }

    /**
     * tearDown for test cases
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->object);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAuth(): void
    {
        (new ReflectionProperty(ResponseRenderer::class, 'instance'))->setValue(null, null);
        Config::getInstance()->selectedServer['SignonURL'] = '';
        $_REQUEST = [];
        ResponseRenderer::getInstance()->setAjax(false);

        ob_start();
        try {
            $this->object->showLoginForm();
        } catch (Throwable $throwable) {
        }

        $result = ob_get_clean();

        self::assertInstanceOf(ExitException::class, $throwable);

        self::assertIsString($result);

        self::assertStringContainsString('You must set SignonURL!', $result);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAuthLogoutURL(): void
    {
        $responseStub = new ResponseRendererStub();
        (new ReflectionProperty(ResponseRenderer::class, 'instance'))->setValue(null, $responseStub);

        $config = Config::getInstance();
        $config->selectedServer['SignonURL'] = 'https://example.com/SignonURL';
        $config->selectedServer['LogoutURL'] = 'https://example.com/logoutURL';

        $this->object->logOut();

        $response = $responseStub->getResponse();
        self::assertSame(['https://example.com/logoutURL'], $response->getHeader('Location'));
        self::assertSame(302, $response->getStatusCode());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAuthLogout(): void
    {
        $responseStub = new ResponseRendererStub();
        (new ReflectionProperty(ResponseRenderer::class, 'instance'))->setValue(null, $responseStub);

        $GLOBALS['header'] = [];
        $config = Config::getInstance();
        $config->selectedServer['SignonURL'] = 'https://example.com/SignonURL';
        $config->selectedServer['LogoutURL'] = '';

        $this->object->logOut();

        $response = $responseStub->getResponse();
        self::assertSame(['https://example.com/SignonURL'], $response->getHeader('Location'));
        self::assertSame(302, $response->getStatusCode());
    }

    public function testAuthCheckEmpty(): void
    {
        Config::getInstance()->selectedServer['SignonURL'] = 'https://example.com/SignonURL';
        $_SESSION['LAST_SIGNON_URL'] = 'https://example.com/SignonDiffURL';

        self::assertFalse(
            $this->object->readCredentials(),
        );
    }

    public function testAuthCheckSession(): void
    {
        $config = Config::getInstance();
        $config->selectedServer['SignonURL'] = 'https://example.com/SignonURL';
        $_SESSION['LAST_SIGNON_URL'] = 'https://example.com/SignonURL';
        $config->selectedServer['SignonScript'] = './examples/signon-script.php';
        $config->selectedServer['SignonSession'] = 'session123';
        $config->selectedServer['SignonCookieParams'] = [];
        $config->selectedServer['host'] = 'localhost';
        $config->selectedServer['port'] = '80';
        $config->selectedServer['user'] = 'user';

        self::assertTrue(
            $this->object->readCredentials(),
        );

        self::assertEquals('user', $this->object->user);

        self::assertEquals('password', $this->object->password);

        self::assertEquals('https://example.com/SignonURL', $_SESSION['LAST_SIGNON_URL']);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAuthCheckToken(): void
    {
        $_SESSION = [' PMA_token ' => 'eefefef'];

        $responseStub = new ResponseRendererStub();
        (new ReflectionProperty(ResponseRenderer::class, 'instance'))->setValue(null, $responseStub);

        $config = Config::getInstance();
        $config->selectedServer = (new Server([
            'SignonURL' => 'https://example.com/SignonURL',
            'SignonSession' => 'session123',
            'SignonCookieParams' => [],
            'host' => 'localhost',
            'port' => '80',
            'user' => 'user',
            'SignonScript' => '',
        ]))->asArray();
        $_COOKIE['session123'] = true;
        $_SESSION['PMA_single_signon_user'] = 'user123';
        $_SESSION['PMA_single_signon_password'] = 'pass123';
        $_SESSION['PMA_single_signon_host'] = 'local';
        $_SESSION['PMA_single_signon_port'] = '12';
        $_SESSION['PMA_single_signon_cfgupdate'] = ['foo' => 'bar'];
        $_SESSION['PMA_single_signon_token'] = 'pmaToken';
        $sessionName = session_name();
        $sessionID = session_id();

        $this->object->logOut();

        $response = $responseStub->getResponse();
        self::assertSame(['https://example.com/SignonURL'], $response->getHeader('Location'));
        self::assertSame(302, $response->getStatusCode());

        self::assertEquals(
            (new Server([
                'SignonURL' => 'https://example.com/SignonURL',
                'SignonScript' => '',
                'SignonSession' => 'session123',
                'SignonCookieParams' => [],
                'host' => 'localhost',
                'port' => '80',
                'user' => 'user',
            ]))->asArray(),
            $config->selectedServer,
        );

        self::assertEquals(
            $sessionName,
            session_name(),
        );

        self::assertEquals(
            $sessionID,
            session_id(),
        );

        self::assertArrayNotHasKey('LAST_SIGNON_URL', $_SESSION);
    }

    public function testAuthCheckKeep(): void
    {
        $config = Config::getInstance();
        $config->selectedServer['SignonURL'] = 'https://example.com/SignonURL';
        $config->selectedServer['SignonSession'] = 'session123';
        $config->selectedServer['SignonCookieParams'] = [];
        $config->selectedServer['host'] = 'localhost';
        $config->selectedServer['port'] = '80';
        $config->selectedServer['user'] = 'user';
        $config->selectedServer['SignonScript'] = '';
        $_COOKIE['session123'] = true;
        $_REQUEST['old_usr'] = '';
        $_SESSION['PMA_single_signon_user'] = 'user123';
        $_SESSION['PMA_single_signon_password'] = 'pass123';
        $_SESSION['PMA_single_signon_host'] = 'local';
        $_SESSION['PMA_single_signon_port'] = '12';
        $_SESSION['PMA_single_signon_cfgupdate'] = ['foo' => 'bar'];
        $_SESSION['PMA_single_signon_token'] = 'pmaToken';

        self::assertTrue(
            $this->object->readCredentials(),
        );

        self::assertEquals('user123', $this->object->user);

        self::assertEquals('pass123', $this->object->password);
    }

    public function testAuthSetUser(): void
    {
        $this->object->user = 'testUser123';
        $this->object->password = 'testPass123';

        self::assertTrue(
            $this->object->storeCredentials(),
        );

        $config = Config::getInstance();
        self::assertEquals('testUser123', $config->selectedServer['user']);

        self::assertEquals('testPass123', $config->selectedServer['password']);
    }

    public function testAuthFailsForbidden(): void
    {
        Config::getInstance()->selectedServer['SignonSession'] = 'newSession';
        $_COOKIE['newSession'] = '42';

        $this->object = $this->getMockBuilder(AuthenticationSignon::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['showLoginForm'])
            ->getMock();

        $this->object->expects(self::exactly(1))
            ->method('showLoginForm')
            ->willThrowException(new ExitException());

        try {
            $this->object->showFailure('empty-denied');
        } catch (ExitException) {
        }

        self::assertEquals(
            'Login without a password is forbidden by configuration (see AllowNoPassword)',
            $_SESSION['PMA_single_signon_error_message'],
        );
    }

    public function testAuthFailsDeny(): void
    {
        Config::getInstance()->selectedServer['SignonSession'] = 'newSession';
        $_COOKIE['newSession'] = '42';

        $this->object = $this->getMockBuilder(AuthenticationSignon::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['showLoginForm'])
            ->getMock();

        $this->object->expects(self::exactly(1))
            ->method('showLoginForm')
            ->willThrowException(new ExitException());

        try {
            $this->object->showFailure('allow-denied');
        } catch (ExitException) {
        }

        self::assertEquals('Access denied!', $_SESSION['PMA_single_signon_error_message']);
    }

    public function testAuthFailsTimeout(): void
    {
        $config = Config::getInstance();
        $config->selectedServer['SignonSession'] = 'newSession';
        $_COOKIE['newSession'] = '42';

        $this->object = $this->getMockBuilder(AuthenticationSignon::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['showLoginForm'])
            ->getMock();

        $this->object->expects(self::exactly(1))
            ->method('showLoginForm')
            ->willThrowException(new ExitException());

        $config->settings['LoginCookieValidity'] = '1440';

        try {
            $this->object->showFailure('no-activity');
        } catch (ExitException) {
        }

        self::assertEquals(
            'You have been automatically logged out due to inactivity of'
            . ' 1440 seconds. Once you log in again, you should be able to'
            . ' resume the work where you left off.',
            $_SESSION['PMA_single_signon_error_message'],
        );
    }

    public function testAuthFailsMySQLError(): void
    {
        Config::getInstance()->selectedServer['SignonSession'] = 'newSession';
        $_COOKIE['newSession'] = '42';

        $this->object = $this->getMockBuilder(AuthenticationSignon::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['showLoginForm'])
            ->getMock();

        $this->object->expects(self::exactly(1))
            ->method('showLoginForm')
            ->willThrowException(new ExitException());

        $dbi = $this->getMockBuilder(DatabaseInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $dbi->expects(self::once())
            ->method('getError')
            ->willReturn('error<123>');

        DatabaseInterface::$instance = $dbi;

        try {
            $this->object->showFailure('');
        } catch (ExitException) {
        }

        self::assertEquals('error&lt;123&gt;', $_SESSION['PMA_single_signon_error_message']);
    }

    public function testAuthFailsConnect(): void
    {
        Config::getInstance()->selectedServer['SignonSession'] = 'newSession';
        $_COOKIE['newSession'] = '42';
        unset($GLOBALS['errno']);

        $this->object = $this->getMockBuilder(AuthenticationSignon::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['showLoginForm'])
            ->getMock();

        $this->object->expects(self::exactly(1))
            ->method('showLoginForm')
            ->willThrowException(new ExitException());

        $dbi = $this->getMockBuilder(DatabaseInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $dbi->expects(self::once())
            ->method('getError')
            ->willReturn('');

        DatabaseInterface::$instance = $dbi;

        try {
            $this->object->showFailure('');
        } catch (ExitException) {
        }

        self::assertEquals('Cannot log in to the MySQL server', $_SESSION['PMA_single_signon_error_message']);
    }

    public function testSetCookieParamsDefaults(): void
    {
        $this->object = $this->getMockBuilder(AuthenticationSignon::class)
        ->disableOriginalConstructor()
        ->onlyMethods(['setCookieParams'])
        ->getMock();

        $this->object->setCookieParams([]);

        $defaultOptions = [
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => false,
            'samesite' => '',
        ];

        self::assertSame(
            $defaultOptions,
            session_get_cookie_params(),
        );
    }
}
