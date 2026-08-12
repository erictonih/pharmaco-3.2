.
├── app
│   ├── Common.php
│   ├── Config
│   │   ├── App.php
│   │   ├── Autoload.php
│   │   ├── Boot
│   │   │   ├── development.php
│   │   │   ├── production.php
│   │   │   └── testing.php
│   │   ├── Cache.php
│   │   ├── Constants.php
│   │   ├── ContentSecurityPolicy.php
│   │   ├── Cookie.php
│   │   ├── Cors.php
│   │   ├── CURLRequest.php
│   │   ├── Database.php
│   │   ├── DocTypes.php
│   │   ├── Email.php
│   │   ├── Encryption.php
│   │   ├── Events.php
│   │   ├── Exceptions.php
│   │   ├── Feature.php
│   │   ├── Filters.php
│   │   ├── ForeignCharacters.php
│   │   ├── Format.php
│   │   ├── Generators.php
│   │   ├── Honeypot.php
│   │   ├── Hostnames.php
│   │   ├── Images.php
│   │   ├── Kint.php
│   │   ├── Logger.php
│   │   ├── Migrations.php
│   │   ├── Mimes.php
│   │   ├── Modules.php
│   │   ├── Optimize.php
│   │   ├── Pager.php
│   │   ├── Paths.php
│   │   ├── Publisher.php
│   │   ├── Routes.php
│   │   ├── Routing.php
│   │   ├── Security.php
│   │   ├── Services.php
│   │   ├── Session.php
│   │   ├── Toolbar.php
│   │   ├── UserAgents.php
│   │   ├── Validation.php
│   │   ├── View.php
│   │   └── WorkerMode.php
│   ├── Controllers
│   │   ├── AdminController.php
│   │   ├── AuthController.php
│   │   ├── BaseController.php
│   │   ├── EmployeController.php
│   │   ├── Home.php
│   │   └── RhController.php
│   ├── Database
│   │   ├── Migrations
│   │   │   ├── 13-12-26(1).sql
│   │   │   └── conge.db
│   │   └── Seeds
│   ├── Filters
│   │   └── AuthFilter.php
│   ├── Helpers
│   ├── index.html
│   ├── Language
│   │   └── en
│   │       └── Validation.php
│   ├── Libraries
│   ├── Models
│   │   ├── AdminModel.php
│   │   ├── CongeModel.php
│   │   └── EspacePersonnelModel.php
│   ├── ThirdParty
│   └── Views
│       ├── admin
│       │   ├── dashboard.php
│       │   ├── demandes.php
│       │   └── employes.php
│       ├── auth
│       │   └── login.php
│       ├── employe
│       │   ├── create.php
│       │   ├── dashboard.php
│       │   ├── index.php
│       │   └── profil.php
│       ├── errors
│       │   ├── cli
│       │   │   ├── error_404.php
│       │   │   ├── error_exception.php
│       │   │   └── production.php
│       │   ├── html
│       │   │   ├── debug.css
│       │   │   ├── debug.js
│       │   │   ├── error_400.php
│       │   │   ├── error_404.php
│       │   │   ├── error_exception.php
│       │   │   └── production.php
│       │   └── layout.php
│       ├── layout.php
│       └── rh
│           ├── dashboard.php
│           ├── demandes.php
│           └── soldes.php
├── composer.json
├── conge.db
├── env
├── LICENSE
├── phpunit.xml.dist
├── preload.php
├── projet.md
├── public
│   ├── assets
│   │   └── css
│   │       ├── app.css
│   │       └── rh-layout.css
│   ├── favicon.ico
│   ├── index.php
│   ├── robots.txt
│   ├── TP — Application CodeIgniter 4.pdf
│   └── uploads
│       ├── 1776694071_81000dbbf45df168e429.png
│       ├── 1776773671_de19aa8cff6557aa4a37.png
│       ├── 1776778439_3b95a9c557cb45d253f7.jpeg
│       ├── 1776778478_330dcee77ff781e52e8b.png
│       ├── 1776778494_63e304902db8bd35633b.png
│       ├── 1778265612_a48881dc7e214a50d6da.png
│       └── default-cover.svg
├── readme.md
├── spark
├── system
│   ├── API
│   │   ├── ApiException.php
│   │   ├── BaseTransformer.php
│   │   ├── ResponseTrait.php
│   │   └── TransformerInterface.php
│   ├── Autoloader
│   │   ├── Autoloader.php
│   │   ├── FileLocatorCached.php
│   │   ├── FileLocatorInterface.php
│   │   └── FileLocator.php
│   ├── BaseModel.php
│   ├── Boot.php
│   ├── bootstrap.php
│   ├── Cache
│   │   ├── CacheFactory.php
│   │   ├── CacheInterface.php
│   │   ├── Exceptions
│   │   │   └── CacheException.php
│   │   ├── FactoriesCache
│   │   │   └── FileVarExportHandler.php
│   │   ├── FactoriesCache.php
│   │   ├── Handlers
│   │   │   ├── ApcuHandler.php
│   │   │   ├── BaseHandler.php
│   │   │   ├── DummyHandler.php
│   │   │   ├── FileHandler.php
│   │   │   ├── MemcachedHandler.php
│   │   │   ├── PredisHandler.php
│   │   │   ├── RedisHandler.php
│   │   │   └── WincacheHandler.php
│   │   └── ResponseCache.php
│   ├── CLI
│   │   ├── BaseCommand.php
│   │   ├── CLI.php
│   │   ├── Commands.php
│   │   ├── Console.php
│   │   ├── Exceptions
│   │   │   └── CLIException.php
│   │   ├── GeneratorTrait.php
│   │   ├── InputOutput.php
│   │   └── SignalTrait.php
│   ├── CodeIgniter.php
│   ├── Commands
│   │   ├── Cache
│   │   │   ├── ClearCache.php
│   │   │   └── InfoCache.php
│   │   ├── Database
│   │   │   ├── CreateDatabase.php
│   │   │   ├── Migrate.php
│   │   │   ├── MigrateRefresh.php
│   │   │   ├── MigrateRollback.php
│   │   │   ├── MigrateStatus.php
│   │   │   ├── Seed.php
│   │   │   └── ShowTableInfo.php
│   │   ├── Encryption
│   │   │   └── GenerateKey.php
│   │   ├── Generators
│   │   │   ├── CellGenerator.php
│   │   │   ├── CommandGenerator.php
│   │   │   ├── ConfigGenerator.php
│   │   │   ├── ControllerGenerator.php
│   │   │   ├── EntityGenerator.php
│   │   │   ├── FilterGenerator.php
│   │   │   ├── MigrationGenerator.php
│   │   │   ├── ModelGenerator.php
│   │   │   ├── ScaffoldGenerator.php
│   │   │   ├── SeederGenerator.php
│   │   │   ├── TestGenerator.php
│   │   │   ├── TransformerGenerator.php
│   │   │   ├── ValidationGenerator.php
│   │   │   └── Views
│   │   │       ├── cell.tpl.php
│   │   │       ├── cell_view.tpl.php
│   │   │       ├── command.tpl.php
│   │   │       ├── config.tpl.php
│   │   │       ├── controller.tpl.php
│   │   │       ├── entity.tpl.php
│   │   │       ├── filter.tpl.php
│   │   │       ├── migration.tpl.php
│   │   │       ├── model.tpl.php
│   │   │       ├── seeder.tpl.php
│   │   │       ├── test.tpl.php
│   │   │       ├── transformer.tpl.php
│   │   │       └── validation.tpl.php
│   │   ├── Help.php
│   │   ├── Housekeeping
│   │   │   ├── ClearDebugbar.php
│   │   │   └── ClearLogs.php
│   │   ├── ListCommands.php
│   │   ├── Server
│   │   │   └── Serve.php
│   │   ├── Translation
│   │   │   ├── LocalizationFinder.php
│   │   │   └── LocalizationSync.php
│   │   ├── Utilities
│   │   │   ├── ConfigCheck.php
│   │   │   ├── Environment.php
│   │   │   ├── FilterCheck.php
│   │   │   ├── Namespaces.php
│   │   │   ├── Optimize.php
│   │   │   ├── PhpIniCheck.php
│   │   │   ├── Publish.php
│   │   │   ├── Routes
│   │   │   │   ├── AutoRouteCollector.php
│   │   │   │   ├── AutoRouterImproved
│   │   │   │   │   ├── AutoRouteCollector.php
│   │   │   │   │   └── ControllerMethodReader.php
│   │   │   │   ├── ControllerFinder.php
│   │   │   │   ├── ControllerMethodReader.php
│   │   │   │   ├── FilterCollector.php
│   │   │   │   ├── FilterFinder.php
│   │   │   │   └── SampleURIGenerator.php
│   │   │   └── Routes.php
│   │   └── Worker
│   │       ├── Views
│   │       │   ├── Caddyfile.tpl
│   │       │   └── frankenphp-worker.php.tpl
│   │       ├── WorkerInstall.php
│   │       └── WorkerUninstall.php
│   ├── Common.php
│   ├── ComposerScripts.php
│   ├── Config
│   │   ├── AutoloadConfig.php
│   │   ├── BaseConfig.php
│   │   ├── BaseService.php
│   │   ├── DotEnv.php
│   │   ├── Factories.php
│   │   ├── Factory.php
│   │   ├── Filters.php
│   │   ├── ForeignCharacters.php
│   │   ├── Publisher.php
│   │   ├── Routing.php
│   │   ├── Services.php
│   │   └── View.php
│   ├── Controller.php
│   ├── Cookie
│   │   ├── CloneableCookieInterface.php
│   │   ├── CookieInterface.php
│   │   ├── Cookie.php
│   │   ├── CookieStore.php
│   │   └── Exceptions
│   │       └── CookieException.php
│   ├── Database
│   │   ├── BaseBuilder.php
│   │   ├── BaseConnection.php
│   │   ├── BasePreparedQuery.php
│   │   ├── BaseResult.php
│   │   ├── BaseUtils.php
│   │   ├── Config.php
│   │   ├── ConnectionInterface.php
│   │   ├── Database.php
│   │   ├── Exceptions
│   │   │   ├── DatabaseException.php
│   │   │   ├── DataException.php
│   │   │   └── ExceptionInterface.php
│   │   ├── Forge.php
│   │   ├── Migration.php
│   │   ├── MigrationRunner.php
│   │   ├── MySQLi
│   │   │   ├── Builder.php
│   │   │   ├── Connection.php
│   │   │   ├── Forge.php
│   │   │   ├── PreparedQuery.php
│   │   │   ├── Result.php
│   │   │   └── Utils.php
│   │   ├── OCI8
│   │   │   ├── Builder.php
│   │   │   ├── Connection.php
│   │   │   ├── Forge.php
│   │   │   ├── PreparedQuery.php
│   │   │   ├── Result.php
│   │   │   └── Utils.php
│   │   ├── Postgre
│   │   │   ├── Builder.php
│   │   │   ├── Connection.php
│   │   │   ├── Forge.php
│   │   │   ├── PreparedQuery.php
│   │   │   ├── Result.php
│   │   │   └── Utils.php
│   │   ├── PreparedQueryInterface.php
│   │   ├── QueryInterface.php
│   │   ├── Query.php
│   │   ├── RawSql.php
│   │   ├── ResultInterface.php
│   │   ├── Seeder.php
│   │   ├── SQLite3
│   │   │   ├── Builder.php
│   │   │   ├── Connection.php
│   │   │   ├── Forge.php
│   │   │   ├── PreparedQuery.php
│   │   │   ├── Result.php
│   │   │   ├── Table.php
│   │   │   └── Utils.php
│   │   ├── SQLSRV
│   │   │   ├── Builder.php
│   │   │   ├── Connection.php
│   │   │   ├── Forge.php
│   │   │   ├── PreparedQuery.php
│   │   │   ├── Result.php
│   │   │   └── Utils.php
│   │   └── TableName.php
│   ├── DataCaster
│   │   ├── Cast
│   │   │   ├── ArrayCast.php
│   │   │   ├── BaseCast.php
│   │   │   ├── BooleanCast.php
│   │   │   ├── CastInterface.php
│   │   │   ├── CSVCast.php
│   │   │   ├── DatetimeCast.php
│   │   │   ├── EnumCast.php
│   │   │   ├── FloatCast.php
│   │   │   ├── IntBoolCast.php
│   │   │   ├── IntegerCast.php
│   │   │   ├── JsonCast.php
│   │   │   ├── TimestampCast.php
│   │   │   └── URICast.php
│   │   ├── DataCaster.php
│   │   └── Exceptions
│   │       └── CastException.php
│   ├── DataConverter
│   │   └── DataConverter.php
│   ├── Debug
│   │   ├── BaseExceptionHandler.php
│   │   ├── ExceptionHandlerInterface.php
│   │   ├── ExceptionHandler.php
│   │   ├── Exceptions.php
│   │   ├── Iterator.php
│   │   ├── Timer.php
│   │   ├── Toolbar
│   │   │   ├── Collectors
│   │   │   │   ├── BaseCollector.php
│   │   │   │   ├── Config.php
│   │   │   │   ├── Database.php
│   │   │   │   ├── Events.php
│   │   │   │   ├── Files.php
│   │   │   │   ├── History.php
│   │   │   │   ├── Logs.php
│   │   │   │   ├── Routes.php
│   │   │   │   ├── Timers.php
│   │   │   │   └── Views.php
│   │   │   └── Views
│   │   │       ├── _config.tpl
│   │   │       ├── _database.tpl
│   │   │       ├── _events.tpl
│   │   │       ├── _files.tpl
│   │   │       ├── _history.tpl
│   │   │       ├── _logs.tpl
│   │   │       ├── _routes.tpl
│   │   │       ├── toolbar.css
│   │   │       ├── toolbar.js
│   │   │       ├── toolbarloader.js
│   │   │       ├── toolbarstandalone.js
│   │   │       └── toolbar.tpl.php
│   │   └── Toolbar.php
│   ├── Email
│   │   └── Email.php
│   ├── Encryption
│   │   ├── EncrypterInterface.php
│   │   ├── Encryption.php
│   │   ├── Exceptions
│   │   │   └── EncryptionException.php
│   │   ├── Handlers
│   │   │   ├── BaseHandler.php
│   │   │   ├── OpenSSLHandler.php
│   │   │   └── SodiumHandler.php
│   │   └── KeyRotationDecorator.php
│   ├── Entity
│   │   ├── Cast
│   │   │   ├── ArrayCast.php
│   │   │   ├── BaseCast.php
│   │   │   ├── BooleanCast.php
│   │   │   ├── CastInterface.php
│   │   │   ├── CSVCast.php
│   │   │   ├── DatetimeCast.php
│   │   │   ├── EnumCast.php
│   │   │   ├── FloatCast.php
│   │   │   ├── IntBoolCast.php
│   │   │   ├── IntegerCast.php
│   │   │   ├── JsonCast.php
│   │   │   ├── ObjectCast.php
│   │   │   ├── StringCast.php
│   │   │   ├── TimestampCast.php
│   │   │   └── URICast.php
│   │   ├── Entity.php
│   │   └── Exceptions
│   │       └── CastException.php
│   ├── Events
│   │   └── Events.php
│   ├── Exceptions
│   │   ├── BadFunctionCallException.php
│   │   ├── BadMethodCallException.php
│   │   ├── ConfigException.php
│   │   ├── CriticalError.php
│   │   ├── DebugTraceableTrait.php
│   │   ├── DownloadException.php
│   │   ├── ExceptionInterface.php
│   │   ├── FrameworkException.php
│   │   ├── HasExitCodeInterface.php
│   │   ├── HTTPExceptionInterface.php
│   │   ├── InvalidArgumentException.php
│   │   ├── LogicException.php
│   │   ├── ModelException.php
│   │   ├── PageNotFoundException.php
│   │   ├── RuntimeException.php
│   │   └── TestException.php
│   ├── Files
│   │   ├── Exceptions
│   │   │   ├── ExceptionInterface.php
│   │   │   ├── FileException.php
│   │   │   └── FileNotFoundException.php
│   │   ├── FileCollection.php
│   │   ├── File.php
│   │   └── FileSizeUnit.php
│   ├── Filters
│   │   ├── Cors.php
│   │   ├── CSRF.php
│   │   ├── DebugToolbar.php
│   │   ├── Exceptions
│   │   │   └── FilterException.php
│   │   ├── FilterInterface.php
│   │   ├── Filters.php
│   │   ├── ForceHTTPS.php
│   │   ├── Honeypot.php
│   │   ├── InvalidChars.php
│   │   ├── PageCache.php
│   │   ├── PerformanceMetrics.php
│   │   └── SecureHeaders.php
│   ├── Format
│   │   ├── Exceptions
│   │   │   └── FormatException.php
│   │   ├── Format.php
│   │   ├── FormatterInterface.php
│   │   ├── JSONFormatter.php
│   │   └── XMLFormatter.php
│   ├── Helpers
│   │   ├── Array
│   │   │   └── ArrayHelper.php
│   │   ├── array_helper.php
│   │   ├── cookie_helper.php
│   │   ├── date_helper.php
│   │   ├── filesystem_helper.php
│   │   ├── form_helper.php
│   │   ├── html_helper.php
│   │   ├── inflector_helper.php
│   │   ├── kint_helper.php
│   │   ├── number_helper.php
│   │   ├── security_helper.php
│   │   ├── test_helper.php
│   │   ├── text_helper.php
│   │   ├── url_helper.php
│   │   └── xml_helper.php
│   ├── Honeypot
│   │   ├── Exceptions
│   │   │   └── HoneypotException.php
│   │   └── Honeypot.php
│   ├── HotReloader
│   │   ├── DirectoryHasher.php
│   │   ├── HotReloader.php
│   │   └── IteratorFilter.php
│   ├── HTTP
│   │   ├── CLIRequest.php
│   │   ├── ContentSecurityPolicy.php
│   │   ├── Cors.php
│   │   ├── CURLRequest.php
│   │   ├── DownloadResponse.php
│   │   ├── Exceptions
│   │   │   ├── BadRequestException.php
│   │   │   ├── ExceptionInterface.php
│   │   │   ├── HTTPException.php
│   │   │   └── RedirectException.php
│   │   ├── Files
│   │   │   ├── FileCollection.php
│   │   │   ├── UploadedFileInterface.php
│   │   │   └── UploadedFile.php
│   │   ├── Header.php
│   │   ├── IncomingRequest.php
│   │   ├── MessageInterface.php
│   │   ├── Message.php
│   │   ├── MessageTrait.php
│   │   ├── Method.php
│   │   ├── Negotiate.php
│   │   ├── OutgoingRequestInterface.php
│   │   ├── OutgoingRequest.php
│   │   ├── RedirectResponse.php
│   │   ├── RequestInterface.php
│   │   ├── Request.php
│   │   ├── RequestTrait.php
│   │   ├── ResponsableInterface.php
│   │   ├── ResponseInterface.php
│   │   ├── Response.php
│   │   ├── ResponseTrait.php
│   │   ├── SiteURIFactory.php
│   │   ├── SiteURI.php
│   │   ├── URI.php
│   │   └── UserAgent.php
│   ├── I18n
│   │   ├── Exceptions
│   │   │   └── I18nException.php
│   │   ├── TimeDifference.php
│   │   ├── TimeLegacy.php
│   │   ├── Time.php
│   │   └── TimeTrait.php
│   ├── Images
│   │   ├── Exceptions
│   │   │   └── ImageException.php
│   │   ├── Handlers
│   │   │   ├── BaseHandler.php
│   │   │   ├── GDHandler.php
│   │   │   └── ImageMagickHandler.php
│   │   ├── ImageHandlerInterface.php
│   │   └── Image.php
│   ├── index.html
│   ├── Language
│   │   ├── en
│   │   │   ├── Api.php
│   │   │   ├── Cache.php
│   │   │   ├── Cast.php
│   │   │   ├── CLI.php
│   │   │   ├── Cookie.php
│   │   │   ├── Core.php
│   │   │   ├── Database.php
│   │   │   ├── Email.php
│   │   │   ├── Encryption.php
│   │   │   ├── Errors.php
│   │   │   ├── Fabricator.php
│   │   │   ├── Files.php
│   │   │   ├── Filters.php
│   │   │   ├── Format.php
│   │   │   ├── Honeypot.php
│   │   │   ├── HTTP.php
│   │   │   ├── Images.php
│   │   │   ├── Language.php
│   │   │   ├── Log.php
│   │   │   ├── Migrations.php
│   │   │   ├── Number.php
│   │   │   ├── Pager.php
│   │   │   ├── Publisher.php
│   │   │   ├── RESTful.php
│   │   │   ├── Router.php
│   │   │   ├── Security.php
│   │   │   ├── Session.php
│   │   │   ├── Test.php
│   │   │   ├── Time.php
│   │   │   ├── Validation.php
│   │   │   └── View.php
│   │   └── Language.php
│   ├── Log
│   │   ├── Exceptions
│   │   │   └── LogException.php
│   │   ├── Handlers
│   │   │   ├── BaseHandler.php
│   │   │   ├── ChromeLoggerHandler.php
│   │   │   ├── ErrorlogHandler.php
│   │   │   ├── FileHandler.php
│   │   │   └── HandlerInterface.php
│   │   └── Logger.php
│   ├── Model.php
│   ├── Modules
│   │   └── Modules.php
│   ├── Pager
│   │   ├── Exceptions
│   │   │   └── PagerException.php
│   │   ├── PagerInterface.php
│   │   ├── Pager.php
│   │   ├── PagerRenderer.php
│   │   └── Views
│   │       ├── default_full.php
│   │       ├── default_head.php
│   │       └── default_simple.php
│   ├── Publisher
│   │   ├── ContentReplacer.php
│   │   ├── Exceptions
│   │   │   └── PublisherException.php
│   │   └── Publisher.php
│   ├── RESTful
│   │   ├── BaseResource.php
│   │   ├── ResourceController.php
│   │   └── ResourcePresenter.php
│   ├── rewrite.php
│   ├── Router
│   │   ├── Attributes
│   │   │   ├── Cache.php
│   │   │   ├── Filter.php
│   │   │   ├── Restrict.php
│   │   │   └── RouteAttributeInterface.php
│   │   ├── AutoRouterImproved.php
│   │   ├── AutoRouterInterface.php
│   │   ├── AutoRouter.php
│   │   ├── DefinedRouteCollector.php
│   │   ├── Exceptions
│   │   │   ├── ExceptionInterface.php
│   │   │   ├── MethodNotFoundException.php
│   │   │   └── RouterException.php
│   │   ├── RouteCollectionInterface.php
│   │   ├── RouteCollection.php
│   │   ├── RouterInterface.php
│   │   └── Router.php
│   ├── Security
│   │   ├── CheckPhpIni.php
│   │   ├── Exceptions
│   │   │   └── SecurityException.php
│   │   ├── SecurityInterface.php
│   │   └── Security.php
│   ├── Session
│   │   ├── Exceptions
│   │   │   └── SessionException.php
│   │   ├── Handlers
│   │   │   ├── ArrayHandler.php
│   │   │   ├── BaseHandler.php
│   │   │   ├── Database
│   │   │   │   ├── MySQLiHandler.php
│   │   │   │   └── PostgreHandler.php
│   │   │   ├── DatabaseHandler.php
│   │   │   ├── FileHandler.php
│   │   │   ├── MemcachedHandler.php
│   │   │   └── RedisHandler.php
│   │   ├── PersistsConnection.php
│   │   ├── SessionInterface.php
│   │   └── Session.php
│   ├── Superglobals.php
│   ├── Test
│   │   ├── bootstrap.php
│   │   ├── CIUnitTestCase.php
│   │   ├── ConfigFromArrayTrait.php
│   │   ├── Constraints
│   │   │   └── SeeInDatabase.php
│   │   ├── ControllerTestTrait.php
│   │   ├── DatabaseTestTrait.php
│   │   ├── DOMParser.php
│   │   ├── Fabricator.php
│   │   ├── FeatureTestTrait.php
│   │   ├── Filters
│   │   │   └── CITestStreamFilter.php
│   │   ├── FilterTestTrait.php
│   │   ├── IniTestTrait.php
│   │   ├── Interfaces
│   │   │   └── FabricatorModel.php
│   │   ├── Mock
│   │   │   ├── MockAppConfig.php
│   │   │   ├── MockAutoload.php
│   │   │   ├── MockBuilder.php
│   │   │   ├── MockCache.php
│   │   │   ├── MockCLIConfig.php
│   │   │   ├── MockCodeIgniter.php
│   │   │   ├── MockCommon.php
│   │   │   ├── MockConnection.php
│   │   │   ├── MockCURLRequest.php
│   │   │   ├── MockEmail.php
│   │   │   ├── MockEvents.php
│   │   │   ├── MockFileLogger.php
│   │   │   ├── MockIncomingRequest.php
│   │   │   ├── MockInputOutput.php
│   │   │   ├── MockLanguage.php
│   │   │   ├── MockLogger.php
│   │   │   ├── MockQuery.php
│   │   │   ├── MockResourceController.php
│   │   │   ├── MockResourcePresenter.php
│   │   │   ├── MockResponse.php
│   │   │   ├── MockResult.php
│   │   │   ├── MockSecurity.php
│   │   │   ├── MockServices.php
│   │   │   ├── MockSession.php
│   │   │   └── MockTable.php
│   │   ├── PhpStreamWrapper.php
│   │   ├── ReflectionHelper.php
│   │   ├── StreamFilterTrait.php
│   │   ├── TestLogger.php
│   │   ├── TestResponse.php
│   │   └── Utilities
│   │       └── NativeHeadersStack.php
│   ├── ThirdParty
│   │   ├── Escaper
│   │   │   ├── EscaperInterface.php
│   │   │   ├── Escaper.php
│   │   │   ├── Exception
│   │   │   │   ├── ExceptionInterface.php
│   │   │   │   ├── InvalidArgumentException.php
│   │   │   │   └── RuntimeException.php
│   │   │   └── LICENSE.md
│   │   ├── Kint
│   │   │   ├── CallFinder.php
│   │   │   ├── FacadeInterface.php
│   │   │   ├── init_helpers.php
│   │   │   ├── init.php
│   │   │   ├── Kint.php
│   │   │   ├── LICENSE
│   │   │   ├── Parser
│   │   │   │   ├── AbstractPlugin.php
│   │   │   │   ├── ArrayLimitPlugin.php
│   │   │   │   ├── ArrayObjectPlugin.php
│   │   │   │   ├── Base64Plugin.php
│   │   │   │   ├── BinaryPlugin.php
│   │   │   │   ├── BlacklistPlugin.php
│   │   │   │   ├── ClassHooksPlugin.php
│   │   │   │   ├── ClassMethodsPlugin.php
│   │   │   │   ├── ClassStaticsPlugin.php
│   │   │   │   ├── ClassStringsPlugin.php
│   │   │   │   ├── ClosurePlugin.php
│   │   │   │   ├── ColorPlugin.php
│   │   │   │   ├── ConstructablePluginInterface.php
│   │   │   │   ├── DateTimePlugin.php
│   │   │   │   ├── DomPlugin.php
│   │   │   │   ├── EnumPlugin.php
│   │   │   │   ├── FsPathPlugin.php
│   │   │   │   ├── HtmlPlugin.php
│   │   │   │   ├── IteratorPlugin.php
│   │   │   │   ├── JsonPlugin.php
│   │   │   │   ├── MicrotimePlugin.php
│   │   │   │   ├── MysqliPlugin.php
│   │   │   │   ├── Parser.php
│   │   │   │   ├── PluginBeginInterface.php
│   │   │   │   ├── PluginCompleteInterface.php
│   │   │   │   ├── PluginInterface.php
│   │   │   │   ├── ProfilePlugin.php
│   │   │   │   ├── ProxyPlugin.php
│   │   │   │   ├── SerializePlugin.php
│   │   │   │   ├── SimpleXMLElementPlugin.php
│   │   │   │   ├── SplFileInfoPlugin.php
│   │   │   │   ├── StreamPlugin.php
│   │   │   │   ├── TablePlugin.php
│   │   │   │   ├── ThrowablePlugin.php
│   │   │   │   ├── TimestampPlugin.php
│   │   │   │   ├── ToStringPlugin.php
│   │   │   │   ├── TracePlugin.php
│   │   │   │   └── XmlPlugin.php
│   │   │   ├── Renderer
│   │   │   │   ├── AbstractRenderer.php
│   │   │   │   ├── AssetRendererTrait.php
│   │   │   │   ├── CliRenderer.php
│   │   │   │   ├── ConstructableRendererInterface.php
│   │   │   │   ├── PlainRenderer.php
│   │   │   │   ├── RendererInterface.php
│   │   │   │   ├── Rich
│   │   │   │   │   ├── AbstractPlugin.php
│   │   │   │   │   ├── BinaryPlugin.php
│   │   │   │   │   ├── CallableDefinitionPlugin.php
│   │   │   │   │   ├── CallablePlugin.php
│   │   │   │   │   ├── ColorPlugin.php
│   │   │   │   │   ├── LockPlugin.php
│   │   │   │   │   ├── MicrotimePlugin.php
│   │   │   │   │   ├── PluginInterface.php
│   │   │   │   │   ├── ProfilePlugin.php
│   │   │   │   │   ├── SourcePlugin.php
│   │   │   │   │   ├── TablePlugin.php
│   │   │   │   │   ├── TabPluginInterface.php
│   │   │   │   │   ├── TraceFramePlugin.php
│   │   │   │   │   └── ValuePluginInterface.php
│   │   │   │   ├── RichRenderer.php
│   │   │   │   ├── Text
│   │   │   │   │   ├── AbstractPlugin.php
│   │   │   │   │   ├── LockPlugin.php
│   │   │   │   │   ├── MicrotimePlugin.php
│   │   │   │   │   ├── PluginInterface.php
│   │   │   │   │   ├── SplFileInfoPlugin.php
│   │   │   │   │   └── TracePlugin.php
│   │   │   │   └── TextRenderer.php
│   │   │   ├── resources
│   │   │   │   └── compiled
│   │   │   │       ├── aante-dark.css
│   │   │   │       ├── aante-light.css
│   │   │   │       ├── main.js
│   │   │   │       ├── original.css
│   │   │   │       ├── plain.css
│   │   │   │       ├── solarized.css
│   │   │   │       └── solarized-dark.css
│   │   │   ├── Utils.php
│   │   │   └── Value
│   │   │       ├── AbstractValue.php
│   │   │       ├── ArrayValue.php
│   │   │       ├── ClosedResourceValue.php
│   │   │       ├── ClosureValue.php
│   │   │       ├── ColorValue.php
│   │   │       ├── Context
│   │   │       │   ├── ArrayContext.php
│   │   │       │   ├── BaseContext.php
│   │   │       │   ├── ClassConstContext.php
│   │   │       │   ├── ClassDeclaredContext.php
│   │   │       │   ├── ClassOwnedContext.php
│   │   │       │   ├── ContextInterface.php
│   │   │       │   ├── DoubleAccessMemberContext.php
│   │   │       │   ├── MethodContext.php
│   │   │       │   ├── PropertyContext.php
│   │   │       │   └── StaticPropertyContext.php
│   │   │       ├── DateTimeValue.php
│   │   │       ├── DeclaredCallableBag.php
│   │   │       ├── DomNodeListValue.php
│   │   │       ├── DomNodeValue.php
│   │   │       ├── EnumValue.php
│   │   │       ├── FixedWidthValue.php
│   │   │       ├── FunctionValue.php
│   │   │       ├── InstanceValue.php
│   │   │       ├── MethodValue.php
│   │   │       ├── MicrotimeValue.php
│   │   │       ├── ParameterBag.php
│   │   │       ├── ParameterHoldingTrait.php
│   │   │       ├── Representation
│   │   │       │   ├── AbstractRepresentation.php
│   │   │       │   ├── BinaryRepresentation.php
│   │   │       │   ├── CallableDefinitionRepresentation.php
│   │   │       │   ├── ColorRepresentation.php
│   │   │       │   ├── ContainerRepresentation.php
│   │   │       │   ├── MicrotimeRepresentation.php
│   │   │       │   ├── ProfileRepresentation.php
│   │   │       │   ├── RepresentationInterface.php
│   │   │       │   ├── SourceRepresentation.php
│   │   │       │   ├── SplFileInfoRepresentation.php
│   │   │       │   ├── StringRepresentation.php
│   │   │       │   ├── TableRepresentation.php
│   │   │       │   └── ValueRepresentation.php
│   │   │       ├── ResourceValue.php
│   │   │       ├── SimpleXMLElementValue.php
│   │   │       ├── SplFileInfoValue.php
│   │   │       ├── StreamValue.php
│   │   │       ├── StringValue.php
│   │   │       ├── ThrowableValue.php
│   │   │       ├── TraceFrameValue.php
│   │   │       ├── TraceValue.php
│   │   │       ├── UninitializedValue.php
│   │   │       ├── UnknownValue.php
│   │   │       └── VirtualValue.php
│   │   └── PSR
│   │       └── Log
│   │           ├── AbstractLogger.php
│   │           ├── InvalidArgumentException.php
│   │           ├── LICENSE
│   │           ├── LoggerAwareInterface.php
│   │           ├── LoggerAwareTrait.php
│   │           ├── LoggerInterface.php
│   │           ├── LoggerTrait.php
│   │           ├── LogLevel.php
│   │           └── NullLogger.php
│   ├── Throttle
│   │   ├── ThrottlerInterface.php
│   │   └── Throttler.php
│   ├── Traits
│   │   ├── ConditionalTrait.php
│   │   └── PropertiesTrait.php
│   ├── Typography
│   │   └── Typography.php
│   ├── util_bootstrap.php
│   ├── Validation
│   │   ├── CreditCardRules.php
│   │   ├── DotArrayFilter.php
│   │   ├── Exceptions
│   │   │   └── ValidationException.php
│   │   ├── FileRules.php
│   │   ├── FormatRules.php
│   │   ├── Rules.php
│   │   ├── StrictRules
│   │   │   ├── CreditCardRules.php
│   │   │   ├── FileRules.php
│   │   │   ├── FormatRules.php
│   │   │   └── Rules.php
│   │   ├── ValidationInterface.php
│   │   ├── Validation.php
│   │   └── Views
│   │       ├── list.php
│   │       └── single.php
│   └── View
│       ├── Cell.php
│       ├── Cells
│       │   └── Cell.php
│       ├── Exceptions
│       │   └── ViewException.php
│       ├── Filters.php
│       ├── Parser.php
│       ├── Plugins.php
│       ├── RendererInterface.php
│       ├── Table.php
│       ├── ViewDecoratorInterface.php
│       ├── ViewDecoratorTrait.php
│       └── View.php
├── template-conges-rh-ci4.html
├── tests
│   ├── database
│   │   └── ExampleDatabaseTest.php
│   ├── index.html
│   ├── session
│   │   └── ExampleSessionTest.php
│   ├── _support
│   │   ├── Database
│   │   │   ├── Migrations
│   │   │   │   └── 2020-02-22-222222_example_migration.php
│   │   │   └── Seeds
│   │   │       └── ExampleSeeder.php
│   │   ├── Libraries
│   │   │   └── ConfigReader.php
│   │   └── Models
│   │       └── ExampleModel.php
│   └── unit
│       └── HealthTest.php
├── todo.md
└── writable
    ├── cache
    │   └── index.html
    ├── database
    │   └── conge.db
    ├── debugbar
    │   └── index.html
    ├── index.html
    ├── logs
    │   ├── debug_auth_post.txt
    │   ├── index.html
    │   ├── log-2026-04-20.log
    │   └── log-2026-05-13.log
    ├── session
    │   ├── ci_session02807a7bcac397316f6f14d8b4b84466
    │   ├── ci_session06992148e207a0f33c65058f5f0a823d
    │   ├── ci_session0a942a6098d957947436dcaec48d78aa
    │   ├── ci_session0e72d7c25412c05d29593dfd137c6ec6
    │   ├── ci_session171c3a5109869d2a13ad1563d9554473
    │   ├── ci_session18bdc7ce2852d3ee8217af64258dfe9d
    │   ├── ci_session1e0376e2967e6089bd9702e28c45878a
    │   ├── ci_session2338c16a5ed373f9b4eea49434e16fb1
    │   ├── ci_session23df54e852f9a210bcea52545084d72a
    │   ├── ci_session288ae2ef63b66ad0cb410b285b2b3ae1
    │   ├── ci_session2a6080f267ffbf435ad64df10aca51a6
    │   ├── ci_session2bfbc98f735e3bd226506b1bf707fbd0
    │   ├── ci_session2ef7d159c95079a814bb2d4de9419927
    │   ├── ci_session2f49c8778008084bd2239b45987bf3b2
    │   ├── ci_session357d406db08ec5d46d8c2a89dedcfdf3
    │   ├── ci_session39084dabdabd2fe67756ed6961e217c0
    │   ├── ci_session3f5a817f8017d34788f1506e5ba02cfa
    │   ├── ci_session423b943b01aeb1e50279ce8f043b2482
    │   ├── ci_session42bf0620b3679f0ab4c21ec7854367ce
    │   ├── ci_session4c4ed70c7e0ded6d6647c709e723f05e
    │   ├── ci_session4f60896837be675eca524a9500bb8c57
    │   ├── ci_session5176f673b2f4184f33b1ece55dd51e65
    │   ├── ci_session5317f36643e4b7ae7bec2655f30d8dd2
    │   ├── ci_session593e23fc9145fb35678b1d066c1a8c5f
    │   ├── ci_session611319f4763e2020540eea8d50beaaa0
    │   ├── ci_session61a37c6b29cbbf512a4c40fb78d09d51
    │   ├── ci_session693eb378c372cf6f2547726176526b2c
    │   ├── ci_session7518cd5c9c09a573a866778eddbb4642
    │   ├── ci_session766800763e8027d3e9da5c9a93b0c65f
    │   ├── ci_session7bb728fe9768a30dbeadda373356e852
    │   ├── ci_session7bbdc03ffe66371d326f20d398b40280
    │   ├── ci_session7f8b1f35b245134ed838a3120027749e
    │   ├── ci_session85743472af60cb6c3b9490e8bbd0b0a2
    │   ├── ci_session8933895738dc4adfe776877eed9c9204
    │   ├── ci_session8bbbfc6bd08ca536aaf1e453b20025ab
    │   ├── ci_session8e587cb0b9cbd3440022b92a11306521
    │   ├── ci_session9a4c6a14737929e77b989ce284d311d3
    │   ├── ci_session9a4fd8165739997bed9f5aa8974ff831
    │   ├── ci_sessiona12cbc8424ba82a94f174efb4bb64acf
    │   ├── ci_sessiona45ea206d578d358b1b265829b24a9a3
    │   ├── ci_sessiona7e92db5f66027aaba5825e3a80e8404
    │   ├── ci_sessiona8573e06c38d21d89bd04eac846d21cd
    │   ├── ci_sessionaa0280e5e3fbbc639c5046c6b94904b7
    │   ├── ci_sessionaa803f18ffb47668232efaf19287faff
    │   ├── ci_sessionb01cbd2e53ea279740aeab91d7665c71
    │   ├── ci_sessionb0a6e678f49523403a532db29070e421
    │   ├── ci_sessionb26edfabad05254cddcd2c824712c0c6
    │   ├── ci_sessionb39eb9c6c0086e631715e3d90ecbedfe
    │   ├── ci_sessionb62ea310119694f3c2828c36dd1db0ef
    │   ├── ci_sessionb947e951f77a3856d7525d4f54870f45
    │   ├── ci_sessionb948c36e0757919aec9134404925843e
    │   ├── ci_sessionbbbfc46f7c9b3400fbdd4ac7912c626e
    │   ├── ci_sessionbbd276bf3760f1a83e34160083890d8b
    │   ├── ci_sessionbd5ad623f4917a1081d3712d6133b461
    │   ├── ci_sessionbe0b47fc83637ffd1d34a71118bcdf34
    │   ├── ci_sessionc02b5f7941792a1485939a752d08e7e1
    │   ├── ci_sessionc069d67fa4619f07cd4279cbf17e7015
    │   ├── ci_sessionc1b8eb1259e50475bbcd373e29fdc932
    │   ├── ci_sessionc831873e9abc50389912e3bbd541ac75
    │   ├── ci_sessionc8e473039f5b030f1180c40647d2906c
    │   ├── ci_sessioncff8573bdc50c145a1105d7f633262fd
    │   ├── ci_sessiond2f33104dce8f92b13992c57567efd89
    │   ├── ci_sessiond6aced9288aba877b95d87c3f80f589d
    │   ├── ci_sessiond84b97b75134a5a4ab6202eca1530687
    │   ├── ci_sessiond86249ded88423de33d106157faaccd4
    │   ├── ci_sessiond9e807d77859d9441abc5eb5e98f00f2
    │   ├── ci_sessionda4073cb46bb346c36a35a7baca0597c
    │   ├── ci_sessionda70ea2bf3b4b2c916827e656b171c72
    │   ├── ci_sessiondcb140871ba671bc49f7edccc3eb6a65
    │   ├── ci_sessione893816a4adf7643d93ea6c750e42615
    │   ├── ci_sessionec197f26fc0ad34340422c8966c42cbe
    │   ├── ci_sessionf320c75133e0f492bb5ff5d51899c2ec
    │   ├── ci_sessionfa5fad2ee4eb791bd37d9c996ee3eebf
    │   ├── ci_sessionfcd3209bfa360e957724d2a2a1291da5
    │   ├── ci_sessionfed5b4f3642484c6f18f335e21c896e7
    │   └── index.html
    └── uploads
        └── index.html

165 directories, 850 files
herimino@Terraque:~/S4/MrRojo/Tech_Mada$ 

