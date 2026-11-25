<?php declare(strict_types=1);

/*
 * Layer dependencies must always point to the Domain:
 *
 *   Infrastructure ──→ Domain ←── Application
 *
 * Implementations from Infrastructure are only wired to Domain interfaces in config/container.php, and the Domain does
 * not depend on the framework.
 */

arch('domain does not depend on application, infrastructure nor the framework')
    ->expect('Domain')
    ->not->toUse(['Application', 'Infrastructure', 'Piccolo']);

arch('application does not depend on infrastructure')
    ->expect('Application')
    ->not->toUse('Infrastructure');
