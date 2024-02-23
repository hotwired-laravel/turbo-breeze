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
        $packages = array_merge(
            ['hotwired-laravel/turbo-laravel:^2.0.0-beta3', 'hotwired-laravel/stimulus-laravel:^1.0.0'],
            $importmaps ? ['tonysm/importmap-laravel:^2.1.4', 'tonysm/tailwindcss-laravel:^0.13'] : [],
        );

        if (! $this->requireComposerPackages($packages)) {
            return 1;
        }

        // Adds the JavaScript files (before running importmap:install)...
        (new Filesystem)->ensureDirectoryExists(resource_path('js/controllers'));
        (new Filesystem)->ensureDirectoryExists(resource_path('js/libs'));

        $bootstrap = $importmaps
            ? __DIR__.'/../../stubs/turbo/resources/js/bootstrap-importmap.js'
            : __DIR__.'/../../stubs/turbo/resources/js/bootstrap-vite.js'

        (new Filesystem)->copy(__DIR__.'/../../stubs/turbo/resources/js/app.js', resource_path('js/app.js'));
        (new Filesystem)->copy($bootstrap, resource_path('js/bootstrap.js'));
        (new Filesystem)->copyDirectory(__DIR__.'/../../stubs/turbo/resources/js/controllers', resource_path('js/controllers'));
        (new Filesystem)->copyDirectory(__DIR__.'/../../stubs/turbo/resources/js/libs', resource_path('js/libs'));

        // Controllers
        (new Filesystem)->ensureDirectoryExists(app_path('Http'));
        (new Filesystem)->copyDirectory(__DIR__.'/../../stubs/turbo/app/Http', app_path('Http'));

        // Views...
        (new Filesystem)->ensureDirectoryExists(resource_path('views'));
        (new Filesystem)->copyDirectory(__DIR__.'/../../stubs/turbo/resources/views', resource_path('views'));

        // Components...
        (new Filesystem)->ensureDirectoryExists(app_path('View/Components'));
        (new Filesystem)->copyDirectory(__DIR__.'/../../stubs/turbo/app/View/Components', app_path('View/Components'));

        // Dark mode...
        if (! $this->option('dark')) {
            $this->removeDarkClasses((new Finder)
                ->in(resource_path('views'))
                ->name('*.blade.php')
                ->notName('welcome.blade.php')
            );
        }

        // Routes...
        copy(__DIR__.'/../../stubs/turbo/routes/web.php', base_path('routes/web.php'));
        copy(__DIR__.'/../../stubs/turbo/routes/auth.php', base_path('routes/auth.php'));

        // "Dashboard" Route...
        $this->replaceInFile('/home', '/dashboard', resource_path('views/welcome.blade.php'));
        $this->replaceInFile('Home', 'Dashboard', resource_path('views/welcome.blade.php'));
        $this->replaceInFile('/home', '/dashboard', app_path('Providers/RouteServiceProvider.php'));

        // TailwindCSS...
        copy(__DIR__.'/../../stubs/turbo/tailwind.config.js', base_path('tailwind.config.js'));
        copy(__DIR__.'/../../stubs/turbo/resources/css/app.css', resource_path('css/app.css'));

        if (! $importmaps) {
            // Vite stuff...
            copy(__DIR__.'/../../stubs/turbo/postcss.config.js', base_path('postcss.config.js'));
            copy(__DIR__.'/../../stubs/turbo/vite.config.js', base_path('vite.config.js'));
        } else {
            // Remove axios...
            $this->updateNodePackages(function ($packages) {
                if ($packages['axios'] ?? false) {
                    unset($packages['axios']);
                }

                return $packages;
            });

            // Install Importmap Packages...
            Process::forever()->path(base_path())->run([$this->phpBinary(), 'artisan', 'importmap:install']);
            Process::forever()->path(base_path())->run([$this->phpBinary(), 'artisan', 'tailwindcss:install']);
        }

        // Install Hotwired Laravel Packages...
        Process::forever()->path(base_path())->run([$this->phpBinary(), 'artisan', 'turbo:install']);
        Process::forever()->path(base_path())->run([$this->phpBinary(), 'artisan', 'stimulus:install', '--strada']);

        if (! $importmaps) {
            // NPM Packages...
            $this->updateNodePackages(function ($packages) {
                return [
                    '@tailwindcss/forms' => '^0.5.3',
                    '@tailwindcss/aspect-ratio' => '^0.4.2',
                    '@tailwindcss/typography' => '^0.5.10',
                    'autoprefixer' => '^10.4.12',
                    'postcss' => '^8.4.18',
                    'tailwindcss' => '^3.2.1',
                    'el-transition' => '^0.0.7',
                ] + $packages;
            });

            Process::forever()->path(base_path())->run([$this->phpBinary(), 'artisan', 'stimulus:manifest']);

            if (file_exists(base_path('pnpm-lock.yaml'))) {
                $this->runCommands(['pnpm install', 'pnpm run build']);
            } elseif (file_exists(base_path('yarn.lock'))) {
                $this->runCommands(['yarn install', 'yarn run build']);
            } else {
                $this->runCommands(['npm install', 'npm run build']);
            }
        } else {
            $this->runStorageLinkCommand();
        }

        // Tests...
        $this->installTests();

        $this->components->info('Breeze scaffolding installed successfully.');

    }

    protected function runStorageLinkCommand(): void
    {
        if ($this->hasComposerPackage('laravel/sail') && file_exists(base_path('docker-compose.yml')) && ! env('LARAVEL_SAIL', 0)) {
            Process::run([base_path('vendor/bin/sail'), 'up', '-d']);
            Process::run([base_path('vendor/bin/sail'), 'artisan', 'storage:link']);
        } else {
            Process::run([$this->phpBinary(), 'artisan', 'storage:link']);
        }
    }
}
