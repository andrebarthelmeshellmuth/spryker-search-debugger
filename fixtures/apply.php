<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

/**
 * Applies this package's demo-fixture claims (see the toolkit repo's FIXTURE_CLAIMS.md) to a real
 * b2b-demo-marketplace checkout's shared import CSVs. Idempotent — safe to re-run; each change is
 * applied only if not already present.
 *
 * Grants the SeeSearchDebugInfoPermissionPlugin permission to a real, loggable-in customer
 * (search-admin@test-company.example / change123) — the account this package's own Presentation suite
 * and README already document. The underlying customer/company-user/role rows are SHARED with the
 * sibling search-feedback/search-ranking/search-ranking-optimizer packages (same account, one role per
 * package's own permission) — this script only ever ADDS its own permission row and glossary keys, so
 * running it alongside any sibling's own apply.php is safe in either order.
 *
 * Usage: php fixtures/apply.php /path/to/b2b-demo-marketplace
 *
 * Then, from that demoshop checkout:
 *   ./docker/sdk console data:import customer
 *   ./docker/sdk console data:import company-user
 *   ./docker/sdk console data:import company-business-unit-user
 *   ./docker/sdk console data:import company-user-role
 *   ./docker/sdk console data:import company-role-permission
 *   ./docker/sdk console data:import glossary
 */

$demoshopRoot = $argv[1] ?? null;

if ($demoshopRoot === null || !is_dir($demoshopRoot)) {
    fwrite(STDERR, "Usage: php fixtures/apply.php /path/to/b2b-demo-marketplace\n");

    exit(1);
}

$dataDir = rtrim($demoshopRoot, '/') . '/data/import/common/common';

if (!is_dir($dataDir)) {
    fwrite(STDERR, "Not a b2b-demo-marketplace checkout (missing $dataDir)\n");

    exit(1);
}

/**
 * @param string $path
 *
 * @return array{header: array<int, string>, rows: array<int, array<string, string>>}
 */
function readCsv(string $path): array
{
    $handle = fopen($path, 'r');
    $header = fgetcsv($handle);
    $rows = [];

    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) !== count($header)) {
            continue;
        }

        $rows[] = array_combine($header, $row);
    }

    fclose($handle);

    return ['header' => $header, 'rows' => $rows];
}

/**
 * @param string $path
 * @param array<int, string> $header
 * @param array<int, array<string, string>> $rows
 */
function writeCsv(string $path, array $header, array $rows): void
{
    $handle = fopen($path, 'w');
    fputcsv($handle, $header);

    foreach ($rows as $row) {
        fputcsv($handle, array_map(fn (string $key): string => $row[$key] ?? '', $header));
    }

    fclose($handle);
}

/**
 * Shared across every sibling package's own apply.php — same customer_reference/company_user_key
 * ("SearchAdmin--1") and role ("test-company_Admin") everywhere, so whichever package's script runs
 * first creates the account and every other package's script just adds its own permission row to it.
 *
 * @param string $dataDir
 *
 * @return int Number of rows added (0 if everything already existed).
 */
function applySearchAdminCustomerFixture(string $dataDir): int
{
    $added = 0;

    $path = $dataDir . '/customer.csv';
    $csv = readCsv($path);

    if (!in_array('SearchAdmin--1', array_column($csv['rows'], 'customer_reference'), true)) {
        $csv['rows'][] = [
            'customer_reference' => 'SearchAdmin--1',
            'locale_name' => 'en_US',
            'phone' => '',
            'email' => 'search-admin@test-company.example',
            'salutation' => 'Mr',
            'first_name' => 'Search',
            'last_name' => 'Admin',
            'company' => '',
            'gender' => 'Male',
            'date_of_birth' => '',
            'password' => '$2y$12$CUw8PyVm4isuM.ugzQhZ0.os.n1nlGJOA61SEd7cgjXivzt5LqJ2.',
            'registered' => '2026-08-10',
        ];
        writeCsv($path, $csv['header'], $csv['rows']);
        $added++;
    }

    $path = $dataDir . '/company_user.csv';
    $csv = readCsv($path);

    if (!in_array('SearchAdmin--1', array_column($csv['rows'], 'company_user_key'), true)) {
        $csv['rows'][] = [
            'company_user_key' => 'SearchAdmin--1',
            'customer_reference' => 'SearchAdmin--1',
            'company_key' => 'test-company',
            'is_default' => 'true',
        ];
        writeCsv($path, $csv['header'], $csv['rows']);
        $added++;
    }

    $path = $dataDir . '/company_business_unit_user.csv';
    $csv = readCsv($path);

    if (!in_array('SearchAdmin--1', array_column($csv['rows'], 'company_user_key'), true)) {
        $csv['rows'][] = ['company_user_key' => 'SearchAdmin--1', 'business_unit_key' => 'test-business-unit-1'];
        writeCsv($path, $csv['header'], $csv['rows']);
        $added++;
    }

    $path = $dataDir . '/company_user_role.csv';
    $csv = readCsv($path);
    $exists = false;

    foreach ($csv['rows'] as $row) {
        if ($row['company_role_key'] === 'test-company_Admin' && $row['company_user_key'] === 'SearchAdmin--1') {
            $exists = true;

            break;
        }
    }

    if (!$exists) {
        $csv['rows'][] = ['company_role_key' => 'test-company_Admin', 'company_user_key' => 'SearchAdmin--1'];
        writeCsv($path, $csv['header'], $csv['rows']);
        $added++;
    }

    return $added;
}

/**
 * @param string $dataDir
 * @param string $companyRoleKey
 * @param string $permissionKey
 *
 * @return bool True if the row was added, false if it already existed.
 */
function applyPermissionRow(string $dataDir, string $companyRoleKey, string $permissionKey): bool
{
    $path = $dataDir . '/company_role_permission.csv';
    $csv = readCsv($path);

    foreach ($csv['rows'] as $row) {
        if ($row['company_role_key'] === $companyRoleKey && $row['permission_key'] === $permissionKey) {
            return false;
        }
    }

    $csv['rows'][] = ['company_role_key' => $companyRoleKey, 'permission_key' => $permissionKey, 'configuration' => ''];
    writeCsv($path, $csv['header'], $csv['rows']);

    return true;
}

/**
 * @param string $dataDir
 * @param string $ownGlossaryCsvPath
 *
 * @return int Number of rows added.
 */
function applyGlossary(string $dataDir, string $ownGlossaryCsvPath): int
{
    $path = $dataDir . '/glossary.csv';
    $csv = readCsv($path);
    $existingKeys = [];

    foreach ($csv['rows'] as $row) {
        $existingKeys[$row['key'] . "\0" . $row['locale']] = true;
    }

    $ownCsv = readCsv($ownGlossaryCsvPath);
    $added = 0;

    foreach ($ownCsv['rows'] as $row) {
        $identity = $row['key'] . "\0" . $row['locale'];

        if (isset($existingKeys[$identity])) {
            continue;
        }

        $csv['rows'][] = $row;
        $existingKeys[$identity] = true;
        $added++;
    }

    if ($added > 0) {
        writeCsv($path, $csv['header'], $csv['rows']);
    }

    return $added;
}

$customerRowsAdded = applySearchAdminCustomerFixture($dataDir);
echo "search-admin customer/company-user fixture: $customerRowsAdded row(s) added\n";

$permissionRowAdded = applyPermissionRow($dataDir, 'test-company_Admin', 'SeeSearchDebugInfoPermissionPlugin');
echo 'company_role_permission.csv: ' . ($permissionRowAdded ? '1 row added' : 'already present') . "\n";

$glossaryRowsAdded = applyGlossary($dataDir, __DIR__ . '/glossary.csv');
echo "glossary.csv: $glossaryRowsAdded row(s) added\n";

echo "\nDone. Now run (from the demoshop root):\n";
echo "  ./docker/sdk console data:import customer\n";
echo "  ./docker/sdk console data:import company-user\n";
echo "  ./docker/sdk console data:import company-business-unit-user\n";
echo "  ./docker/sdk console data:import company-user-role\n";
echo "  ./docker/sdk console data:import company-role-permission\n";
echo "  ./docker/sdk console data:import glossary\n";
