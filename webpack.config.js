const Encore = require('@symfony/webpack-encore');

Encore
    // Stel de directory in waar de gegenereerde assets worden opgeslagen
    .setOutputPath('public/build/')
    // Stel het openbare pad in waarmee de server toegang heeft tot deze bestanden
    .setPublicPath('/build')
    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())

    // Activeer versienummers voor caching in productie
    .enableVersioning(Encore.isProduction())

    // Definieer de assets van het project
    .addEntry('app', './assets/js/app.js')
    .addStyleEntry('app_css', './assets/css/app.scss')

    // Activeer Sass/SCSS
    .enableSassLoader()

    // Zorg ervoor dat jQuery correct wordt geladen als globale variabele
    .autoProvidejQuery()
    .enableSingleRuntimeChunk()
;

module.exports = Encore.getWebpackConfig();
