<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= $this->e($title) ?> - Piccolo</title>
    <!-- Fonts from Google Fonts: remove these 3 lines to fall back to the system fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wdth,wght@12..96,75..100,400..800&family=Newsreader:ital,opsz,wght@1,6..72,400&display=swap" rel="stylesheet">
    <style>
        /*
         * Piccolo is named after the smallest flute of the orchestra: nickel silver keys on a grenadilla wood body.
         * The palette follows it, with the PHP violet as the only accent.
         */
        :root {
            --paper: #f2f3f5;
            --ink: #2b1e1a;
            --muted: #6e6460;
            --staff: #b9bec6;
            --accent: #4f5394;

            --sans: "Bricolage Grotesque", system-ui, sans-serif;
            --serif: "Newsreader", Georgia, serif;
            --mono: ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace;

            --gutter: clamp(1.25rem, 5vw, 4rem);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --paper: #1f1715;
                --ink: #edeef0;
                --muted: #a79e9a;
                --staff: #4b403c;
                --accent: #a6aae0;
            }
        }

        *, *::before, *::after {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: var(--paper);
            color: var(--ink);
            font: 400 1.0625rem/1.6 var(--sans);
            -webkit-font-smoothing: antialiased;
        }

        a {
            color: var(--accent);
            text-decoration-thickness: 1px;
            text-underline-offset: .2em;
        }

        a:hover {
            text-decoration-thickness: 2px;
        }

        :focus-visible {
            outline: 2px solid var(--accent);
            outline-offset: 3px;
            border-radius: 2px;
        }

        code {
            font: .9em var(--mono);
        }

        .site-header,
        .site-main,
        .site-footer {
            width: 100%;
            max-width: 72rem;
            margin: 0 auto;
            padding-inline: var(--gutter);
        }

        .site-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            padding-block: 1.75rem;
        }

        .wordmark {
            color: var(--ink);
            font-weight: 700;
            font-size: 1.25rem;
            letter-spacing: -.02em;
            text-decoration: none;
        }

        .site-main {
            flex: 1;
        }

        .site-footer {
            padding-block: 3rem 2rem;
            color: var(--muted);
            font-size: .9375rem;
        }
    </style>
    <?= $this->section('stylesheets') ?>
</head>
<body>
<header class="site-header">
    <a class="wordmark" href="/">piccolo</a>
    <a href="https://github.com/debuss/piccolo">GitHub</a>
</header>
<main class="site-main">
    <?= $this->section('content') ?>
</main>
<footer class="site-footer">
    <?php if ($this->section('footer')): ?>
        <?= $this->section('footer') ?>
    <?php else: ?>
        <p>Piccolo, a PSR-15 application skeleton.</p>
    <?php endif ?>
</footer>
<?= $this->section('javascript') ?>
</body>
</html>
