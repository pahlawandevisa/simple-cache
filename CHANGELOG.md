# Changelog 3.2.2 (2018-12-21)

- fix APC(u) detection for CLI usage


# Changelog 3.2.1 (2018-12-20)

- use phpcs fixer 


# Changelog 3.2.0 (2018-12-03)

- "AdapterOpCache" -> use "opcache_compile_file()"
- add "AdapterFileSimple" + tests
- "Cache" -> add the possibility to disable the cache behavior via constructor() -> "disableCacheGetParameter, useCheckForAdminSession, useCheckForServerIpIsClientIp, useCheckForDev"


# Changelog 3.1.1 (2018-01-07)

- "AdapterApcu" -> fixed php-warning from "apcu_clear_cache()"


# Changelog 3.1.0 (2018-01-07)

- "AdapterOpCache" -> added
- "AdapterFile" -> remove duplicate file-get code


# Changelog 3.0.2 (2017-12-14)

- "AdapterFile" -> fix php warning


# Chabgelog 3.0.1 (2017-12-01)

- fix return from the "CacheChain"-class
- update phpunit-config


# Changelog 3.0.0 (2017-11-25)

- drop support for PHP < 7.0
- use "strict_types"


# Changelog 2.4.0 (2017-10-06)

- add support for PSR-16