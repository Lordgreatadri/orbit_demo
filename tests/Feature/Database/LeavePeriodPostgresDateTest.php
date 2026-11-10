<?php

namespace Tests\Feature\Database;

use App\Database\Upgrades\LeavePeriodBackfill;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class LeavePeriodPostgresDateTest extends TestCase
{
    #[Test]
    public function postgresql_date_comparison_selects_only_the_legacy_sentinel(): void
    {
        $name = 'orbit_date_proof_'.bin2hex(random_bytes(4));
        $created = false;
        $admin = $this->postgres('postgres');

        try {
            $admin->unprepared('CREATE DATABASE '.$name);
            $created = true;
            $proof = $this->postgres($name);
            $proof->unprepared('create table leave_period_probe (
                id bigint generated always as identity primary key,
                label varchar(40) not null,
                period_start date null,
                period_end date null
            )');
            $proof->table('leave_period_probe')->insert([
                ['label' => 'sentinel', 'period_start' => '2000-01-01', 'period_end' => '2000-12-31'],
                ['label' => 'null_start', 'period_start' => null, 'period_end' => null],
                ['label' => 'null_end', 'period_start' => '2000-01-01', 'period_end' => null],
                ['label' => 'timestamp_cast', 'period_start' => '2000-01-01 00:00:00', 'period_end' => '2000-12-31 00:00:00'],
                ['label' => 'custom_end', 'period_start' => '2000-01-01', 'period_end' => '2023-06-30'],
                ['label' => 'custom', 'period_start' => '2026-07-01', 'period_end' => '2027-06-30'],
            ]);

            $query = LeavePeriodBackfill::legacyPeriods($proof->table('leave_period_probe'));
            $sql = strtolower($query->toSql());
            $this->assertStringContainsString('::date', $sql);
            $this->assertStringNotContainsString('like', $sql);
            $this->assertEqualsCanonicalizing(
                ['sentinel', 'null_start', 'null_end', 'timestamp_cast'],
                $query->orderBy('label')->pluck('label')->all(),
            );

            $likeFailed = false;

            try {
                $proof->select("select label from leave_period_probe where period_start like '2000-01-01%'");
            } catch (QueryException $exception) {
                $likeFailed = str_contains($exception->getMessage(), 'operator does not exist')
                    && str_contains($exception->getMessage(), '~~');
            }

            $this->assertTrue($likeFailed, 'PostgreSQL accepted a DATE column compared with LIKE.');
        } finally {
            DB::disconnect('date_proof');
            DB::purge('date_proof');

            if ($created) {
                $admin->unprepared('DROP DATABASE IF EXISTS '.$name);
            }

            DB::disconnect('date_proof_admin');
            DB::purge('date_proof_admin');
        }
    }

    private function postgres(string $database): Connection
    {
        $name = $database === 'postgres' ? 'date_proof_admin' : 'date_proof';
        $config = config('database.connections.pgsql');
        $config['url'] = null;
        $config['driver'] = 'pgsql';
        $config['database'] = $database;

        if (($config['password'] ?? '') === '') {
            throw new RuntimeException('PostgreSQL credentials are not available to the test process.');
        }

        config(["database.connections.{$name}" => $config]);
        DB::purge($name);

        try {
            $connection = DB::connection($name);
            $connection->getPdo();
        } catch (Throwable $exception) {
            $password = (string) $config['password'];
            $message = str_replace($password, '[redacted]', $exception->getMessage());

            throw new RuntimeException('PostgreSQL date proof could not connect: '.$message);
        }

        return DB::connection($name);
    }
}
