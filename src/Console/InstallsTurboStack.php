<?php

namespace HotwiredLaravel\TurboBreeze\Console;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Finder\Finder;

trait InstallsTurboStack
{
    /**
     * Install the Turbo Breeze stack.
     *
     * @param bool $importmaps
     * @return int|null
     */
    protected function installTurboStack(bool $importmaps = true)
    {
        // Install Turbo Laravel, Stimulus Laravel, Importmap Laravel, and TailwindCSS Laravel...
        $packages = ['hotwired-laravel/turbo-laravel:2.x-dev', 'hotwired-laravel/stimulus-laravel:^0.3'] + ($importmaps ? [
            'tonysm/importmap-laravel:^1.8', 'tonysm/tailwindcss-laravel:^0.11'
        ] : []);

        if (! $this->requireComposerPackages($packages)) {
            return 1;
        }

        // Controllers
        (new Filesystem)->ensureDirectoryExists(app_path('Http'));
        (new Filesystem)->copyDirectory(__DIR__.'/../../stubs/default/app/Http', app_path('Http'));

        // Views...
        (new Filesystem)->ensureDirectoryExists(resource_path('views'));
        (new Filesystem)->copyDirectory(__DIR__.'/../../stubs/default/resources/views', resource_path('views'));

        // Views Components...
        (new Filesystem)->ensureDirectoryExists(resource_path('views/components'));
        (new Filesystem)->copyDirectory(__DIR__.'/../../stubs/default/resources/views/components', resource_path('views/components'));

        // Views Layouts...
        (new Filesystem)->ensureDirectoryExists(resource_path('views/layouts'));
        (new Filesystem)->copyDirectory(__DIR__.'/../../stubs/default/resources/views/layouts', resource_path('views/layouts'));

        // Components...
        (new Filesystem)->ensureDirectoryExists(app_path('View/Components'));
        (new Filesystem)->copyDirectory(__DIR__.'/../../stubs/default/app/View/Components', app_path('View/Components'));

        // Dark mode...
        if (! $this->option('dark')) {
            $this->removeDarkClasses((new Finder)
                ->in(resource_path('views'))
                ->name('*.blade.php')
                ->notName('welcome.blade.php')
            );
        }

        // Tests...
        if (! $this->installTests()) {
            return 1;
        }

        // Routes...
        copy(__DIR__.'/../../stubs/default/routes/web.php', base_path('routes/web.php'));
        copy(__DIR__.'/../../stubs/default/routes/auth.php', base_path('routes/auth.php'));

        // "Dashboard" Route...
        $this->replaceInFile('/home', '/dashboard', app_path('Providers/RouteServiceProvider.php'));

        if (! $importmaps) {
            copy(__DIR__.'/../../stubs/default/postcss.config.js', base_path('postcss.config.js'));
            copy(__DIR__.'/../../stubs/default/vite.config.js', base_path('vite.config.js'));
        }

        copy(__DIR__.'/../../stubs/default/tailwind.config.js', base_path('tailwind.config.js'));
        copy(__DIR__.'/../../stubs/default/resources/css/app.css', resource_path('css/app.css'));

        // Components + Pages...
        (new Filesystem)->ensureDirectoryExists(resource_path('js/controllers'));
        (new Filesystem)->ensureDirectoryExists(resource_path('js/libs'));

        (new Filesystem)->copyDirectory(__DIR__.'/../../stubs/detault/resources/js/controllers', resource_path('js/controllers'));
        (new Filesystem)->copyDirectory(__DIR__.'/../../stubs/default/resources/js/libs', resource_path('js/libs'));

        // Install Packages...
        if ($importmaps) {
            Process::forever()->run([$this->phpBinary(), 'artisan', 'importmap:install'], base_path());
            Process::forever()->run([$this->phpBinary(), 'artisan', 'tailwindcss:install'], base_path());
        }

        Process::forever()->run([$this->phpBinary(), 'artisan', 'turbo:install'], base_path());
        Process::forever()->run([$this->phpBinary(), 'artisan', 'stimulus:install'], base_path());

        if ( ! $importmaps) {
            if (file_exists(base_path('pnpm-lock.yaml'))) {
                $this->runCommands(['pnpm install', 'pnpm run build']);
            } elseif (file_exists(base_path('yarn.lock'))) {
                $this->runCommands(['yarn install', 'yarn run build']);
            } else {
                $this->runCommands(['npm install', 'npm run build']);
            }
        }

        $this->components->info('Breeze scaffolding installed successfully.');
    }
}
