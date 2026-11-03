<?php
declare(strict_types=1);

// Shared error page for the ErrorDocument directives in .htaccess.
$errors = [
    400 => ['bi-exclamation-octagon',
        'nl' => ['Ongeldig verzoek', 'Het verzoek kon niet worden verwerkt. Controleer de link en probeer het opnieuw.'],
        'en' => ['Bad request', 'The request could not be processed. Check the link and try again.']],
    401 => ['bi-person-lock',
        'nl' => ['Inloggen vereist', 'Voor deze pagina moet je ingelogd zijn.'],
        'en' => ['Login required', 'You need to be logged in to view this page.']],
    403 => ['bi-shield-lock',
        'nl' => ['Geen toegang', 'Je hebt geen toegang tot deze pagina.'],
        'en' => ['Access denied', 'You do not have access to this page.']],
    404 => ['bi-cloud-slash',
        'nl' => ['Pagina niet gevonden', 'Deze pagina bestaat niet (meer). Misschien is de link verouderd of zit er een typfout in.'],
        'en' => ['Page not found', 'This page does not exist (anymore). The link may be outdated or contain a typo.']],
    405 => ['bi-slash-circle',
        'nl' => ['Methode niet toegestaan', 'Deze actie is op deze pagina niet toegestaan.'],
        'en' => ['Method not allowed', 'This action is not allowed on this page.']],
    408 => ['bi-hourglass-split',
        'nl' => ['Time-out', 'Het verzoek duurde te lang. Probeer het opnieuw.'],
        'en' => ['Request timeout', 'The request took too long. Please try again.']],
    429 => ['bi-speedometer',
        'nl' => ['Te veel verzoeken', 'Er zijn te veel verzoeken in korte tijd gedaan. Wacht even en probeer het opnieuw.'],
        'en' => ['Too many requests', 'Too many requests were made in a short time. Please wait a moment and try again.']],
    500 => ['bi-cloud-lightning-rain',
        'nl' => ['Serverfout', 'Er ging iets mis op de server. Probeer het later opnieuw.'],
        'en' => ['Server error', 'Something went wrong on the server. Please try again later.']],
    502 => ['bi-cloud-lightning-rain',
        'nl' => ['Slechte gateway', 'De server kreeg geen geldig antwoord. Probeer het later opnieuw.'],
        'en' => ['Bad gateway', 'The server received an invalid response. Please try again later.']],
    503 => ['bi-tools',
        'nl' => ['Tijdelijk niet beschikbaar', 'De site is even niet beschikbaar, bijvoorbeeld door onderhoud. Probeer het later opnieuw.'],
        'en' => ['Temporarily unavailable', 'The site is temporarily unavailable, for example due to maintenance. Please try again later.']],
    504 => ['bi-hourglass-split',
        'nl' => ['Gateway time-out', 'De server reageerde niet op tijd. Probeer het later opnieuw.'],
        'en' => ['Gateway timeout', 'The server did not respond in time. Please try again later.']],
];

$ui = [
    'nl' => ['tagline' => 'Historische weerdata van Nederland', 'home' => 'Naar daggegevens', 'yearly' => 'Jaarstatistieken', 'siteName' => 'KNMI Daggegevens'],
    'en' => ['tagline' => 'Historical weather data for the Netherlands', 'home' => 'Go to daily data', 'yearly' => 'Yearly statistics', 'siteName' => 'KNMI Daily Data'],
];

// Apache passes the original status as REDIRECT_STATUS; Nginx passes it as ?code=
// (its FastCGI setup always sets REDIRECT_STATUS to 200, so that one is ignored).
$redirectStatus = (int)($_SERVER['REDIRECT_STATUS'] ?? 0);
$code = $redirectStatus >= 400 ? $redirectStatus : (int)($_GET['code'] ?? 404);
if (!isset($errors[$code])) {
    $code = 404;
}
$icon = $errors[$code][0];
// Server-side default matches the site (Dutch unless ?lang=en); the script below
// switches to the language the visitor picked on the site.
$lang = ($_GET['lang'] ?? '') === 'en' ? 'en' : 'nl';
[$title, $message] = $errors[$code][$lang];
http_response_code($code);

$translations = [];
foreach (['nl', 'en'] as $language) {
    $translations[$language] = $ui[$language] + [
        'title' => $errors[$code][$language][0],
        'message' => $errors[$code][$language][1],
        'docTitle' => $code . ' - ' . $errors[$code][$language][0] . ' - ' . $ui[$language]['siteName'],
    ];
}

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#0a66c2">
    <title><?php echo e($translations[$lang]['docTitle']); ?></title>
    <link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/icons/favicon-16x16.png">
    <link rel="shortcut icon" href="/icons/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/icons/apple-touch-icon.png">
    <script>
        (function() {
            try {
                var savedTheme = localStorage.getItem('knmi-theme');
                var preferredTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                document.documentElement.dataset.theme = savedTheme || preferredTheme;
                var savedLanguage = localStorage.getItem('knmi-language');
                if (savedLanguage === 'nl' || savedLanguage === 'en') {
                    document.documentElement.lang = savedLanguage;
                }
            } catch (e) {}
        })();
    </script>
    <link href="/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/modern-style.css">
    <style>
        .error-card .card-body { padding: 3rem 1.5rem; text-align: center; }
        .error-icon { font-size: 3.5rem; color: var(--primary-blue); line-height: 1; }
        .error-code {
            font-size: clamp(4rem, 14vw, 7rem);
            font-weight: 700;
            line-height: 1;
            margin: 1rem 0 .5rem;
            background: var(--gradient-secondary);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .error-title { color: var(--text-main); font-size: 1.5rem; margin-bottom: .75rem; }
        .error-message { color: var(--text-muted); max-width: 32rem; margin: 0 auto 2rem; }
    </style>
</head>
<body>
    <main class="container-fluid main-container" id="mainContent" tabindex="-1">
        <div class="row mb-4">
            <div class="col-12">
                <div class="weather-card">
                    <div class="card-header">
                        <div class="app-header">
                            <div class="app-title">
                                <div class="mb-0 app-name">
                                    <i class="bi bi-cloud-sun me-2"></i>
                                    <span data-t="siteName"><?php echo e($translations[$lang]['siteName']); ?></span>
                                </div>
                                <p class="mb-0 mt-2" data-t="tagline"><?php echo e($translations[$lang]['tagline']); ?></p>
                            </div>
                            <div class="preference-controls">
                                <div class="btn-group btn-group-sm" role="group" aria-label="Language">
                                    <button type="button" class="btn btn-outline-light" data-language="nl">NL</button>
                                    <button type="button" class="btn btn-outline-light" data-language="en">EN</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <div class="weather-card error-card">
                    <div class="card-body">
                        <i class="bi <?php echo e($icon); ?> error-icon" aria-hidden="true"></i>
                        <div class="error-code"><?php echo $code; ?></div>
                        <h1 class="error-title" data-t="title"><?php echo e($title); ?></h1>
                        <p class="error-message" data-t="message"><?php echo e($message); ?></p>
                        <div class="d-flex flex-wrap justify-content-center gap-2">
                            <a href="/" class="btn btn-primary"><i class="bi bi-calendar-check me-1"></i><span data-t="home"><?php echo e($translations[$lang]['home']); ?></span></a>
                            <a href="/yearly" class="btn btn-outline-primary"><i class="bi bi-bar-chart-line me-1"></i><span data-t="yearly"><?php echo e($translations[$lang]['yearly']); ?></span></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <script>
        (function() {
            var translations = <?php echo json_encode($translations, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;

            function apply(language) {
                var t = translations[language] || translations.nl;
                document.documentElement.lang = language;
                document.title = t.docTitle;
                document.querySelectorAll('[data-t]').forEach(function(el) {
                    el.textContent = t[el.getAttribute('data-t')];
                });
                document.querySelectorAll('[data-language]').forEach(function(button) {
                    var active = button.getAttribute('data-language') === language;
                    button.classList.toggle('btn-light', active);
                    button.classList.toggle('active', active);
                    button.classList.toggle('btn-outline-light', !active);
                    button.setAttribute('aria-pressed', active ? 'true' : 'false');
                });
            }

            document.querySelectorAll('[data-language]').forEach(function(button) {
                button.addEventListener('click', function() {
                    var language = button.getAttribute('data-language');
                    try { localStorage.setItem('knmi-language', language); } catch (e) {}
                    apply(language);
                });
            });

            apply(document.documentElement.lang === 'en' ? 'en' : 'nl');
        })();
    </script>
</body>
</html>
