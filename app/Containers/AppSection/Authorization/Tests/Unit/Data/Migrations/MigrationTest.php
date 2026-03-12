<?php

namespace App\Containers\AppSection\Authorization\Tests\Unit\Data\Migrations;

use App\Containers\AppSection\Authorization\Tests\UnitTestCase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class MigrationTest extends UnitTestCase
{
    private array $tableNames;
    private array $columnNames;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tableNames = config('permission.table_names');
        $this->columnNames = config('permission.column_names');
    }

    public function testPermissionsTableHasExpectedColumns(): void
    {
        $expectedColumns = ['id', 'name', 'guard_name', 'display_name', 'description', 'created_at', 'updated_at'];

        $actualColumns = Schema::getColumnListing($this->tableNames['permissions']);
        sort($expectedColumns);
        sort($actualColumns);

        $this->assertSame($expectedColumns, $actualColumns);
    }

    public function testRolesTableHasExpectedColumns(): void
    {
        $expectedColumns = ['id', 'name', 'guard_name', 'display_name', 'description', 'created_at', 'updated_at'];

        $actualColumns = Schema::getColumnListing($this->tableNames['roles']);
        sort($expectedColumns);
        sort($actualColumns);

        $this->assertSame($expectedColumns, $actualColumns);
    }

    public function testModelHasPermissionsTableHasExpectedColumns(): void
    {
        $expectedColumns = ['permission_id', 'model_type', $this->columnNames['model_morph_key']];

        $actualColumns = Schema::getColumnListing($this->tableNames['model_has_permissions']);
        sort($expectedColumns);
        sort($actualColumns);

        $this->assertSame($expectedColumns, $actualColumns);
    }

    public function testModelHasRolesTableHasExpectedColumns(): void
    {
        $expectedColumns = ['role_id', 'model_type', $this->columnNames['model_morph_key']];

        $actualColumns = Schema::getColumnListing($this->tableNames['model_has_roles']);
        sort($expectedColumns);
        sort($actualColumns);

        $this->assertSame($expectedColumns, $actualColumns);
    }

    public function testRoleHasPermissionsTableHasExpectedColumns(): void
    {
        $expectedColumns = ['permission_id', 'role_id'];

        $actualColumns = Schema::getColumnListing($this->tableNames['role_has_permissions']);
        sort($expectedColumns);
        sort($actualColumns);

        $this->assertSame($expectedColumns, $actualColumns);
    }
}

