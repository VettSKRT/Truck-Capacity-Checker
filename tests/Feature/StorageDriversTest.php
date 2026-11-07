<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Aplikasi ini tidak memakai tabel sessions, cache, atau jobs. Driver-nya harus tetap file dan sync,
 * apa pun isi variabel lingkungan di terminal (variabel lingkungan yang basi pernah menimpa .env
 * dan membuat halaman error "Table 'sessions' doesn't exist").
 */
class StorageDriversTest extends TestCase
{
    public function test_sessions_cache_and_queue_do_not_depend_on_database_tables_or_environment_variables(): void
    {
        $this->assertSame('file', config('session.driver'));
        $this->assertSame('file', config('cache.default'));
        $this->assertSame('sync', config('queue.default'));
    }

    public function test_config_files_do_not_read_these_drivers_from_the_environment(): void
    {
        foreach (['session.php' => 'SESSION_DRIVER', 'cache.php' => 'CACHE_STORE', 'queue.php' => 'QUEUE_CONNECTION'] as $file => $var) {
            $this->assertStringNotContainsString("env('{$var}'", file_get_contents(config_path($file)), "{$file} tidak boleh membaca {$var} dari environment");
        }
    }
}
