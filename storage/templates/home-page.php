<?php
/**
 * Welcome page: this template and HomeHandler can be deleted once you no longer need them.
 *
 * @var Mezzio\Plates\PlatesRenderer $this
 * @var string $php
 * @var string $environment
 * @var int|null $duration Time spent until the template rendering, in milliseconds
 */

$this->layout('app::layout', ['title' => 'It works']);

// The path of a `GET /` request through config/pipeline.php, scored: each middleware it goes through is a note
// (climbing to the handler), the `/api` group it skips is a rest.
$score = [
    ['lyric' => 'errors', 'name' => 'ErrorHandler', 'y' => 140],
    ['lyric' => 'original URI', 'name' => 'OriginalMessages', 'y' => 130],
    ['lyric' => '/api', 'name' => '/api middleware group, skipped', 'rest' => true],
    ['lyric' => 'routing', 'name' => 'RouteMiddleware', 'y' => 120],
    ['lyric' => 'HEAD', 'name' => 'ImplicitHeadMiddleware', 'y' => 110],
    ['lyric' => 'OPTIONS', 'name' => 'ImplicitOptionsMiddleware', 'y' => 100],
    ['lyric' => '405', 'name' => 'MethodNotAllowedMiddleware', 'y' => 90],
    ['lyric' => 'dispatch', 'name' => 'DispatchMiddleware', 'y' => 70],
    ['lyric' => 'HomeHandler', 'name' => 'HomeHandler', 'y' => 40, 'final' => true],
];

$tempo = match (true) {
    $duration === null => null,
    $duration < 50 => 'Presto',
    $duration < 200 => 'Allegro',
    $duration < 1000 => 'Andante',
    default => 'Largo',
};
?>

<?php $this->start('stylesheets') ?>
<style>
    .hero {
        padding-block: clamp(2rem, 8vh, 6rem) 0;
    }

    .hero h1 {
        margin: 0;
        font-size: clamp(4.5rem, 15vw, 11rem);
        font-weight: 800;
        font-stretch: 75%;
        line-height: .85;
        letter-spacing: -.01em;
    }

    .hero .lead {
        max-width: 34rem;
        margin: 1.75rem 0 0;
        font-size: clamp(1.125rem, 2vw, 1.3125rem);
        line-height: 1.5;
    }

    .hero .try {
        margin: .75rem 0 0;
        color: var(--muted);
    }

    /* The score: the one expressive element of the page */
    .score {
        margin: clamp(2.5rem, 7vh, 4.5rem) 0 0;
    }

    .score svg {
        display: block;
        width: 100%;
        height: auto;
        overflow: visible;
    }

    /* Straight lines keep their thickness whatever the score scale (not the slur, whose drawing relies on pathLength) */
    .score line {
        vector-effect: non-scaling-stroke;
    }

    .score .staff line,
    .score .ledger {
        stroke: var(--staff);
        stroke-width: 1.25;
    }

    .score .barline {
        fill: var(--ink);
    }

    .score .head {
        fill: var(--ink);
    }

    .score .stem {
        stroke: var(--ink);
        stroke-width: 1.5;
    }

    .score .final .head {
        fill: var(--paper);
        stroke: var(--accent);
        stroke-width: 3.5;
    }

    .score .fermata {
        fill: none;
        stroke: var(--accent);
        stroke-width: 2.5;
        stroke-linecap: round;
    }

    .score .fermata-dot {
        fill: var(--accent);
    }

    .score .rest {
        fill: var(--muted);
    }

    .score .slur {
        fill: none;
        stroke: var(--accent);
        stroke-width: 1.75;
        stroke-linecap: round;
    }

    .score text {
        font-family: var(--serif);
        font-style: italic;
        fill: var(--muted);
    }

    .score .lyric {
        font-size: 15px;
        text-anchor: middle;
    }

    .score .final .lyric {
        fill: var(--accent);
    }

    .score .tempo {
        font-size: 17px;
        fill: var(--ink);
    }

    .score figcaption {
        max-width: 38rem;
        margin-top: 1rem;
        color: var(--muted);
        font-size: .9375rem;
    }

    /* Smaller screens: the whole score still fits, lyrics alternate on two rows to stay readable */
    @media (max-width: 52rem) {
        .score .slur {
            stroke-width: 3;
        }

        .score .fermata {
            stroke-width: 4;
        }

        .score .lyric {
            font-size: 24px;
        }

        .score .low .lyric {
            transform: translateY(32px);
        }

        .score .tempo {
            font-size: 26px;
        }

        .score figcaption {
            margin-top: 2.25rem;
        }
    }

    @media (max-width: 30rem) {
        .score .lyric {
            font-size: 34px;
        }

        .score .low .lyric {
            transform: translateY(40px);
        }

        .score .tempo {
            font-size: 34px;
        }
    }

    /* One orchestrated moment: the request plays through the pipeline, then holds on the handler */
    @media (prefers-reduced-motion: no-preference) {
        .score .note {
            animation: note-in .45s cubic-bezier(.2, .7, .3, 1.3) both;
            animation-delay: calc(var(--i) * 110ms + 250ms);
            transform-box: fill-box;
            transform-origin: center;
        }

        .score .slur {
            stroke-dasharray: 1;
            animation: slur-in 1.1s ease-in-out .3s both;
        }

        .score .fermata,
        .score .fermata-dot {
            animation: fade-in .5s ease-out 1.5s both;
        }
    }

    @keyframes note-in {
        from {
            opacity: 0;
            transform: translateY(14px);
        }
    }

    @keyframes slur-in {
        from {
            stroke-dashoffset: 1;
        }
        to {
            stroke-dashoffset: 0;
        }
    }

    @keyframes fade-in {
        from {
            opacity: 0;
        }
    }

    .next {
        display: grid;
        gap: 1.5rem 4rem;
        margin-top: clamp(4rem, 12vh, 7rem);
    }

    @media (min-width: 52rem) {
        .next {
            grid-template-columns: 15rem 1fr;
        }
    }

    .next h2 {
        margin: 0;
        font-size: 1.75rem;
        font-weight: 700;
        font-stretch: 85%;
        letter-spacing: -.02em;
        line-height: 1.15;
    }

    .next dl {
        display: grid;
        gap: 1.25rem;
        margin: 0;
    }

    .next dt code {
        color: var(--ink);
        font-size: .9375rem;
    }

    .next dd {
        margin: .15rem 0 0;
        color: var(--muted);
    }

    .next .removal {
        margin: 1rem 0 0;
        padding-top: 1.25rem;
        border-top: 1px solid var(--staff);
        color: var(--muted);
        font-size: .9375rem;
    }
</style>
<?php $this->stop() ?>

<section class="hero">
    <h1>It works.</h1>
    <p class="lead">
        This page is a response from <code>HomeHandler</code>. Here is the path your request took through the
        middleware pipeline to get there.
    </p>
    <p class="try">
        Try <a href="/api/ping"><code>GET /api/ping</code></a>, or open the <a href="/api/v1/redoc">API reference</a>.
    </p>
</section>

<figure class="score">
    <svg viewBox="0 0 1000 210" role="img" aria-labelledby="score-title score-desc">
        <title id="score-title">Your request through the middleware pipeline</title>
        <desc id="score-desc">
            <?php foreach ($score as $step): ?><?= $this->e($step['name']) ?>, <?php endforeach ?>where the request is handled.
        </desc>

        <?php if ($tempo !== null): ?>
            <text class="tempo" x="0" y="22"><?= $this->e($tempo) ?>, rendered in <?= $this->e($duration) ?> ms</text>
        <?php endif ?>

        <g class="staff">
            <?php foreach ([60, 80, 100, 120, 140] as $y): ?>
                <line x1="0" x2="1000" y1="<?= $y ?>" y2="<?= $y ?>"/>
            <?php endforeach ?>
        </g>

        <!-- Final double barline: the response is sent -->
        <rect class="barline" x="986" y="60" width="2" height="80"/>
        <rect class="barline" x="993" y="60" width="7" height="80"/>

        <path class="slur" pathLength="1" d="M 77 76 C 310 18, 610 8, 814 50"/>

        <?php foreach ($score as $i => $step): ?>
            <?php $x = 85 + $i * 103 ?>
            <g class="note<?= isset($step['final']) ? ' final' : '' ?><?= $i % 2 ? ' low' : '' ?>" style="--i: <?= $i ?>">
                <title><?= $this->e($step['name']) ?></title>
                <?php if (isset($step['rest'])): ?>
                    <rect class="rest" x="<?= $x - 11 ?>" y="80" width="22" height="9"/>
                <?php else: ?>
                    <?php $y = $step['y'] ?>
                    <?php if ($y < 60): ?>
                        <line class="ledger" x1="<?= $x - 17 ?>" x2="<?= $x + 17 ?>" y1="40" y2="40"/>
                    <?php endif ?>
                    <?php if (isset($step['final'])): ?>
                        <!-- Whole note under a fermata: the request is held, and handled, here -->
                        <ellipse class="head" cx="<?= $x ?>" cy="<?= $y ?>" rx="12" ry="8"
                                 transform="rotate(-25 <?= $x ?> <?= $y ?>)"/>
                        <path class="fermata" d="M <?= $x - 14 ?> 16 A 14 13 0 0 1 <?= $x + 14 ?> 16"/>
                        <circle class="fermata-dot" cx="<?= $x ?>" cy="12" r="2.6"/>
                    <?php else: ?>
                        <ellipse class="head" cx="<?= $x ?>" cy="<?= $y ?>" rx="9.5" ry="7"
                                 transform="rotate(-22 <?= $x ?> <?= $y ?>)"/>
                        <?php if ($y > 100): ?>
                            <line class="stem" x1="<?= $x + 8.5 ?>" x2="<?= $x + 8.5 ?>" y1="<?= $y - 2 ?>" y2="<?= $y - 50 ?>"/>
                        <?php else: ?>
                            <line class="stem" x1="<?= $x - 8.5 ?>" x2="<?= $x - 8.5 ?>" y1="<?= $y + 2 ?>" y2="<?= $y + 50 ?>"/>
                        <?php endif ?>
                    <?php endif ?>
                <?php endif ?>
                <text class="lyric" x="<?= $x ?>" y="196"><?= $this->e($step['lyric']) ?></text>
            </g>
        <?php endforeach ?>
    </svg>
    <figcaption>
        Each note is a middleware from <code>config/pipeline.php</code>. The rest is the <code>/api</code> group, skipped
        because this path is not under <code>/api</code>.
    </figcaption>
</figure>

<section class="next">
    <h2>Make it yours</h2>
    <div>
        <dl>
            <div>
                <dt><code>src/Application/Handler/</code></dt>
                <dd>Add a handler and give it a route with <code>#[Get('/path')]</code>.</dd>
            </div>
            <div>
                <dt><code>src/Domain/</code></dt>
                <dd>Keep your business models, interfaces and exceptions here, free of HTTP.</dd>
            </div>
            <div>
                <dt><code>config/container.php</code></dt>
                <dd>Bind your Domain interfaces to their implementations.</dd>
            </div>
            <div>
                <dt><code>config/pipeline.php</code></dt>
                <dd>Add middleware to every request, or only to a path.</dd>
            </div>
            <div>
                <dt><code>.env</code></dt>
                <dd>Set the environment, the logs and the timezone.</dd>
            </div>
        </dl>
        <p class="removal">
            Done with this page? Delete <code>src/Application/Handler/HomeHandler.php</code> and
            <code>storage/templates/home-page.php</code>.
        </p>
    </div>
</section>

<?php $this->start('footer') ?>
<p>Running PHP <?= $this->e($php) ?> in <?= $this->e($environment) ?>.</p>
<?php $this->stop() ?>
