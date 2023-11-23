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
        (new Filesystem)->copyDirectory(__DIR__.'/../../stubs/turbo/app/Http', app_path('Http'));

        // Views...
        (new Filesystem)->ensureDirectoryExists(resource_path('views'));
        (new Filesystem)->copyDirectory(__DIR__.'/../../stubs/turbo/resources/views', resource_path('views'));

        // Views Components...
        (new Filesystem)->ensureDirectoryExists(resource_path('views/components'));
        (new Filesystem)->copyDirectory(__DIR__.'/../../stubs/turbo/resources/views/components', resource_path('views/components'));

        // Views Layouts...
        (new Filesystem)->ensureDirectoryExists(resource_path('views/layouts'));
        (new Filesystem)->copyDirectory(__DIR__.'/../../stubs/turbo/resources/views/layouts', resource_path('views/layouts'));

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

        // Tests...
        if (! $this->installTests()) {
            return 1;
        }

        // Routes...
        copy(__DIR__.'/../../stubs/turbo/routes/web.php', base_path('routes/web.php'));
        copy(__DIR__.'/../../stubs/turbo/routes/auth.php', base_path('routes/auth.php'));

        // "Dashboard" Route...
        $this->replaceInFile('/home', '/dashboard', app_path('Providers/RouteServiceProvider.php'));

        // Vite stuff...
        if (! $importmaps) {
            copy(__DIR__.'/../../stubs/turbo/postcss.config.js', base_path('postcss.config.js'));
            copy(__DIR__.'/../../stubs/turbo/vite.config.js', base_path('vite.config.js'));
        }

        // TailwindCSS...
        copy(__DIR__.'/../../stubs/turbo/tailwind.config.js', base_path('tailwind.config.js'));
        copy(__DIR__.'/../../stubs/turbo/resources/css/app.css', resource_path('css/app.css'));

        // Components...
        (new Filesystem)->ensureDirectoryExists(resource_path('js/controllers'));
        (new Filesystem)->ensureDirectoryExists(resource_path('js/libs'));

        (new Filesystem)->copyDirectory(__DIR__.'/../../stubs/turbo/resources/js/controllers', resource_path('js/controllers'));
        (new Filesystem)->copyDirectory(__DIR__.'/../../stubs/turbo/resources/js/libs', resource_path('js/libs'));

        // Install Packages...
        if ($importmaps) {
            Process::forever()->path(base_path())->run([$this->phpBinary(), 'artisan', 'importmap:install']);
            Process::forever()->path(base_path())->run([$this->phpBinary(), 'artisan', 'tailwindcss:install']);
        }

        Process::forever()->path(base_path())->run([$this->phpBinary(), 'artisan', 'turbo:install']);
        Process::forever()->path(base_path())->run([$this->phpBinary(), 'artisan', 'stimulus:install']);

        if ($importmaps) {
           Process::forever()->path(base_path())->run([$this->phpBinary(), 'artisan', 'importmap:pin', 'el-transition']);
        } else {
            if (file_exists(base_path('pnpm-lock.yaml'))) {
                $this->runCommands(['pnpm install', 'pnpm install el-transition', 'pnpm run build']);
            } elseif (file_exists(base_path('yarn.lock'))) {
                $this->runCommands(['yarn install', 'yarn add el-transition', 'yarn run build']);
            } else {
                $this->runCommands(['npm install', 'npm install el-transition', 'npm run build']);
            }
        }

        if ($importmaps) {
            $this->runStorageLinkCommand();
        }

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
