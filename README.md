<img src="https://raw.githubusercontent.com/apie-lib/apie-lib-monorepo/main/docs/apie-logo.svg" width="100px" align="left" />
<h1>dataset</h1>






 [![Latest Stable Version](https://poser.pugx.org/apie/dataset/v)](https://packagist.org/packages/apie/dataset) [![Total Downloads](https://poser.pugx.org/apie/dataset/downloads)](https://packagist.org/packages/apie/dataset) [![Latest Unstable Version](https://poser.pugx.org/apie/dataset/v/unstable)](https://packagist.org/packages/apie/dataset) [![License](https://poser.pugx.org/apie/dataset/license)](https://packagist.org/packages/apie/dataset) [![PHP Composer](https://apie-lib.github.io/projectCoverage/coverage-dataset.svg)](https://apie-lib.github.io/projectCoverage/dataset/index.html)  

[![PHP Composer](https://github.com/apie-lib/dataset/actions/workflows/php.yml/badge.svg?event=push)](https://github.com/apie-lib/dataset/actions/workflows/php.yml)

This package is part of the [Apie](https://github.com/apie-lib) library.
The code is maintained in a monorepo, so PR's need to be sent to the [monorepo](https://github.com/apie-lib/apie-lib-monorepo/pulls)

## Documentation
`Dataset` is a self-contained test/fixture entity (`Apie\Dataset\Dataset`, identified by
`Apie\Dataset\DatasetIdentifier`) used internally by the Apie test suite. Constructing it via
`Dataset::createRandom()` spins up a temporary SQLite file (`apie/core`'s `SqliteFile`) and a
full `Apie\DoctrineEntityDatalayer\DoctrineEntityDatalayer` (built with
`apie/doctrine-entity-datalayer` and `apie/doctrine-entity-converter`) for a single, throwaway
bounded context containing the `Apie\Dataset\DummyApp\Resources\DummyUser` fixture entity.

### Standalone usage
Install it with:
```bash
composer require apie/dataset
```

This package is not intended to be depended on by application code; it exists so other
Apie packages and tests can exercise the complete entity/datalayer/indexing stack against a
real (temporary) database without needing to configure Doctrine or a persistent bounded
context themselves:

```php
<?php
use Apie\Dataset\Dataset;

$dataset = Dataset::createRandom();
$datalayer = $dataset->toDatalayer();
// $datalayer is a fully working DoctrineEntityDatalayer backed by a temporary SQLite file
```
