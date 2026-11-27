<?php declare(strict_types=1);

/*
 * Layer dependencies must always point to the Domain:
 *
 *   Infrastructure ──→ Domain ←── Application
 *
 * Implementations from Infrastructure are only wired to Domain interfaces in config/container.php, and the Domain does
 * not depend on the framework.
 *
 * The database is only accessed through its stored procedures (debuss-a/piccolo-database), from the Infrastructure:
 * never with PDO, and never from the Application nor the Domain.
 */

arch('domain does not depend on application, infrastructure, the framework nor the database')
    ->expect('Domain')
    ->not->toUse(['Application', 'Infrastructure', 'Piccolo', 'StoredProcedures', 'PDO']);

arch('application does not depend on infrastructure nor the database')
    ->expect('Application')
    ->not->toUse(['Infrastructure', 'Piccolo\Database', 'StoredProcedures', 'PDO']);

arch('the database is only accessed through its stored procedures, never with PDO')
    ->expect('Infrastructure')
    ->not->toUse('PDO');
