<?php
/**
 * FrankenPHP worker mode entry point for Magento.
 *
 * Bootstraps Magento once, then handles requests in a loop. After each
 * request, every shared instance in the ObjectManager that is not in the
 * hardcoded $whitelist is dropped so the next request rebuilds it from
 * scratch — stateless services (controllers, blocks, models, view state)
 * never persist between requests.
 *
 * Only stateless infrastructure (config, DI, filesystem, DB connection,
 * autoloader, caches, event manager) is allowed to survive.
 */

ignore_user_abort(true);

require __DIR__ . '/../app/bootstrap.php';

use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ObjectManager as AppObjectManager;

const MAX_REQUESTS = 500;

/**
 * Hardcoded whitelist of class/interface names whose shared instances may
 * persist across requests. Anything else is cleared after every request.
 *
 * Keys are matched against the keys of ObjectManager::$_sharedInstances
 * (which are class/interface names, after preference resolution).
 *
 * Wildcards: a trailing "*" matches by prefix (e.g. "Magento\Framework\App\Cache\Type\*").
 */
$whitelist = [
    // ---- Core ObjectManager / DI ----
    \Magento\Framework\ObjectManagerInterface::class,
    \Magento\Framework\ObjectManager\ConfigInterface::class,
    \Magento\Framework\ObjectManager\Config\Config::class,
    \Magento\Framework\ObjectManager\FactoryInterface::class,
    \Magento\Framework\ObjectManager\Factory\Dynamic\Developer::class,
    \Magento\Framework\ObjectManager\Factory\Dynamic\Production::class,
    \Magento\Framework\ObjectManager\Factory\Compiled::class,
    \Magento\Framework\ObjectManager\DefinitionInterface::class,
    \Magento\Framework\ObjectManager\Definition\Compiled\Serialized::class,
    \Magento\Framework\ObjectManager\Definition\Compiled\Binary::class,
    \Magento\Framework\ObjectManager\Definition\Runtime::class,
    \Magento\Framework\ObjectManager\RelationsInterface::class,
    \Magento\Framework\ObjectManager\Relations\Runtime::class,
    \Magento\Framework\ObjectManager\Relations\Compiled::class,
    \Magento\Framework\ObjectManager\ConfigCacheInterface::class,
    \Magento\Framework\ObjectManager\ConfigCache::class,
    \Magento\Framework\ObjectManager\DefinitionFactory::class,
    \Magento\Framework\Interception\DefinitionInterface::class,
    \Magento\Framework\Interception\Definition\Runtime::class,
    \Magento\Framework\Interception\Definition\Compiled::class,
    \Magento\Framework\Interception\PluginListInterface::class,
    \Magento\Framework\Interception\PluginList\PluginList::class,
    \Magento\Framework\Interception\ObjectManager\ConfigInterface::class,
    \Magento\Framework\Interception\ObjectManager\Config\Developer::class,
    \Magento\Framework\Interception\ObjectManager\Config\Compiled::class,
    \Magento\Framework\Interception\Config\CacheManager::class,
    \Magento\Framework\App\ObjectManager\ConfigLoader::class,
    \Magento\Framework\App\ObjectManager\ConfigWriterInterface::class,
    // The OMFactory stores ConfigLoader under the *interface* key during
    // bootstrap (see ObjectManagerFactory::create line 171). Keep both keys.
    \Magento\Framework\ObjectManager\ConfigLoaderInterface::class,
    \Magento\Framework\App\ObjectManager\Environment\Developer::class,
    \Magento\Framework\App\ObjectManager\Environment\Compiled::class,

    // ---- Deployment / scope config ----
    \Magento\Framework\App\DeploymentConfig::class,
    \Magento\Framework\App\DeploymentConfig\Reader::class,
    \Magento\Framework\App\DeploymentConfig\Writer::class,
    \Magento\Framework\App\Config\ScopeConfigInterface::class,
    \Magento\Framework\App\Config::class,
    \Magento\Framework\App\Config\ScopePool::class,
    \Magento\Framework\App\ScopeResolverPool::class,
    \Magento\Framework\App\Config\Initial::class,
    \Magento\Framework\App\Config\Initial\Reader::class,
    \Magento\Framework\App\Config\FileResolver::class,
    \Magento\Framework\App\Config\ConfigSourceAggregated::class,
    \Magento\Framework\App\Config\ConfigSourceInterface::class,
    \Magento\Framework\App\Config\ConfigPathResolver::class,
    \Magento\Framework\App\Config\ConfigTypeInterface::class,

    // ---- Filesystem ----
    \Magento\Framework\Filesystem::class,
    \Magento\Framework\Filesystem\DirectoryList::class,
    \Magento\Framework\App\Filesystem\DirectoryList::class,
    \Magento\Framework\Filesystem\DriverPool::class,
    \Magento\Framework\Filesystem\Driver\File::class,
    \Magento\Framework\Filesystem\Driver\Http::class,
    \Magento\Framework\Filesystem\Driver\Https::class,
    \Magento\Framework\Filesystem\DriverInterface::class,
    \Magento\Framework\Filesystem\Directory\ReadFactory::class,
    \Magento\Framework\Filesystem\Directory\WriteFactory::class,
    \Magento\Framework\Filesystem\File\ReadFactory::class,
    \Magento\Framework\Filesystem\File\WriteFactory::class,

    // ---- DB / resource connection ----
    \Magento\Framework\App\ResourceConnection::class,
    \Magento\Framework\App\ResourceConnection\ConfigInterface::class,
    \Magento\Framework\App\ResourceConnection\Config::class,
    \Magento\Framework\App\ResourceConnection\ConnectionFactory::class,
    \Magento\Framework\Model\ResourceModel\Type\Db\ConnectionFactory::class,
    \Magento\Framework\Model\ResourceModel\Type\Db\ConnectionFactoryInterface::class,
    \Magento\Framework\DB\LoggerInterface::class,
    \Magento\Framework\DB\Logger\Quiet::class,
    \Magento\Framework\DB\Logger\File::class,
    \Magento\Framework\DB\SelectFactory::class,

    // ---- Cache infrastructure (cache backends are pooled; cache TYPE wrappers are stateless façades) ----
    \Magento\Framework\App\CacheInterface::class,
    \Magento\Framework\App\Cache::class,
    \Magento\Framework\App\Cache\Frontend\Pool::class,
    \Magento\Framework\App\Cache\Frontend\Factory::class,
    \Magento\Framework\App\Cache\Type\FrontendPool::class,
    \Magento\Framework\App\Cache\Type\Config::class,
    \Magento\Framework\App\Cache\Type\Layout::class,
    \Magento\Framework\App\Cache\Type\Block::class,
    \Magento\Framework\App\Cache\Type\Collection::class,
    \Magento\Framework\App\Cache\Type\Reflection::class,
    \Magento\Framework\App\Cache\Type\Translate::class,
    \Magento\Framework\App\Cache\Type\Webhooks::class,
    \Magento\Framework\App\Cache\Type\Dummy::class,
    \Magento\Framework\App\Cache\StateInterface::class,
    \Magento\Framework\App\Cache\State::class,
    \Magento\Framework\App\Cache\TypeListInterface::class,
    \Magento\Framework\App\Cache\TypeList::class,
    \Magento\Framework\Cache\FrontendInterface::class,
    \Magento\Framework\Cache\Frontend\Decorator\TagScope::class,
    \Magento\Framework\Cache\Config::class,
    \Magento\Framework\Cache\ConfigInterface::class,
    \Magento\Framework\Cache\InvalidateLogger::class,

    // ---- Event manager (stateless dispatcher; observer instances are resolved per dispatch) ----
    \Magento\Framework\Event\ManagerInterface::class,
    \Magento\Framework\Event\Manager::class,
    \Magento\Framework\Event\ConfigInterface::class,
    \Magento\Framework\Event\Config::class,
    \Magento\Framework\Event\Config\Data::class,
    \Magento\Framework\Event\InvokerInterface::class,
    \Magento\Framework\Event\Invoker\InvokerDefault::class,
    \Magento\Framework\Event\Observer\Collection::class,
    \Magento\Framework\Event\Collection::class,

    // ---- Autoloader / class generation services ----
    \Magento\Framework\Code\Generator::class,
    \Magento\Framework\Code\Generator\Io::class,
    \Magento\Framework\Code\Generator\DefinedClasses::class,
    \Magento\Framework\Code\Reader\ClassReader::class,
    \Magento\Framework\Code\Reader\SourceArgumentsReader::class,
    \Magento\Framework\Code\Validator::class,
    \Magento\Framework\Code\Minifier\AdapterInterface::class,
    \Magento\Framework\Autoload\AutoloaderRegistry::class,

    // ---- Module / area infrastructure (read once from compiled config) ----
    \Magento\Framework\Module\ModuleListInterface::class,
    \Magento\Framework\Module\ModuleList::class,
    \Magento\Framework\Module\ModuleList\Loader::class,
    \Magento\Framework\Module\Manager::class,
    \Magento\Framework\Module\Dir::class,
    \Magento\Framework\Module\Dir\Reader::class,
    \Magento\Framework\Module\Dir\ReverseResolver::class,
    \Magento\Framework\Module\ResourceInterface::class,
    \Magento\Framework\Module\Resource::class,
    \Magento\Framework\Module\DbVersionInfo::class,
    \Magento\Framework\Module\PackageInfo::class,
    \Magento\Framework\Module\PackageInfoFactory::class,
    \Magento\Framework\Module\FullModuleList::class,

    // ---- Application state shell ----
    // App\State is NOT whitelisted: it holds the area code, which Http::launch()
    // sets via setAreaCode(). That method throws "Area code is already set" if
    // called twice — so State must be a fresh instance per request.
    \Magento\Framework\App\AreaList::class,

    // ---- Encryption / crypto keys (read from env, immutable) ----
    \Magento\Framework\Encryption\EncryptorInterface::class,
    \Magento\Framework\Encryption\Encryptor::class,
    \Magento\Framework\Encryption\KeyValidator::class,
    \Magento\Framework\Math\Random::class,

    // ---- Stdlib helpers that are pure ----
    \Magento\Framework\Serialize\SerializerInterface::class,
    \Magento\Framework\Serialize\Serializer\Json::class,
    \Magento\Framework\Serialize\Serializer\Serialize::class,
    \Magento\Framework\Serialize\SerializerFactory::class,
    \Magento\Framework\Stdlib\DateTime::class,
    \Magento\Framework\Stdlib\DateTime\DateTimeFormatterInterface::class,
    \Magento\Framework\Stdlib\StringUtils::class,
    \Magento\Framework\Phrase\Renderer\Placeholder::class,
    \Magento\Framework\Phrase\RendererInterface::class,

    // ---- App-level singletons that are stateless wrappers ----
    \Magento\Framework\App\Http\Context::class,    // reset per-request below
    \Magento\Framework\App\RequestFactory::class,
    \Magento\Framework\App\ResponseFactory::class,
    \Magento\Framework\App\Bootstrap::class,
];

/**
 * Wildcard prefixes — anything starting with one of these survives.
 */
$whitelistPrefixes = [
    'Magento\\Framework\\App\\Cache\\Type\\',
    // Interceptor/proxy classes for whitelisted services would be added here if generated under known prefixes.
];

$whitelistMap = array_flip($whitelist);

/**
 * Bootstrap once, warm opcache and the ObjectManager. The Http app instance
 * is NOT cached — it is rebuilt per request via createApplication() so its
 * constructor-injected dependencies come from a freshly-pruned OM.
 */
$bootstrap = Bootstrap::create(BP, $_SERVER);
$bootstrap->createApplication(\Magento\Framework\App\Http::class); // prime DI graph
$objectManager = AppObjectManager::getInstance();

/**
 * Reset per-request state on every shared instance that opts into the
 * ResetAfterRequestInterface contract (or exposes the legacy public
 * _resetState() method). Mirrors Magento\Framework\ObjectManager\Resetter::
 * addInstance + _resetState, but without the WeakMap — we walk live shared
 * instances directly.
 *
 * This is critical for services like View\Asset\Repository whose internal
 * caches ($defaults, fallback contexts) are captured per-area+theme. If they
 * survive a request without being reset, subsequent requests see stale data
 * (e.g. asset URLs collapse to "_view" because the cached defaults snapshot
 * was taken before any theme was loaded).
 *
 * Runs BEFORE the whitelist-based prune below — instances pruned afterward
 * are still reset correctly first.
 */
/**
 * Classes whose _resetState() must NOT be called between requests.
 *
 * Magento\Framework\Session\Storage::_resetState() reassigns $_data = []
 * which severs the reference binding between $_SESSION and Storage::$_data
 * established at session_start(). After that, every read of session data
 * (quote_id, form_key, customer_id, messages) returns empty — cart-add,
 * login, and form submissions silently fail.
 *
 * SessionManager already wipes per-request volatile state via
 * session_write_close() at the end of each request — leaving Storage alone
 * is the correct behavior for worker mode.
 */
$resetSkipPrefixes = [
    'Magento\\Framework\\Session\\Storage',
    'Magento\\Customer\\Model\\Session\\Storage',
];

$resetSharedState = \Closure::bind(
    function (array $skipPrefixes): void {
        foreach ($this->_sharedInstances as $instance) {
            if (!$instance instanceof \Magento\Framework\ObjectManager\ResetAfterRequestInterface) {
                continue;
            }
            $class = get_class($instance);
            foreach ($skipPrefixes as $prefix) {
                if (strncmp($class, $prefix, strlen($prefix)) === 0) {
                    continue 2;
                }
            }
            try {
                $instance->_resetState();
            } catch (\Throwable $e) {
                error_log('[frankenphp-worker] _resetState failed on '
                    . $class . ': ' . $e->getMessage());
            }
        }
    },
    $objectManager,
    \Magento\Framework\ObjectManager\ObjectManager::class,
);

/**
 * Build a Closure bound to the ObjectManager class scope so it can read and
 * mutate the protected $_sharedInstances property as if it were a method on
 * the OM itself. ReflectionProperty::getValue() returns the array by value;
 * a bound closure that uses `&$this->_sharedInstances` mutates in place.
 */
$pruneShared = \Closure::bind(
    function (array $whitelistMap, array $whitelistPrefixes): void {
        foreach ($this->_sharedInstances as $key => $instance) {
            if (isset($whitelistMap[$key])) {
                continue;
            }
            $keep = false;
            foreach ($whitelistPrefixes as $prefix) {
                if (strncmp($key, $prefix, strlen($prefix)) === 0) {
                    $keep = true;
                    break;
                }
            }
            if (!$keep) {
                unset($this->_sharedInstances[$key]);
            }
        }
    },
    $objectManager,
    \Magento\Framework\ObjectManager\ObjectManager::class,
);

/**
 * The OM factory holds a `creationStack` array used to detect circular DI
 * dependencies during a single create() call. If a child resolution throws,
 * the parent's stack entry can leak — polluting the next request with a
 * stale entry, which then trips a false "circular dependency" error.
 * Reset it per request via a closure bound to AbstractFactory's scope.
 */
$factoryRef = (function () {
    return $this->_factory;
})->bindTo($objectManager, \Magento\Framework\ObjectManager\ObjectManager::class)();

$resetCreationStack = \Closure::bind(
    function () {
        $this->creationStack = [];
    },
    $factoryRef,
    \Magento\Framework\ObjectManager\Factory\AbstractFactory::class,
);

$requestCount = 0;
$workerPid = getmypid();

/**
 * Detect developer mode once at boot. MAGE_MODE lives in app/etc/env.php,
 * not in the container env. Read it directly — env.php is a simple
 * `return [...]` file, no Magento bootstrapping needed.
 */
$envConfig = @include __DIR__ . '/../app/etc/env.php';
$devMode = is_array($envConfig) && ($envConfig['MAGE_MODE'] ?? '') === 'developer';

/**
 * Bootstrap warmup ran with the worker boot $_SERVER (no HTTPS=on, no real
 * REQUEST_URI). The Request/Response objects it created snapshot that stale
 * environment via $_SERVER at construction time. Drop just those two so the
 * first real request rebuilds them — leave everything else from warmup
 * intact (a full prune here triggers Magento DI circular-dep edge cases
 * that are masked when classes get instantiated in their normal order).
 */
$dropKeys = \Closure::bind(
    function (array $keys): void {
        foreach ($keys as $k) {
            unset($this->_sharedInstances[$k]);
        }
    },
    $objectManager,
    \Magento\Framework\ObjectManager\ObjectManager::class,
);
$dropKeys([
    \Magento\Framework\App\RequestInterface::class,
    \Magento\Framework\App\Request\Http::class,
    \Magento\Framework\App\ResponseInterface::class,
    \Magento\Framework\App\Response\Http::class,
    \Magento\Framework\HTTP\PhpEnvironment\Request::class,
    \Magento\Framework\HTTP\PhpEnvironment\Response::class,
]);


/**
 * Per-request handler. Inside the closure, anything not in $whitelist is
 * dropped from the OM before the next request, then a fresh Http app is
 * resolved through the OM — pulling fresh State/Request/Response/etc.
 */
$handler = static function () use (
    $bootstrap,
    $objectManager,
    $resetSharedState,
    $resetSkipPrefixes,
    $pruneShared,
    $resetCreationStack,
    $whitelistMap,
    $whitelistPrefixes,
    $devMode
) {
    $t0 = microtime(true);

    // Dev-only: inject the live-reload snippet into HTML responses just before
    // the closing </head>. Idempotent — skips bodies that already reference it.
    // Gated on MAGE_MODE=developer so this is a no-op in staging/production.
    $devReload = $devMode;
    if ($devReload) {
        ob_start(function (string $body): string {
            if ($body === '' || !str_contains($body, '</head>') || str_contains($body, 'dev-reload.js')) {
                return $body;
            }
            return str_replace(
                '</head>',
                '<script src="/dev-reload.js"></script></head>',
                $body
            );
        });
    }

    try {
        /** @var \Magento\Framework\App\Http $app */
        $app = $objectManager->create(\Magento\Framework\App\Http::class);
        $tCreate = microtime(true);
        $bootstrap->run($app);
        $tRun = microtime(true);
    } catch (\Throwable $e) {
        http_response_code(500);
        error_log('[frankenphp-worker] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        $tCreate = $tRun = microtime(true);
    } finally {
        // Flush the dev-reload ob_start buffer (no-op when $devReload is false).
        if ($devReload && ob_get_level() > 0) {
            @ob_end_flush();
        }
        $resetCreationStack();
        $resetSharedState($resetSkipPrefixes);
        $tReset = microtime(true);
        $pruneShared($whitelistMap, $whitelistPrefixes);
        $tPrune = microtime(true);
        $rpSize = function_exists('realpath_cache_size') ? realpath_cache_size() : -1;
        $mem = memory_get_usage(true);
        error_log(sprintf(
            '[timing] create=%.3fs run=%.3fs reset=%.3fs prune=%.3fs total=%.3fs mem=%dMB rp=%dK uri=%s',
            $tCreate - $t0,
            $tRun - $tCreate,
            $tReset - $tRun,
            $tPrune - $tReset,
            $tPrune - $t0,
            (int)($mem / 1024 / 1024),
            (int)($rpSize / 1024),
            $_SERVER['REQUEST_URI'] ?? '-'
        ));
    }
};

while (frankenphp_handle_request($handler)) {
    $requestCount++;
    gc_collect_cycles();

    if ($requestCount >= MAX_REQUESTS) {
        error_log(sprintf('[frankenphp-worker] pid=%d recycling after %d requests', $workerPid, $requestCount));
        break;
    }
}
