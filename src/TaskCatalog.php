<?php

declare(strict_types=1);

require_once __DIR__ . '/web_bootstrap.php';

final class TaskCatalog
{
    public static function all(): array
    {
        $php = 'C:\\xampp\\php\\php.exe';
        $python = 'python';
        $root = app_root();
        $src = $root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;

        return [
            'refresh_competitions' => [
                'label' => 'Actualizar Competiciones',
                'command' => [$php, $src . 'run_import_competitions.php'],
            ],
            'refresh_leaderboards' => [
                'label' => 'Actualizar Tablas Clasificación',
                'command' => [$php, $src . 'run_import_leaderboards.php', '--type=both', '--limit=100', '--species-source=table'],
            ],
            'refresh_best_all' => [
                'label' => 'Actualizar Mejores Marcas Usuarios',
                'command' => [$php, $src . 'run_import_best_users.php'],
            ],
            'refresh_public_all' => [
                'label' => 'Actualizar Estadísticas Usuarios',
                'command' => [$php, $src . 'run_import_users_public_stats.php'],
            ],
            'refresh_expeditions_all_users' => [
                'label' => 'Actualizar Expediciones de todos los users',
                'command' => [$php, $src . 'run_import_users.php', '--page-size=40'],
            ],
            'export_best_xml' => [
                'label' => 'Generar XML de Mejores Marcas',
                'command' => [$python, $root . '\\export_best_excel.py', '--out', $root . '\\out\\best_all.xml'],
            ],
            'scrape_kill_urls' => [
                'label' => 'Scraper URLs de Muertes',
                'command' => [$php, $src . 'run_scrape_kill_urls.php', '--from=all', '--sleep-ms=200'],
            ],
            'refresh_my_expeditions' => [
                'label' => 'Actualizar mis Expediciones',
                'command' => [$php, $src . 'run_import_user.php', '0', '40'],
            ],
        ];
    }

    /**
     * @return array<int,string>
     */
    public static function nonAdminRunnableActions(): array
    {
        return [
            'refresh_competitions',
            'refresh_leaderboards',
            'refresh_best_all',
            'refresh_public_all',
            'export_best_xml',
            'scrape_kill_urls',
            'refresh_my_expeditions',
        ];
    }

    public static function canRunAction(string $action, bool $isAdmin): bool
    {
        if ($isAdmin) {
            return isset(self::all()[$action]);
        }
        return in_array($action, self::nonAdminRunnableActions(), true);
    }
}
