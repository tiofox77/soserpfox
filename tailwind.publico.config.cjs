/**
 * O TAILWIND DAS PÁGINAS PÚBLICAS, COMPILADO — e não montado no browser.
 *
 * A página inicial carregava o `cdn.tailwindcss.com` e as outras públicas o
 * `/vendor/js/tailwind.js` (o mesmo ficheiro, 407 KB): um compilador de CSS que
 * corre no telemóvel de cada visita, lê o DOM e só então pinta. Pesa no LCP e no
 * INP que o Google mede, e o próprio CDN avisa na consola que não é para
 * produção.
 *
 * É a MESMA versão (3.4.17) e o mesmo tema por omissão que o runtime usava: o
 * CSS sai igual, só que feito antes. `npm run css:publico` volta a gerá-lo; o
 * ensaio `CssPublicoCompiladoTest` falha se uma vista pública voltar a carregar
 * o compilador ou se o CSS ficar para trás.
 *
 * O Tailwind 4 do `package.json` (via @tailwindcss/vite) não serve aqui: mudou
 * nomes e valores por omissão (sombras, raios, anéis, bordas) e as páginas
 * mudavam de aspecto.
 */
module.exports = {
    content: [
        './resources/views/landing/**/*.blade.php',
        './resources/views/modules/**/*.blade.php',
        './resources/views/components/modules-layout.blade.php',
        './resources/views/legal/**/*.blade.php',
        './resources/views/layouts/legal.blade.php',
        './resources/views/react/publico.blade.php',
        './resources/views/partials/**/*.blade.php',
        './app/Http/Controllers/LandingController.php',
        './app/Http/Controllers/ModulePagesController.php',
        // Os ecrãs públicos em React (carta, marcação, reservas) e as peças que usam.
        './resources/js/**/*.{ts,tsx}',
    ],
    theme: { extend: {} },
    plugins: [],
};
