<?php declare(strict_types=1);

/*
 * Layer dependencies must always point to the Domain:
 *
 *   Infrastructure ──→ Domain ←── Application
 *
 * Implementations from Infrastructure are only wired to Domain interfaces in config/container.php.
 */

arch('domain does not depend on application nor infrastructure')
    ->expect('Domain')
    ->not->toUse(['Application', 'Infrastructure']);

arch('application does not depend on infrastructure')
    ->expect('Application')
    ->not->toUse('Infrastructure');
